<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$code = trim((string)($_GET['code'] ?? ''));

$pdo = Database::getInstance();
$session = null;
$courseTitle = '';

if ($code !== '') {
    $stmt = $pdo->prepare("
        SELECT s.*, c.title AS course_title
        FROM live_eval_sessions s
        JOIN courses c ON s.course_id = c.id
        WHERE s.session_code = :code
    ");
    $stmt->execute(['code' => $code]);
    $session = $stmt->fetch();
}

$isStudent = isset($_SESSION['user_id'], $_SESSION['user_role']) && $_SESSION['user_role'] === 'student';
$redirectUrl = $isStudent ? 'student/dashboard.php' : 'index.php';
$redirectLabel = $isStudent ? 'Retour au tableau de bord' : "Retour à l'accueil";

$isAsync = isset($session['is_async']) && (int)$session['is_async'] === 1;
$asyncDeadlinePassed = false;
if ($isAsync && !empty($session['async_deadline'])) {
    $asyncDeadlinePassed = (time() > strtotime($session['async_deadline']));
}

$error = null;
if (!$session) {
    $error = "Cette séance de téléévaluation est introuvable ou le lien est invalide.";
} elseif ((int)$session['status'] === 0) {
    $error = "Cette séance de téléévaluation a été désactivée par l'enseignant.";
} elseif ($isAsync && $asyncDeadlinePassed) {
    $error = "La date limite pour participer à cette évaluation asynchrone est dépassée.";
}

// Gérer l'enregistrement du participant
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_live'])) {
    $name  = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));

    if ($name === '' || $email === '') {
        $regError = "Veuillez remplir tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $regError = "Adresse e-mail invalide.";
    } else {
        try {
            // Insérer ou récupérer l'inscription existante (si même e-mail pour cette session)
            // Note: PDO interdit de réutiliser le même placeholder nommé — on utilise :name2 pour le UPDATE
            $stmt = $pdo->prepare("
                INSERT INTO live_eval_registrations (session_id, name, email)
                VALUES (:sid, :name, :email)
                ON DUPLICATE KEY UPDATE name = :name2
            ");
            $stmt->execute([
                'sid'   => $session['id'],
                'name'  => $name,
                'name2' => $name,
                'email' => $email,
            ]);

            // Récupérer le ID d'inscription
            $stmt = $pdo->prepare("SELECT id FROM live_eval_registrations WHERE session_id = :sid AND email = :email");
            $stmt->execute(['sid' => $session['id'], 'email' => $email]);
            $regId = (int)$stmt->fetchColumn();

            if ($regId <= 0) {
                throw new PDOException("Impossible de récupérer l'ID d'inscription.");
            }

            // Enregistrer dans la session PHP
            $_SESSION['live_registrations'][$code] = $regId;

            if ($isAsync) {
                $stmt = $pdo->prepare("UPDATE live_eval_registrations SET score = NULL WHERE id = :id");
                $stmt->execute(['id' => $regId]);
                
                $stmt = $pdo->prepare("DELETE FROM live_eval_answers WHERE registration_id = :id");
                $stmt->execute(['id' => $regId]);
                
                unset($_SESSION['verified_registrations'][$code]);
                unset($_SESSION['async_q_start'][$session['id']]);
                $_SESSION['answered_questions'] = [];
            }

            header("Location: /live-session.php?code=" . urlencode($code));
            exit;
        } catch (PDOException $e) {
            $regError = "Erreur lors de l'inscription : " . $e->getMessage();
        }
    }
}

// Vérifier si le participant est déjà enregistré
$regId = 0;
$registration = null;
if (!$error) {
    $regId = (int)($_SESSION['live_registrations'][$code] ?? 0);
    if ($regId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM live_eval_registrations WHERE id = :id AND session_id = :sid");
        $stmt->execute(['id' => $regId, 'sid' => $session['id']]);
        $registration = $stmt->fetch();

        // Gérer le redémarrage automatique d'une tentative complétée en mode asynchrone
        if ($isAsync) {
            $shouldReset = (isset($_GET['restart']) && (int)$_GET['restart'] === 1) || ($registration && $registration['score'] !== null);
            if ($shouldReset) {
                $stmt = $pdo->prepare("UPDATE live_eval_registrations SET score = NULL WHERE id = :id");
                $stmt->execute(['id' => $regId]);

                $stmt = $pdo->prepare("DELETE FROM live_eval_answers WHERE registration_id = :id");
                $stmt->execute(['id' => $regId]);

                unset($_SESSION['verified_registrations'][$code]);
                unset($_SESSION['async_q_start'][$session['id']]);
                $_SESSION['answered_questions'] = [];

                header("Location: /live-session.php?code=" . urlencode($code));
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Téléévaluation StudyVibe LIVE</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    
    <!-- Bibliothèques KaTeX pour le rendu des formules mathématiques et caractères spéciaux en LaTeX -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js" onload="renderMath()"></script>
    
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --cream:   #EAE6DF;
            --green:   #004B23;
            --green2:  #00873F;
            --ink:     #1A1A1A;
            --muted:   #5C5C5C;
            --faint:   #9A9A9A;
            --gold:    #C9A84C;
            --surface: #FFFFFF;
        }

        html, body {
            height: 100%;
            background: var(--cream);
            font-family: 'Inter', sans-serif;
            color: var(--ink);
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
        }

        .page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        /* ── Floating particles ── */
        .floating-particles {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }

        .particle {
            position: absolute;
            opacity: 0;
            animation: floatParticle linear infinite;
        }

        @keyframes floatParticle {
            0%   { opacity: 0; transform: translateY(0) rotate(var(--r)) scale(0.6); }
            10%  { opacity: 0.15; }
            90%  { opacity: 0.12; }
            100% { opacity: 0; transform: translateY(-110vh) rotate(calc(var(--r) + 40deg)) scale(0.9); }
        }

        /* ── Card Layout ── */
        .card {
            background: var(--surface);
            border: 1px solid rgba(0,75,35,0.15);
            max-width: 1080px;
            width: 100%;
            height: min(640px, 90vh);
            display: flex;
            position: relative;
            box-shadow:
                0 30px 70px rgba(0,0,0,0.08),
                0 10px 30px rgba(0,0,0,0.04);
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--green) 0%, var(--green2) 50%, var(--gold) 100%);
            z-index: 10;
        }

        .col-image {
            flex: 1.1;
            position: relative;
            background: #0f1c14;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .col-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.85;
            transition: opacity 0.5s ease;
        }

        .illustration-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1.5rem;
            background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.4) 60%, transparent 100%);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-family: 'Inter', sans-serif;
            text-shadow: 0 1px 2px rgba(0,0,0,0.5);
            z-index: 2;
        }

        .col-content {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow-y: auto;
            position: relative;
            background: var(--surface);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            margin-bottom: 1.5rem;
        }

        .brand-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--ink);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.625rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--green);
            border: 1px solid var(--green);
            padding: 0.3rem 0.75rem;
            align-self: flex-start;
            margin-bottom: 1rem;
        }

        .option-btn {
            width: 100%;
            padding: 1rem;
            border: 1px solid rgba(0,0,0,0.1);
            background: #FAFAFA;
            color: var(--ink);
            text-align: left;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 500;
            font-size: 0.9rem;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .option-btn:hover:not(:disabled) {
            border-color: var(--green);
            background: rgba(0,75,35,0.03);
        }

        .option-btn.selected {
            border-color: var(--green);
            background: var(--green);
            color: #FFFFFF;
            font-weight: 600;
        }

        .option-btn.selected span.opt-label {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.3);
            color: #FFF;
        }

        .option-btn:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        .opt-label {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #FFF;
            border: 1px solid rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.75rem;
            color: var(--muted);
            transition: all 0.15s ease;
        }

        .countdown-number {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 4rem;
            font-weight: 700;
            color: var(--green);
            line-height: 1;
            letter-spacing: -0.02em;
        }

        .progress-bar-container {
            width: 100%;
            height: 4px;
            background: rgba(0,0,0,0.06);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--green), var(--green2));
            width: 100%;
            transition: width 1s linear;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: var(--ink);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            text-decoration: none;
            padding: 0.9rem 1.5rem;
            border: 2px solid var(--ink);
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
            width: 100%;
        }

        .btn-primary:hover {
            background: var(--green);
            border-color: var(--green);
        }

        .input-field {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid rgba(0,0,0,0.12);
            background: #FAFAFA;
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            outline: none;
            transition: all 0.15s ease;
        }

        .input-field:focus {
            border-color: var(--green);
            background: #FFF;
            box-shadow: 0 0 0 3px rgba(0,75,35,0.05);
        }

        #typewriter-line {
            font-family: monospace;
            font-size: 0.72rem;
            color: var(--green);
            background: rgba(0,75,35,0.04);
            border: 1px solid rgba(0,75,35,0.1);
            padding: 0.6rem 0.8rem;
            margin-top: 1.5rem;
            display: block;
            border-radius: 1px;
            width: 100%;
        }

        .cursor {
            display: inline-block;
            width: 2px;
            height: 1em;
            background: var(--green);
            margin-left: 2px;
            vertical-align: text-bottom;
            animation: blink 1s step-end infinite;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }

        .hint {
            font-size: 0.68rem;
            color: var(--faint);
            font-weight: 300;
            letter-spacing: 0.02em;
            margin-top: 1rem;
        }

        .page-footer {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(0,0,0,0.06);
            font-size: 0.65rem;
            color: var(--faint);
            letter-spacing: 0.04em;
        }

        /* ── Quiz Mode : plein écran, pas d'image ─────────────── */
        .card.quiz-mode {
            max-width: 760px;
            height: auto;
            min-height: 480px;
        }
        .card.quiz-mode .col-image {
            display: none !important;
        }
        .card.quiz-mode .col-content {
            flex: 1 1 100%;
            padding: 2.5rem 3.5rem;
            justify-content: center;
        }

        /* ── Watermark Timer for Lobby ── */
        .timer-watermark {
            position: absolute;
            right: 2rem;
            top: 2.5rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--green);
            opacity: 0.08;
            user-select: none;
            pointer-events: none;
            line-height: 1;
            z-index: 5;
        }

        /* ── Barre live supérieure (quiz uniquement) ── */
        #quiz-live-bar {
            display: none;
            position: sticky;
            top: 0;
            background: var(--ink);
            color: #fff;
            padding: 0.55rem 1.5rem;
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            z-index: 20;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        #quiz-live-bar.active { display: flex; }
        .live-dot {
            display: inline-block;
            width: 7px; height: 7px;
            background: #EF4444;
            border-radius: 50%;
            animation: blink 1s step-end infinite;
            margin-right: 5px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.4;} }

        .hidden { display: none !important; }

        @media (max-width: 880px) {
            html, body { overflow-y: auto; height: auto; }
            .page { height: auto; padding: 1rem; }
            .card { flex-direction: column; height: auto; max-width: 520px; }
            .card.quiz-mode { max-width: 520px; }
            .col-image { height: 240px; }
            .col-content { padding: 2rem 1.5rem; }
            .card.quiz-mode .col-content { padding: 1.5rem 1.25rem; }
        }
    </style>
</head>
<body>

    <!-- Floating background particles -->
    <div class="floating-particles" id="floating-particles" aria-hidden="true"></div>

    <main class="page">
        <div class="card" id="main-card">

            <!-- Barre live quiz (visible uniquement en phase quiz) -->
            <div id="quiz-live-bar">
                <span><span class="live-dot"></span>QUIZ EN COURS</span>
                <span id="live-bar-question">Question -- / --</span>
            </div>
            
            <!-- Left column (Dynamic Graphic) -->
            <div class="col-image" id="col-image-panel">
                <img id="live-illustration" src="/assets/img/live-lobby-illustration.png" alt="Illustration Téléévaluation">
                <div class="illustration-caption" id="live-caption">Dans la salle de Téléévaluation StudyVibe…</div>
            </div>

            <!-- Right column (Content panel) -->
            <div class="col-content">
                
                <!-- Absolute Timer Watermark (top-right corner) -->
                <div class="timer-watermark" id="lobby-timer-watermark">--:--</div>
                
                <div id="col-top-bar">
                    <!-- Brand -->
                    <a href="/" class="brand">
                        <svg width="22" height="22" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="g1" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#004B23"/>
                                    <stop offset="100%" stop-color="#00873F"/>
                                </linearGradient>
                            </defs>
                            <path d="M32 52 L8 46 L8 16 L32 22 Z" fill="url(#g1)"/>
                            <path d="M32 52 L56 46 L56 16 L32 22 Z" fill="#003318"/>
                            <path d="M32 22 L10 17 L10 44 L32 49 Z" fill="#EAE6DF"/>
                            <path d="M32 22 L54 17 L54 44 L32 49 Z" fill="#F5F3EF"/>
                            <line x1="32" y1="22" x2="32" y2="52" stroke="#004B23" stroke-width="1.5"/>
                            <path d="M32 10 L33.2 13.8 L37 15 L33.2 16.2 L32 20 L30.8 16.2 L27 15 L30.8 13.8 Z" fill="#C9A84C"/>
                        </svg>
                        <span class="brand-name">StudyVibe <span style="font-size:0.625rem; font-weight:700; color:#FFF; background:var(--green); padding: 1px 6px; border-radius:10px; margin-left:4px;">LIVE</span></span>
                    </a>

                    <!-- Badge Status -->
                    <span class="badge" id="state-badge">
                        <svg width="6" height="6" viewBox="0 0 8 8" fill="none" style="margin-right: 2px;">
                            <circle cx="4" cy="4" r="3" fill="#004B23"/>
                        </svg>
                        Téléévaluation
                    </span>
                </div>

                <!-- Main dynamic content area -->
                <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: center; margin: 1.5rem 0;">
                    
                    <?php if ($error): ?>
                        <!-- ÉCRAN : ERREUR -->
                        <div class="text-center py-6">
                            <h2 class="story-title" style="font-family:'Plus Jakarta Sans',sans-serif; font-size:1.8rem; font-weight:500; margin-bottom: 1rem;">
                                Séance indisponible
                            </h2>
                            <p style="font-size:0.85rem; color:var(--muted); line-height:1.6; margin-bottom: 2rem;">
                                <?= htmlspecialchars($error) ?>
                            </p>
                            <a href="/" class="btn-primary">Retour à l'accueil</a>
                        </div>

                    <?php elseif (!$registration): ?>
                        <!-- ÉCRAN : ENREGISTREMENT -->
                        <div>
                            <h2 style="font-family:'Plus Jakarta Sans',sans-serif; font-size:1.6rem; font-weight:500; margin-bottom: 0.5rem; line-height:1.2;">
                                <?= htmlspecialchars($session['title']) ?>
                            </h2>
                            <p style="font-size:0.8rem; color:var(--muted); margin-bottom: 1.5rem;">
                                Cours : <strong style="color:var(--ink); font-weight:500;"><?= htmlspecialchars($session['course_title']) ?></strong>
                            </p>

                            <?php if (isset($regError)): ?>
                                <div style="margin-bottom: 1.5rem; padding: 0.8rem 1rem; background-color: #FDF2F2; border: 1px solid #FBD5D5; color: #9B1C1C; font-size: 0.8rem;">
                                    <?= htmlspecialchars($regError) ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="register_live" value="1">
                                
                                <div>
                                    <label style="display:block; font-size:0.65rem; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); margin-bottom:0.5rem;">Nom Complet</label>
                                    <input type="text" name="name" required placeholder="Ex: Jean Dupont" class="input-field">
                                </div>

                                <div>
                                    <label style="display:block; font-size:0.65rem; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); margin-bottom:0.5rem;">Adresse E-mail</label>
                                    <input type="email" name="email" required placeholder="Ex: jean.dupont@email.com" class="input-field">
                                    <p style="font-size:0.68rem; color:var(--faint); mt-1">Vos résultats et votre note officielle y seront envoyés.</p>
                                </div>

                                <div style="padding-top: 1rem;">
                                    <button type="submit" class="btn-primary">Rejoindre la séance</button>
                                </div>
                            </form>
                        </div>

                    <?php else: ?>
                        <!-- ÉCRAN PRINCIPAL DYNAMIQUE (Lobby / Quiz / Fin) -->
                        <div id="live-app">
                            
                            <!-- Chargement initial -->
                            <div class="text-center py-8" id="loading-state">
                                <div style="border: 2px solid rgba(0,75,35,0.1); border-top-color: var(--green); border-radius: 50%; width: 28px; height: 28px; animation: spin 1s linear infinite; margin: 0 auto 1rem auto;"></div>
                                <p style="font-size: 0.85rem; color: var(--muted);">Synchronisation avec la séance en cours...</p>
                            </div>

                            <!-- 1. VUE : Salle d'attente (Lobby) -->
                            <div id="lobby-view" class="hidden">
                                <h2 style="font-family:'Plus Jakarta Sans',sans-serif; font-size: 2rem; font-weight: 400; line-height: 1.25; color: var(--ink); margin-bottom: 0.75rem;">
                                    En attente du<br><em>lancement</em>.
                                </h2>
                                <p style="font-size: 0.85rem; font-weight: 300; line-height: 1.7; color: var(--muted); margin-bottom: 1.5rem; max-width: 44ch;">
                                    L'évaluation <strong><?= htmlspecialchars($session['title']) ?></strong> (cours : <em><?= htmlspecialchars($session['course_title']) ?></em>) débutera automatiquement à l'heure programmée. Veuillez patienter dans cette salle d'attente.
                                </p>

                                <!-- Nouveau compteur temps réel d'inscrits -->
                                <div style="background-color: rgba(0,75,35,0.04); border-left: 3px solid var(--green); padding: 12px 16px; border-radius: 4px; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 1.5rem;">
                                    <span style="display:inline-block; width: 8px; height: 8px; background-color: #22C55E; border-radius: 50%; animation: pulse 1.5s infinite;"></span>
                                    <span style="font-size: 0.85rem; font-weight: 500; color: var(--ink);">
                                        Participants connectés : <strong id="lobby-registered-count">0</strong>
                                    </span>
                                </div>
                            </div>

                            <!-- 2. VUE : Compte à rebours final (5s avant lancement) -->
                            <div id="countdown-view" class="hidden text-center py-6">
                                <span style="font-size:0.65rem; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); display:block; margin-bottom:1rem;">Lancement imminent</span>
                                <div style="font-size: 6rem; font-weight: 700; color: var(--green); line-height: 1; animation: bounce 1s infinite;" id="final-countdown-num">5</div>
                                <p style="font-size:0.9rem; color:var(--ink); font-weight:500; margin-top: 1.5rem;">
                                    Soyez prêt, l'évaluation commence dans un instant !
                                </p>
                            </div>

                             <!-- 3. VUE : Quiz Actif -->
                             <div id="quiz-view" class="hidden" style="user-select: none; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none;">

                                <!-- Barre de chrono + question -->
                                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; gap:10px;">
                                    <span style="font-size:0.65rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:var(--green); border:1px solid var(--green); padding:3px 10px;" id="quiz-question-number">Question -- / --</span>
                                    
                                    <!-- Compteur dynamique des participants restants à répondre -->
                                    <span id="quiz-live-participants-container" style="font-size:0.75rem; font-weight:500; color:var(--ink); display:flex; align-items:center; gap:6px; background: rgba(0,0,0,0.04); padding: 4px 10px; border-radius: 9999px;">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="stroke-width:2;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        <span>En attente : <strong id="quiz-live-remaining-count">--</strong></span>
                                    </span>

                                    <span style="font-size:1rem; font-weight:800; color:#E02424; display:flex; align-items:center; gap:5px; font-family:'Plus Jakarta Sans',sans-serif;">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="stroke-width:2.5;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span id="quiz-timer-text">--s</span>
                                    </span>
                                </div>

                                <!-- Barre de progression du temps -->
                                <div class="progress-bar-container">
                                    <div class="progress-bar" id="quiz-progress-bar"></div>
                                </div>

                                <!-- Énoncé de la question -->
                                <h3 id="quiz-question-text" style="font-family:'Plus Jakarta Sans',sans-serif; font-size:1.3rem; font-weight:600; color:var(--ink); line-height:1.45; margin-bottom:1.75rem;">--</h3>

                                <!-- Image question (si présente) -->
                                <div id="quiz-image-container" class="hidden" style="margin-bottom:1.5rem; border:1px solid rgba(0,0,0,0.08); overflow:hidden; border-radius:4px; background:#000; display:flex; align-items:center; justify-content:center; max-height:240px;">
                                    <img src="" id="quiz-image" style="max-width:100%; max-height:240px; object-fit:contain;" alt="Illustration question">
                                </div>

                                <!-- Options de réponse -->
                                <div style="display:flex; flex-direction:column; gap:0.65rem;" id="quiz-options-container">
                                    <button onclick="submitLiveAnswer('A')" id="btn-opt-A" class="option-btn">
                                        <span class="opt-label">A</span><span id="text-opt-A">--</span>
                                    </button>
                                    <button onclick="submitLiveAnswer('B')" id="btn-opt-B" class="option-btn">
                                        <span class="opt-label">B</span><span id="text-opt-B">--</span>
                                    </button>
                                    <button onclick="submitLiveAnswer('C')" id="btn-opt-C" class="option-btn">
                                        <span class="opt-label">C</span><span id="text-opt-C">--</span>
                                    </button>
                                    <button onclick="submitLiveAnswer('D')" id="btn-opt-D" class="option-btn">
                                        <span class="opt-label">D</span><span id="text-opt-D">--</span>
                                    </button>
                                </div>

                                <!-- Confirmation de soumission -->
                                <div id="quiz-submit-status" class="hidden" style="margin-top:1.25rem; padding:0.9rem 1rem; background:rgba(16,185,129,0.07); border:1px solid rgba(16,185,129,0.25); color:#065F46; font-size:0.82rem; text-align:center; font-weight:600; border-radius:3px;">
                                    ✓ Réponse enregistrée — en attente de la prochaine question...
                                </div>

                                <!-- Pied de quiz : nom -->
                                <div style="margin-top:1.5rem; padding-top:0.85rem; border-top:1px solid rgba(0,0,0,0.06); display:flex; justify-content:space-between; align-items:center; font-size:0.68rem; color:var(--muted);">
                                    <span><?= htmlspecialchars($registration['name']) ?></span>
                                </div>
                            </div>

                            <!-- 4. VUE : Fin de session -->
                            <div id="finished-view" class="hidden text-center py-4">
                                <h2 style="font-family:'Plus Jakarta Sans',sans-serif; font-size:1.8rem; font-weight:500; margin-bottom: 1rem; line-height:1.2;">
                                    Évaluation terminée !
                                </h2>
                                <p style="font-size:0.85rem; color:var(--muted); line-height:1.6; margin-bottom: 1.5rem;">
                                    Merci pour votre participation, <strong><?= htmlspecialchars($registration['name']) ?></strong>.
                                </p>
                                
                                <div style="background: rgba(0,75,35,0.04); border: 1px solid rgba(0,75,35,0.1); padding: 1.25rem; font-size: 0.8rem; line-height: 1.6; color: var(--ink); text-align: left; margin-bottom: 1.5rem; border-radius: 1px;">
                                    Vos réponses ont été soumises avec succès. Vos résultats et votre note officielle ont été envoyés à l'adresse e-mail suivante :
                                    <strong style="display:block; font-size:0.9rem; color:var(--green); margin-top:0.4rem;"><?= htmlspecialchars($registration['email']) ?></strong>
                                    <span style="display:block; margin-top:0.5rem; font-size:0.75rem; color:var(--muted);">
                                        Vous pouvez consulter votre boîte de réception ou attendre le fichier de résultats publié par votre enseignant.
                                    </span>
                                </div>

                                <div style="font-size: 0.82rem; color: var(--muted); margin-bottom: 1.5rem;">
                                    Nombre total de participants à cette séance : <strong id="finished-total-users" style="color: var(--ink);">--</strong>
                                </div>

                                <div style="font-size: 0.8rem; color: var(--muted); margin-bottom: 2rem;" id="redirect-countdown-container">
                                    Redirection automatique vers <?= $isStudent ? 'votre tableau de bord' : "l'accueil" ?> dans <strong id="redirect-counter">30</strong> secondes...
                                </div>

                                <a href="<?= htmlspecialchars($redirectUrl) ?>" class="btn-primary"><?= htmlspecialchars($redirectLabel) ?></a>
                                <?php if ($isAsync): ?>
                                    <div style="margin-top: 1rem;">
                                        <a href="/live-session.php?code=<?= urlencode($code) ?>&restart=1" class="btn-primary" style="background-color: var(--ink); border-color: var(--ink); text-decoration: none; display: inline-block;">Recommencer l'évaluation</a>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endif; ?>

                </div>

                <!-- Typewriter telemetry block -->
                <div id="col-bottom-bar">
                    <div id="typewriter-line">
                        <span id="tw-text">> Initialisation...</span><span class="cursor"></span>
                    </div>

                    <?php if ($registration): ?>
                        <p class="hint">Code session : <strong><?= htmlspecialchars($code) ?></strong> | Enregistrement ID : <strong><?= (int)$regId ?></strong></p>
                    <?php endif; ?>

                    <div class="page-footer">
                        © <?= date('Y') ?> StudyVibe — Live Tele-Evaluation Registry
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Scripts JavaScript -->
    <?php if (!$error): ?>
    <script>
        const sessionCode = <?= json_encode($code) ?>;
        const registrationId = <?= $registration ? (int)$regId : 'null' ?>;
        const serverStartTimestamp = <?= strtotime($session['start_time']) ?>;
        const serverCurrentTimestamp = <?= time() ?>;
        const serverTimeOffset = (serverCurrentTimestamp * 1000) - Date.now();
        const isAsync = <?= $isAsync ? 'true' : 'false' ?>;

        function getServerTime() {
            return Date.now() + serverTimeOffset;
        }

        let pollingInterval = null;
        let countdownTimer = null;
        let isFinalCountdown = false;
        let currentQuestionId = null;
        let redirectTimer = null;

        // Message telemetry typewriter system
        const telemetryMessages = [
            <?= $registration ? "`> Connecté en tant que: " . json_encode($registration['name']) . "`" : "`> En attente d'enregistrement...`" ?>,
            `> Attente de synchronisation de la téléévaluation...`,
            `> Serveur d'évaluation en ligne. Statut: OK`,
            `> Synchronisation temporelle serveur active (Abs. Time)...`,
            `> En attente du début officiel de la séance...`
        ];

        let msgIdx = 0;
        let charIdx = 0;
        let deleting = false;
        const twEl = document.getElementById('tw-text');
        let delay = 0;

        function typewriteTelemetry() {
            const current = telemetryMessages[msgIdx];
            if (!current) return;
            if (!deleting) {
                twEl.textContent = current.slice(0, charIdx + 1);
                charIdx++;
                if (charIdx === current.length) {
                    deleting = true;
                    delay = 3000;
                } else {
                    delay = 25 + Math.random() * 25;
                }
            } else {
                twEl.textContent = current.slice(0, charIdx - 1);
                charIdx--;
                if (charIdx === 0) {
                    deleting = false;
                    msgIdx = (msgIdx + 1) % telemetryMessages.length;
                    delay = 500;
                } else {
                    delay = 12;
                }
            }
            setTimeout(typewriteTelemetry, delay);
        }
        setTimeout(typewriteTelemetry, 800);

        // Update typewriter log instantly with custom event message
        function logTelemetry(msg) {
            // Push message to log queue and reset indices to display it immediately
            telemetryMessages.unshift(`> ${msg}`);
            if (telemetryMessages.length > 8) telemetryMessages.pop();
            msgIdx = 0;
            charIdx = 0;
            deleting = false;
        }

        // Floating particles
        const particlesContainer = document.getElementById('floating-particles');
        const particleSVGs = [
            `<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="8" r="7" stroke="#004B23" stroke-width="1.5" opacity="0.3"/></svg>`,
            `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2 Q14 8 20 12 Q14 16 12 22 Q10 16 4 12 Q10 8 12 2 Z" fill="#C9A84C" opacity="0.35"/></svg>`,
            `<text x="0" y="16" fill="#004B23" opacity="0.25" font-family="monospace" font-size="16">?</text>`,
            `<text x="0" y="16" fill="#C9A84C" opacity="0.25" font-family="monospace" font-size="16">✓</text>`
        ];

        for (let i = 0; i < 12; i++) {
            const el = document.createElement('div');
            el.className = 'particle';
            const left = Math.random() * 100;
            const delayTime = Math.random() * 15;
            const duration = 10 + Math.random() * 12;
            const rotate = (Math.random() - 0.5) * 60;
            const scale = 0.6 + Math.random() * 0.8;
            el.style.cssText = `
                left: ${left}%;
                bottom: -40px;
                --r: ${rotate}deg;
                animation-duration: ${duration}s;
                animation-delay: ${delayTime}s;
                transform: scale(${scale});
            `;
            el.innerHTML = particleSVGs[Math.floor(Math.random() * particleSVGs.length)];
            particlesContainer.appendChild(el);
        }

        let lobbyClockInterval = null;

        // Start polling loop
        function startApp() {
            // Sécuriser les questions contre la copie
            const quizViewEl = document.getElementById('quiz-view');
            if (quizViewEl) {
                quizViewEl.addEventListener('selectstart', (e) => e.preventDefault());
                quizViewEl.addEventListener('copy', (e) => e.preventDefault());
                quizViewEl.addEventListener('contextmenu', (e) => e.preventDefault());
            }

            // Start real-time absolute timer ticking for lobby
            updateLobbyClock();
            lobbyClockInterval = setInterval(updateLobbyClock, 1000);

            if (registrationId !== null) {
                poll();
                pollingInterval = setInterval(poll, 2500);
            }
        }

        function updateLobbyClock() {
            if (isFinalCountdown) return;

            const msLeft = (serverStartTimestamp * 1000) - getServerTime();
            const seconds = Math.max(0, Math.floor(msLeft / 1000));
            const watermark = document.getElementById('lobby-timer-watermark');

            if (seconds > 0) {
                const mins = Math.floor(seconds / 60);
                const secs = seconds % 60;
                if (watermark) {
                    watermark.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                }
            } else {
                if (watermark) {
                    watermark.textContent = "00:00";
                }
            }

            // If start is imminent (5s or less)
            if (seconds <= 5 && seconds > 0 && !isFinalCountdown) {
                isFinalCountdown = true;
                if (pollingInterval) clearInterval(pollingInterval);
                if (lobbyClockInterval) clearInterval(lobbyClockInterval);
                startFinalCountdown(seconds);
            }
        }

        function poll() {
            if (isFinalCountdown) return;

            fetch(`/api/live-eval-poll.php?code=${sessionCode}&action=poll_lobby`)
            .then(res => res.json())
            .then(data => {
                const loadingEl = document.getElementById('loading-state');
                if (loadingEl) loadingEl.classList.add('hidden');
                
                if (!data.success) {
                    showError(data.message);
                    return;
                }

                if (data.status === 'waiting') {
                    showView('lobby-view');
                    const lobbyRegCountEl = document.getElementById('lobby-registered-count');
                    if (lobbyRegCountEl) {
                        lobbyRegCountEl.textContent = data.registered_count || 0;
                    }
                } else if (data.status === 'active') {
                    if (lobbyClockInterval) clearInterval(lobbyClockInterval);
                    pollQuiz();
                    // Switch interval to pollQuiz
                    clearInterval(pollingInterval);
                    pollingInterval = setInterval(pollQuiz, 2000);
                }
            })
            .catch(err => console.error("Erreur connexion : ", err));
        }

        function pollQuiz() {
            fetch(`/api/live-eval-poll.php?code=${sessionCode}&action=poll_quiz`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    if (data.not_registered) {
                        window.location.reload();
                    } else {
                        showError(data.message);
                    }
                    return;
                }

                if (data.status === 'finished' || data.is_finished) {
                    clearInterval(pollingInterval);
                    showView('finished-view');
                    const totalUsersEl = document.getElementById('finished-total-users');
                    if (totalUsersEl) {
                        totalUsersEl.textContent = data.total_registered || '--';
                    }
                    return;
                }

                if (data.status === 'waiting') {
                    showView('lobby-view');
                    return;
                }

                showView('quiz-view');
                const qNumText = `Question ${data.current_question_index + 1} / ${data.total_questions}`;
                document.getElementById('quiz-question-number').textContent = qNumText;
                document.getElementById('live-bar-question').textContent = qNumText;

                // Mettre à jour le compteur dynamique des participants restants à répondre
                const remainingEl = document.getElementById('quiz-live-remaining-count');
                if (remainingEl) {
                    const total = data.total_registered || 0;
                    const answers = data.answers_received || 0;
                    remainingEl.textContent = Math.max(0, total - answers);
                }

                const q = data.question;
                
                if (currentQuestionId !== q.id) {
                    currentQuestionId = q.id;
                    resetQuizForm(q);
                    logTelemetry(`Question ${data.current_question_index + 1} activée: ${q.question_text.slice(0, 30)}...`);
                }

                updateQuestionTimer(q.seconds_left);
            })
            .catch(err => console.error("Erreur quiz : ", err));
        }

        let questionTimer = null;
        let questionSecondsLeft = 0;
        let questionTotalDuration = 0;

        function updateQuestionTimer(seconds) {
            // Synchronisation intelligente : si la dérive est supérieure à 2s ou si le timer local est à 0, on resynchronise
            const drift = Math.abs(questionSecondsLeft - seconds);
            if (drift > 2 || questionSecondsLeft <= 0) {
                questionSecondsLeft = seconds;
                if (questionTotalDuration === 0 || seconds > questionTotalDuration) {
                    questionTotalDuration = seconds;
                }
            }

            // Mettre à jour l'affichage immédiatement
            document.getElementById('quiz-timer-text').textContent = `${questionSecondsLeft}s`;
            const pct = (questionSecondsLeft / questionTotalDuration) * 100;
            document.getElementById('quiz-progress-bar').style.width = `${pct}%`;

            // Démarrer l'intervalle local unique s'il n'est pas déjà actif
            if (!questionTimer) {
                questionTimer = setInterval(() => {
                    if (questionSecondsLeft > 0) {
                        questionSecondsLeft--;
                        document.getElementById('quiz-timer-text').textContent = `${questionSecondsLeft}s`;
                        const currentPct = (questionSecondsLeft / questionTotalDuration) * 100;
                        document.getElementById('quiz-progress-bar').style.width = `${currentPct}%`;
                    }
                    if (questionSecondsLeft <= 0) {
                        clearInterval(questionTimer);
                        questionTimer = null;
                        document.getElementById('quiz-timer-text').textContent = `0s`;
                        document.getElementById('quiz-progress-bar').style.width = `0%`;
                        disableOptions();
                        if (isAsync) {
                            submitLiveAnswer("");
                        }
                    }
                }, 1000);
            }
        }

        function showView(viewId) {
            const views = ['lobby-view', 'countdown-view', 'quiz-view', 'finished-view', 'loading-state'];
            views.forEach(v => {
                const el = document.getElementById(v);
                if (el) el.classList.toggle('hidden', v !== viewId);
            });

            const card       = document.getElementById('main-card');
            const liveBar    = document.getElementById('quiz-live-bar');
            const img        = document.getElementById('live-illustration');
            const caption    = document.getElementById('live-caption');
            const badge      = document.getElementById('state-badge');
            const topBar     = document.getElementById('col-top-bar');
            const bottomBar  = document.getElementById('col-bottom-bar');
            const watermark  = document.getElementById('lobby-timer-watermark');

            if (viewId === 'quiz-view') {
                // Immersive full-screen quiz mode
                card.classList.add('quiz-mode');
                liveBar.classList.add('active');
                if (topBar) topBar.classList.add('hidden');
                if (bottomBar) bottomBar.classList.add('hidden');
                if (watermark) watermark.classList.add('hidden');
                if (badge) {
                    badge.innerHTML = `<svg width="6" height="6" viewBox="0 0 8 8" fill="none" style="margin-right:2px;"><circle cx="4" cy="4" r="3" fill="#EF4444"/></svg> Quiz Actif`;
                }
            } else if (viewId === 'finished-view') {
                // Restore standard card styling for the finish view
                card.classList.remove('quiz-mode');
                liveBar.classList.remove('active');
                if (topBar) topBar.classList.remove('hidden');
                if (bottomBar) bottomBar.classList.remove('hidden');
                if (watermark) watermark.classList.add('hidden');
                if (img) img.src = "/assets/img/live-success-illustration.png";
                if (caption) caption.textContent = "Téléévaluation terminée. Résultats prêts !";
                if (badge) {
                    badge.innerHTML = `<svg width="6" height="6" viewBox="0 0 8 8" fill="none" style="margin-right:2px;"><circle cx="4" cy="4" r="3" fill="#10B981"/></svg> Session Complétée`;
                }

                // Hide irrelevant telemetry in finished screen
                const typewriterLine = document.getElementById('typewriter-line');
                const hintEl = document.querySelector('#col-bottom-bar .hint');
                if (typewriterLine) typewriterLine.classList.add('hidden');
                if (hintEl) hintEl.classList.add('hidden');

                // Redirect timer (30 seconds)
                if (!redirectTimer) {
                    let redirectSecs = 30;
                    const counterEl = document.getElementById('redirect-counter');
                    if (counterEl) {
                        counterEl.textContent = redirectSecs;
                        redirectTimer = setInterval(() => {
                            redirectSecs--;
                            if (redirectSecs <= 0) {
                                clearInterval(redirectTimer);
                                window.location.href = <?= json_encode($redirectUrl) ?>;
                            } else {
                                counterEl.textContent = redirectSecs;
                            }
                        }, 1000);
                    }
                }
            } else {
                // Lobby & count down: standard double column card with watermark timer
                card.classList.remove('quiz-mode');
                liveBar.classList.remove('active');
                if (topBar) topBar.classList.remove('hidden');
                if (bottomBar) bottomBar.classList.remove('hidden');
                if (img) img.src = "/assets/img/live-lobby-illustration.png";
                if (caption) caption.textContent = "Dans la salle d'attente synchronisée…";

                if (viewId === 'lobby-view') {
                    if (watermark) watermark.classList.remove('hidden');
                } else {
                    if (watermark) watermark.classList.add('hidden');
                }

                const color = viewId === 'countdown-view' ? '#F59E0B' : '#004B23';
                if (badge) {
                    badge.innerHTML = `<svg width="6" height="6" viewBox="0 0 8 8" fill="none" style="margin-right:2px;"><circle cx="4" cy="4" r="3" fill="${color}"/></svg> Salle d'attente`;
                }

                // Restore typewriter and hint for lobby/countdown
                const typewriterLine = document.getElementById('typewriter-line');
                const hintEl = document.querySelector('#col-bottom-bar .hint');
                if (typewriterLine) typewriterLine.classList.remove('hidden');
                if (hintEl) hintEl.classList.remove('hidden');
            }
        }

        function showError(msg) {
            if (pollingInterval) clearInterval(pollingInterval);
            if (lobbyClockInterval) clearInterval(lobbyClockInterval);
            logTelemetry(`ERREUR SERVEUR: ${msg}`);
            alert(msg);
        }

        function startFinalCountdown(seconds) {
            showView('countdown-view');
            logTelemetry(`Lancement imminent dans ${seconds} secondes !`);
            
            let count = seconds > 0 ? seconds : 5;
            document.getElementById('final-countdown-num').textContent = count;

            const timer = setInterval(() => {
                count--;
                if (count <= 0) {
                    clearInterval(timer);
                    showView('loading-state');
                    isFinalCountdown = false;
                    
                    pollQuiz();
                    pollingInterval = setInterval(pollQuiz, 2000);
                } else {
                    document.getElementById('final-countdown-num').textContent = count;
                }
            }, 1000);
        }

        function renderMath() {
            if (typeof renderMathInElement === 'function') {
                renderMathInElement(document.body, {
                    delimiters: [
                        {left: '$$', right: '$$', display: true},
                        {left: '$', right: '$', display: false},
                        {left: '\\(', right: '\\)', display: false},
                        {left: '\\[', right: '\\]', display: true}
                    ],
                    throwOnError: false
                });
            }
        }

        function resetQuizForm(q) {
            if (questionTimer) {
                clearInterval(questionTimer);
                questionTimer = null;
            }
            questionTotalDuration = q.seconds_left;
            questionSecondsLeft = q.seconds_left;
            document.getElementById('quiz-question-text').textContent = q.question_text;
            
            const imgContainer = document.getElementById('quiz-image-container');
            const imgEl = document.getElementById('quiz-image');
            if (q.image_path) {
                imgEl.src = q.image_path;
                imgContainer.classList.remove('hidden');
            } else {
                imgContainer.classList.add('hidden');
            }

            document.getElementById('text-opt-A').textContent = q.option_a;
            document.getElementById('text-opt-B').textContent = q.option_b;
            document.getElementById('text-opt-C').textContent = q.option_c;
            document.getElementById('text-opt-D').textContent = q.option_d;

            ['A', 'B', 'C', 'D'].forEach(opt => {
                const btn = document.getElementById(`btn-opt-${opt}`);
                btn.disabled = false;
                btn.className = "option-btn";
            });

            document.getElementById('quiz-submit-status').classList.add('hidden');

            // Lancer le rendu des formules mathématiques sur le nouveau contenu
            setTimeout(renderMath, 50);
        }

        function submitLiveAnswer(option) {
            ['A', 'B', 'C', 'D'].forEach(opt => {
                const btn = document.getElementById(`btn-opt-${opt}`);
                if (opt === option) {
                    btn.className = "option-btn selected";
                } else {
                    btn.className = "option-btn opacity-40";
                }
            });

            disableOptions();
            document.getElementById('quiz-submit-status').classList.remove('hidden');
            logTelemetry(`Option ${option} soumise. En attente...`);

            const formData = new FormData();
            formData.append('code', sessionCode);
            formData.append('action', 'submit_answer');
            formData.append('question_id', currentQuestionId);
            formData.append('selected_option', option);

            fetch('/api/live-eval-poll.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    console.error(data.message);
                }
            })
            .catch(err => console.error("Erreur de soumission :", err));
        }

        function disableOptions() {
            ['A', 'B', 'C', 'D'].forEach(opt => {
                const btn = document.getElementById(`btn-opt-${opt}`);
                if (btn) btn.disabled = true;
            });
        }

        window.addEventListener('DOMContentLoaded', startApp);
    </script>
    <?php endif; ?>

</body>
</html>
