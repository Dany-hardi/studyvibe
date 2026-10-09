<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Live Evaluation Session Controller
 * 
 * Manages the synchronised student-facing tele-evaluation interface.
 * Handles student registration/login flows, real-time polling synchronisation,
 * automatic LaTeX equation rendering using KaTeX, audio chime indicators,
 * circular progress countdown tracking, local cache queue for resilient offline 
 * submission synchronisation, and live podium leaderboard calculations.
 * 
 * @package    StudyVibe
 * @subpackage Core
 * @author     Advanced Engineering Team
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Brand.php';
require_once __DIR__ . '/lib/LiveGuest.php';

// =========================================================================
// SECTION 1: LOGOUT OR STATE RESET
// =========================================================================

// Handle evaluation-specific logout requests to switch accounts
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $code = trim((string)($_GET['code'] ?? ''));
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: /live-session.php?code=" . urlencode($code));
    exit;
}

/**
 * Formats evaluation start time into a localized human-readable string.
 * 
 * @param string $dateTimeStr Standard ISO/MySQL datetime string.
 * @return string Localized french presentation string.
 */
function getFormattedEvalStartTime(string $dateTimeStr): string {
    $timestamp = strtotime($dateTimeStr);
    if (!$timestamp) return $dateTimeStr;
    
    $months = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'
    ];
    
    $day = date('j', $timestamp);
    $monthNum = (int)date('n', $timestamp);
    $year = date('Y', $timestamp);
    $time = date('H\hi', $timestamp);
    
    $today = date('Y-m-d');
    $evalDate = date('Y-m-d', $timestamp);
    
    if ($today === $evalDate) {
        return "aujourd'hui à " . $time;
    }
    
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    if ($tomorrow === $evalDate) {
        return "demain à " . $time;
    }
    
    return "le " . $day . " " . $months[$monthNum] . " " . $year . " à " . $time;
}

// Enforce active PHP session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$code = trim((string)($_GET['code'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));

require_once __DIR__ . '/lib/Analytics.php';
Analytics::captureSource();
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === '') {
    Analytics::hit('view:live_invite');
}

// Handle participant manual session leave / disconnect logic
if ($code !== '' && $action === 'disconnect') {
    if (isset($_SESSION['live_registrations'][$code])) {
        $regId = (int)$_SESSION['live_registrations'][$code];
        unset($_SESSION['live_registrations'][$code]);
        unset($_SESSION['verified_registrations'][$code]);
        
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("SELECT score FROM live_eval_registrations WHERE id = :id");
            $stmt->execute(['id' => $regId]);
            $score = $stmt->fetchColumn();
            if ($score === false || $score === null) {
                // Delete empty/abandoned session registrations to keep the student dashboard clean
                $delStmt = $pdo->prepare("DELETE FROM live_eval_registrations WHERE id = :id");
                $delStmt->execute(['id' => $regId]);
            }
        } catch (Exception $e) {}
    }
    header('Location: /evaluations.php');
    exit;
}

$pdo = null;
$session = null;
$courseTitle = '';
$error = null;

$currentUser = getCurrentUser();
$currentUserName = $currentUser['name'] ?? '';
$currentUserEmail = $currentUser['email'] ?? '';

// Fetch the targeted live evaluation metadata from database
try {
    $pdo = Database::getInstance();
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
} catch (Exception $e) {
    $error = "Erreur de connexion à la base de données (trop de connexions ou serveur saturé). Veuillez rafraîchir la page dans quelques instants.";
}

// Evaluate asynchronous constraints
$isAsync = ($session && isset($session['is_async']) && (int)$session['is_async'] === 1);
$asyncDeadlinePassed = false;
if ($isAsync && !empty($session['async_deadline'])) {
    $asyncDeadlinePassed = (time() > strtotime($session['async_deadline']));
}

$isStudent = isset($_SESSION['user_id'], $_SESSION['user_role']) && $_SESSION['user_role'] === 'student';
$redirectUrl = $isStudent ? 'student/dashboard.php' : 'index.php';
$redirectLabel = $isStudent ? 'Retour au tableau de bord' : "Retour à l'accueil";

// Render custom professional error views on invalid states
if ($error !== null) {
    $errorCode = 500;
    $errorTitle = "Erreur de connexion";
    $errorMessage = $error;
    $badgeText = "Alerte de connexion";
    $typewriterLines = [
        '> ERREUR 500 : Échec de la liaison de données',
        '> Vérification des pools de connexion… Saturé.',
        '> Suggestion : Rafraîchissez la page ou réessayez plus tard.',
    ];
    include __DIR__ . '/error.php';
    exit;
}

if (!$session) {
    $errorCode = 404;
    $errorTitle = 'Séance de téléévaluation introuvable';
    $errorMessage = 'Le code de session saisi est introuvable ou le lien a expiré. Veuillez vérifier le code et réessayer.';
    $badgeText = 'Session introuvable';
    $typewriterLines = [
        '> ERREUR : Code de session inconnu',
        '> Recherche de la téléévaluation dans l\'annuaire académique…',
        '> Aucun résultat trouvé pour la clé fournie.',
        '> Veuillez contacter votre enseignant ou promoteur.',
    ];
    include __DIR__ . '/error.php';
    exit;
} elseif ((int)$session['status'] === 0) {
    $errorCode = 403;
    $errorTitle = 'Téléévaluation désactivée';
    $errorMessage = 'Cette séance d\'évaluation a été désactivée par l\'enseignant responsable.';
    $badgeText = 'Session inactive';
    $typewriterLines = [
        '> SÉCURITÉ : Session archivée ou suspendue',
        '> Accès refusé pour les tentatives de connexion active.',
        '> Contactez le secrétariat pédagogique si nécessaire.',
    ];
    include __DIR__ . '/error.php';
    exit;
} elseif ($isAsync && $asyncDeadlinePassed) {
    $errorCode = 403;
    $errorTitle = 'Date limite dépassée';
    $errorMessage = 'La date limite pour participer à cette évaluation asynchrone est dépassée.';
    $badgeText = 'Date limite dépassée';
    $typewriterLines = [
        '> ALERTE : Soumission tardive refusée',
        '> Date de clôture enregistrée : ' . htmlspecialchars($session['async_deadline']),
        '> Protocole académique : Inscription fermée.',
    ];
    include __DIR__ . '/error.php';
    exit;
}



// =========================================================================
// SECTION 2: REGISTRATION & AUTHENTICATION POST DISPATCHER
// =========================================================================
$regError = null;
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_live'])) {
    $authAction = trim((string)($_POST['auth_action'] ?? ''));
    
    if (isLoggedIn()) {
        $currentUser = getCurrentUser();
        $name = $currentUser['name'];
        $email = $currentUser['email'];
        $studentId = $currentUser['id'];
    } else {
        // No account needed: a working email on an accepted domain and a name are enough.
        $email = LiveGuest::normalize((string)($_POST['email'] ?? ''));
        $name  = trim((string)($_POST['name'] ?? ''));
        $studentId = null;

        $regError = LiveGuest::check($email);
        if ($regError === null) {
            try {
                $pdo = Database::getInstance();
                $known = LiveGuest::findUser($pdo, $email);
                if ($known) {
                    // Registered users are recognised by their email and keep the name on their account
                    $name = $known['name'];
                    $studentId = $known['id'];
                } elseif (mb_strlen($name) < 2) {
                    $regError = "Veuillez saisir votre nom complet pour continuer.";
                } elseif (mb_strlen($name) > 120) {
                    $regError = "Ce nom est trop long (120 caractères maximum).";
                }
            } catch (PDOException $e) {
                $regError = "Le service est momentanément indisponible. Veuillez réessayer dans un instant.";
            }
        }
    }

    // Register the participant for the specific live evaluation session if auth completes successfully
    if ($regError === null) {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("
                INSERT INTO live_eval_registrations (session_id, name, email, student_id)
                VALUES (:sid, :name, :email, :student_id)
                ON DUPLICATE KEY UPDATE name = :name2, student_id = :student_id2
            ");
            $stmt->execute([
                'sid'        => $session['id'],
                'name'       => $name,
                'name2'      => $name,
                'email'      => $email,
                'student_id'  => $studentId ?? null,
                'student_id2' => $studentId ?? null,
            ]);

            // Fetch registration identifier
            $stmt = $pdo->prepare("SELECT id FROM live_eval_registrations WHERE session_id = :sid AND email = :email");
            $stmt->execute(['sid' => $session['id'], 'email' => $email]);
            $regId = (int)$stmt->fetchColumn();

            if ($regId <= 0) {
                throw new PDOException("Impossible de récupérer l'ID d'inscription.");
            }

            // Sync with local session
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
            $regError = "Erreur lors de l'enregistrement de l'évaluation : " . $e->getMessage();
        }
    }
}

// =========================================================================
// SECTION 3: SESSION REGISTRATION RECOVERY & ASYNC RESTARTS
// =========================================================================
$regId = 0;
$registration = null;
$isReset = false;
$resultsUrl = '';
$isGuestParticipant = false;
if (!$error) {
    $regId = (int)($_SESSION['live_registrations'][$code] ?? 0);
    if ($regId <= 0 && isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM live_eval_registrations WHERE session_id = :sid AND student_id = :student_id");
        $stmt->execute(['sid' => $session['id'], 'student_id' => $_SESSION['user_id']]);
        $dbRegId = (int)$stmt->fetchColumn();
        if ($dbRegId > 0) {
            $regId = $dbRegId;
            $_SESSION['live_registrations'][$code] = $regId;
        }
    }
    if ($regId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM live_eval_registrations WHERE id = :id AND session_id = :sid");
        $stmt->execute(['id' => $regId, 'sid' => $session['id']]);
        $registration = $stmt->fetch();
        if (!$registration) {
            // Soft reset session registers if excluded or purged from database
            $isReset = true;
            unset($_SESSION['live_registrations'][$code]);
            unset($_SESSION['verified_registrations'][$code]);
        } else {
            // A participant is a guest when nobody is logged in and the email has no StudyVibe account behind it
            if (!isLoggedIn() && empty($registration['student_id'])) {
                try {
                    $isGuestParticipant = LiveGuest::findUser($pdo, (string)$registration['email']) === null;
                } catch (Throwable $e) {
                    $isGuestParticipant = false;
                }
            }
            if (defined('APP_SECRET') && !$isGuestParticipant) {
                $resultsToken = hash_hmac('sha256', (string)$regId, APP_SECRET);
                $resultsUrl = "/student/evaluation-results.php?registration_id={$regId}&token={$resultsToken}";
            }
        }

        // Handle automated restart triggers for completed async student attempts
        if ($isAsync && $registration) {
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
    <?= Brand::headLinks() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/sv2.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    
    <!-- KaTeX mathematical typesetting integrations -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js" onload="renderMath()"></script>
    
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --cream:   var(--paper);
            --green:   var(--clay);
            --green2:  var(--clay-press);
            --muted:   var(--ink-2);
            --faint:   var(--ink-3);
            --gold:    var(--ochre);
            --surface: var(--card);
        }

        html, body {
            height: 100%;
            background: var(--cream);
            font-family: var(--font-body);
            color: var(--ink);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Sleek glowing mesh background using color palette */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(181, 72, 42, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 100% 100%, rgba(201, 168, 76, 0.08) 0%, transparent 40%),
                url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.035'/%3E%3C/svg%3E");
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
            padding: 2.5rem 1.5rem;
        }

        /* ── Floating particles ── */
        .floating-particles { display: none !important; }
        .floating-particles-legacy {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }

        .particle {
            position: absolute;
            background: var(--green);
            border-radius: 50%;
            opacity: 0;
            animation: floatParticle linear infinite;
        }

        @keyframes floatParticle {
            0%   { opacity: 0; transform: translateY(0) rotate(var(--r)) scale(0.4); }
            10%  { opacity: 0.12; }
            90%  { opacity: 0.08; }
            100% { opacity: 0; transform: translateY(-110vh) rotate(calc(var(--r) + 30deg)) scale(0.8); }
        }

        /* ── Card Layout ── */
        .card {
            background: var(--surface);
            border: 1px solid rgba(181,72,42,0.08);
            max-width: 1120px;
            width: 100%;
            height: min(720px, 90vh);
            display: flex;
            position: relative;
            box-shadow:
                0 30px 100px -20px rgba(0,0,0,0.08),
                0 15px 40px -15px rgba(181, 72, 42, 0.04),
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
            overflow: hidden;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--green) 0%, var(--green2) 50%, var(--gold) 100%);
            z-index: 10;
        }

        .col-image {
            flex: 1.1;
            position: relative;
            background: #1E1B16;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border-top-left-radius: 20px;
            border-bottom-left-radius: 20px;
        }

        .col-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.75;
            transition: all 0.7s ease;
        }

        .card:hover .col-image img {
            transform: scale(1.03);
            opacity: 0.8;
        }

        .illustration-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 2rem 1.5rem;
            background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.4) 60%, transparent 100%);
            color: #fff;
            font-size: 0.78rem;
            font-weight: 500;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            font-family: var(--font-body);
            text-shadow: 0 1px 2px rgba(0,0,0,0.6);
            z-index: 2;
        }

        .col-content {
            flex: 1;
            padding: 3.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow-y: auto;
            position: relative;
            background: var(--surface);
            border-top-right-radius: 20px;
            border-bottom-right-radius: 20px;
            scroll-behavior: smooth;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            text-decoration: none;
            margin-bottom: 2rem;
            transition: transform 0.25s ease;
        }
        .brand:hover {
            transform: translateX(2px);
        }

        .brand-name {
            font-family: var(--font-body);
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.01em;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--green);
            border: 1px solid rgba(181, 72, 42, 0.2);
            background: rgba(181, 72, 42, 0.04);
            padding: 0.4rem 1rem;
            border-radius: 9999px;
            align-self: flex-start;
            margin-bottom: 1.5rem;
            transition: all 0.2s ease;
        }
        .badge:hover {
            background: rgba(181, 72, 42, 0.08);
            border-color: var(--green);
        }

        .option-btn {
            width: 100%;
            padding: 1.1rem 1.25rem;
            border: 1px solid rgba(0, 0, 0, 0.07);
            background: rgba(252, 250, 246, 0.85);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: var(--ink);
            text-align: left;
            font-family: var(--font-body);
            font-weight: 600;
            font-size: 0.95rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.01);
        }

        .option-btn:hover:not(:disabled) {
            border-color: var(--green);
            background: rgba(181, 72, 42, 0.03);
            transform: translateY(-2.5px) scale(1.015);
            box-shadow: 
                0 10px 20px -8px rgba(181, 72, 42, 0.12),
                0 0 12px rgba(181, 72, 42, 0.05);
        }

        .option-btn.selected {
            border-color: var(--green2);
            background: rgba(181,72,42, 0.07);
            color: var(--green);
            font-weight: 700;
            transform: translateY(-1.5px) scale(1.01);
            box-shadow: 
                0 0 0 1px var(--green2),
                0 10px 25px -8px rgba(181,72,42, 0.2),
                0 0 16px rgba(181,72,42, 0.15);
        }

        .option-btn.selected span.opt-label {
            background: var(--green);
            border-color: var(--green);
            color: #FFF;
        }

        .option-btn:disabled {
            cursor: not-allowed;
            opacity: 0.55;
        }

        .opt-label {
            width: 26px;
            height: 26px;
            border-radius: 8px;
            background: var(--card);
            border: 1px solid rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--muted);
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        
        .option-btn:hover:not(:disabled) .opt-label {
            border-color: var(--green);
            color: var(--green);
        }

        .countdown-number {
            font-family: var(--font-body);
            font-size: 5rem;
            font-weight: 800;
            color: var(--green);
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .progress-bar-container {
            width: 100%;
            height: 6px;
            background: rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 2rem;
            border-radius: 9999px;
        }

        .progress-bar {
            height: 100%;
            background: var(--clay);
            width: 100%;
            transition: width 1s linear;
            border-radius: 9999px;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            background: var(--clay);
            color: #fff;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            text-decoration: none;
            padding: 1.05rem 1.75rem;
            border: 2px solid var(--ink);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .btn-primary:hover {
            background: var(--clay-press);
            border-color: var(--clay-press);
            transform: translateY(-1.5px);
            box-shadow: 0 8px 20px -6px rgba(181, 72, 42, 0.25);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }

        .input-field {
            width: 100%;
            padding: 0.95rem 1.15rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: #FCFAF6;
            font-family: var(--font-body);
            font-size: 0.88rem;
            outline: none;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .input-field:focus {
            border-color: var(--green);
            background: var(--card);
            box-shadow: 
                0 0 0 1px var(--green),
                0 0 12px rgba(181, 72, 42, 0.15);
        }

        /* Styled sliding auth tabs options */
        .auth-tabs-container {
            position: relative;
            display: flex;
            background: rgba(0, 0, 0, 0.04);
            border-radius: 9999px;
            padding: 4px;
            margin-bottom: 2rem;
            border: 1px solid rgba(181, 72, 42, 0.05);
        }
        .auth-tabs-pill {
            position: absolute;
            top: 4px;
            bottom: 4px;
            left: 4px;
            width: calc(50% - 4px);
            background: var(--surface);
            border-radius: 9999px;
            box-shadow: 0 4px 10px rgba(181, 72, 42, 0.06);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
        }
        .auth-tab-btn {
            position: relative;
            z-index: 2;
            flex: 1;
            background: none;
            border: none;
            padding: 0.7rem 0;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--muted);
            cursor: pointer;
            text-align: center;
            transition: color 0.3s ease;
            font-family: var(--font-body);
        }
        .auth-tab-btn.active {
            color: var(--green);
            font-weight: 700;
        }

        #typewriter-line {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--green);
            background: rgba(181, 72, 42, 0.035);
            border: 1px solid rgba(181, 72, 42, 0.08);
            padding: 0.7rem 1rem;
            margin-top: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 6px;
            width: 100%;
        }

        .cursor {
            display: inline-block;
            width: 2px;
            height: 1.1em;
            background: var(--green);
            margin-left: 2px;
            vertical-align: text-bottom;
            animation: blink 1s step-end infinite;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }

        .hint {
            font-size: 0.68rem;
            color: var(--faint);
            font-weight: 400;
            letter-spacing: 0.02em;
            margin-top: 1.25rem;
        }

        .page-footer {
            margin-top: 2rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(0,0,0,0.05);
            font-size: 0.68rem;
            color: var(--faint);
            letter-spacing: 0.05em;
            font-weight: 500;
        }

        /* ── Quiz Mode : full width, center card ─────────────── */
        .card.quiz-mode {
            max-width: 800px;
            height: auto;
            min-height: 520px;
        }
        .card.quiz-mode .col-image {
            display: none !important;
        }
        .card.quiz-mode .col-content {
            flex: 1 1 100%;
            padding: 3.5rem 4.5rem;
            justify-content: center;
            border-radius: 20px;
        }

        /* ── Watermark Timer for Lobby ── */
        .space-y-4 > * + * { margin-top: 1.1rem; }
        .timer-watermark { display: none;
            position: absolute;
            right: 2.5rem;
            top: 3rem;
            font-family: var(--font-body);
            font-size: 4rem;
            font-weight: 800;
            color: var(--green);
            opacity: 0.06;
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
            padding: 0.75rem 2rem;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            z-index: 20;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        #quiz-live-bar.active { display: flex; }
        .live-dot {
            display: inline-block;
            width: 8px; height: 8px;
            background: #EF4444;
            border-radius: 50%;
            animation: blink 1s step-end infinite;
            margin-right: 6px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.4;} }

        .hidden { display: none !important; }

        /* Premium Esports Podium */
        #podium-wrapper {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 20px;
            margin-bottom: 2.5rem;
            height: 180px;
            padding-top: 24px;
        }
        
        .podium-bar {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100px;
            transition: all 0.3s ease;
        }
        
        .podium-name {
            font-size: 0.78rem;
            font-weight: 700;
            text-align: center;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            width: 100%;
            margin-bottom: 6px;
        }
        
        /* 3D Podium Bars styling */
        .podium-box {
            position: relative;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px 12px 0 0 !important;
            box-shadow: 
                0 10px 15px -3px rgba(0, 0, 0, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.4),
                inset 0 -4px 0 rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Soft reflection overlay */
        .podium-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 50%;
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0) 100%);
            pointer-events: none;
        }

        /* Gold Gradient (1st Place) */
        .podium-gold {
            background: linear-gradient(135deg, #FFE066 0%, #F5C400 50%, #D4A373 100%) !important;
            border: 1px solid #E6B800 !important;
            box-shadow: 
                0 12px 20px -8px rgba(245, 196, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.5),
                inset 0 -6px 0 rgba(0, 0, 0, 0.1) !important;
        }

        /* Silver Gradient (2nd Place) */
        .podium-silver {
            background: linear-gradient(135deg, #FFFFFF 0%, #E5E7EB 50%, #9CA3AF 100%) !important;
            border: 1px solid #D1D5DB !important;
            box-shadow: 
                0 12px 20px -8px rgba(156, 163, 175, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.6),
                inset 0 -6px 0 rgba(0, 0, 0, 0.1) !important;
        }

        /* Bronze Gradient (3rd Place) */
        .podium-bronze {
            background: linear-gradient(135deg, #FFEDD5 0%, #EDC4B3 50%, #C89F9C 100%) !important;
            border: 1px solid #DDB892 !important;
            box-shadow: 
                0 12px 20px -8px rgba(221, 184, 146, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.5),
                inset 0 -6px 0 rgba(0, 0, 0, 0.1) !important;
        }

        .podium-rank-icon {
            font-size: 1.85rem;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
            animation: float 3s ease-in-out infinite;
        }
        #podium-1 .podium-rank-icon {
            font-size: 2.25rem;
            animation-delay: 0.2s;
        }
        #podium-3 .podium-rank-icon {
            font-size: 1.5rem;
            animation-delay: 0.4s;
        }

        .podium-score {
            font-size: 0.72rem;
            font-weight: 700;
            margin-top: 6px;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        /* Lobby Status Banner */
        .lobby-status-banner {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(181, 72, 42, 0.04);
            border: 1px solid rgba(181, 72, 42, 0.1);
            padding: 0.85rem 1.2rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--green);
            margin-bottom: 2rem;
            animation: softPulse 2s infinite ease-in-out;
        }
        .lobby-status-banner.async {
            background: rgba(26, 86, 219, 0.04);
            border-color: rgba(26, 86, 219, 0.1);
            color: #24402F;
        }
        .lobby-pulse-dot {
            width: 8px;
            height: 8px;
            background: var(--green);
            border-radius: 50%;
        }
        .lobby-status-banner.async .lobby-pulse-dot {
            background: #24402F;
        }

        @keyframes softPulse {
            0%, 100% { opacity: 0.9; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.01); }
        }

        /* Connected participants badge with live radar ping */
        .participants-badge {
            background: rgba(181, 72, 42, 0.04);
            border: 1px solid rgba(181, 72, 42, 0.08);
            padding: 0.85rem 1.25rem;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        .participants-badge:hover {
            background: rgba(181, 72, 42, 0.07);
            border-color: rgba(181, 72, 42, 0.15);
        }
        .radar-ping {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 14px;
            height: 14px;
        }
        .ping-dot {
            width: 8px;
            height: 8px;
            background: var(--pine);
            border-radius: 50%;
            z-index: 2;
        }
        .ping-wave {
            position: absolute;
            width: 100%;
            height: 100%;
            background: var(--pine);
            border-radius: 50%;
            opacity: 0.4;
            animation: radarRipple 1.6s infinite cubic-bezier(0, 0, 0.2, 1);
            z-index: 1;
        }
        @keyframes radarRipple {
            0% { transform: scale(0.6); opacity: 0.8; }
            100% { transform: scale(2.4); opacity: 0; }
        }

        /* High-contrast container for LaTeX formulas */
        .katex-display {
            background: rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(181, 72, 42, 0.06);
            padding: 1rem;
            border-radius: 8px;
            margin: 0.75rem 0;
            overflow-x: auto;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.01);
        }
        .katex {
            font-size: 1.05em;
            padding: 0 4px;
            border-radius: 4px;
            background: rgba(0, 0, 0, 0.015);
        }

        /* Live Leaderboard table styling */
        .leaderboard-scroll {
            max-height: 220px;
            overflow-y: auto;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.825rem;
            text-align: left;
        }
        .leaderboard-table th {
            padding: 10px 14px;
            font-weight: 700;
            color: var(--ink);
            text-transform: uppercase;
            font-size: 0.65rem;
            letter-spacing: 0.08em;
            background: rgba(181, 72, 42, 0.02);
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        }
        .leaderboard-table tr {
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
            transition: background-color 0.2s ease;
        }
        .leaderboard-table tr:last-child {
            border-bottom: none;
        }
        .leaderboard-table tr:hover {
            background-color: rgba(181, 72, 42, 0.03);
        }
        .leaderboard-table td {
            padding: 10px 14px;
            color: var(--ink);
        }
        .top-three-row {
            background-color: rgba(181, 72, 42, 0.02);
        }

        @media (max-width: 880px) {
            html, body { overflow-y: auto; height: auto; }
            .page { height: auto; padding: 1.5rem 1rem; }
            .card { flex-direction: column; height: auto; max-width: 540px; border-radius: 16px; }
            .card.quiz-mode { max-width: 540px; }
            .col-image { height: 260px; border-radius: 16px 16px 0 0; }
            .col-content { padding: 2.5rem 1.75rem; border-radius: 0 0 16px 16px; }
            .card.quiz-mode .col-content { padding: 2rem 1.5rem; border-radius: 16px; }
        }

        /* ═══ v2 exam room: calmer, flatter, readable under stress ═══ */
        .card::before { display: none; }
        #live-bar-question { display: none; }
        .card.quiz-mode { flex-direction: column; }
        .card.quiz-mode #quiz-live-bar { width: 100%; border-radius: 20px 20px 0 0; padding: .7rem 2rem; }
        .card.quiz-mode .col-content { border-radius: 0 0 20px 20px; }
        .option-btn.selected:disabled { opacity: 1; }
        .progress-bar-fill, .progress-fill { background: var(--clay) !important; }
        .card { box-shadow: var(--shadow-2); border: 1px solid var(--line); background: var(--card); }
        .card:hover .col-image img { transform: none; opacity: .75; }
        .col-content { background: var(--card); }
        #typewriter-line { display: none; }
        .illustration-caption { font-family: var(--font-body); letter-spacing: .06em; }
        .badge { background: var(--clay-soft); border-color: transparent; color: var(--clay-press); }
        .badge:hover { background: var(--clay-soft); }
        .input-field { background: var(--paper); border: 1px solid var(--line-2); border-radius: 10px; font-family: var(--font-body); }
        .input-field:focus { outline: none; border-color: var(--clay); box-shadow: 0 0 0 3px color-mix(in srgb, var(--clay) 22%, transparent); }
        .btn-primary { border-radius: 10px; text-transform: none; letter-spacing: 0; font-size: .95rem; font-weight: 600; }
        .auth-tabs-pill { background: var(--card); box-shadow: var(--shadow-1); }

        /* Live bar */
        #quiz-live-bar { background: var(--paper-2); color: var(--ink-2); border-bottom: 1px solid var(--line); letter-spacing: .06em; font-weight: 600; }
        .live-dot { background: var(--clay); animation: pulse 1.8s ease-in-out infinite; }
        .card.quiz-mode { max-width: 780px; }

        /* Two windows when a question has a picture: the question and options on one side, the picture on the other */
        #quiz-image-container.hidden { display: none !important; }
        .quiz-body { display: block; }
        .card.quiz-mode.has-image { max-width: 1100px; }
        .quiz-pane-image { display: flex; flex-direction: column; align-items: center; gap: .6rem; }
        .quiz-pane-image img { width: 100%; max-height: 62vh; object-fit: contain; border: 1px solid var(--line); border-radius: 12px; background: #fff; cursor: zoom-in; }
        .quiz-zoom-btn { background: none; border: 1px solid var(--line-2); border-radius: 10px; padding: .45rem .9rem; min-height: 44px; font: 600 .85rem var(--font-body); color: var(--ink); cursor: pointer; }
        .quiz-zoom-btn:focus-visible { outline: 2px solid var(--clay); outline-offset: 2px; }
        @media (min-width: 900px) {
            .quiz-body.has-image { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); gap: 1.75rem; align-items: start; }
            .quiz-body.has-image .quiz-pane-image { position: sticky; top: 1rem; }
        }
        @media (max-width: 899px) {
            .quiz-body.has-image .quiz-pane-image { order: -1; margin-bottom: 1.25rem; }
            .quiz-body.has-image { display: flex; flex-direction: column; }
            .quiz-pane-image img { max-height: 38vh; }
        }
        #quiz-zoom { position: fixed; inset: 0; z-index: 10000; background: rgba(0,0,0,.88); display: none; align-items: center; justify-content: center; padding: 1rem; cursor: zoom-out; }
        #quiz-zoom img { max-width: 100%; max-height: 100%; object-fit: contain; background: #fff; border-radius: 8px; }

        .card.quiz-mode .col-content { padding: 3rem 3.5rem 2.25rem; }

        /* Question + answers */
        #quiz-question-number { border: 0 !important; padding: 0 !important; border-radius: 0 !important; font-size: .78rem !important; color: var(--clay) !important; letter-spacing: .12em !important; font-variant-numeric: tabular-nums; }
        #quiz-live-participants-container { background: transparent !important; padding: 0 !important; color: var(--ink-3) !important; font-weight: 500 !important; }
        #quiz-question-text { font-family: var(--font-display) !important; font-weight: 450 !important; font-size: 1.65rem !important; line-height: 1.35 !important; letter-spacing: -.01em; color: var(--ink) !important; text-wrap: pretty; }
        #quiz-timer-text { font-family: var(--font-body) !important; font-variant-numeric: tabular-nums; }
        #quiz-svg-timer-circle { stroke: var(--pine); }
        #quiz-svg-timer-container circle:first-child { stroke: var(--paper-2); }
        .option-btn { border: 1.5px solid var(--line-2); background: var(--paper); backdrop-filter: none; -webkit-backdrop-filter: none; border-radius: 12px; font-weight: 500; font-size: 1.02rem; line-height: 1.45; box-shadow: none; padding: 1rem 1.15rem; transition: border-color .15s, background-color .15s; min-height: 56px; }
        .option-btn:hover:not(:disabled) { transform: none; border-color: var(--ink-3); background: var(--paper); box-shadow: none; }
        .option-btn:focus-visible { outline: 2px solid var(--clay); outline-offset: 2px; }
        .option-btn.selected { transform: none; border-color: var(--clay); background: var(--clay-soft); color: var(--ink); font-weight: 600; box-shadow: none; }
        .option-btn.selected span.opt-label { background: var(--clay); border-color: var(--clay); color: #fff; }
        .option-btn:disabled { opacity: .6; }
        .opt-label { border-radius: 8px; background: var(--paper-2); border: 0; color: var(--ink-2); width: 28px; height: 28px; flex: none; box-shadow: none; }
        .option-btn:hover:not(:disabled) .opt-label { border: 0; color: var(--ink); }
        #quiz-submit-status { background: color-mix(in srgb, var(--ok) 10%, transparent) !important; border: 1px solid color-mix(in srgb, var(--ok) 35%, transparent) !important; color: var(--ok) !important; border-radius: 10px; font-weight: 600; }
        #written-answer-input { border-radius: 10px !important; }

        /* Waiting room + countdown */
        .countdown-number, #final-countdown-num { font-family: var(--font-display) !important; font-weight: 400 !important; color: var(--clay) !important; letter-spacing: -.03em; }
        .lobby-status-banner { background: var(--clay-soft); border-color: transparent; color: var(--clay-press); border-radius: 999px; }
        .lobby-status-banner.async { background: var(--pine-soft); color: var(--pine); }
        .participants-badge { background: var(--paper); border: 1px solid var(--line); border-radius: 12px; }

        /* Results: flat podium, no medals */
        #podium-wrapper { height: 170px; }
        .podium-box { border-radius: 8px 8px 0 0 !important; box-shadow: none !important; }
        .podium-box::before { display: none; }
        .podium-gold   { background: var(--ochre) !important; border: 0 !important; }
        .podium-silver { background: var(--line-2) !important; border: 0 !important; }
        .podium-bronze { background: var(--clay-soft) !important; border: 0 !important; }
        .podium-rank-icon { color: var(--ink) !important; font-family: var(--font-display); font-weight: 500; }
        .podium-name, .podium-score { color: var(--ink) !important; font-variant-numeric: tabular-nums; }
        #live-leaderboard-container { background: var(--card) !important; border: 1px solid var(--line) !important; border-radius: 12px !important; box-shadow: none !important; }
        .leaderboard-table th { background: transparent; color: var(--ink-3); border-bottom: 1px solid var(--line); }
        .leaderboard-table tr { border-bottom: 1px solid var(--line); }
        .leaderboard-table tr:hover, .top-three-row { background: var(--paper); }
        .leaderboard-table td { font-variant-numeric: tabular-nums; }
        #finished-view h2 { font-weight: 450 !important; font-size: 2.2rem !important; }

        @media (max-width: 880px) {
            .card.quiz-mode .col-content { padding: 1.75rem 1.25rem 1.5rem; }
            #quiz-question-text { font-size: 1.35rem !important; }
        }
        @media (prefers-reduced-motion: reduce) { .live-dot, .lobby-pulse-dot, .ping-wave { animation: none !important; } }
    </style>
    <style>
        /* Guest entry form: email check + tick */
        .guest-email-wrap { position: relative; }
        .guest-email-wrap .input-field { padding-right: 2.6rem; }
        .guest-tick { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: #2E7D4F; color: #fff; flex: none; }
        .guest-email-wrap .guest-tick { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); }
        .guest-tick[hidden] { display: none; }
        .guest-hint { font-size: .72rem; line-height: 1.5; color: var(--faint, #6F695C); margin-top: .4rem; min-height: 1.1em; }
        .guest-hint.is-ok { color: #2E7D4F; font-weight: 600; }
        .guest-hint.is-err { color: #B3261E; font-weight: 600; }
        .input-field.is-bad { border-color: #B3261E !important; }
        .input-field.is-locked { background: #F4F1EA !important; cursor: not-allowed; }

        /* Lobby note for participants without a StudyVibe account: friendly, red, scrolling sideways */
        .guest-marquee { margin-top: 1.25rem; overflow: hidden; border: 1px solid #E8B7AB; background: #FDF1EE; border-radius: 10px; padding: .65rem 0; position: relative; }
        .guest-marquee-track { display: flex; width: max-content; animation: guest-scroll 48s linear infinite; }
        .guest-marquee:hover .guest-marquee-track, .guest-marquee:focus-within .guest-marquee-track { animation-play-state: paused; }
        .guest-marquee-item { flex: none; padding: 0 3rem; white-space: nowrap; font-size: .88rem; font-weight: 600; color: #B3261E; }
        .guest-marquee-item a { color: #B3261E; text-decoration: underline; text-underline-offset: 3px; }
        @keyframes guest-scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
        @media (prefers-reduced-motion: reduce) {
            .guest-marquee-track { animation: none; width: auto; }
            .guest-marquee-item { white-space: normal; padding: 0 1rem; }
            .guest-marquee-item[aria-hidden="true"] { display: none; }
        }
        /* Visible lobby countdown: horizontal pill in the top bar */
        .lobby-countdown { display: inline-flex; flex-direction: row; align-items: center; gap: .55rem; padding: .3rem .8rem; border: 1px solid var(--clay, #B5482A); border-radius: 999px; background: var(--clay-soft, #F1DDD2); white-space: nowrap; }
        .lobby-countdown.hidden { display: none; }
        .lobby-countdown-label { font-size: .62rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--clay-press, #96391E); }
        .lobby-countdown-time { font-family: var(--font-display); font-size: 1.15rem; line-height: 1; font-weight: 600; color: var(--clay, #B5482A); font-variant-numeric: tabular-nums; }
        .lobby-countdown.is-soon .lobby-countdown-time { animation: lobby-beat 1s ease-in-out infinite; }
        @keyframes lobby-beat { 50% { transform: scale(1.05); } }
        @media (prefers-reduced-motion: reduce) { .lobby-countdown.is-soon .lobby-countdown-time { animation: none; } }
    </style>
</head>
<body>

    <!-- Decorative background elements -->
    <div class="floating-particles" id="floating-particles" aria-hidden="true"></div>

    <main class="page">
        <div class="card" id="main-card">

            <!-- Sticky top bar for quiz mode information -->
            <div id="quiz-live-bar">
                <span><span class="live-dot"></span>QUIZ EN COURS</span>
                <span id="live-bar-question">Question -- / --</span>
            </div>
            
            <!-- Graphic Illustration Column -->
            <div class="col-image" id="col-image-panel">
                <img id="live-illustration" src="/assets/img/live-lobby-illustration.png" alt="Illustration Téléévaluation">
                <div class="illustration-caption" id="live-caption">Dans la salle de Téléévaluation StudyVibe…</div>
            </div>

            <!-- Interactive content column -->
            <div class="col-content">
                
                <!-- Watermark Clock -->
                <div class="timer-watermark" id="lobby-timer-watermark">--:--</div>
                
                <div id="col-top-bar">
                    <!-- Brand Identity -->
                    <a href="/" class="brand">
                        <?= Brand::logo('md', true) ?>
                        <span class="brand-name"><span style="font-size:0.625rem; font-weight:700; letter-spacing:.08em; color:var(--clay); border:1px solid var(--clay); padding: 1px 7px; border-radius:10px; margin-left:4px;">LIVE</span></span>
                    </a>

                    <!-- Live Session status badges -->
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <?php if (!$isAsync): ?>
                        <div class="lobby-countdown hidden" id="lobby-countdown" role="timer" aria-live="off">
                            <span class="lobby-countdown-label">Début dans</span>
                            <span class="lobby-countdown-time" id="lobby-countdown-time">--:--</span>
                        </div>
                        <?php endif; ?>
                        <span class="badge" id="state-badge">
                            <svg width="6" height="6" viewBox="0 0 8 8" fill="none" style="margin-right: 2px;">
                                <circle cx="4" cy="4" r="3" fill="#B5482A"/>
                            </svg>
                            Téléévaluation
                        </span>
                        <?php if (isset($registration) && $registration): ?>
                            <a href="live-session.php?code=<?= urlencode($code) ?>&action=disconnect" class="badge" style="background-color: var(--clay-soft); color: var(--clay-press); text-decoration: none; border: 1px solid var(--clay); font-weight: 600; cursor: pointer; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='var(--clay-soft)'" onmouseout="this.style.backgroundColor='var(--clay-soft)'">
                                Quitter ✕
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dynamic components switchboard -->
                <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: center; margin: 1.5rem 0;">
                    
                    <?php if ($error): ?>
                        <!-- Error Message View -->
                        <div class="text-center py-6">
                            <h2 class="story-title" style="font-family:var(--font-display); letter-spacing:-.01em; font-size:1.8rem; font-weight:500; margin-bottom: 1rem;">
                                Séance indisponible
                            </h2>
                            <p style="font-size:0.85rem; color:var(--muted); line-height:1.6; margin-bottom: 2rem;">
                                <?= htmlspecialchars($error) ?>
                            </p>
                            <a href="/" class="btn-primary">Retour à l'accueil</a>
                        </div>

                    <?php elseif (!$registration): ?>
                        <!-- Form Step: Auth & Registration Gateway -->
                        <div>
                            <?php if (isset($isReset) && $isReset): ?>
                                <!-- Excluded or Reset notification banner -->
                                <div style="display: flex; flex-direction: column; gap: 1.5rem; align-items: center; justify-content: center; text-align: center; margin-bottom: 2rem;">
                                    <div style="max-width: 280px; width: 100%;">
                                        <img src="/assets/img/reset_eval_illustration.png" alt="Session Reset" style="width: 100%; height: auto; border-radius: 8px; border: 1px solid var(--line); box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                                    </div>
                                    <div style="max-width: 450px;">
                                        <span style="font-size:0.65rem; font-weight:700; color:var(--clay-press); background:var(--clay-soft); border: 1px solid var(--clay); padding: 4px 10px; border-radius:12px; text-transform:uppercase; letter-spacing: 0.05em;">Séance Réinitialisée</span>
                                        <h2 style="font-family:var(--font-display); letter-spacing:-.01em; font-size:1.4rem; font-weight:600; margin: 1rem 0 0.5rem 0; line-height:1.2; color:var(--ink);">
                                            L'examen a été réinitialisé
                                        </h2>
                                        <p style="font-size:0.8rem; color:var(--muted); line-height:1.6;">
                                            L'enseignant a réinitialisé la séance d'évaluation afin d'accueillir les nouveaux participants. Veuillez remplir à nouveau le formulaire pour continuer.
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <h2 style="font-family:var(--font-display); letter-spacing:-.01em; font-size:1.6rem; font-weight:500; margin-bottom: 0.5rem; line-height:1.2;">
                                <?= htmlspecialchars($session['title']) ?>
                            </h2>
                            <p style="font-size:0.8rem; color:var(--muted); margin-bottom: 0.75rem;">
                                Cours : <strong style="color:var(--ink); font-weight:500;"><?= htmlspecialchars($session['course_title']) ?></strong>
                            </p>
                            
                            <?php if ($isAsync): ?>
                                <!-- Free Study Mode (Asynchronous) -->
                                <div style="background-color: rgba(36,64,47,0.04); border: 1px dashed rgba(36,64,47,0.3); border-radius: 6px; padding: 12px 14px; display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.5rem; position: relative;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <svg width="16" height="16" fill="none" stroke="#24402F" viewBox="0 0 24 24" style="flex-shrink:0;">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span style="font-size: 0.8rem; font-weight: 600; color: #24402F;">
                                                Devoir Libre disponible jusqu'au : <strong style="text-transform: capitalize;"><?= htmlspecialchars(getFormattedEvalStartTime($session['async_deadline'] ?? '')) ?></strong>
                                            </span>
                                        </div>
                                        <button type="button" onclick="toggleAsyncExplanation()" style="background: none; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; background-color: rgba(36,64,47,0.1); color: #24402F; font-size: 0.75rem; font-weight: 700; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(36,64,47,0.2)'" onmouseout="this.style.backgroundColor='rgba(36,64,47,0.1)'" title="En savoir plus sur le Devoir Libre">
                                            ?
                                        </button>
                                    </div>
                                    
                                    <div id="async-explanation-box" style="display: none; margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(36,64,47,0.15); font-size: 0.75rem; color: #24402F; line-height: 1.5;">
                                        <strong>Qu'est-ce qu'un Devoir Libre ?</strong><br>
                                        Il s'agit d'une évaluation asynchrone autonome. Contrairement aux sessions en direct animées en temps réel par l'enseignant, vous pouvez réaliser cette évaluation à votre rythme, à n'importe quel moment avant la date limite indiquée.
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Standard Live Mode start time -->
                                <div style="background-color: rgba(181,72,42,0.04); border: 1px dashed rgba(181,72,42,0.25); border-radius: 6px; padding: 10px 14px; display: flex; align-items: center; gap: 8px; margin-bottom: 1.5rem;">
                                    <svg width="16" height="16" fill="none" stroke="var(--green)" viewBox="0 0 24 24" style="flex-shrink:0;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span style="font-size: 0.8rem; font-weight: 500; color: var(--green);">
                                        Début programmé : <strong style="text-transform: capitalize;"><?= htmlspecialchars(getFormattedEvalStartTime($session['start_time'])) ?></strong>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($regError)): ?>
                                <div style="margin-bottom: 1.5rem; padding: 0.8rem 1rem; background-color: var(--clay-soft); border: 1px solid var(--clay-soft); color: var(--clay-press); font-size: 0.8rem;">
                                    <?= htmlspecialchars($regError) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (isLoggedIn()): ?>
                                <!-- Logged-in verification state -->
                                <div style="margin-bottom: 1.5rem; font-size: 0.75rem; color: var(--muted); background-color: rgba(181,72,42,0.03); border: 1px solid rgba(181,72,42,0.1); padding: 8px 12px; border-radius: 6px;">
                                    Connecté en tant que <strong style="color: var(--ink);"><?= htmlspecialchars($currentUserName) ?></strong> (<?= htmlspecialchars($currentUserEmail) ?>).
                                    <a href="/live-session.php?code=<?= urlencode($code) ?>&action=logout" style="color: var(--clay-press); text-decoration: underline; margin-left: 0.5rem; font-weight: 600;">
                                        Changer de compte
                                    </a>
                                </div>
                                
                                <form method="POST" class="space-y-4">
                                    <input type="hidden" name="register_live" value="1">
                                    
                                    <div>
                                        <label style="display:block; font-size:0.65rem; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); margin-bottom:0.5rem;">Nom Complet</label>
                                        <input type="text" name="name" required value="<?= htmlspecialchars($currentUserName) ?>" readonly style="background-color: #F9FAFB; cursor: not-allowed;" class="input-field">
                                    </div>

                                    <div>
                                        <label style="display:block; font-size:0.65rem; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); margin-bottom:0.5rem;">Adresse E-mail</label>
                                        <input type="email" name="email" required value="<?= htmlspecialchars($currentUserEmail) ?>" readonly style="background-color: #F9FAFB; cursor: not-allowed;" class="input-field">
                                        <p style="font-size:0.68rem; color:var(--faint); mt-1">Vos résultats et votre note officielle y seront envoyés.</p>
                                    </div>

                                    <div style="padding-top: 1rem;">
                                        <button type="submit" class="btn-primary">Rejoindre la séance</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <!-- Guest entry: email (checked in the background) + name, no account required -->
                                <form method="POST" id="guest-entry-form" class="space-y-4" novalidate>
                                    <input type="hidden" name="register_live" value="1">

                                    <div>
                                        <label for="guest-email" style="display:block; font-size:0.65rem; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); margin-bottom:0.5rem;">Adresse E-mail</label>
                                        <div class="guest-email-wrap">
                                            <input type="email" name="email" id="guest-email" required autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false" placeholder="Ex: jean.dupont@gmail.com" class="input-field" value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>">
                                            <span class="guest-tick" id="guest-email-tick" hidden title="Compte StudyVibe reconnu">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                                            </span>
                                        </div>
                                        <p id="guest-email-hint" class="guest-hint" role="status" aria-live="polite">Adresses acceptées : gmail.com, icloud.com ou facsciences-uy1.cm.</p>
                                    </div>

                                    <div>
                                        <label for="guest-name" style="display:flex; align-items:center; gap:0.5rem; font-size:0.65rem; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); margin-bottom:0.5rem;">
                                            Nom Complet
                                            <span class="guest-tick" id="guest-name-tick" hidden title="Compte StudyVibe reconnu">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                                            </span>
                                        </label>
                                        <input type="text" name="name" id="guest-name" required maxlength="120" autocomplete="name" placeholder="Ex: Jean Dupont" class="input-field" value="<?= htmlspecialchars((string)($_POST['name'] ?? '')) ?>">
                                    </div>

                                    <div style="padding-top: 1rem;">
                                        <button type="submit" id="guest-submit" class="btn-primary"><?= $isAsync ? 'Commencer l’évaluation' : 'Entrer dans la salle' ?></button>
                                    </div>
                                </form>

                                <script>
                                (function () {
                                    const ALLOWED = <?= json_encode(LiveGuest::ALLOWED_DOMAINS) ?>;
                                    const form = document.getElementById('guest-entry-form');
                                    const mail = document.getElementById('guest-email');
                                    const nameIn = document.getElementById('guest-name');
                                    const tick = document.getElementById('guest-email-tick');
                                    const nameTick = document.getElementById('guest-name-tick');
                                    const hint = document.getElementById('guest-email-hint');
                                    const baseHint = hint.textContent;
                                    let timer = null, seq = 0, nameLocked = false, ok = false;

                                    function setHint(text, kind) {
                                        hint.textContent = text;
                                        hint.className = 'guest-hint' + (kind ? ' is-' + kind : '');
                                    }
                                    function unlockName() {
                                        if (nameLocked) { nameIn.readOnly = false; nameIn.classList.remove('is-locked'); nameLocked = false; }
                                        tick.hidden = true; nameTick.hidden = true;
                                    }
                                    function localCheck(v) {
                                        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return 'format';
                                        const dom = v.slice(v.lastIndexOf('@') + 1);
                                        return ALLOWED.indexOf(dom) === -1 ? 'domain' : null;
                                    }
                                    function verify() {
                                        const v = mail.value.trim().toLowerCase();
                                        const mine = ++seq;
                                        unlockName();
                                        ok = false;
                                        mail.classList.remove('is-bad');
                                        if (v === '') { setHint(baseHint); return; }
                                        const bad = localCheck(v);
                                        if (bad === 'format') { setHint('Poursuivez la saisie de votre adresse e-mail…'); return; }
                                        if (bad === 'domain') { mail.classList.add('is-bad'); setHint('Seules les adresses se terminant par gmail.com, icloud.com ou facsciences-uy1.cm sont acceptées.', 'err'); return; }
                                        ok = true;
                                        setHint('Vérification en cours…');
                                        fetch('/api/live-check-email.php?email=' + encodeURIComponent(v), { cache: 'no-store', credentials: 'same-origin' })
                                            .then(r => r.json())
                                            .then(d => {
                                                if (mine !== seq) return;
                                                if (!d.success) { setHint(baseHint); return; }
                                                if (d.registered) {
                                                    tick.hidden = false; nameTick.hidden = false;
                                                    nameIn.value = d.name; nameIn.readOnly = true; nameIn.classList.add('is-locked'); nameLocked = true;
                                                    setHint('Compte StudyVibe reconnu. Votre nom est déjà renseigné.', 'ok');
                                                } else {
                                                    setHint('Parfait. Indiquez simplement votre nom ci-dessous.');
                                                }
                                            })
                                            .catch(() => { if (mine === seq) setHint(baseHint); });
                                    }
                                    mail.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(verify, 350); });
                                    mail.addEventListener('blur', () => { clearTimeout(timer); verify(); });
                                    form.addEventListener('submit', (e) => {
                                        const v = mail.value.trim().toLowerCase();
                                        const bad = localCheck(v);
                                        if (bad) {
                                            e.preventDefault();
                                            mail.classList.add('is-bad'); mail.focus();
                                            setHint(bad === 'domain' ? 'Seules les adresses se terminant par gmail.com, icloud.com ou facsciences-uy1.cm sont acceptées.' : 'Veuillez saisir une adresse e-mail valide.', 'err');
                                            return;
                                        }
                                        if (nameIn.value.trim().length < 2) {
                                            e.preventDefault(); nameIn.focus();
                                            setHint('Veuillez saisir votre nom complet pour continuer.', 'err');
                                            return;
                                        }
                                        document.getElementById('guest-submit').disabled = true;
                                    });
                                    if (mail.value.trim() !== '') verify();
                                })();
                                </script>
                            <?php endif; ?>
                        </div>

                    <?php else: ?>
                        <!-- Real-time Interactive Portal views -->
                        <div id="live-app" style="position: relative;">
                            
                            <!-- Overlay in case the evaluation gets paused by the teacher/host -->
                            <div id="pause-overlay" class="hidden" style="position: absolute; inset: 0; background: rgba(255,255,255,0.96); z-index: 100; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 2rem;">
                                <div style="width: 64px; height: 64px; border-radius: 50%; background: #FEF08A; border: 2px solid #FACC15; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; animation: pulse 2s infinite;">
                                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 2rem; height: 2rem;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <h3 style="font-family:var(--font-display); letter-spacing:-.01em; font-size:1.6rem; font-weight:600; color:var(--ink); margin-bottom:0.75rem;">
                                    Évaluation suspendue
                                </h3>
                                <p style="font-size:0.85rem; color:var(--muted); max-width: 35ch; line-height: 1.6;">
                                    L'enseignant a mis l'examen en pause. Le temps est arrêté et le quiz reprendra sous peu.
                                </p>
                            </div>
                            
                            <!-- Polling/Initial Loading feedback spinner -->
                            <div class="text-center py-8" id="loading-state">
                                <div style="border: 2px solid rgba(181,72,42,0.1); border-top-color: var(--green); border-radius: 50%; width: 28px; height: 28px; animation: spin 1s linear infinite; margin: 0 auto 1rem auto;"></div>
                                <p style="font-size: 0.85rem; color: var(--muted);">Synchronisation avec la séance en cours...</p>
                            </div>

                            <!-- View: Waiting Lobby -->
                            <div id="lobby-view" class="hidden">
                                <div class="lobby-status-banner <?= $isAsync ? 'async' : '' ?>">
                                    <span class="lobby-pulse-dot"></span>
                                    <span><?= $isAsync ? "Examen disponible en Devoir Libre" : "En attente du signal de départ" ?></span>
                                </div>

                                <h2 style="font-family:var(--font-display); letter-spacing:-.01em; font-size: 2rem; font-weight: 400; line-height: 1.25; color: var(--ink); margin-bottom: 0.75rem;">
                                    La salle ouvre,<br><em style="color:var(--clay)">l’examen suit.</em>
                                </h2>
                                <?php if ($isAsync): ?>
                                    <p style="font-size: 0.85rem; font-weight: 300; line-height: 1.7; color: var(--muted); margin-bottom: 1rem; max-width: 44ch;">
                                        L'évaluation <strong><?= htmlspecialchars($session['title']) ?></strong> (cours : <em><?= htmlspecialchars($session['course_title']) ?></em>) est disponible en Devoir Libre. Vous pouvez la commencer à tout moment.
                                    </p>

                                    <div style="background-color: rgba(36,64,47,0.04); border: 1px dashed rgba(36,64,47,0.3); border-radius: 6px; padding: 12px 14px; display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.5rem; position: relative;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <svg width="16" height="16" fill="none" stroke="#24402F" viewBox="0 0 24 24" style="flex-shrink:0;">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                                <span style="font-size: 0.85rem; font-weight: 600; color: #24402F;">
                                                    Disponible en Devoir Libre jusqu'au : <strong style="text-transform: capitalize;"><?= htmlspecialchars(getFormattedEvalStartTime($session['async_deadline'] ?? '')) ?></strong>
                                                </span>
                                            </div>
                                            <button type="button" onclick="toggleAsyncExplanationLobby()" style="background: none; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; background-color: rgba(36,64,47,0.1); color: #24402F; font-size: 0.75rem; font-weight: 700; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(36,64,47,0.2)'" onmouseout="this.style.backgroundColor='rgba(36,64,47,0.1)'" title="En savoir plus sur le Devoir Libre">
                                                ?
                                            </button>
                                        </div>
                                        
                                        <div id="async-explanation-box-lobby" style="display: none; margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(36,64,47,0.15); font-size: 0.75rem; color: #24402F; line-height: 1.5;">
                                            <strong>Qu'est-ce qu'un Devoir Libre ?</strong><br>
                                            Il s'agit d'une évaluation asynchrone autonome. Contrairement aux sessions en direct animées en temps réel par l'enseignant, vous pouvez réaliser cette évaluation à votre rythme, à n'importe quel moment avant la date limite indiquée.
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p style="font-size: 0.85rem; font-weight: 300; line-height: 1.7; color: var(--muted); margin-bottom: 1rem; max-width: 44ch;">
                                        L'évaluation <strong><?= htmlspecialchars($session['title']) ?></strong> (cours : <em><?= htmlspecialchars($session['course_title']) ?></em>) débutera automatiquement à l'heure programmée. Restez sur cette page, la séance démarre toute seule.
                                    </p>

                                    <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                                        <svg width="18" height="18" fill="none" stroke="var(--green)" viewBox="0 0 24 24" style="flex-shrink:0;">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span style="font-size: 0.85rem; font-weight: 500; color: var(--green);">
                                            Début de l'évaluation programmé : <strong style="text-transform: capitalize;"><?= htmlspecialchars(getFormattedEvalStartTime($session['start_time'])) ?></strong>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <!-- Connected participants metrics indicator -->
                                <div class="participants-badge">
                                    <div class="radar-ping">
                                        <span class="ping-dot"></span>
                                        <span class="ping-wave"></span>
                                    </div>
                                    <span style="font-size: 0.85rem; font-weight: 500; color: var(--ink);">
                                        Dans la salle : <strong class="num" id="lobby-registered-count">0</strong>
                                    </span>
                                </div>

                                <?php if ($isGuestParticipant): ?>
                                    <!-- Friendly note for participants who are not registered StudyVibe users -->
                                    <div class="guest-marquee" role="note" aria-label="Information pour les invités">
                                        <div class="guest-marquee-track">
                                            <span class="guest-marquee-item">Bienvenue dans la salle ! Vous participez en tant qu’invité(e) avec votre adresse e-mail, et l’évaluation se déroule pour vous exactement comme pour les autres. Pour profiter de toutes les fonctionnalités de StudyVibe (suivi de vos résultats, relevé de notes, certificats, cours et badges), nous vous invitons à créer votre compte gratuit sur la plateforme quand vous voudrez. Bonne chance et bonne évaluation ! <a href="/index.php" target="_blank" rel="noopener">Créer mon compte</a></span>
                                            <span class="guest-marquee-item" aria-hidden="true">Bienvenue dans la salle ! Vous participez en tant qu’invité(e) avec votre adresse e-mail, et l’évaluation se déroule pour vous exactement comme pour les autres. Pour profiter de toutes les fonctionnalités de StudyVibe (suivi de vos résultats, relevé de notes, certificats, cours et badges), nous vous invitons à créer votre compte gratuit sur la plateforme quand vous voudrez. Bonne chance et bonne évaluation ! <a href="/index.php" target="_blank" rel="noopener" tabindex="-1">Créer mon compte</a></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- View: Launch Countdown (5s before start) -->
                            <div id="countdown-view" class="hidden text-center py-6">
                                <span style="font-size:0.65rem; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); display:block; margin-bottom:1rem;">Lancement imminent</span>
                                <div style="font-size: 6rem; font-weight: 700; color: var(--green); line-height: 1; font-family:var(--font-display); font-variant-numeric:tabular-nums;" id="final-countdown-num">5</div>
                                <p style="font-size:0.9rem; color:var(--ink); font-weight:500; margin-top: 1.5rem;">
                                    Respirez. Ça commence dans un instant.
                                </p>
                            </div>

                             <!-- View: Active Quiz Question Panel -->
                             <div id="quiz-view" class="hidden" style="user-select: none; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none;">

                                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; border-bottom:1px solid rgba(0,0,0,0.06); padding-bottom:0.85rem; gap:12px;">
                                    <div style="display:flex; flex-direction:column; gap:6px; flex-grow: 1;">
                                        <span style="font-size:0.65rem; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:var(--green); border:1px solid var(--green); padding:3px 10px; width: fit-content; border-radius: 2px;" id="quiz-question-number">Question -- / --</span>
                                        
                                        <!-- Active participants progress counter -->
                                        <span id="quiz-live-participants-container" style="font-size:0.75rem; font-weight:500; color:var(--ink); display:flex; align-items:center; gap:6px; background: rgba(0,0,0,0.04); padding: 4px 10px; border-radius: 9999px; width: fit-content;">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="stroke-width:2;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                            <span>Restants : <strong id="quiz-live-remaining-count">--</strong></span>
                                        </span>
                                    </div>

                                    <!-- Circular countdown container -->
                                    <div style="position: relative; width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: transform 0.2s ease;" id="quiz-svg-timer-container">
                                        <svg width="56" height="56" style="transform: rotate(-90deg); filter: drop-shadow(0 2px 4px rgba(0,0,0,0.02));">
                                            <circle cx="28" cy="28" r="23" stroke="rgba(0,0,0,0.05)" stroke-width="3.5" fill="transparent" />
                                            <circle id="quiz-svg-timer-circle" cx="28" cy="28" r="23" stroke="var(--green)" stroke-width="3.5" fill="transparent" 
                                                    stroke-dasharray="144.51" stroke-dashoffset="0" stroke-linecap="round" style="transition: stroke-dashoffset 0.3s linear, stroke 0.3s ease;" />
                                        </svg>
                                        <span id="quiz-timer-text" style="position: absolute; font-family:var(--font-body); font-size: 1rem; font-weight: 700; color: var(--ink); tabular-nums: true;">--</span>
                                    </div>
                                </div>

                                <div id="quiz-body" class="quiz-body">
                                <div class="quiz-pane quiz-pane-main">
                                <!-- Question statement text -->
                                <h3 id="quiz-question-text" style="font-family:var(--font-display); letter-spacing:-.01em; font-size:1.3rem; font-weight:600; color:var(--ink); line-height:1.45; margin-bottom:1.75rem;">--</h3>

                                <!-- MCQ Options layout -->
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

                                <!-- Written / Numeric input response layout -->
                                <div id="quiz-written-container" class="hidden" style="display:flex; flex-direction:column; gap:0.85rem;">
                                    <div style="position: relative;">
                                        <input type="text" id="written-answer-input" placeholder="Saisissez votre réponse (ex: 2.5, x^2, ...)" class="input-field" style="font-size: 1rem; padding: 0.95rem 1.2rem; border-radius: 4px; border: 1px solid rgba(181, 72, 42, 0.2);">
                                    </div>
                                    <button onclick="submitWrittenAnswer()" id="btn-submit-written" class="btn-primary" style="background-color: var(--green); border-color: var(--green); font-weight: 700; border-radius: 4px; padding: 0.95rem;">
                                        Soumettre ma réponse
                                    </button>
                                </div>

                                <!-- Submit feedback status overlay -->
                                <div id="quiz-submit-status" class="hidden" style="margin-top:1.25rem; padding:0.9rem 1rem; background:rgba(62,107,71,0.08); border:1px solid rgba(62,107,71,0.3); color:#3E6B47; font-size:0.82rem; text-align:center; font-weight:600; border-radius:3px;">
                                    ✓ Réponse enregistrée — en attente de la prochaine question...
                                </div>
                                </div>
                                <!-- Picture window: only shown when the question has a picture -->
                                <aside id="quiz-image-container" class="quiz-pane quiz-pane-image hidden" aria-label="Illustration de la question">
                                    <img src="" id="quiz-image" alt="Illustration de la question" onclick="openQuizImageZoom()" title="Cliquer pour agrandir">
                                    <button type="button" class="quiz-zoom-btn" onclick="openQuizImageZoom()">Agrandir l’image</button>
                                </aside>
                                </div>

                                <div style="margin-top:1.5rem; padding-top:0.85rem; border-top:1px solid rgba(0,0,0,0.06); display:flex; justify-content:space-between; align-items:center; font-size:0.68rem; color:var(--muted);">
                                    <span><?= htmlspecialchars($registration['name']) ?></span>
                                </div>
                            </div>

                            <!-- View: Completed session view -->
                            <div id="finished-view" class="hidden text-center py-4">
                                <h2 style="font-family:var(--font-display); letter-spacing:-.01em; font-size:1.8rem; font-weight:500; margin-bottom: 1rem; line-height:1.2;">
                                    Évaluation terminée !
                                </h2>
                                <p style="font-size:0.85rem; color:var(--muted); line-height:1.6; margin-bottom: 1.5rem;">
                                    Merci pour votre participation, <strong><?= htmlspecialchars($registration['name']) ?></strong>.
                                </p>
                                
                                <?php if (!empty($resultsUrl)): ?>
                                <div style="margin: 1.5rem 0;">
                                    <a href="<?= htmlspecialchars($resultsUrl) ?>" class="btn-primary" style="background-color: var(--green); border-color: var(--green); text-decoration: none; display: inline-block; width: auto; min-width: 250px; font-weight: 700; border-radius: 4px; padding: 0.9rem 1.8rem;">
                                        Consulter mes résultats & explications en ligne
                                    </a>
                                </div>
                                <?php endif; ?>
                                
                                <div style="background: rgba(181,72,42,0.04); border: 1px solid rgba(181,72,42,0.1); padding: 1.25rem; font-size: 0.8rem; line-height: 1.6; color: var(--ink); text-align: left; margin-bottom: 1.5rem; border-radius: 1px;">
                                    Vos réponses ont été soumises avec succès. Un e-mail contenant votre score, vos statistiques individuelles et le lien sécurisé vers votre rapport de correction a été envoyé à :
                                    <strong style="display:block; font-size:0.9rem; color:var(--green); margin-top:0.4rem;"><?= htmlspecialchars($registration['email']) ?></strong>
                                    <span style="display:block; margin-top:0.5rem; font-size:0.75rem; color:var(--muted);">
                                        Vous pouvez consulter votre boîte de réception ou accéder à vos résultats directement ci-dessus.
                                    </span>
                                </div>

                                <!-- Podium & Leaderboard visual graphics -->
                                <div id="live-leaderboard-container" class="hidden" style="margin: 2rem 0; padding: 1.5rem; background: var(--card); border: 1px solid var(--line); border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); text-align: left;">
                                    <h3 style="font-family:var(--font-display); letter-spacing:-.01em; font-size:1.15rem; font-weight:600; color:var(--ink); margin-bottom:1.5rem; display:flex; align-items:center; gap:8px;">
                                        <span>Tableau d'Honneur</span> (Classement Live)
                                    </h3>
                                    
                                    <div id="podium-wrapper">
                                        <!-- Place 2 (Left) -->
                                        <div id="podium-2" class="podium-bar">
                                            <div id="podium-name-2" class="podium-name" style="color:var(--muted);">--</div>
                                            <div class="podium-box podium-silver" style="height:60px;">
                                                <span class="podium-rank-icon">#2</span>
                                            </div>
                                            <div id="podium-score-2" class="podium-score" style="color:var(--muted);">--%</div>
                                        </div>
                                        
                                        <!-- Place 1 (Center) -->
                                        <div id="podium-1" class="podium-bar">
                                            <div id="podium-name-1" class="podium-name" style="color:var(--ink);">--</div>
                                            <div class="podium-box podium-gold" style="height:90px;">
                                                <span class="podium-rank-icon">#1</span>
                                            </div>
                                            <div id="podium-score-1" class="podium-score" style="color:var(--ink);">--%</div>
                                        </div>
                                        
                                        <!-- Place 3 (Right) -->
                                        <div id="podium-3" class="podium-bar">
                                            <div id="podium-name-3" class="podium-name" style="color:var(--ink);">--</div>
                                            <div class="podium-box podium-bronze" style="height:45px;">
                                                <span class="podium-rank-icon">#3</span>
                                            </div>
                                            <div id="podium-score-3" class="podium-score" style="color:var(--ink);">--%</div>
                                        </div>
                                    </div>
                                    
                                    <div class="leaderboard-scroll">
                                        <table class="leaderboard-table">
                                            <thead>
                                                <tr>
                                                    <th>Rang</th>
                                                    <th>Participant</th>
                                                    <th style="text-align: right;">Score Final</th>
                                                </tr>
                                            </thead>
                                            <tbody id="leaderboard-tbody">
                                                <!-- Dynmically loaded rows -->
                                            </tbody>
                                        </table>
                                    </div>
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

                <!-- Telemetry log terminal line -->
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

    <!-- =========================================================================
         SECTION 4: JAVASCRIPT POLLING & DYNAMIC STATE MACHINE
         ========================================================================= -->
    <?php if (!$error): ?>
    <script>
        // Synchronise client clock with the server
        const sessionCode = <?= json_encode($code) ?>;
        const registrationId = <?= $registration ? (int)$regId : 'null' ?>;
        const serverStartTimestamp = <?= strtotime($session['start_time']) ?>;
        const serverCurrentTimestamp = <?= time() ?>;
        let serverTimeOffset = (serverCurrentTimestamp * 1000) - Date.now();

        /** Re-aligns the local clock with the server using the time stamp sent with every poll. */
        function syncServerClock(data) {
            if (data && typeof data.server_time_ms === 'number') {
                serverTimeOffset = data.server_time_ms - Date.now();
            }
        }
        const isAsync = <?= $isAsync ? 'true' : 'false' ?>;

        /**
         * Returns estimated absolute server timestamp in milliseconds.
         */
        function getServerTime() {
            return Date.now() + serverTimeOffset;
        }

        function toggleAsyncExplanation() {
            const box = document.getElementById('async-explanation-box');
            if (box) {
                box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
            }
        }

        function toggleAsyncExplanationLobby() {
            const box = document.getElementById('async-explanation-box-lobby');
            if (box) {
                box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
            }
        }

        let pollingInterval = null;
        let countdownTimer = null;
        let isFinalCountdown = false;
        let currentQuestionId = null;
        let currentOptionKeys = ['A', 'B', 'C', 'D'];   // original letter behind each displayed button (shuffled per student)
        let integrityWatch = false;
        let redirectTimer = null;

        // Custom typewriter message pipeline
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

        /**
         * Simulates a character-by-character telemetry terminal typewriter effect.
         */
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

        /**
         * Pushes a new alert string to the front of the telemetry typewriter loop.
         */
        function logTelemetry(msg) {
            telemetryMessages.unshift(`> ${msg}`);
            if (telemetryMessages.length > 8) telemetryMessages.pop();
            msgIdx = 0;
            charIdx = 0;
            deleting = false;
        }

        // Setup background floating canvas graphics particles
        const particlesContainer = document.getElementById('floating-particles');
        const particleSVGs = [
            `<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="8" r="7" stroke="#B5482A" stroke-width="1.5" opacity="0.3"/></svg>`,
            `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2 Q14 8 20 12 Q14 16 12 22 Q10 16 4 12 Q10 8 12 2 Z" fill="#D9A23B" opacity="0.35"/></svg>`,
            `<text x="0" y="16" fill="#B5482A" opacity="0.25" font-family="monospace" font-size="16">?</text>`,
            `<text x="0" y="16" fill="#D9A23B" opacity="0.25" font-family="monospace" font-size="16">✓</text>`
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

        /**
         * Bootstraps the real-time application listeners and triggers polling.
         */
        function startApp() {
            // Anti-cheat mechanisms (disabling text copying/select triggers during active quiz)
            const quizViewEl = document.getElementById('quiz-view');
            if (quizViewEl) {
                quizViewEl.addEventListener('selectstart', (e) => e.preventDefault());
                quizViewEl.addEventListener('copy', (e) => e.preventDefault());
                quizViewEl.addEventListener('contextmenu', (e) => e.preventDefault());
            }

            updateLobbyClock();
            lobbyClockInterval = setInterval(updateLobbyClock, 1000);

            if (registrationId !== null) {
                poll();
                pollingInterval = setInterval(poll, 2500);
                
                // Keep resilient synchronization queue active
                setInterval(syncPendingAnswers, 3000);
            }
        }

        /**
         * Counts down absolute time parameters towards lobby start index.
         */
        function updateLobbyClock() {
            if (isFinalCountdown) return;

            const pauseOverlay = document.getElementById('pause-overlay');
            if (pauseOverlay && !pauseOverlay.classList.contains('hidden')) {
                return;
            }

            const msLeft = (serverStartTimestamp * 1000) - getServerTime();
            const seconds = Math.max(0, Math.floor(msLeft / 1000));
            const watermark = document.getElementById('lobby-timer-watermark');
            const bigTimer = document.getElementById('lobby-countdown-time');
            if (bigTimer) {
                const h = Math.floor(seconds / 3600), m = Math.floor((seconds % 3600) / 60), sc = seconds % 60;
                const pad = (n) => String(n).padStart(2, '0');
                bigTimer.textContent = h > 0 ? `${h}:${pad(m)}:${pad(sc)}` : `${pad(m)}:${pad(sc)}`;
                const box = document.getElementById('lobby-countdown');
                if (box) box.classList.toggle('is-soon', seconds > 0 && seconds <= 60);
            }

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

            // Launch immediate 5-seconds countdown visual indicator if start threshold crossed
            if (seconds <= 5 && seconds > 0 && !isFinalCountdown) {
                isFinalCountdown = true;
                if (pollingInterval) clearInterval(pollingInterval);
                if (lobbyClockInterval) clearInterval(lobbyClockInterval);
                startFinalCountdown(seconds);
            }
        }

        /**
         * Polls lobby endpoint state to detect live session switches.
         */
        function poll() {
            if (isFinalCountdown) return;

            fetch(`/api/live-eval-poll.php?code=${sessionCode}&action=poll_lobby`)
            .then(res => res.json())
            .then(data => {
                syncServerClock(data);
                const loadingEl = document.getElementById('loading-state');
                if (loadingEl) loadingEl.classList.add('hidden');
                
                if (!data.success) {
                    showError(data.message);
                    return;
                }

                // Handle pause overlay switch state
                const pauseOverlay = document.getElementById('pause-overlay');
                if (pauseOverlay) {
                    if (data.is_paused) {
                        pauseOverlay.classList.remove('hidden');
                        pauseOverlay.style.display = 'flex';
                    } else {
                        pauseOverlay.classList.add('hidden');
                        pauseOverlay.style.display = 'none';
                    }
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
                    clearInterval(pollingInterval);
                    pollingInterval = setInterval(pollQuiz, 2000);
                }
            })
            .catch(err => console.error("Erreur connexion : ", err));
        }

        /**
         * Fetches current active question parameters from polling cache layer.
         */
        function pollQuiz() {
            fetch(`/api/live-eval-poll.php?code=${sessionCode}&action=poll_quiz`)
            .then(res => res.json())
            .then(data => {
                syncServerClock(data);
                if (!data.success) {
                    if (data.not_registered) {
                        window.location.reload();
                    } else {
                        showError(data.message);
                    }
                    return;
                }

                const pauseOverlay = document.getElementById('pause-overlay');
                if (pauseOverlay) {
                    if (data.is_paused) {
                        pauseOverlay.classList.remove('hidden');
                        pauseOverlay.style.display = 'flex';
                        if (questionTimer) {
                            clearInterval(questionTimer);
                            questionTimer = null;
                        }
                    } else {
                        pauseOverlay.classList.add('hidden');
                        pauseOverlay.style.display = 'none';
                    }
                }

                if (data.status === 'finished' || data.is_finished) {
                    clearInterval(pollingInterval);
                    showView('finished-view');
                    const totalUsersEl = document.getElementById('finished-total-users');
                    if (totalUsersEl) {
                        totalUsersEl.textContent = data.total_registered || '--';
                    }

                    // Render podium rankings and dynamic leaderboard rows
                    if (data.leaderboard && data.leaderboard.length > 0) {
                        const boardContainer = document.getElementById('live-leaderboard-container');
                        if (boardContainer) boardContainer.classList.remove('hidden');

                        const top1 = data.leaderboard[0] || null;
                        const top2 = data.leaderboard[1] || null;
                        const top3 = data.leaderboard[2] || null;

                        if (top1) {
                            document.getElementById('podium-name-1').textContent = top1.name;
                            document.getElementById('podium-score-1').textContent = parseFloat(top1.score).toFixed(1) + '%';
                            document.getElementById('podium-1').style.opacity = '1';
                        } else {
                            document.getElementById('podium-1').style.opacity = '0.3';
                        }

                        if (top2) {
                            document.getElementById('podium-name-2').textContent = top2.name;
                            document.getElementById('podium-score-2').textContent = parseFloat(top2.score).toFixed(1) + '%';
                            document.getElementById('podium-2').style.opacity = '1';
                        } else {
                            document.getElementById('podium-2').style.opacity = '0.3';
                        }

                        if (top3) {
                            document.getElementById('podium-name-3').textContent = top3.name;
                            document.getElementById('podium-score-3').textContent = parseFloat(top3.score).toFixed(1) + '%';
                            document.getElementById('podium-3').style.opacity = '1';
                        } else {
                            document.getElementById('podium-3').style.opacity = '0.3';
                        }

                        const tbody = document.getElementById('leaderboard-tbody');
                        if (tbody) {
                            tbody.innerHTML = '';
                            data.leaderboard.forEach((player, idx) => {
                                const tr = document.createElement('tr');
                                if (idx < 3) tr.classList.add('top-three-row');
                                tr.innerHTML = `
                                    <td style="font-weight: ${idx < 3 ? '700' : 'normal'};">
                                        ${idx + 1} ${idx === 0 ? '#1' : idx === 1 ? '#2' : idx === 2 ? '#3' : ''}
                                    </td>
                                    <td style="font-weight: ${idx < 3 ? '600' : 'normal'};">
                                        ${player.name}
                                    </td>
                                    <td style="text-align: right; font-weight: 700; color: var(--green);">
                                        ${parseFloat(player.score).toFixed(1)}%
                                    </td>
                                `;
                                tbody.appendChild(tr);
                            });
                        }
                    }
                    return;
                }

                if (data.status === 'waiting') {
                    // Past the scheduled start (small clock gap): stay on the sync screen and retry quickly
                    if (!isAsync && getServerTime() >= (serverStartTimestamp * 1000) - 1500) {
                        showView('loading-state');
                        setTimeout(pollQuiz, 600);
                    } else {
                        showView('lobby-view');
                    }
                    return;
                }

                showView('quiz-view');
                const qNumText = `Question ${data.current_question_index + 1} / ${data.total_questions}`;
                document.getElementById('quiz-question-number').textContent = qNumText;
                document.getElementById('live-bar-question').textContent = qNumText;

                const remainingEl = document.getElementById('quiz-live-remaining-count');
                if (remainingEl) {
                    const total = data.total_registered || 0;
                    const answers = data.answers_received || 0;
                    remainingEl.textContent = Math.max(0, total - answers);
                }

                integrityWatch = !!data.integrity_watch;
                const q = data.question;
                
                if (currentQuestionId !== q.id) {
                    currentQuestionId = q.id;
                    questionTotalDuration = 0;
                    resetQuizForm(q);
                    logTelemetry(`Question ${data.current_question_index + 1} activée: ${q.question_text.slice(0, 30)}...`);
                }

                if (data.already_answered) {
                    disableOptions();
                    const statusEl = document.getElementById('quiz-submit-status');
                    if (statusEl) {
                        statusEl.classList.remove('hidden');
                        statusEl.innerHTML = '✓ Réponse enregistrée — en attente de la prochaine question...';
                        statusEl.style.borderColor = 'rgba(62,107,71,0.3)';
                        statusEl.style.background = 'rgba(62,107,71,0.08)';
                        statusEl.style.color = '#3E6B47';
                    }
                }

                updateQuestionTimer(q.seconds_left, data.is_paused);
            })
            .catch(err => console.error("Erreur quiz : ", err));
        }

        let questionTimer = null;
        let questionSecondsLeft = 0;
        let questionTotalDuration = 0;

        /**
         * Synthesizes a positive confirmation chime sound.
         */
        function playPositiveChime() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc1 = ctx.createOscillator();
                const gain1 = ctx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(523.25, ctx.currentTime);
                osc1.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15);
                
                gain1.gain.setValueAtTime(0.15, ctx.currentTime);
                gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
                
                osc1.connect(gain1);
                gain1.connect(ctx.destination);
                
                osc1.start();
                osc1.stop(ctx.currentTime + 0.35);
            } catch (e) {
                console.log("Audio Context blocked or not supported", e);
            }
        }

        /**
         * Synthesizes a periodic ticking sound.
         */
        function playTickSound() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(1000, ctx.currentTime);
                
                gain.gain.setValueAtTime(0.05, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.05);
                
                osc.connect(gain);
                gain.connect(ctx.destination);
                
                osc.start();
                osc.stop(ctx.currentTime + 0.05);
            } catch (e) {}
        }

        /**
         * Computes circular SVG countdown ring offset vectors.
         */
        function updateSvgTimer(seconds, total) {
            const circle = document.getElementById('quiz-svg-timer-circle');
            const text = document.getElementById('quiz-timer-text');
            if (!circle || !text) return;

            text.textContent = seconds;

            const radius = 23;
            const circumference = 2 * Math.PI * radius;
            
            let pct = total > 0 ? (seconds / total) : 0;
            pct = Math.min(Math.max(pct, 0), 1);
            
            const offset = circumference - (pct * circumference);
            circle.style.strokeDashoffset = offset;

            if (seconds > 10) {
                circle.style.stroke = '#B5482A';
                circle.style.filter = 'drop-shadow(0 0 6px rgba(181, 72, 42, 0.4))';
                text.style.color = '#111111';
            } else if (seconds > 5) {
                circle.style.stroke = '#D97706';
                circle.style.filter = 'drop-shadow(0 0 6px rgba(217, 119, 6, 0.5))';
                text.style.color = '#D97706';
            } else {
                circle.style.stroke = '#DC2626';
                circle.style.filter = 'drop-shadow(0 0 6px rgba(220, 38, 38, 0.6))';
                text.style.color = '#DC2626';
            }
        }

        /**
         * Ticks down the question duration parameters locally between server queries.
         */
        function updateQuestionTimer(seconds, isPaused) {
            const drift = Math.abs(questionSecondsLeft - seconds);
            if (drift > 2 || questionSecondsLeft <= 0 || isPaused) {
                questionSecondsLeft = seconds;
                if (questionTotalDuration === 0 || seconds > questionTotalDuration) {
                    questionTotalDuration = seconds;
                }
            }

            updateSvgTimer(questionSecondsLeft, questionTotalDuration);

            if (isPaused) {
                if (questionTimer) {
                    clearInterval(questionTimer);
                    questionTimer = null;
                }
                return;
            }

            if (!questionTimer) {
                questionTimer = setInterval(() => {
                    if (questionSecondsLeft > 0) {
                        questionSecondsLeft--;
                        
                        if (questionSecondsLeft <= 5 && questionSecondsLeft > 0) {
                            const container = document.getElementById('quiz-svg-timer-container');
                            if (container) {
                                container.style.transform = 'scale(1.25)';
                                setTimeout(() => {
                                    container.style.transform = 'scale(1)';
                                }, 150);
                            }
                            playTickSound();
                        }
                        
                        updateSvgTimer(questionSecondsLeft, questionTotalDuration);
                    }
                    if (questionSecondsLeft <= 0) {
                        clearInterval(questionTimer);
                        questionTimer = null;
                        updateSvgTimer(0, questionTotalDuration);
                        disableOptions();
                        if (isAsync) {
                            submitLiveAnswerRaw("");
                        }
                    }
                }, 1000);
            }
        }

        /**
         * Switches the active DOM layout views.
         */
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
            const lobbyTimer = document.getElementById('lobby-countdown');
            if (lobbyTimer) lobbyTimer.classList.toggle('hidden', viewId !== 'lobby-view');

            if (viewId === 'quiz-view') {
                card.classList.add('quiz-mode');
                liveBar.classList.add('active');
                if (topBar) topBar.classList.add('hidden');
                if (bottomBar) bottomBar.classList.add('hidden');
                if (watermark) watermark.classList.add('hidden');
                if (badge) {
                    badge.innerHTML = `<svg width="6" height="6" viewBox="0 0 8 8" fill="none" style="margin-right:2px;"><circle cx="4" cy="4" r="3" fill="#EF4444"/></svg> Quiz Actif`;
                }
            } else if (viewId === 'finished-view') {
                card.classList.remove('quiz-mode');
                liveBar.classList.remove('active');
                if (topBar) topBar.classList.remove('hidden');
                if (bottomBar) bottomBar.classList.remove('hidden');
                if (watermark) watermark.classList.add('hidden');
                if (img) img.src = "/assets/img/live-success-illustration.png";
                if (caption) caption.textContent = "Téléévaluation terminée. Résultats prêts !";
                if (badge) {
                    badge.innerHTML = `<svg width="6" height="6" viewBox="0 0 8 8" fill="none" style="margin-right:2px;"><circle cx="4" cy="4" r="3" fill="#3E6B47"/></svg> Session Complétée`;
                }

                const typewriterLine = document.getElementById('typewriter-line');
                const hintEl = document.querySelector('#col-bottom-bar .hint');
                if (typewriterLine) typewriterLine.classList.add('hidden');
                if (hintEl) hintEl.classList.add('hidden');

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

                const color = viewId === 'countdown-view' ? '#F59E0B' : '#B5482A';
                if (badge) {
                    badge.innerHTML = `<svg width="6" height="6" viewBox="0 0 8 8" fill="none" style="margin-right:2px;"><circle cx="4" cy="4" r="3" fill="${color}"/></svg> Salle d'attente`;
                }

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

        /**
         * Launches a 5-second countdown step.
         */
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

        /**
         * Resets the question form parameters when switching questions.
         */
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
            const setHasImage = (on) => {
                document.getElementById('quiz-body').classList.toggle('has-image', on);
                const card = document.querySelector('.card');
                if (card) card.classList.toggle('has-image', on);
            };
            if (q.image_path) {
                imgEl.onerror = function() {
                    const fileOnly = q.image_path.split('file=').pop().split('/').pop();
                    if (!imgEl.dataset.fallbackTried && fileOnly) {
                        imgEl.dataset.fallbackTried = 'true';
                        imgEl.src = '/uploads/live_questions/' + fileOnly;
                    } else {
                        imgContainer.classList.add('hidden');
                        setHasImage(false);
                    }
                };
                imgEl.dataset.fallbackTried = '';
                imgEl.src = q.image_path;
                imgContainer.classList.remove('hidden');
                setHasImage(true);
            } else {
                imgContainer.classList.add('hidden');
                imgEl.removeAttribute('src');
                setHasImage(false);
            }

            const qType = q.question_type || 'mcq';
            const mcqContainer = document.getElementById('quiz-options-container');
            const writtenContainer = document.getElementById('quiz-written-container');
            
            if (qType === 'written') {
                mcqContainer.classList.add('hidden');
                writtenContainer.classList.remove('hidden');
                
                const wrInput = document.getElementById('written-answer-input');
                wrInput.value = '';
                wrInput.disabled = false;
                
                const wrBtn = document.getElementById('btn-submit-written');
                wrBtn.disabled = false;
                wrBtn.className = "btn-primary";
            } else {
                mcqContainer.classList.remove('hidden');
                writtenContainer.classList.add('hidden');
                
                currentOptionKeys = Array.isArray(q.option_keys) && q.option_keys.length === 4 ? q.option_keys : ['A', 'B', 'C', 'D'];
                document.getElementById('text-opt-A').textContent = q.option_a;
                document.getElementById('text-opt-B').textContent = q.option_b;
                document.getElementById('text-opt-C').textContent = q.option_c;
                document.getElementById('text-opt-D').textContent = q.option_d;

                const optionTexts = { A: q.option_a, B: q.option_b, C: q.option_c, D: q.option_d };
                ['A', 'B', 'C', 'D'].forEach(opt => {
                    const btn = document.getElementById(`btn-opt-${opt}`);
                    btn.disabled = false;
                    btn.className = "option-btn";
                    // True/false questions only fill A and B: the empty buttons are not shown
                    btn.style.display = String(optionTexts[opt] ?? '').trim() === '' ? 'none' : '';
                });
            }

            document.getElementById('quiz-submit-status').classList.add('hidden');
            setTimeout(renderMath, 50);
        }

        function openQuizImageZoom() {
            const src = document.getElementById('quiz-image').getAttribute('src');
            if (!src) return;
            let z = document.getElementById('quiz-zoom');
            if (!z) {
                z = document.createElement('div');
                z.id = 'quiz-zoom';
                z.setAttribute('role', 'dialog');
                z.innerHTML = '<img alt="">';
                z.addEventListener('click', () => { z.style.display = 'none'; });
                document.body.appendChild(z);
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') z.style.display = 'none'; });
            }
            z.querySelector('img').src = src;
            z.style.display = 'flex';
        }

        function submitLiveAnswer(option) {
            ['A', 'B', 'C', 'D'].forEach(opt => {
                const btn = document.getElementById(`btn-opt-${opt}`);
                if (btn) {
                    if (opt === option) {
                        btn.className = "option-btn selected";
                    } else {
                        btn.className = "option-btn opacity-40";
                    }
                }
            });

            disableOptions();
            // The button the student pressed is a display position; the server wants the original letter
            submitLiveAnswerRaw(currentOptionKeys['ABCD'.indexOf(option)] || option);
        }

        function submitWrittenAnswer() {
            const input = document.getElementById('written-answer-input');
            const val = input.value.trim();
            if (val === '') {
                alert("Veuillez saisir une réponse avant de soumettre.");
                return;
            }
            
            disableOptions();
            submitLiveAnswerRaw(val);
        }

        /**
         * Dispatches answer submission forms asynchronously.
         */
        function submitLiveAnswerRaw(option) {
            const statusEl = document.getElementById('quiz-submit-status');
            if (statusEl) {
                statusEl.classList.remove('hidden');
                statusEl.innerHTML = 'Enregistrement de votre réponse...';
                statusEl.style.borderColor = 'rgba(62,107,71,0.3)';
                statusEl.style.background = 'rgba(62,107,71,0.08)';
                statusEl.style.color = '#3E6B47';
            }
            logTelemetry(`Réponse "${option}" soumise. En attente...`);

            // Queue the request details in local storage for network fault resilience
            const pendingAnswer = {
                code: sessionCode,
                action: 'submit_answer',
                question_id: currentQuestionId,
                selected_option: option,
                timestamp: Date.now()
            };
            localStorage.setItem('pending_live_answer_' + sessionCode, JSON.stringify(pendingAnswer));

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
                if (data.success) {
                    localStorage.removeItem('pending_live_answer_' + sessionCode);
                    if (statusEl) {
                        statusEl.innerHTML = '✓ Réponse enregistrée — en attente de la prochaine question...';
                        statusEl.style.borderColor = 'rgba(62,107,71,0.3)';
                        statusEl.style.background = 'rgba(62,107,71,0.08)';
                        statusEl.style.color = '#3E6B47';
                    }
                    playPositiveChime();
                } else {
                    console.error(data.message);
                    if (statusEl) {
                        statusEl.innerHTML = 'Erreur: ' + data.message;
                        statusEl.style.borderColor = '#EF4444';
                        statusEl.style.background = '#FEF2F2';
                        statusEl.style.color = '#991B1B';
                    }
                }
            })
            .catch(err => {
                console.error("Erreur de soumission :", err);
                if (statusEl) {
                    statusEl.innerHTML = 'Connexion instable — Réponse mise en attente (synchronisation automatique...)';
                    statusEl.style.borderColor = '#F59E0B';
                    statusEl.style.background = '#FEF3C7';
                    statusEl.style.color = '#92400E';
                }
            });
        }

        /**
         * Empties and synchronizes locally cached submission queue if internet link restores.
         */
        function syncPendingAnswers() {
            const pendingKey = 'pending_live_answer_' + sessionCode;
            const dataStr = localStorage.getItem(pendingKey);
            if (!dataStr) return;

            let pending;
            try {
                pending = JSON.parse(dataStr);
            } catch(e) {
                localStorage.removeItem(pendingKey);
                return;
            }

            if (pending.question_id !== currentQuestionId) {
                localStorage.removeItem(pendingKey);
                return;
            }

            const formData = new FormData();
            formData.append('code', pending.code);
            formData.append('action', pending.action);
            formData.append('question_id', pending.question_id);
            formData.append('selected_option', pending.selected_option);

            fetch('/api/live-eval-poll.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    localStorage.removeItem(pendingKey);
                    const statusEl = document.getElementById('quiz-submit-status');
                    if (statusEl) {
                        statusEl.innerHTML = '✓ Réponse enregistrée (synchronisée) — en attente de la prochaine question...';
                        statusEl.style.borderColor = 'rgba(62,107,71,0.3)';
                        statusEl.style.background = 'rgba(62,107,71,0.08)';
                        statusEl.style.color = '#3E6B47';
                    }
                    playPositiveChime();
                }
            })
            .catch(err => console.log("Retrying pending sync... connection still offline."));
        }

        /**
         * Disables question options to prevent double-submissions.
         */
        function disableOptions() {
            ['A', 'B', 'C', 'D'].forEach(opt => {
                const btn = document.getElementById(`btn-opt-${opt}`);
                if (btn) btn.disabled = true;
            });
            const wrInput = document.getElementById('written-answer-input');
            if (wrInput) wrInput.disabled = true;
            const wrBtn = document.getElementById('btn-submit-written');
            if (wrBtn) wrBtn.disabled = true;
        }

        /**
         * Exam integrity: when the teacher turned it on for this session, leaving the exam tab is counted and the student
         * is told so on their return. The count is shown to the teacher in the session analysis.
         */
        let integrityLeftAt = 0;
        function reportFocusLoss() {
            const quiz = document.getElementById('quiz-view');
            if (!integrityWatch || !quiz || quiz.classList.contains('hidden') || integrityLeftAt) return;
            integrityLeftAt = Date.now();
            const fd = new FormData();
            fd.append('code', sessionCode);
            fd.append('action', 'report_integrity');
            try { fetch('/api/live-eval-poll.php', { method: 'POST', body: fd, keepalive: true }); } catch (e) {}
        }
        function warnFocusReturn() {
            if (!integrityLeftAt) return;
            integrityLeftAt = 0;
            let bar = document.getElementById('integrity-warning');
            if (!bar) {
                bar = document.createElement('div');
                bar.id = 'integrity-warning';
                bar.setAttribute('role', 'alert');
                bar.style.cssText = 'position:fixed;left:50%;top:1rem;transform:translateX(-50%);z-index:9999;max-width:min(92vw,34rem);padding:.75rem 1rem;border-radius:12px;background:var(--ink);color:var(--paper);font:600 .9rem/1.4 inherit;box-shadow:0 8px 30px rgba(0,0,0,.25)';
                bar.textContent = document.documentElement.lang === 'en'
                    ? 'You left the exam window. This has been recorded and your teacher can see it.'
                    : "Vous avez quitté la fenêtre de l'examen. Cela a été enregistré et votre enseignant peut le voir.";
                document.body.appendChild(bar);
            }
            bar.style.display = 'block';
            setTimeout(() => { bar.style.display = 'none'; }, 7000);
        }
        document.addEventListener('visibilitychange', () => { document.hidden ? reportFocusLoss() : warnFocusReturn(); });
        window.addEventListener('blur', () => { setTimeout(() => { if (!document.hasFocus()) reportFocusLoss(); }, 300); });
        window.addEventListener('focus', warnFocusReturn);

        window.addEventListener('DOMContentLoaded', startApp);
    </script>
    <?php endif; ?>

</body>
</html>
