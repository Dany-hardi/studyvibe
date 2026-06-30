<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
requireRole('student');

$attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
$user      = getCurrentUser();
$attempt   = null;

if ($attemptId <= 0) {
    $errorCode = 404;
    $errorTitle = "Relevé Introuvable";
    $errorMessage = "L'identifiant du relevé de tentative demandé est invalide ou absent.";
    $badgeText = "Paramètre incorrect";
    include __DIR__ . '/../error.php';
    exit;
}

try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare("
        SELECT ca.*, c.title AS course_title, m.title AS module_title, u.name AS student_name
        FROM certification_attempts ca
        JOIN courses c ON c.id = ca.course_id
        JOIN modules m ON m.id = c.module_id
        JOIN users u ON u.id = ca.student_id
        WHERE ca.id = :id AND ca.student_id = :sid AND ca.passed = 0
    ");
    $stmt->execute(['id' => $attemptId, 'sid' => $user['id']]);
    $attempt = $stmt->fetch();
} catch (PDOException $e) {
    $attempt = null;
}

if (!$attempt) {
    $errorCode = 404;
    $errorTitle = "Relevé Introuvable";
    $errorMessage = "Le relevé de tentative demandé est introuvable ou vous n'êtes pas autorisé à y accéder.";
    $badgeText = "Non Trouvé";
    include __DIR__ . '/../error.php';
    exit;
}

$totalQ     = (int)($attempt['total_questions'] ?? 0);
$score      = (float)$attempt['score'];
$threshold  = 80;
$dateStr    = date('d/m/Y à H:i', strtotime((string)$attempt['attempted_at']));
$approxOk   = $totalQ > 0 ? (int)round(($score / 100) * $totalQ) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relevé de tentative — <?= htmlspecialchars($attempt['course_title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #EAE6DF; color: #1A1A1A; }
        .no-print { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; background: #fff; border-bottom: 1px solid #D5D0C8; }
        .no-print a { font-size: 0.75rem; color: #555; text-decoration: none; text-transform: uppercase; letter-spacing: 0.08em; }
        .no-print button { padding: 0.6rem 1.25rem; background: #1A1A1A; color: #fff; border: none; cursor: pointer; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; }
        .no-print button:hover { background: #004B23; }
        .wrap { display: flex; justify-content: center; padding: 2.5rem 1.5rem; }
        .page { width: 210mm; min-height: 260mm; background: #fff; border: 1px solid #8A857C; padding: 3rem 3.5rem; position: relative; overflow: hidden; }
        .brand { font-family: 'Playfair Display', serif; font-size: 1.35rem; font-weight: 500; color: #004B23; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem; position: relative; z-index: 2; }
        .subtitle { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.14em; color: #8A8A8A; margin-bottom: 2.5rem; position: relative; z-index: 2; }
        h1 { font-family: 'Playfair Display', serif; font-size: 1.75rem; font-weight: 400; margin-bottom: 0.5rem; position: relative; z-index: 2; }
        .meta { font-size: 0.875rem; color: #5C5C5C; line-height: 1.7; margin-bottom: 2rem; position: relative; z-index: 2; }
        .box { border: 1px solid #D5D0C8; padding: 1.5rem; margin-bottom: 1.5rem; position: relative; z-index: 2; }
        .box h2 { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.12em; color: #8A8A8A; margin-bottom: 1rem; }
        .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .stat { padding: 0.75rem 0; border-bottom: 1px solid #EAE6DF; }
        .stat-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.1em; color: #8A8A8A; }
        .stat-value { font-size: 1.125rem; font-weight: 600; margin-top: 0.25rem; }
        .stat-value.fail { color: #C62828; }
        .notice { font-size: 0.8125rem; color: #5C5C5C; line-height: 1.65; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #EAE6DF; position: relative; z-index: 2; }
        .footer { margin-top: 3rem; font-size: 0.65rem; color: #8A8A8A; text-align: center; position: relative; z-index: 2; }
        
        /* Filigrane transparent */
        .report-watermark {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 320px; height: 320px;
            opacity: 0.025;
            pointer-events: none;
            z-index: 1;
        }

        @media print {
            .no-print { display: none; }
            body { background: #fff; }
            .wrap { padding: 0; }
            .report-watermark { opacity: 0.025; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <a href="/student/dashboard.php">← Retour au tableau de bord</a>
        <div style="display:flex;gap:0.5rem;">
            <button type="button" onclick="window.print()">Imprimer</button>
            <button type="button" id="btn-pdf" style="background:#004B23;">Télécharger PDF</button>
        </div>
    </div>
    <div class="wrap">
        <div class="page" id="report-content">
            <!-- Filigrane Sceau d'Esprit Pensant Épuré (Monochrome Académique) -->
            <svg class="report-watermark" viewBox="0 0 120 120" fill="none" stroke="#111111" stroke-width="0.8" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.03;">
                <!-- Doubles cercles extérieurs académiques -->
                <circle cx="60" cy="60" r="54" stroke-dasharray="2 2" />
                <circle cx="60" cy="60" r="50" />
                <circle cx="60" cy="60" r="40" stroke-dasharray="1 3" />
                
                <!-- Constellation (liaisons fines et nœuds noirs) -->
                <line x1="43" y1="41" x2="72" y2="35" stroke="#111111" stroke-width="0.6" />
                <line x1="43" y1="41" x2="59" y2="63" stroke="#111111" stroke-width="0.6" />
                <line x1="43" y1="41" x2="24" y2="23" stroke="#111111" stroke-width="0.6" />
                <line x1="43" y1="41" x2="52" y2="21" stroke="#111111" stroke-width="0.6" />
                <line x1="43" y1="41" x2="30" y2="63" stroke="#111111" stroke-width="0.6" />
                <line x1="59" y1="63" x2="72" y2="35" stroke="#111111" stroke-width="0.6" />

                <circle cx="72" cy="35" r="2" fill="#111111" />
                <circle cx="59" cy="63" r="2" fill="#111111" />
                <circle cx="43" cy="41" r="2" fill="#111111" />
                <circle cx="24" cy="23" r="2" fill="#111111" />
                <circle cx="52" cy="21" r="2" fill="#111111" />
                <circle cx="30" cy="63" r="2" fill="#111111" />

                <!-- Profil du visage -->
                <path d="M66 20 C62 24, 62 34, 62 39 C62 41, 60 43, 59 43 L55 43 L59 45 C60 47, 61 48, 60 50 C59 51, 57 52, 59 54 C61 55, 64 56, 66 56 C69 56, 75 48, 76 51 C78 56, 70 62, 66 70 C61 78, 60 88, 63 98" stroke="#111111" stroke-width="0.8" stroke-linecap="round" stroke-linejoin="round" />

                <!-- Main du penseur -->
                <path d="M43 65 C42 62, 42 58, 43 56 C44 54, 46 54, 47 57 C47 60, 47 63, 47 65 C47 61, 48 56, 49 54 C50 52, 52 52, 53 55 C53 58, 53 61, 53 64 C53 61, 54 57, 55 55 C56 53, 58 53, 59 56 C60 59, 61 67, 61 78 C61 85, 59 91, 57 95" stroke="#111111" stroke-width="0.8" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M43 65 C44 71, 45 78, 47 85 C48 91, 49 94, 50 96" stroke="#111111" stroke-width="0.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>


            <div class="brand">
                <svg class="w-6 h-6" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 24px; height: 24px;">
                    <circle cx="50" cy="50" r="46" stroke="#111111" stroke-width="3.5" />
                    <line x1="33" y1="31" x2="62" y2="25" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                    <line x1="33" y1="31" x2="49" y2="53" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                    <line x1="33" y1="31" x2="14" y2="13" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                    <line x1="33" y1="31" x2="42" y2="11" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                    <line x1="33" y1="31" x2="20" y2="53" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                    <line x1="49" y1="53" x2="62" y2="25" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                    <circle cx="62" cy="25" r="6" fill="#111111" />
                    <circle cx="49" cy="53" r="6" fill="#111111" />
                    <circle cx="33" cy="31" r="6" fill="#111111" />
                    <circle cx="14" cy="13" r="6" fill="#111111" />
                    <circle cx="42" cy="11" r="6" fill="#111111" />
                    <circle cx="20" cy="53" r="6" fill="#111111" />
                    <path d="M56 10 C52 14, 52 24, 52 29 C52 31, 50 33, 49 33 L45 33 L49 35 C50 37, 51 38, 50 40 C49 41, 47 42, 49 44 C51 45, 54 46, 56 46 C59 46, 65 38, 66 41 C68 46, 60 52, 56 60 C51 68, 50 78, 53 88" stroke="#111111" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M33 55 C32 52, 32 48, 33 46 C34 44, 36 44, 37 47 C37 50, 37 53, 37 55 C37 51, 38 46, 39 44 C40 42, 42 42, 43 45 C43 48, 43 51, 43 54 C43 51, 44 47, 45 45 C46 43, 48 43, 49 46 C50 49, 51 57, 51 68 C51 75, 49 81, 47 85" stroke="#111111" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M33 55 C34 61, 35 68, 37 75 C38 81, 39 84, 40 86" stroke="#111111" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                StudyVibe
            </div>
            <div class="subtitle">Relevé de tentative de certification</div>
            <h1><?= htmlspecialchars($attempt['course_title']); ?></h1>
            <p class="meta">
                Module : <strong><?= htmlspecialchars($attempt['module_title']); ?></strong><br>
                Apprenant : <strong><?= htmlspecialchars($attempt['student_name']); ?></strong><br>
                Date de la tentative : <strong><?= $dateStr; ?></strong>
            </p>
            <div class="box">
                <h2>Récapitulatif de la tentative</h2>
                <div class="stat-grid">
                    <div class="stat">
                        <div class="stat-label">Résultat</div>
                        <div class="stat-value fail">Non validé</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Score obtenu</div>
                        <div class="stat-value fail"><?= number_format($score, 1); ?> %</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Seuil requis</div>
                        <div class="stat-value"><?= $threshold; ?> %</div>
                    </div>
                    <?php if ($totalQ > 0): ?>
                    <div class="stat">
                        <div class="stat-label">Questions de l'épreuve</div>
                        <div class="stat-value"><?= $totalQ; ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <p class="notice">
                Ce document constitue un récapitulatif de votre tentative de certification.
                Il ne contient pas le détail des réponses fournies ni les corrections individuelles.
                Vous pouvez retenter l'épreuve après révision du cours (maximum 3 tentatives par 24 heures).
            </p>
            <div class="footer">StudyVibe Academic LMS — Document généré le <?= date('d/m/Y'); ?></div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        document.getElementById('btn-pdf')?.addEventListener('click', () => {
            html2pdf().set({
                margin: 10,
                filename: 'releve-tentative-<?= (int)$attemptId; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            }).from(document.getElementById('report-content')).save();
        });
    </script>
</body>
</html>
