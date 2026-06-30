<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Mailer.php';
require_once __DIR__ . '/../Newsletter.php';
requireRole('promoter');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // requireCsrf();
}

$user = getCurrentUser();

try {
    $pdo = Database::getInstance();

    // Handle Module Creation (Form POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_module') {
        $title = trim((string)$_POST['module_title']);
        $description = trim((string)$_POST['module_desc']);
        if (!empty($title)) {
            $stmt = $pdo->prepare("INSERT INTO modules (title, description) VALUES (:title, :description)");
            $stmt->execute(['title' => $title, 'description' => $description]);
            auditLog('module_created', "Module: {$title}");
            header("Location: /promoter/dashboard.php?success=module_created");
            exit;
        }
    }

    // Handle Tele-Evaluation Postpone (Form POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'postpone_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $startTime = trim((string)($_POST['start_time'] ?? ''));
        $endTime   = trim((string)($_POST['end_time'] ?? ''));
        
        if ($sessionId > 0 && !empty($startTime) && !empty($endTime)) {
            // Check status isn't active/finished if we want to guard, but let's allow promoter full override
            $stmt = $pdo->prepare("UPDATE live_eval_sessions SET start_time = :start, end_time = :end WHERE id = :id");
            $stmt->execute(['start' => $startTime, 'end' => $endTime, 'id' => $sessionId]);
            auditLog('live_eval_postponed', "Session ID: {$sessionId}");
            header("Location: /promoter/dashboard.php?success=session_postponed");
            exit;
        }
    }

    // Handle Tele-Evaluation Cancel (Form POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if ($sessionId > 0) {
            $stmt = $pdo->prepare("DELETE FROM live_eval_sessions WHERE id = :id");
            $stmt->execute(['id' => $sessionId]);
            auditLog('live_eval_cancelled', "Session ID: {$sessionId}");
            header("Location: /promoter/dashboard.php?success=session_cancelled");
            exit;
        }
    }

    // Fetch Live Session data and telemetry
    $liveSessions = $pdo->query("
        SELECT s.*, u.name AS teacher_name, c.title AS course_title,
               (SELECT COUNT(*) FROM live_eval_registrations r WHERE r.session_id = s.id) AS registered_count,
               (SELECT COUNT(*) FROM live_eval_registrations r WHERE r.session_id = s.id AND r.score IS NOT NULL) AS evaluated_count,
               (SELECT COALESCE(AVG(r.score), 0) FROM live_eval_registrations r WHERE r.session_id = s.id AND r.score IS NOT NULL) AS avg_score
        FROM live_eval_sessions s
        JOIN courses c ON s.course_id = c.id
        JOIN users u ON s.teacher_id = u.id
        ORDER BY s.start_time DESC
    ")->fetchAll();

    $allRegs = $pdo->query("SELECT score FROM live_eval_registrations WHERE score IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    $totalEvaluatedCount = count($allRegs);
    $overallAvgScore = $totalEvaluatedCount > 0 ? array_sum($allRegs) / $totalEvaluatedCount : 0.0;
    
    $successCount = 0;
    foreach ($allRegs as $sc) {
        if ($sc >= 50.0) {
            $successCount++;
        }
    }
    $overallSuccessRate = $totalEvaluatedCount > 0 ? ($successCount / $totalEvaluatedCount) * 100 : 0.0;

    $buckets = [0, 0, 0, 0, 0];
    foreach ($allRegs as $sc) {
        if ($sc <= 20) $buckets[0]++;
        elseif ($sc <= 40) $buckets[1]++;
        elseif ($sc <= 60) $buckets[2]++;
        elseif ($sc <= 80) $buckets[3]++;
        else $buckets[4]++;
    }
    $maxBucket = max(1, max($buckets));

    // Handle Course Creation (Form POST)
    // (Création de cours déplacée vers l'espace enseignant)

    // Fetch all Modules
    $modules = $pdo->query("SELECT * FROM modules ORDER BY id DESC")->fetchAll();

    // Fetch all Courses with Module, Teacher and Creator names
    $courses = $pdo->query("
        SELECT c.*, m.title AS module_title,
               u.name AS teacher_name,
               cr.name AS creator_name
        FROM courses c
        JOIN modules m ON c.module_id = m.id
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN users cr ON c.created_by = cr.id
        ORDER BY c.id DESC
    ")->fetchAll();

    // Fetch all Teachers
    $teachers = $pdo->query("SELECT id, name, email, is_active, is_approved, created_at FROM users WHERE role = 'teacher' ORDER BY name ASC")->fetchAll();

    // Fetch all Certificates
    $certificates = $pdo->query("
        SELECT cert.*, u.name AS student_name, u.email AS student_email, m.title AS module_title,
               (
                   SELECT GROUP_CONCAT(DISTINCT ut.name SEPARATOR ', ')
                   FROM courses c
                   JOIN users ut ON c.teacher_id = ut.id
                   WHERE c.module_id = cert.module_id
               ) AS teachers_list,
               issuer.name AS issuer_name
        FROM certificates cert
        JOIN users u ON cert.student_id = u.id
        JOIN modules m ON cert.module_id = m.id
        LEFT JOIN users issuer ON cert.issued_by = issuer.id
        ORDER BY cert.id DESC
    ")->fetchAll();

    // Fetch all Users for User Management panel
    $allUsers = $pdo->query("
        SELECT id, name, email, role, is_active, is_approved, created_at FROM users ORDER BY role ASC, name ASC
    ")->fetchAll();

    $students = $pdo->query("SELECT id, name, email, is_active, created_at FROM users WHERE role = 'student' ORDER BY name ASC")->fetchAll();

    $auditLogs = $pdo->query("
        SELECT al.*, u.name AS user_name
        FROM audit_logs al
        LEFT JOIN users u ON u.id = al.user_id
        ORDER BY al.id DESC LIMIT 50
    ")->fetchAll();

    // Newsletter — abonnés et campagnes
    $newsletterSubscribers = [];
    $newsletterCampaigns   = [];
    $kpiNewsletterSubs     = 0;
    try {
        $newsletterSubscribers = Newsletter::getActiveSubscribers($pdo);
        $newsletterCampaigns   = Newsletter::getCampaigns($pdo, 10);
        $kpiNewsletterSubs     = count($newsletterSubscribers);
    } catch (PDOException) {
        // Tables newsletter non migrées
    }
    $smtpConfigured = Mailer::isConfigured();

    // ── KPIs (Stats) ──
    $kpiTotalStudents  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $kpiTotalCourses   = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $kpiTotalCerts     = (int)$pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
    $kpiEnrollments    = (int)$pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();
    $kpiAvgProgress    = (float)($pdo->query("SELECT COALESCE(AVG(progress_percent),0) FROM enrollments")->fetchColumn());
    $kpiPassRate       = $pdo->query("
        SELECT COALESCE(ROUND(SUM(passed)/COUNT(*)*100,1), 0)
        FROM certification_attempts
    ")->fetchColumn();

} catch (PDOException $e) {
    dieSafe('Erreur serveur. Veuillez réessayer.', $e, 'promoter/dashboard');
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full sv-cream">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="icon" type="image/png" href="/assets/img/favicon.png" sizes="32x32">
    <link rel="apple-touch-icon" href="/assets/img/favicon.png">

    <title>Espace Promoteur — StudyVibe</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <?= csrfMetaTag(); ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        serif: ['Plus Jakarta Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        .fade-in {
            animation: fadeIn 0.4s ease-out forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body class="font-sans antialiased text-[#111111] sv-page min-h-screen flex flex-col justify-between">

    <!-- En-tête Principal -->
    <header class="sv-header border-b border-[#E5E5E7] py-6 px-6 md:px-12 flex justify-between items-center bg-[#FFFFFF]">
        <div class="flex items-center gap-3">
            <svg class="w-9 h-9" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 36px; height: 36px;">
                <circle cx="50" cy="50" r="46" stroke="#006630" stroke-width="3.5" />
                <line x1="33" y1="31" x2="62" y2="25" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="49" y2="53" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="14" y2="13" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="42" y2="11" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="20" y2="53" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                <line x1="49" y1="53" x2="62" y2="25" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                <circle cx="62" cy="25" r="6" fill="#006630" />
                <circle cx="49" cy="53" r="6" fill="#006630" />
                <circle cx="33" cy="31" r="6" fill="#006630" />
                <circle cx="14" cy="13" r="6" fill="#006630" />
                <circle cx="42" cy="11" r="6" fill="#006630" />
                <circle cx="20" cy="53" r="6" fill="#006630" />
                <path d="M56 10 C52 14, 52 24, 52 29 C52 31, 50 33, 49 33 L45 33 L49 35 C50 37, 51 38, 50 40 C49 41, 47 42, 49 44 C51 45, 54 46, 56 46 C59 46, 65 38, 66 41 C68 46, 60 52, 56 60 C51 68, 50 78, 53 88" stroke="#111111" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M33 55 C32 52, 32 48, 33 46 C34 44, 36 44, 37 47 C37 50, 37 53, 37 55 C37 51, 38 46, 39 44 C40 42, 42 42, 43 45 C43 48, 43 51, 43 54 C43 51, 44 47, 45 45 C46 43, 48 43, 49 46 C50 49, 51 57, 51 68 C51 75, 49 81, 47 85" stroke="#111111" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M33 55 C34 61, 35 68, 37 75 C38 81, 39 84, 40 86" stroke="#111111" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span class="font-serif text-xl tracking-tight text-[#111111] font-semibold">StudyVibe</span>
            <span class="text-xs uppercase tracking-widest bg-[#F5F5F7] text-[#555555] px-2 py-1 border border-[#E5E5E7] ml-2 font-mono">Promoteur</span>
        </div>
        <div class="flex items-center gap-6">
            <div class="relative" id="notif-wrap">
                <button type="button" id="notif-btn" class="relative text-xs uppercase tracking-wider text-[#555555] hover:text-[#111111]" aria-label="Notifications">
                    Notifications <span id="notif-count" class="hidden ml-1 bg-[#004B23] text-white text-[10px] px-1.5 py-0.5 rounded-full">0</span>
                </button>
                <div id="notif-panel" class="hidden absolute right-0 top-full mt-2 w-80 max-h-64 overflow-y-auto bg-white border border-[#E5E5E7] shadow-lg z-50 text-left text-sm"></div>
            </div>
            <span class="text-sm font-light text-[#555555]"><?= htmlspecialchars($user['name']); ?></span>
            <button class="sv-dark-toggle" data-dark-toggle title="Mode sombre"></button>
            <div class="relative inline-block text-left">
                <select id="lang-selector" onchange="changeLanguage(this.value)" class="bg-transparent text-xs border border-[#E5E5E7] text-[#555555] rounded-sm py-1 px-2 focus:outline-none focus:border-[#004B23]">
                    <option value="fr" <?= TranslationService::getLang() === 'fr' ? 'selected' : ''; ?>>FR</option>
                    <option value="en" <?= TranslationService::getLang() === 'en' ? 'selected' : ''; ?>>EN</option>
                </select>
            </div>
            <a href="/logout.php" class="text-xs uppercase tracking-wider text-[#D32F2F] hover:underline">Déconnexion</a>
        </div>
    </header>

    <!-- Corps de Page -->
    <main class="flex-grow px-12 py-16 max-w-7xl mx-auto w-full space-y-16">

        <!-- Message de succès -->
        <?php if (isset($_GET['success'])): ?>
            <div class="p-4 bg-[#FFFFFF] border border-[#004B23] text-[#004B23] text-sm font-light fade-in">
                ✓ L'opération académique a été exécutée avec succès.
            </div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <?php
            $kpisRaw = [
                ['label' => 'Apprenants',      'raw' => $kpiTotalStudents,            'suffix' => '',  'decimals' => 0],
                ['label' => 'Cours',            'raw' => $kpiTotalCourses,             'suffix' => '',  'decimals' => 0],
                ['label' => 'Inscriptions',     'raw' => $kpiEnrollments,              'suffix' => '',  'decimals' => 0],
                ['label' => 'Certifications',   'raw' => $kpiTotalCerts,               'suffix' => '',  'decimals' => 0],
                ['label' => 'Progression moy.', 'raw' => round($kpiAvgProgress, 1),   'suffix' => '%', 'decimals' => 1],
                ['label' => 'Taux de réussite', 'raw' => $kpiPassRate,                 'suffix' => '%', 'decimals' => 0],
            ];
            foreach ($kpisRaw as $kpi): ?>
                <div class="bg-[#FFFFFF] border border-[#E5E5E7] p-5 space-y-2 group hover:border-[#004B23] transition-colors">
                    <div
                        class="font-serif text-2xl font-light text-[#111111] tabular-nums"
                        data-counter="<?= $kpi['raw']; ?>"
                        data-suffix="<?= $kpi['suffix']; ?>"
                        data-decimals="<?= $kpi['decimals']; ?>"
                    >0<?= $kpi['suffix']; ?></div>
                    <div class="text-[10px] uppercase tracking-wider text-[#888888] font-medium"><?= $kpi['label']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Section Audit Stratégique (IA Gemini) -->
        <div class="border border-[#E5E5E7] bg-[#FFFFFF] p-8 space-y-6">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div class="space-y-2">
                    <h2 class="font-serif text-3xl font-light text-[#111111]">Audit Académique</h2>
                    <p class="text-sm text-[#555555] font-light max-w-2xl">
                        Générez un rapport d'analyse critique, de diagnostic pédagogique et de recommandations opérationnelles basé sur l'activité en temps réel de votre établissement.
                    </p>
                </div>
                <button type="button" onclick="runStrategicAudit()" id="audit-btn"
                   class="px-5 py-2.5 bg-[#111111] text-[#FFFFFF] text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] transition-colors rounded-sm flex-shrink-0 text-center">
                    Générer le rapport stratégique
                </button>
            </div>

            <!-- Loader de l'audit -->
            <div id="audit-loading" class="hidden flex flex-col items-center justify-center py-12 space-y-3 border-t border-[#E5E5E7] border-dashed">
                <div class="w-8 h-8 border-2 border-[#111111] border-t-transparent rounded-full animate-spin"></div>
                <p class="text-xs font-mono uppercase tracking-widest text-[#555555]">Audit en cours de rédaction par l'IA...</p>
            </div>

            <!-- Contenu de l'audit -->
            <div id="audit-result-container" class="hidden border-t border-[#E5E5E7] pt-6 space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-[10px] font-mono uppercase tracking-widest text-[#888888]">Rapport généré par l'IA Gemini</span>
                    <button type="button" onclick="window.print()" class="text-xs uppercase tracking-wider text-[#555555] hover:underline font-semibold">
                        Imprimer le rapport
                    </button>
                </div>
                <div id="audit-text" class="p-6 bg-[var(--sv-cream-light)] border border-[#D5D0C8] text-sm text-[#111111] leading-relaxed whitespace-pre-line font-light">
                    <!-- Rempli par JS -->
                </div>
            </div>
        </div>

        <!-- Section Exports -->
        <div class="border border-[#E5E5E7] bg-[#FFFFFF] p-8 space-y-6">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div class="space-y-2">
                    <h2 class="font-serif text-3xl font-light text-[#111111]">Exports Excel</h2>
                    <p class="text-sm text-[#555555] font-light max-w-2xl">
                        Tous les rapports et données de la plateforme sont exportables au format Excel (.xls).
                    </p>
                </div>
                <a href="/promoter/export-excel.php?type=all"
                   class="px-5 py-2.5 bg-[#004B23] text-[#FFFFFF] text-xs font-semibold uppercase tracking-wider hover:bg-[#111111] transition-colors rounded-sm flex-shrink-0 text-center">
                    ⬇ Export Excel complet (9 feuilles)
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php
                $excelExports = [
                    ['type' => 'metrics', 'label' => 'Métriques KPI'],
                    ['type' => 'students', 'label' => 'Apprenants'],
                    ['type' => 'teachers', 'label' => 'Enseignants'],
                    ['type' => 'modules', 'label' => 'Modules'],
                    ['type' => 'courses', 'label' => 'Cours'],
                    ['type' => 'enrollments', 'label' => 'Inscriptions'],
                    ['type' => 'certifications', 'label' => 'Certifications délivrées'],
                    ['type' => 'certification_attempts', 'label' => 'Tentatives QCM certification'],
                    ['type' => 'audit_logs', 'label' => 'Journal d\'audit'],
                ];
                foreach ($excelExports as $exp): ?>
                    <a href="/promoter/export-excel.php?type=<?= $exp['type']; ?>"
                       class="flex items-center justify-between px-4 py-3 border border-[#E5E5E7] hover:border-[#004B23] text-sm font-light transition-colors rounded-sm">
                        <span><?= $exp['label']; ?></span>
                        <span class="text-[10px] uppercase tracking-wider text-[#004B23] font-semibold">.xls</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Introduction Éditoriale -->
        <div class="border-b border-[#E5E5E7] pb-10">
            <h1 class="font-serif text-5xl font-light tracking-tight mb-4">Gouvernance Académique</h1>
            <p class="text-base font-light text-[#555555] max-w-2xl leading-relaxed">
                Espace dédié à la structuration des modules de formation, à l'assignation du corps professoral aux cours créés par les enseignants, et au contrôle officiel des certifications décernées.
            </p>
        </div>

        <!-- Section 1: Gestion des Modules -->
        <div class="max-w-2xl">
            <!-- Créer un Module -->
            <div class="space-y-6">
                <h2 class="font-serif text-2xl font-light text-[#111111]">1. Structurer un Nouveau Module</h2>
                <p class="text-xs text-[#555555] font-light">Un module regroupe plusieurs cours thématiques pour délivrer une certification globale. Les enseignants créent les cours au sein de ces modules.</p>
                <form action="/promoter/dashboard.php" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create_module">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Titre du Module</label>
                        <input type="text" name="module_title" required placeholder="ex: Sciences Formelles & Logique"
                            class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] focus:bg-[#FFFFFF] transition-all duration-300 rounded-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Description Académique</label>
                        <textarea name="module_desc" rows="3" placeholder="Description concise du cursus..."
                            class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] focus:bg-[#FFFFFF] transition-all duration-300 rounded-sm"></textarea>
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-[#111111] text-[#FFFFFF] text-xs font-semibold uppercase tracking-widest hover:bg-[#004B23] transition-all duration-300 rounded-sm">
                        Créer le Module
                    </button>
                </form>
            </div>
        </div>

        <!-- Section 2: Assignation & révocation des enseignants (AJAX) -->
        <div class="border-t border-[#E5E5E7] pt-12 space-y-6">
            <h2 class="font-serif text-3xl font-light text-[#111111]">Catalogue des Cours et Assignations</h2>
            <p class="text-sm text-[#555555] font-light max-w-2xl">
                Les cours sont créés par les enseignants. Vous êtes informé automatiquement à chaque création.
                Réassignez ou révoquez l'enseignant titulaire à tout moment — le contenu pédagogique reste intact.
            </p>

            <?php
            $recentCourses = array_filter($courses, static function (array $c): bool {
                if (empty($c['created_at'])) return false;
                return strtotime((string)$c['created_at']) >= strtotime('-14 days');
            });
            if (!empty($recentCourses)): ?>
            <div class="p-4 border border-[#004B23] bg-[#F5F5F7] space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-[#004B23]">Nouveaux cours (14 derniers jours)</p>
                <ul class="text-sm font-light text-[#555555] space-y-1">
                    <?php foreach (array_slice($recentCourses, 0, 5) as $rc): ?>
                        <li>
                            <strong class="text-[#111111]"><?= htmlspecialchars($rc['title']); ?></strong>
                            — module <?= htmlspecialchars($rc['module_title']); ?>
                            <?php if (!empty($rc['creator_name'])): ?>
                                · créé par <?= htmlspecialchars($rc['creator_name']); ?>
                            <?php endif; ?>
                            <?php if (!empty($rc['teacher_name'])): ?>
                                · assigné à <?= htmlspecialchars($rc['teacher_name']); ?>
                            <?php else: ?>
                                · <span class="italic text-[#888888]">non assigné</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-[#111111] text-xs uppercase tracking-wider text-[#555555]">
                            <th class="pb-4 font-medium">Cours</th>
                            <th class="pb-4 font-medium">Module</th>
                            <th class="pb-4 font-medium">Créé par</th>
                            <th class="pb-4 font-medium">Enseignant assigné</th>
                            <th class="pb-4 font-medium">Clé d'accès</th>
                            <th class="pb-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E5E7] text-sm font-light">
                        <?php if (empty($courses)): ?>
                            <tr><td colspan="6" class="py-8 text-center italic text-[#888888]">Aucun cours pour le moment. Les enseignants peuvent en créer depuis leur espace.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($courses as $c): ?>
                            <tr>
                                <td class="py-4 font-medium text-[#111111]">
                                    <?= htmlspecialchars($c['title']); ?>
                                    <?php if (!empty($c['description'])): ?>
                                        <p class="text-xs text-[#888888] font-light mt-1 max-w-xs line-clamp-2"><?= htmlspecialchars($c['description']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 text-[#555555]">
                                    <?= htmlspecialchars($c['module_title']); ?>
                                </td>
                                <td class="py-4 text-[#555555]">
                                    <?= !empty($c['creator_name']) ? htmlspecialchars($c['creator_name']) : '<span class="italic text-[#888888]">—</span>'; ?>
                                </td>
                                <td class="py-4">
                                    <select id="teacher-select-<?= $c['id']; ?>"
                                        class="px-2 py-1 bg-[#F5F5F7] border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm">
                                        <option value="" <?= empty($c['teacher_id']) ? 'selected' : ''; ?>>— Non assigné —</option>
                                        <?php foreach ($teachers as $t): ?>
                                            <option value="<?= $t['id']; ?>" <?= (int)($c['teacher_id'] ?? 0) === (int)$t['id'] ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($t['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="py-4 font-mono text-xs">
                                    <?= $c['enrollment_key'] ? htmlspecialchars($c['enrollment_key']) : '<span class="italic text-[#888888]">Libre</span>'; ?>
                                </td>
                                <td class="py-4 text-right space-x-2 whitespace-nowrap">
                                    <button type="button" onclick="assignTeacher(<?= $c['id']; ?>)"
                                        class="px-3 py-1 text-[10px] font-semibold uppercase tracking-wider bg-[#111111] text-white hover:bg-[#004B23] rounded-sm">
                                        Assigner
                                    </button>
                                    <?php if (!empty($c['teacher_id'])): ?>
                                    <button type="button" onclick="revokeTeacher(<?= $c['id']; ?>)"
                                        class="px-3 py-1 text-[10px] font-semibold uppercase tracking-wider border border-[#E5E5E7] hover:border-[#D32F2F] hover:text-[#D32F2F] rounded-sm">
                                        Révoquer
                                    </button>
                                    <?php endif; ?>
                                    <span id="status-<?= $c['id']; ?>" class="text-xs text-[#004B23] font-medium hidden"></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 3: Registre Officiel des Certifications Délivrées -->
        <div class="border-t border-[#E5E5E7] pt-12 space-y-6">
            <div class="flex flex-col md:flex-row justify-between md:items-end gap-4">
                <div class="space-y-2">
                    <h2 class="font-serif text-3xl font-light text-[#111111]">Registre des Certifications</h2>
                    <p class="text-sm text-[#555555] font-light max-w-xl">
                        Suivi officiel des diplômes. Délivrance automatique (≥80%) ou manuelle exceptionnelle.
                    </p>
                </div>
                <a href="/promoter/export-excel.php?type=certifications"
                   class="px-5 py-2.5 bg-[#004B23] text-[#FFFFFF] text-xs font-semibold uppercase tracking-wider hover:bg-[#111111] transition-colors rounded-sm flex-shrink-0">
                    ⬇ Exporter Excel
                </a>
            </div>

            <!-- Délivrance manuelle -->
            <div class="border border-[#E5E5E7] p-6 space-y-4 max-w-2xl">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-[#555555]">Délivrance manuelle (cas exceptionnel)</h3>
                <form id="manual-cert-form" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-xs text-[#888] mb-1">Étudiant</label>
                        <select name="student_id" required class="w-full px-3 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm rounded-sm">
                            <option value="">Choisir…</option>
                            <?php foreach ($students as $s): ?>
                                <option value="<?= $s['id']; ?>"><?= htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-[#888] mb-1">Module</label>
                        <select name="module_id" required class="w-full px-3 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm rounded-sm">
                            <option value="">Choisir…</option>
                            <?php foreach ($modules as $m): ?>
                                <option value="<?= $m['id']; ?>"><?= htmlspecialchars($m['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-[#111111] text-white text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] rounded-sm">
                        Délivrer
                    </button>
                </form>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-[#111111] text-xs uppercase tracking-wider text-[#555555]">
                            <th class="pb-4 font-medium">Étudiant</th>
                            <th class="pb-4 font-medium">Module Certifié</th>
                            <th class="pb-4 font-medium">Enseignant(s)</th>
                            <th class="pb-4 font-medium">Émis par</th>
                            <th class="pb-4 font-medium">Code Unique</th>
                            <th class="pb-4 font-medium">Date de Délivrance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E5E7] text-sm font-light">
                        <?php if (empty($certificates)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-[#888888] italic">
                                    Aucun certificat n'a été délivré pour le moment.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($certificates as $cert): ?>
                                <tr>
                                    <td class="py-4">
                                        <span class="font-medium text-[#111111]"><?= htmlspecialchars($cert['student_name']); ?></span><br>
                                        <span class="text-xs text-[#888888]"><?= htmlspecialchars($cert['student_email']); ?></span>
                                    </td>
                                    <td class="py-4 text-[#555555]">
                                        <?= htmlspecialchars($cert['module_title']); ?>
                                    </td>
                                    <td class="py-4 text-xs text-[#555555]">
                                        <?= htmlspecialchars($cert['teachers_list'] ?? 'Aucun'); ?>
                                    </td>
                                    <td class="py-4 text-xs">
                                        <?php if ($cert['manual_issue']): ?>
                                            <span class="px-2 py-0.5 bg-[#FFF8E1] text-[#5D4037] border border-[#FFE082] rounded-sm font-medium">Manuel (<?= htmlspecialchars($cert['issuer_name'] ?? 'Inconnu'); ?>)</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 bg-[#E8F5E9] text-[#1B5E20] border border-[#A5D6A7] rounded-sm font-medium">Automatique</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 font-mono text-xs font-semibold text-[#004B23]">
                                        <a href="/certificate.php?code=<?= urlencode($cert['certificate_code']); ?>" target="_blank" class="hover:underline flex items-center gap-1">
                                            <?= htmlspecialchars($cert['certificate_code']); ?>
                                            <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                        </a>
                                    </td>
                                    <td class="py-4 text-[#888888]">
                                        <?= date('d/m/Y H:i', strtotime($cert['issued_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 4 : Gestion des Comptes Utilisateurs -->
        <?php
        $pendingTeachersCount = 0;
        foreach ($teachers as $t) {
            if (!(int)$t['is_approved']) {
                $pendingTeachersCount++;
            }
        }
        $totalTeachersCount = count($teachers);
        $totalStudentsCount = count($students);
        ?>
        <div class="border-t border-[#E5E5E7] pt-12 space-y-8">
            <div class="flex flex-col md:flex-row justify-between md:items-end gap-4">
                <div class="space-y-2">
                    <h2 class="font-serif text-3xl font-light text-[#111111]">Gestion de la Communauté</h2>
                    <p class="text-sm text-[#555555] font-light max-w-xl">
                        Supervisez le corps enseignant et la communauté apprenante. Validez les nouveaux enseignants, suspendez ou supprimez des comptes à tout moment.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 flex-shrink-0">
                    <a href="/promoter/export-excel.php?type=students"
                       class="px-4 py-2 border border-[#E5E5E7] text-xs font-semibold uppercase tracking-wider hover:border-[#004B23] rounded-sm">
                        Excel apprenants
                    </a>
                    <button onclick="toggleModal('user-modal')"
                        class="px-5 py-2.5 bg-[#111111] text-[#FFFFFF] text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] transition-colors rounded-sm">
                        + Créer un Compte
                    </button>
                </div>
            </div>

            <!-- Feedback AJAX -->
            <div id="user-success" class="hidden p-3 border border-[#004B23] text-[#004B23] text-xs font-medium"></div>
            <div id="user-error"   class="hidden p-3 border border-[#D32F2F] text-[#D32F2F] text-xs font-medium"></div>

            <!-- Dashboard Cards pour les Modals -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Card 1: Corps Enseignant -->
                <div class="bg-white border border-[#E5E5E7] p-8 flex flex-col justify-between hover:shadow-lg transition-all duration-300 rounded-sm relative group">
                    <div class="space-y-4">
                        <div class="flex justify-between items-start">
                            <div class="w-12 h-12 bg-[#E8F5E9] text-[#004B23] flex items-center justify-center rounded-full">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 4a2 2 0 00-2-2m-2 3h4m-4 3h4m-4 3h4m-4 3h4" />
                                </svg>
                            </div>
                            <?php if ($pendingTeachersCount > 0): ?>
                                <span class="flex h-3 w-3 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="font-serif text-xl text-[#111111] font-medium">Corps Enseignant</h3>
                            <p class="text-xs text-[#888888] mt-1">Supervisez et validez les comptes professeurs du StudyVibe.</p>
                        </div>
                        <div class="flex gap-6 pt-2 text-xs">
                            <div>
                                <span class="font-bold text-lg text-[#111111]"><?= $totalTeachersCount; ?></span>
                                <span class="text-[#888888] block text-[10px] uppercase">Enseignants</span>
                            </div>
                            <div>
                                <span class="font-bold text-lg <?= $pendingTeachersCount > 0 ? 'text-amber-600 font-semibold' : 'text-[#888888]'; ?>"><?= $pendingTeachersCount; ?></span>
                                <span class="text-[#888888] block text-[10px] uppercase">En attente</span>
                            </div>
                        </div>
                    </div>
                    <div class="pt-6">
                        <button onclick="toggleModal('teachers-list-modal')"
                            class="w-full py-2.5 bg-[#004B23] text-white text-xs font-semibold uppercase tracking-wider hover:bg-[#111111] transition-colors rounded-sm flex items-center justify-center gap-2">
                            <span>Gérer les Enseignants</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Card 2: Communauté Apprenante -->
                <div class="bg-white border border-[#E5E5E7] p-8 flex flex-col justify-between hover:shadow-lg transition-all duration-300 rounded-sm group">
                    <div class="space-y-4">
                        <div class="flex justify-between items-start">
                            <div class="w-12 h-12 bg-[#F5F5F7] text-[#555555] flex items-center justify-center rounded-full">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <h3 class="font-serif text-xl text-[#111111] font-medium">Communauté Apprenante</h3>
                            <p class="text-xs text-[#888888] mt-1">Gérez les inscriptions et les statuts des étudiants.</p>
                        </div>
                        <div class="flex gap-6 pt-2 text-xs">
                            <div>
                                <span class="font-bold text-lg text-[#111111]"><?= $totalStudentsCount; ?></span>
                                <span class="text-[#888888] block text-[10px] uppercase">Étudiants Inscrits</span>
                            </div>
                        </div>
                    </div>
                    <div class="pt-6">
                        <button onclick="toggleModal('students-list-modal')"
                            class="w-full py-2.5 bg-[#111111] text-white text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] transition-colors rounded-sm flex items-center justify-center gap-2">
                            <span>Gérer les Apprenants</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Card 3: Téléévaluations -->
                <div class="bg-white border border-[#E5E5E7] p-8 flex flex-col justify-between hover:shadow-lg transition-all duration-300 rounded-sm group">
                    <div class="space-y-4">
                        <div class="flex justify-between items-start">
                            <div class="w-12 h-12 bg-[#E3F2FD] text-[#1E88E5] flex items-center justify-center rounded-full">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <h3 class="font-serif text-xl text-[#111111] font-medium">Téléévaluations</h3>
                            <p class="text-xs text-[#888888] mt-1">Supervisez les sessions, modifiez les horaires ou annulez des séances.</p>
                        </div>
                        <div class="flex gap-6 pt-2 text-xs">
                            <div>
                                <span class="font-bold text-lg text-[#111111]"><?= count($liveSessions); ?></span>
                                <span class="text-[#888888] block text-[10px] uppercase">Séances</span>
                            </div>
                            <div>
                                <span class="font-bold text-lg text-[#111111]"><?= $totalEvaluatedCount; ?></span>
                                <span class="text-[#888888] block text-[10px] uppercase">Évalués</span>
                            </div>
                        </div>
                    </div>
                    <div class="pt-6">
                        <button onclick="toggleModal('tele-evaluations-modal')"
                            class="w-full py-2.5 bg-[#111111] text-white text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] transition-colors rounded-sm flex items-center justify-center gap-2">
                            <span>Console de Supervision</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 5 : Newsletter -->
        <div class="border-t border-[#E5E5E7] pt-12 space-y-8" id="newsletter">
            <div class="flex flex-col md:flex-row justify-between md:items-end gap-4">
                <div class="space-y-2">
                    <h2 class="font-serif text-3xl font-light text-[#111111]">Newsletter</h2>
                    <p class="text-sm text-[#555555] font-light max-w-xl">
                        Composez et envoyez des communications à vos abonnés ou à l'ensemble des apprenants.
                        <?php if ($smtpConfigured): ?>
                            <span class="text-[#004B23]">SMTP configuré ✓</span>
                        <?php else: ?>
                            <span class="text-[#D32F2F]">SMTP non configuré — renseignez .env pour activer l'envoi.</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="text-right">
                    <div class="font-serif text-2xl text-[#111111]"><?= $kpiNewsletterSubs; ?></div>
                    <div class="text-[10px] uppercase tracking-wider text-[#888888]">Abonnés actifs</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <form id="newsletter-form" class="space-y-4 bg-[#FFFFFF] border border-[#E5E5E7] p-6">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Audience</label>
                        <select name="audience" class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm rounded-sm">
                            <option value="subscribers">Abonnés newsletter uniquement</option>
                            <option value="students">Tous les apprenants inscrits</option>
                            <option value="all">Abonnés + tous les apprenants</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Sujet</label>
                        <input type="text" name="subject" required placeholder="ex: Nouveaux cours disponibles"
                            class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm rounded-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Message</label>
                        <textarea name="body_html" rows="8" required placeholder="Rédigez votre message…"
                            class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm rounded-sm"></textarea>
                    </div>
                    <button type="submit" <?= $smtpConfigured ? '' : 'disabled'; ?>
                        class="px-6 py-2.5 bg-[#111111] text-[#FFFFFF] text-xs font-semibold uppercase tracking-widest hover:bg-[#004B23] transition-colors rounded-sm disabled:opacity-40">
                        Envoyer la newsletter
                    </button>
                    <button type="button" id="test-smtp-btn" <?= $smtpConfigured ? '' : 'disabled'; ?>
                        class="ml-3 px-4 py-2.5 border border-[#E5E5E7] text-xs font-semibold uppercase tracking-widest rounded-sm disabled:opacity-40">
                        Tester SMTP
                    </button>
                </form>

                <div class="space-y-6">
                    <h3 class="font-serif text-xl font-light">Abonnés récents</h3>
                    <div class="overflow-x-auto max-h-48 overflow-y-auto border border-[#E5E5E7]">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-[#E5E5E7] text-[#555555] uppercase tracking-wider">
                                    <th class="p-3">Email</th>
                                    <th class="p-3">Depuis</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E5E5E7]">
                                <?php if (empty($newsletterSubscribers)): ?>
                                    <tr><td colspan="2" class="p-4 text-center text-[#888] italic">Aucun abonné pour le moment.</td></tr>
                                <?php else: ?>
                                    <?php foreach (array_slice($newsletterSubscribers, 0, 15) as $sub): ?>
                                        <tr>
                                            <td class="p-3"><?= htmlspecialchars($sub['email']); ?></td>
                                            <td class="p-3 font-mono text-[#888]"><?= date('d/m/Y', strtotime($sub['subscribed_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="font-serif text-xl font-light">Campagnes envoyées</h3>
                    <div class="overflow-x-auto max-h-48 overflow-y-auto border border-[#E5E5E7]">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-[#E5E5E7] text-[#555555] uppercase tracking-wider">
                                    <th class="p-3">Sujet</th>
                                    <th class="p-3">Envoyés</th>
                                    <th class="p-3">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E5E5E7]">
                                <?php if (empty($newsletterCampaigns)): ?>
                                    <tr><td colspan="3" class="p-4 text-center text-[#888] italic">Aucune campagne envoyée.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($newsletterCampaigns as $camp): ?>
                                        <tr>
                                            <td class="p-3 font-medium"><?= htmlspecialchars($camp['subject']); ?></td>
                                            <td class="p-3"><?= (int)$camp['recipient_count']; ?></td>
                                            <td class="p-3 font-mono text-[#888]"><?= date('d/m/Y H:i', strtotime($camp['sent_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 6 : Journal d'audit -->
        <div class="border-t border-[#E5E5E7] pt-12 space-y-6">
            <div class="flex flex-col md:flex-row justify-between md:items-end gap-4">
                <div>
                    <h2 class="font-serif text-3xl font-light">Journal d'Audit</h2>
                    <p class="text-sm text-[#555555] font-light">Connexions, certifications, modifications importantes.</p>
                </div>
                <a href="/promoter/export-excel.php?type=audit_logs"
                   class="px-5 py-2.5 bg-[#004B23] text-white text-xs font-semibold uppercase tracking-wider hover:bg-[#111111] rounded-sm flex-shrink-0">
                    ⬇ Export Excel complet
                </a>
            </div>
            <div class="overflow-x-auto max-h-80 overflow-y-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-[#111111] uppercase tracking-wider text-[#555555]">
                            <th class="pb-3 pr-4">Date</th>
                            <th class="pb-3 pr-4">Utilisateur</th>
                            <th class="pb-3 pr-4">Action</th>
                            <th class="pb-3">Détails</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E5E5E7]">
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td class="py-2 pr-4 font-mono text-[#888]"><?= date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                <td class="py-2 pr-4"><?= htmlspecialchars($log['user_name'] ?? '—'); ?></td>
                                <td class="py-2 pr-4 font-medium"><?= htmlspecialchars($log['action']); ?></td>
                                <td class="py-2 text-[#555]"><?= htmlspecialchars($log['details'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 7 : API REST -->
        <div class="border-t border-[#E5E5E7] pt-12 space-y-4">
            <h2 class="font-serif text-3xl font-light">API REST (v1)</h2>
            <p class="text-sm text-[#555555] font-light">Générez une clé API pour connecter une application mobile future.</p>
            <button onclick="createApiKey()" class="px-5 py-2 bg-[#111111] text-white text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] rounded-sm">
                Générer une clé API
            </button>
            <div id="api-key-result" class="hidden p-4 border border-[#004B23] text-xs font-mono"></div>
        </div>

    </main>

    <!-- Modal : Créer un Utilisateur -->
    <div id="user-modal" class="hidden fixed inset-0 bg-black bg-opacity-40 backdrop-blur-sm z-50 flex items-center justify-center p-6">
        <div class="bg-[#FFFFFF] p-8 max-w-md w-full border border-[#E5E5E7] space-y-6">
            <h3 class="font-serif text-2xl font-light">Créer un Nouveau Compte</h3>

            <div id="modal-error" class="hidden p-3 border border-[#D32F2F] text-[#D32F2F] text-xs font-medium"></div>

            <form id="create-user-form" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Nom Complet</label>
                    <input type="text" name="name" required placeholder="ex: Dr. Isabelle Martin"
                        class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Adresse Électronique</label>
                    <input type="email" name="email" required placeholder="ex: isabelle@studyvibe.edu"
                        class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Mot de passe initial</label>
                    <input type="password" name="password" required minlength="6" placeholder="Min. 6 caractères"
                        class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Rôle Institutionnel</label>
                    <select name="role" required
                        class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
                        <option value="teacher">Enseignant</option>
                        <option value="student">Apprenant</option>
                        <option value="promoter">Promoteur (Co-Administrateur)</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="toggleModal('user-modal')"
                        class="px-4 py-2 bg-[#F5F5F7] text-[#111111] text-xs font-semibold uppercase tracking-wider border border-[#E5E5E7] rounded-sm">
                        Annuler
                    </button>
                    <button type="submit" id="create-user-btn"
                        class="px-4 py-2 bg-[#111111] text-[#FFFFFF] text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] rounded-sm">
                        Créer le Compte
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal : Supervision des Téléévaluations -->
    <div id="tele-evaluations-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-start justify-center p-4 overflow-y-auto">
        <div class="bg-white w-full max-w-6xl border border-[#E5E5E7] my-8 rounded-sm shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="flex justify-between items-center px-8 py-6 border-b border-[#E5E5E7] bg-[#F5F5F7]">
                <div>
                    <h3 class="font-serif text-2xl font-light text-[#111111]">Supervision des Téléévaluations</h3>
                    <p class="text-xs text-[#888888] mt-1">Gérez le calendrier des séances de télé-évaluation et consultez les statistiques en temps réel.</p>
                </div>
                <button type="button" onclick="toggleModal('tele-evaluations-modal')" class="text-[#888888] hover:text-[#D32F2F] p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="p-8 space-y-8 max-h-[80vh] overflow-y-auto bg-[#FAFAFA]">
                <!-- Stats Overview & Chart -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Metrics Column -->
                    <div class="space-y-4 lg:col-span-1">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Indicateurs clés</h4>
                        
                        <!-- Metric 1: Total évalués -->
                        <div class="bg-white border border-[#E5E5E7] p-5 rounded-sm flex items-center justify-between">
                            <div>
                                <span class="text-[10px] text-gray-400 uppercase tracking-wider block font-medium">Participants Évalués</span>
                                <span class="text-3xl font-light text-gray-900 mt-1 block"><?= $totalEvaluatedCount ?></span>
                            </div>
                            <div class="w-10 h-10 bg-green-50 text-[#004B23] flex items-center justify-center rounded-full">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                        </div>

                        <!-- Metric 2: Moyenne générale -->
                        <div class="bg-white border border-[#E5E5E7] p-5 rounded-sm flex items-center justify-between">
                            <div>
                                <span class="text-[10px] text-gray-400 uppercase tracking-wider block font-medium">Moyenne Générale</span>
                                <span class="text-3xl font-light text-gray-900 mt-1 block"><?= round($overallAvgScore, 1) ?> %</span>
                            </div>
                            <div class="w-10 h-10 bg-blue-50 text-blue-600 flex items-center justify-center rounded-full">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                        </div>

                        <!-- Metric 3: Taux de réussite -->
                        <div class="bg-white border border-[#E5E5E7] p-5 rounded-sm flex items-center justify-between">
                            <div>
                                <span class="text-[10px] text-gray-400 uppercase tracking-wider block font-medium">Taux de Réussite (≥50%)</span>
                                <span class="text-3xl font-light text-gray-900 mt-1 block"><?= round($overallSuccessRate, 1) ?> %</span>
                            </div>
                            <div class="w-10 h-10 bg-purple-50 text-purple-600 flex items-center justify-center rounded-full">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Score Distribution SVG Chart -->
                    <div class="bg-white border border-[#E5E5E7] p-6 rounded-sm lg:col-span-2 flex flex-col justify-between">
                        <div>
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Distribution des notes</h4>
                            <p class="text-[11px] text-gray-400">Répartition du nombre d'étudiants évalués par tranches de notes (en %)</p>
                        </div>
                        
                        <div class="w-full h-48 mt-4 flex items-end justify-between relative px-2">
                            <!-- Draw Y axis grid lines -->
                            <div class="absolute inset-0 flex flex-col justify-between pointer-events-none border-b border-gray-100 pb-6">
                                <div class="border-b border-dashed border-gray-100 w-full h-0"></div>
                                <div class="border-b border-dashed border-gray-100 w-full h-0"></div>
                                <div class="border-b border-dashed border-gray-100 w-full h-0"></div>
                            </div>
                            
                            <?php 
                            $labels = ['0-20%', '21-40%', '41-60%', '61-80%', '81-100%'];
                            foreach ($buckets as $idx => $val):
                                $heightPercent = ($val / $maxBucket) * 120; // Scale to max height
                            ?>
                                <div class="flex-grow flex flex-col items-center group relative z-10 mx-2">
                                    <!-- Tooltip -->
                                    <div class="absolute bottom-full mb-2 bg-[#111] text-white text-[10px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none font-semibold">
                                        <?= $val ?> participant(s)
                                    </div>
                                    <!-- Bar -->
                                    <div class="w-full bg-[#EAF2EC] border border-[#004B23]/10 hover:bg-[#004B23] hover:border-[#004B23] transition-all duration-300 rounded-t-sm" style="height: <?= max(4, $heightPercent) ?>px;"></div>
                                    <!-- Label -->
                                    <span class="text-[10px] text-gray-500 mt-2 font-medium"><?= $labels[$idx] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Live Sessions List -->
                <div class="space-y-4">
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Séances programmées & passées</h4>
                    
                    <div class="bg-white border border-[#E5E5E7] rounded-sm overflow-hidden">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-[#F5F5F7] text-gray-500 uppercase tracking-wider font-semibold border-b border-[#E5E5E7]">
                                    <th class="p-4">Séance / Cours</th>
                                    <th class="p-4">Enseignant</th>
                                    <th class="p-4">Statut</th>
                                    <th class="p-4">Inscrits / Évalués</th>
                                    <th class="p-4">Moyenne</th>
                                    <th class="p-4">Date & Heures</th>
                                    <th class="p-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E5E5E7]">
                                <?php if (empty($liveSessions)): ?>
                                    <tr>
                                        <td colspan="7" class="p-8 text-center text-gray-400 italic">Aucune séance de télé-évaluation n'est actuellement configurée.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($liveSessions as $session): 
                                        $statusColor = match($session['status']) {
                                            'lobby' => 'bg-blue-100 text-blue-800 border-blue-200',
                                            'active' => 'bg-green-100 text-green-800 border-green-200',
                                            'finished' => 'bg-gray-100 text-gray-800 border-gray-200',
                                            default => 'bg-yellow-100 text-yellow-800 border-yellow-200'
                                        };
                                        $statusText = match($session['status']) {
                                            'lobby' => 'Lobby',
                                            'active' => 'En cours',
                                            'finished' => 'Terminé',
                                            default => 'Brouillon'
                                        };
                                    ?>
                                        <tr class="hover:bg-gray-50/50">
                                            <td class="p-4">
                                                <div class="font-semibold text-gray-900"><?= htmlspecialchars($session['title']) ?></div>
                                                <div class="text-[10px] text-gray-400 mt-0.5">Code: <span class="font-mono bg-gray-100 px-1 py-0.5"><?= htmlspecialchars($session['session_code']) ?></span> | Cours: <?= htmlspecialchars($session['course_title']) ?></div>
                                            </td>
                                            <td class="p-4 text-gray-600"><?= htmlspecialchars($session['teacher_name']) ?></td>
                                            <td class="p-4">
                                                <span class="px-2 py-0.5 text-[10px] rounded-full border font-medium <?= $statusColor ?>"><?= $statusText ?></span>
                                            </td>
                                            <td class="p-4 text-gray-600 font-medium">
                                                <?= $session['registered_count'] ?> inscrits / <?= $session['evaluated_count'] ?> évalués
                                            </td>
                                            <td class="p-4 text-gray-700 font-semibold"><?= round((float)$session['avg_score'], 1) ?> %</td>
                                            <td class="p-4 text-gray-500 space-y-0.5">
                                                <div>Début: <?= date('d/m/Y H:i', strtotime($session['start_time'])) ?></div>
                                                <div>Fin: <?= date('d/m/Y H:i', strtotime($session['end_time'])) ?></div>
                                            </td>
                                            <td class="p-4 text-right">
                                                <div class="flex items-center justify-end gap-2" id="session-actions-<?= $session['id'] ?>">
                                                    <button onclick="showPostponeForm(<?= $session['id'] ?>)" class="px-2 py-1 text-[10px] font-semibold text-[#004B23] bg-green-50 hover:bg-[#EAF2EC] border border-green-200/50 rounded-sm">Reporter</button>
                                                    
                                                    <form action="" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer/annuler définitivement cette séance ?');" class="inline">
                                                        <input type="hidden" name="action" value="cancel_session">
                                                        <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                                        <button type="submit" class="px-2 py-1 text-[10px] font-semibold text-red-600 bg-red-50 hover:bg-red-100 border border-red-200/50 rounded-sm">Annuler</button>
                                                    </form>
                                                </div>
                                                
                                                <form action="" method="POST" id="postpone-form-<?= $session['id'] ?>" class="hidden text-left mt-2 p-3 bg-[#F5F5F7] border border-[#E5E5E7] rounded-sm space-y-2 max-w-xs ml-auto">
                                                    <input type="hidden" name="action" value="postpone_session">
                                                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                                    <div>
                                                        <label class="block text-[9px] uppercase tracking-wider text-gray-500 font-semibold mb-1">Date de début</label>
                                                        <input type="datetime-local" name="start_time" required value="<?= date('Y-m-d\TH:i', strtotime($session['start_time'])) ?>" class="w-full px-2 py-1 bg-white border border-[#E5E5E7] text-[11px] focus:outline-none rounded-sm">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[9px] uppercase tracking-wider text-gray-500 font-semibold mb-1">Date de fin</label>
                                                        <input type="datetime-local" name="end_time" required value="<?= date('Y-m-d\TH:i', strtotime($session['end_time'])) ?>" class="w-full px-2 py-1 bg-white border border-[#E5E5E7] text-[11px] focus:outline-none rounded-sm">
                                                    </div>
                                                    <div class="flex gap-2 justify-end">
                                                        <button type="button" onclick="hidePostponeForm(<?= $session['id'] ?>)" class="px-2 py-1 text-[9px] font-semibold text-gray-500 border border-gray-300 bg-white rounded-sm">Retour</button>
                                                        <button type="submit" class="px-2 py-1 text-[9px] font-semibold text-white bg-[#004B23] rounded-sm hover:bg-[#003d1c]">Enregistrer</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal : Liste des Enseignants (Modal Cards) -->
    <div id="teachers-list-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-start justify-center p-4 overflow-y-auto">
        <div class="bg-white w-full max-w-5xl border border-[#E5E5E7] my-8 rounded-sm shadow-2xl">
            <!-- Header -->
            <div class="flex justify-between items-center px-8 py-6 border-b border-[#E5E5E7] bg-[#F5F5F7]">
                <div>
                    <h3 class="font-serif text-2xl font-light text-[#111111]">Membres du Corps Enseignant</h3>
                    <p class="text-xs text-[#888888] mt-1">Validez les comptes des nouveaux professeurs et gérez les accès.</p>
                </div>
                <button type="button" onclick="toggleModal('teachers-list-modal')" class="text-[#888888] hover:text-[#D32F2F] p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Filtre de recherche rapide -->
            <div class="px-8 py-4 bg-white border-b border-[#E5E5E7]">
                <input type="text" oninput="filterTeachersCards(this.value)" placeholder="Rechercher un enseignant par nom ou email..."
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm">
            </div>

            <!-- Liste des cartes -->
            <div class="p-8 max-h-[60vh] overflow-y-auto bg-[#FAFAFA]" id="teachers-cards-container">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($teachers)): ?>
                        <p class="text-sm italic text-[#888888] col-span-3 text-center py-8">Aucun enseignant inscrit.</p>
                    <?php else: ?>
                        <?php foreach ($teachers as $t): 
                            $initials = strtoupper(substr($t['name'], 0, 2));
                            $isApproved = (int)$t['is_approved'];
                            $isActive = (int)$t['is_active'];
                        ?>
                            <div class="bg-white border border-[#E5E5E7] p-6 rounded-sm space-y-4 hover:shadow-md transition-shadow duration-300 teacher-card" 
                                data-name="<?= htmlspecialchars(strtolower($t['name'])); ?>" 
                                data-email="<?= htmlspecialchars(strtolower($t['email'])); ?>">
                                
                                <!-- Header carte -->
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-[#E8F5E9] text-[#004B23] flex items-center justify-center font-bold text-xs uppercase border border-[#004B23]/20">
                                        <?= $initials; ?>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="font-semibold text-sm text-[#111111] truncate"><?= htmlspecialchars($t['name']); ?></h4>
                                        <p class="text-[11px] text-[#888888] truncate font-light"><?= htmlspecialchars($t['email']); ?></p>
                                    </div>
                                </div>

                                <!-- Badges d'état -->
                                <div class="flex flex-wrap gap-2 text-[10px]">
                                    <?php if ($isApproved): ?>
                                        <span class="px-2 py-0.5 rounded-full font-semibold bg-[#DCFCE7] text-[#15803D]">✓ Validé</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full font-semibold bg-[#FEF3C7] text-[#D97706] animate-pulse">⚡ En attente</span>
                                    <?php endif; ?>

                                    <?php if ($isActive): ?>
                                        <span class="px-2 py-0.5 rounded-full font-semibold bg-[#E0F2FE] text-[#0369A1]">Actif</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full font-semibold bg-[#F3F4F6] text-[#6B7280]">Suspendu</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Actions -->
                                <div class="pt-4 border-t border-[#E5E5E7] flex flex-wrap gap-2 justify-between">
                                    <div class="flex gap-2">
                                        <?php if (!$isApproved): ?>
                                            <button onclick="manageUserAccount(<?= $t['id']; ?>, 'approve')"
                                                class="px-2.5 py-1.5 bg-[#004B23] text-white text-[10px] font-semibold uppercase tracking-wider hover:bg-[#111111] transition-colors rounded-sm">
                                                Valider
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="manageUserAccount(<?= $t['id']; ?>, 'toggle_active')"
                                            class="px-2.5 py-1.5 border border-[#E5E5E7] text-[10px] font-semibold uppercase tracking-wider hover:bg-[#111111] hover:text-white hover:border-[#111111] transition-colors rounded-sm">
                                            <?= $isActive ? 'Suspendre' : 'Réactiver'; ?>
                                        </button>
                                    </div>
                                    <button onclick="manageUserAccount(<?= $t['id']; ?>, 'delete')"
                                        class="px-2.5 py-1.5 border border-[#D32F2F] text-[#D32F2F] text-[10px] font-semibold uppercase tracking-wider hover:bg-[#D32F2F] hover:text-white transition-colors rounded-sm"
                                        title="Supprimer définitivement">
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-8 py-4 border-t border-[#E5E5E7] bg-[#F5F5F7] flex justify-end">
                <button onclick="toggleModal('teachers-list-modal')" class="px-5 py-2 bg-[#111111] text-white text-xs font-semibold uppercase tracking-wider rounded-sm hover:bg-[#004B23] transition-colors">Fermer</button>
            </div>
        </div>
    </div>

    <!-- Modal : Liste des Apprenants (Modal Cards) -->
    <div id="students-list-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-start justify-center p-4 overflow-y-auto">
        <div class="bg-white w-full max-w-5xl border border-[#E5E5E7] my-8 rounded-sm shadow-2xl">
            <!-- Header -->
            <div class="flex justify-between items-center px-8 py-6 border-b border-[#E5E5E7] bg-[#F5F5F7]">
                <div>
                    <h3 class="font-serif text-2xl font-light text-[#111111]">Membres de la Communauté Apprenante</h3>
                    <p class="text-xs text-[#888888] mt-1">Gérez les accès et suspensions des comptes étudiants.</p>
                </div>
                <button type="button" onclick="toggleModal('students-list-modal')" class="text-[#888888] hover:text-[#D32F2F] p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Filtre de recherche rapide -->
            <div class="px-8 py-4 bg-white border-b border-[#E5E5E7]">
                <input type="text" oninput="filterStudentsCards(this.value)" placeholder="Rechercher un étudiant par nom ou email..."
                    class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-xs focus:outline-none focus:border-[#004B23] rounded-sm">
            </div>

            <!-- Liste des cartes -->
            <div class="p-8 max-h-[60vh] overflow-y-auto bg-[#FAFAFA]" id="students-cards-container">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($students)): ?>
                        <p class="text-sm italic text-[#888888] col-span-3 text-center py-8">Aucun apprenant inscrit.</p>
                    <?php else: ?>
                        <?php foreach ($students as $s): 
                            $initials = strtoupper(substr($s['name'], 0, 2));
                            $isActive = (int)$s['is_active'];
                        ?>
                            <div class="bg-white border border-[#E5E5E7] p-6 rounded-sm space-y-4 hover:shadow-md transition-shadow duration-300 student-card"
                                data-name="<?= htmlspecialchars(strtolower($s['name'])); ?>" 
                                data-email="<?= htmlspecialchars(strtolower($s['email'])); ?>">
                                
                                <!-- Header carte -->
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-[#F5F5F7] text-[#555555] flex items-center justify-center font-bold text-xs uppercase border border-[#E5E5E7]">
                                        <?= $initials; ?>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="font-semibold text-sm text-[#111111] truncate"><?= htmlspecialchars($s['name']); ?></h4>
                                        <p class="text-[11px] text-[#888888] truncate font-light"><?= htmlspecialchars($s['email']); ?></p>
                                    </div>
                                </div>

                                <!-- Badges d'état -->
                                <div class="flex flex-wrap gap-2 text-[10px]">
                                    <?php if ($isActive): ?>
                                        <span class="px-2 py-0.5 rounded-full font-semibold bg-[#E0F2FE] text-[#0369A1]">Actif</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full font-semibold bg-[#F3F4F6] text-[#6B7280]">Suspendu</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Actions -->
                                <div class="pt-4 border-t border-[#E5E5E7] flex flex-wrap gap-2 justify-between items-center">
                                    <div class="flex gap-2">
                                        <button onclick="openDirectMessageModal(<?= $s['id']; ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES); ?>')"
                                            class="px-2.5 py-1.5 bg-[#004B23] text-white text-[10px] font-semibold uppercase tracking-wider hover:bg-[#111111] transition-colors rounded-sm">
                                            Contacter
                                        </button>
                                        <button onclick="manageUserAccount(<?= $s['id']; ?>, 'toggle_active')"
                                            class="px-2.5 py-1.5 border border-[#E5E5E7] text-[10px] font-semibold uppercase tracking-wider hover:bg-[#111111] hover:text-white hover:border-[#111111] transition-colors rounded-sm">
                                            <?= $isActive ? 'Suspendre' : 'Réactiver'; ?>
                                        </button>
                                    </div>
                                    <button onclick="manageUserAccount(<?= $s['id']; ?>, 'delete')"
                                        class="px-2.5 py-1.5 border border-[#D32F2F] text-[#D32F2F] text-[10px] font-semibold uppercase tracking-wider hover:bg-[#D32F2F] hover:text-white transition-colors rounded-sm"
                                        title="Supprimer définitivement">
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-8 py-4 border-t border-[#E5E5E7] bg-[#F5F5F7] flex justify-end">
                <button onclick="toggleModal('students-list-modal')" class="px-5 py-2 bg-[#111111] text-white text-xs font-semibold uppercase tracking-wider rounded-sm hover:bg-[#004B23] transition-colors">Fermer</button>
            </div>
        </div>
    </div>

    <!-- Modal : Envoyer un Message Direct à un Apprenant -->
    <div id="direct-message-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-6">
        <div class="bg-white p-8 max-w-lg w-full border border-[#E5E5E7] space-y-6 shadow-2xl rounded-sm">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="font-serif text-2xl font-light text-[#111111]">Envoyer un Message Direct</h3>
                    <p class="text-xs text-[#888888] mt-1">Le message sera envoyé directement dans la boîte mail de l'apprenant.</p>
                </div>
                <button type="button" onclick="toggleModal('direct-message-modal')" class="text-[#888888] hover:text-[#D32F2F]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="direct-message-form" class="space-y-4">
                <input type="hidden" name="student_id" id="dm-student-id">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Destinataire</label>
                    <input type="text" id="dm-student-name" readonly
                        class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm text-[#555555] cursor-not-allowed rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Sujet du Message</label>
                    <input type="text" name="subject" required placeholder="ex: Information concernant votre certificat"
                        class="w-full px-4 py-2 bg-white border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Message</label>
                    <textarea name="message" rows="6" required placeholder="Rédigez votre message ici..."
                        class="w-full px-4 py-2 bg-white border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="toggleModal('direct-message-modal')"
                        class="px-4 py-2 bg-[#F5F5F7] text-[#111111] text-xs font-semibold uppercase tracking-wider border border-[#E5E5E7] rounded-sm">
                        Annuler
                    </button>
                    <button type="submit" id="send-dm-btn"
                        class="px-4 py-2 bg-[#004B23] text-white text-xs font-semibold uppercase tracking-wider hover:bg-[#111111] rounded-sm">
                        Envoyer le Message
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pied de Page -->
    <footer class="border-t border-[#E5E5E7] py-6 px-12 flex justify-between items-center bg-[#F5F5F7] text-xs text-[#888888] font-light">
        <div>StudyVibe Académique — Plateforme de Gouvernance</div>
        <div>Console d'Administration</div>
    </footer>

    <!-- Scripts AJAX -->
    <script>
        // --- Modal Helper ---
        function toggleModal(id) {
            document.getElementById(id).classList.toggle('hidden');
        }

        // --- Audit Strategique IA (Gemini) ---
        function runStrategicAudit() {
            const btn = document.getElementById('audit-btn');
            const loader = document.getElementById('audit-loading');
            const container = document.getElementById('audit-result-container');
            const textDiv = document.getElementById('audit-text');

            btn.disabled = true;
            loader.classList.remove('hidden');
            container.classList.add('hidden');

            fetch('/api/ai-promoter.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                loader.classList.add('hidden');
                if (data.success && data.report) {
                    textDiv.textContent = data.report;
                    container.classList.remove('hidden');
                    Toast.success('Rapport strategique genere.');
                } else {
                    Toast.error(data.error || 'Erreur lors de la generation du rapport.');
                }
            })
            .catch(err => {
                btn.disabled = false;
                loader.classList.add('hidden');
                Toast.error('Erreur reseau : ' + err.message);
            });
        }

        // --- Assignation / révocation enseignant ---
        function postTeacherUpdate(courseId, teacherId, successLabel) {
            const statusLabel = document.getElementById(`status-${courseId}`);
            const fd = new FormData();
            fd.append('course_id', courseId);
            fd.append('teacher_id', teacherId);

            fetch('/promoter/update-teacher.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        statusLabel.textContent = successLabel;
                        statusLabel.className = 'text-xs text-[#004B23] font-medium';
                        statusLabel.classList.remove('hidden');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        Toast.error('Erreur: ' + (data.message || 'Impossible de mettre à jour.'));
                    }
                })
                .catch(err => Toast.error('Erreur réseau: ' + err.message));
        }

        function assignTeacher(courseId) {
            const select = document.getElementById(`teacher-select-${courseId}`);
            const teacherId = select ? select.value : '';
            if (!teacherId) {
                Toast.error('Sélectionnez un enseignant à assigner.');
                return;
            }
            postTeacherUpdate(courseId, teacherId, 'Assigné ✓');
        }

        function revokeTeacher(courseId) {
            if (!confirm('Révoquer l\'enseignant de ce cours ? Le contenu pédagogique sera conservé.')) return;
            postTeacherUpdate(courseId, '0', 'Révoqué ✓');
        }

        document.getElementById('test-smtp-btn')?.addEventListener('click', () => {
            fetch('/promoter/test-mail.php', { method: 'POST' })
            .then(r => r.json())
            .then(data => data.success ? Toast.success(data.message) : Toast.error(data.message));
        });

        document.getElementById('newsletter-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!confirm('Confirmer l\'envoi de cette newsletter ?')) return;
            const btn = this.querySelector('button[type=submit]');
            btn.disabled = true;
            btn.textContent = 'Envoi en cours…';
            const fd = new FormData(this);
            fetch('/promoter/send-newsletter.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Toast.success(data.message, 6000);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    Toast.error(data.message);
                    btn.disabled = false;
                    btn.textContent = 'Envoyer la newsletter';
                }
            })
            .catch(err => {
                Toast.error('Erreur réseau: ' + err.message);
                btn.disabled = false;
                btn.textContent = 'Envoyer la newsletter';
            });
        });

        document.getElementById('manual-cert-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fetch('/promoter/issue-certificate.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Toast.success(`Certificat ${data.certificate_code} délivré à ${data.student_name}.`);
                    setTimeout(() => location.reload(), 1200);
                } else Toast.error(data.message);
            });
        });

        function createApiKey() {
    const fd = new FormData();
    fd.append('label', 'Clé mobile ' + new Date().toLocaleDateString('fr-FR'));
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
    fetch('/promoter/create-api-key.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        const el = document.getElementById('api-key-result');
        if (data.success) {
            el.classList.remove('hidden');
            el.innerHTML = `<strong>Clé API (à copier maintenant) :</strong><br>${data.api_key}<br><span class="text-[#888]">Endpoints : GET /api/v1/courses, GET /api/v1/modules</span>`;
            Toast.success('Clé API générée.');
        } else Toast.error(data.message);
    });
    }



        // --- Création de Compte Utilisateur ---
        document.getElementById('create-user-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('create-user-btn');
            const modalErr = document.getElementById('modal-error');
            modalErr.classList.add('hidden');
            btn.disabled = true;
            btn.textContent = 'Création…';

            const fd = new FormData(this);

            fetch('/promoter/create-user.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.textContent = 'Créer le Compte';

                    if (data.success) {
                        toggleModal('user-modal');
                        this.reset();
                        Toast.success(`Compte créé avec succès.`);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        Toast.error(data.message || 'Erreur lors de la création.');
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.textContent = 'Créer le Compte';
                    Toast.error('Erreur réseau : ' + err.message);
                });
        });

        // --- Administration des Utilisateurs (Approbation, Suspension, Suppression) ---
        function manageUserAccount(userId, action) {
            let confirmMsg = '';
            if (action === 'delete') {
                confirmMsg = 'Êtes-vous sûr de vouloir supprimer définitivement ce compte ? Cette action est irréversible et effacera toutes ses données.';
            } else if (action === 'toggle_active') {
                confirmMsg = 'Confirmez-vous le changement de statut de ce compte ?';
            } else if (action === 'approve') {
                confirmMsg = 'Valider cet enseignant et l\'autoriser à enseigner sur la plateforme ?';
            }

            if (confirmMsg && !confirm(confirmMsg)) return;

            const fd = new FormData();
            fd.append('user_id', userId);
            fd.append('action', action);

            fetch('/promoter/manage-user.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Toast.success(data.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        Toast.error(data.message || 'Une erreur est survenue.');
                    }
                })
                .catch(err => Toast.error('Erreur réseau : ' + err.message));
        }

        function filterTeachersCards(query) {
            const val = query.toLowerCase().trim();
            document.querySelectorAll('.teacher-card').forEach(card => {
                const name = card.dataset.name || '';
                const email = card.dataset.email || '';
                if (name.includes(val) || email.includes(val)) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        }

        function filterStudentsCards(query) {
            const val = query.toLowerCase().trim();
            document.querySelectorAll('.student-card').forEach(card => {
                const name = card.dataset.name || '';
                const email = card.dataset.email || '';
                if (name.includes(val) || email.includes(val)) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        }

        // --- Envoi de Message Direct à un Apprenant ---
        function openDirectMessageModal(studentId, studentName) {
            document.getElementById('dm-student-id').value = studentId;
            document.getElementById('dm-student-name').value = studentName;
            toggleModal('direct-message-modal');
        }

        document.getElementById('direct-message-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('send-dm-btn');
            btn.disabled = true;
            btn.textContent = 'Envoi…';

            const fd = new FormData(this);
            fetch('/promoter/send-direct-message.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.textContent = 'Envoyer le Message';
                    if (data.success) {
                        Toast.success(data.message);
                        toggleModal('direct-message-modal');
                        this.reset();
                    } else {
                        Toast.error(data.message || 'Une erreur est survenue.');
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.textContent = 'Envoyer le Message';
                    Toast.error('Erreur réseau : ' + err.message);
                });
        });

        function loadNotifications() {
            fetch('/student/get-notifications.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const badge = document.getElementById('notif-count');
                const panel = document.getElementById('notif-panel');
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
                panel.innerHTML = data.notifications.length
                    ? data.notifications.map(n => `<a href="${n.link || '#'}" class="block p-3 border-b border-[#E5E5E7] hover:bg-[#F5F5F7] ${n.is_read == 0 ? 'font-semibold' : ''}"><div class="text-xs">${n.title}</div><div class="text-[11px] text-[#888]">${n.body || ''}</div></a>`).join('')
                    : '<p class="p-3 text-xs text-[#888]">Aucune notification.</p>';
            });
        }
        document.getElementById('notif-btn')?.addEventListener('click', () => {
            document.getElementById('notif-panel').classList.toggle('hidden');
            loadNotifications();
        });
        loadNotifications();

        function showPostponeForm(id) {
            document.getElementById('session-actions-' + id).classList.add('hidden');
            document.getElementById('postpone-form-' + id).classList.remove('hidden');
        }
        function hidePostponeForm(id) {
            document.getElementById('session-actions-' + id).classList.remove('hidden');
            document.getElementById('postpone-form-' + id).classList.add('hidden');
        }
    </script>
    <script src="/assets/js/app.js"></script>
</body>
</html>
