<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Database.php';

$pdo = Database::getInstance();

// Récupérer toutes les évaluations asynchrones actives
$stmt = $pdo->prepare("
    SELECT s.*, c.title AS course_title,
           (SELECT COUNT(*) FROM live_eval_questions WHERE session_id = s.id) AS question_count
    FROM live_eval_sessions s
    JOIN courses c ON s.course_id = c.id
    WHERE s.status = 1 
      AND s.is_async = 1 
      AND (s.async_deadline IS NULL OR s.async_deadline > NOW())
    ORDER BY s.created_at DESC
");
$stmt->execute();
$evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si l'utilisateur est connecté, vérifier ses tentatives / notes
$userScores = [];
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user) {
        $scoreStmt = $pdo->prepare("
            SELECT session_id, score 
            FROM live_eval_registrations 
            WHERE email = :email
        ");
        $scoreStmt->execute(['email' => $user['email']]);
        $scores = $scoreStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($scores as $s) {
            $userScores[(int)$s['session_id']] = $s['score'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="sv-cream">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="icon" type="image/png" href="/assets/img/favicon.png" sizes="32x32">
    <link rel="apple-touch-icon" href="/assets/img/favicon.png">

    <title>Portail des Évaluations — StudyVibe</title>
    <meta name="description" content="Accédez aux évaluations asynchrones et téléévaluations de StudyVibe.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    
    <style>
        .portal-header {
            background: linear-gradient(135deg, #004B23 0%, #00220F 100%);
            color: #ffffff;
            padding: 4rem 2rem;
            text-align: center;
            border-radius: 4px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 75, 35, 0.15);
        }
        .portal-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            letter-spacing: -0.02em;
        }
        .portal-subtitle {
            font-size: 0.95rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
            font-weight: 300;
            line-height: 1.6;
        }
        .search-box-container {
            margin-top: -2rem;
            margin-bottom: 3rem;
            display: flex;
            justify-content: center;
            padding: 0 1rem;
        }
        .search-box-wrapper {
            background: #ffffff;
            border: 1px solid #E5E5E7;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }
        .search-icon {
            color: #888888;
            margin-right: 0.5rem;
            flex-shrink: 0;
        }
        .search-input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 0.9rem;
            color: #111111;
        }
        .eval-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 4rem;
        }
        .eval-card {
            background: #ffffff;
            border: 1px solid #E5E5E7;
            border-radius: 4px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s ease-in-out;
            position: relative;
            overflow: hidden;
        }
        .eval-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.08);
            border-color: #004B23;
        }
        .course-badge {
            background: #E2ECE9;
            color: #004B23;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.25rem 0.6rem;
            border-radius: 50px;
            align-self: flex-start;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .eval-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.15rem;
            font-weight: 600;
            color: #111111;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        .eval-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.75rem;
            color: #555555;
            margin-bottom: 1.25rem;
            border-bottom: 1px solid #F0F0F2;
            padding-bottom: 0.75rem;
        }
        .eval-meta-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .eval-meta-item svg {
            width: 14px;
            height: 14px;
            color: #888888;
        }
        .countdown-container {
            font-size: 0.75rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 500;
        }
        .countdown-active {
            color: #E6A817;
        }
        .countdown-expired {
            color: #D32F2F;
        }
        .score-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin-bottom: 1rem;
        }
        .score-badge-completed {
            background-color: #E8F5E9;
            color: #2E7D32;
            border: 1px solid #C8E6C9;
        }
        .score-badge-pending {
            background-color: #FFF3E0;
            color: #EF6C00;
            border: 1px solid #FFE0B2;
        }
        .btn-start-eval {
            display: block;
            text-align: center;
            width: 100%;
            background: #111111;
            color: #ffffff;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.75rem 1rem;
            border-radius: 3px;
            transition: background 0.2s;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-start-eval:hover {
            background: #004B23;
        }
        .empty-portal {
            text-align: center;
            padding: 4rem 2rem;
            background: #ffffff;
            border: 1px solid #E5E5E7;
            border-radius: 4px;
            color: #888888;
        }
    </style>
</head>
<body class="sv-landing sv-page">

<!-- Navbar -->
<nav class="sv-navbar" role="navigation" aria-label="Navigation principale">
    <a href="/" class="sv-navbar-brand">
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
        StudyVibe
    </a>
    <div class="sv-navbar-links">
        <a href="/" class="sv-navbar-link">Accueil</a>
        <?php if (isLoggedIn()): ?>
            <a href="/<?= $_SESSION['user_role'] ?>/dashboard.php" class="sv-navbar-link">Mon Tableau de bord</a>
            <a href="/logout.php" class="sv-btn sv-btn-outline" style="padding: 0.5rem 1rem;">Déconnexion</a>
        <?php else: ?>
            <a href="/index.php" class="sv-btn sv-btn-primary" style="padding: 0.5rem 1rem;">Connexion</a>
        <?php endif; ?>
        <button class="sv-dark-toggle" data-dark-toggle title="Mode sombre"></button>
    </div>
</nav>

<main class="sv-container" style="margin-top: 2rem;">
    <!-- En-tête -->
    <div class="portal-header">
        <h1 class="portal-title">Portail des Évaluations</h1>
        <p class="portal-subtitle">Accédez à vos téléévaluations asynchrones et réalisez vos quiz académiques à votre propre rythme, avant la date limite indiquée.</p>
    </div>

    <!-- Barre de recherche -->
    <div class="search-box-container">
        <div class="search-box-wrapper">
            <svg class="search-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" id="search-input" placeholder="Rechercher par cours ou évaluation..." class="search-input" oninput="filterEvaluations()">
        </div>
    </div>

    <!-- Grille des évaluations -->
    <?php if (empty($evaluations)): ?>
        <div class="empty-portal">
            <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 48px; height: 48px; margin: 0 auto 1rem;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
            </svg>
            <p class="text-sm font-medium">Aucune évaluation asynchrone n'est disponible pour le moment.</p>
        </div>
    <?php else: ?>
        <div class="eval-grid" id="eval-grid">
            <?php foreach ($evaluations as $eval): 
                $hasDeadline = !empty($eval['async_deadline']);
                $deadlineTime = $hasDeadline ? strtotime($eval['async_deadline']) : 0;
                $hasAttempted = isset($userScores[(int)$eval['id']]);
                $score = $hasAttempted ? $userScores[(int)$eval['id']] : null;
                
                $isStudent = isLoggedIn() && $_SESSION['user_role'] === 'student';
                $isLocked = false;
            ?>
                <div class="eval-card" data-title="<?= htmlspecialchars(strtolower($eval['title'])) ?>" data-course="<?= htmlspecialchars(strtolower($eval['course_title'])) ?>" style="<?= $isLocked ? 'opacity: 0.8;' : '' ?>">
                    <div>
                        <span class="course-badge"><?= htmlspecialchars($eval['course_title']) ?></span>
                        <h3 class="eval-name"><?= htmlspecialchars($eval['title']) ?></h3>
                        
                        <div class="eval-meta">
                            <div class="eval-meta-item">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span><?= $eval['question_count'] ?> questions</span>
                            </div>
                            <div class="eval-meta-item">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span><?= $eval['default_time_limit'] ?>s par question</span>
                            </div>
                        </div>

                        <!-- Date limite / Compte à rebours -->
                        <?php if ($hasDeadline): ?>
                            <div class="countdown-container countdown-active" id="timer-<?= $eval['id'] ?>" data-deadline="<?= $deadlineTime ?>">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="countdown-label">Ferme dans : </span>
                                <strong class="countdown-value" id="time-val-<?= $eval['id'] ?>">--:--:--</strong>
                            </div>
                        <?php else: ?>
                            <div class="countdown-container" style="color:var(--text-muted);">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Toujours ouvert</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <!-- Badge de complétion ou cadenas -->
                        <?php if ($isLocked): ?>
                            <div class="score-badge score-badge-pending" style="background-color: #FFF0F0; border-color: #FFC0C0; color: #D32F2F;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                <span>Cours incomplet</span>
                            </div>
                        <?php elseif ($hasAttempted): ?>
                            <div class="score-badge <?= $score !== null ? 'score-badge-completed' : 'score-badge-pending' ?>">
                                <?php if ($score !== null): ?>
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Complété : <?= number_format((float)$score, 2) ?>/20</span>
                                <?php else: ?>
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>En cours d'évaluation</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Lien d'action -->
                        <?php if ($isLocked): ?>
                            <button class="btn-start-eval" style="background-color: #777777; cursor: not-allowed;" disabled title="Veuillez terminer toutes les leçons de ce cours pour accéder à l'évaluation.">
                                Accès Verrouillé
                            </button>
                        <?php else: ?>
                            <a href="/live-session.php?code=<?= urlencode($eval['session_code']) ?>" class="btn-start-eval">
                                <?= $hasAttempted ? 'Recommencer l\'évaluation' : 'Démarrer l\'évaluation' ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer class="sv-footer" role="contentinfo" style="margin-top: 6rem;">
    <div class="sv-container" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div>
            <div class="sv-footer-brand">StudyVibe</div>
            <div style="font-size:0.75rem; margin-top:0.35rem;">© <?= date('Y') ?> StudyVibe Academic LMS · <a href="/privacy.php" style="color:rgba(255,255,255,0.7); text-decoration:underline; font-weight:300;">Politique de Confidentialité</a></div>
        </div>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            <span class="sv-badge" style="border-color:rgba(255,255,255,0.2); color:rgba(255,255,255,0.7); background:transparent;">Évaluations Asynchrones</span>
            <span class="sv-badge" style="border-color:rgba(255,255,255,0.2); color:rgba(255,255,255,0.7); background:transparent;">Suivi direct</span>
        </div>
    </div>
</footer>

<script src="/assets/js/app.js"></script>
<script>
    // Filtrage dynamique en temps réel
    function filterEvaluations() {
        const query = document.getElementById('search-input').value.toLowerCase().trim();
        const cards = document.querySelectorAll('.eval-card');
        
        cards.forEach(card => {
            const title = card.getAttribute('data-title');
            const course = card.getAttribute('data-course');
            
            if (title.includes(query) || course.includes(query)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Gestion des comptes à rebours en temps réel
    function updateCountdowns() {
        const now = Math.floor(Date.now() / 1000);
        const timers = document.querySelectorAll('[id^="timer-"]');
        
        timers.forEach(timer => {
            const deadline = parseInt(timer.getAttribute('data-deadline'), 10);
            const id = timer.id.split('-')[1];
            const valueEl = document.getElementById('time-val-' + id);
            
            const diff = deadline - now;
            
            if (diff <= 0) {
                timer.className = 'countdown-container countdown-expired';
                timer.innerHTML = `
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <strong>Date limite dépassée</strong>
                `;
                // Cacher ou désactiver le bouton démarrer si expiré
                const btn = timer.closest('.eval-card').querySelector('.btn-start-eval');
                if (btn) {
                    btn.style.display = 'none';
                }
            } else {
                const hours = Math.floor(diff / 3600);
                const mins = Math.floor((diff % 3600) / 60);
                const secs = diff % 60;
                
                const displayH = hours.toString().padStart(2, '0');
                const displayM = mins.toString().padStart(2, '0');
                const displayS = secs.toString().padStart(2, '0');
                
                if (valueEl) {
                    valueEl.textContent = `${displayH}h ${displayM}m ${displayS}s`;
                }
            }
        });
    }

    // Rafraîchir les chronomètres toutes les secondes
    setInterval(updateCountdowns, 1000);
    updateCountdowns();
</script>
</body>
</html>
