<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

// requireCsrf();

$email    = trim((string)($_POST['email']    ?? ''));
$password = (string)($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Identifiants requis.']);
    exit;
}

// ── Vérification brute-force ────────────────────────────────
if (isRateLimited($email)) {
    $minutes = LOGIN_LOCKOUT_MINUTES;
    echo json_encode([
        'success' => false,
        'message' => "Trop de tentatives. Compte temporairement bloqué pour {$minutes} minutes."
    ]);
    exit;
}

try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare("SELECT id, name, email, password, role, email_verified_at, is_active FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        // Enregistrer la tentative échouée
        recordLoginAttempt($email);
        auditLog('login_failed', "Email: {$email}");
        echo json_encode(['success' => false, 'message' => 'Adresse électronique ou mot de passe incorrect.']);
        exit;
    }

    if (!(int)($user['is_active'] ?? 1)) {
        echo json_encode(['success' => false, 'message' => 'Ce compte a été désactivé. Contactez l\'administrateur.']);
        exit;
    }

    if ($user['role'] === 'teacher' && !(int)($user['is_approved'] ?? 1)) {
        echo json_encode(['success' => false, 'message' => 'Votre compte enseignant est en attente de validation par le promoteur.']);
        exit;
    }

    // ── Succès — ouvrir la session ──────────────────────────
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['last_regen'] = time();

    // Effacer les tentatives en cas de succès
    clearLoginAttempts($email);
    auditLog('login_success', "User #{$user['id']} ({$user['role']})");

    $redirectMap = [
        'promoter' => '/promoter/dashboard.php',
        'teacher'  => '/teacher/dashboard.php',
        'student'  => '/student/dashboard.php',
    ];

    // Allow a trusted relative redirect (e.g. from join.php) — students only, no open-redirect
    $postRedirect = trim((string)($_POST['redirect'] ?? ''));
    $safeRedirect = (
        $user['role'] === 'student'
        && !empty($postRedirect)
        && str_starts_with($postRedirect, '/')
        && !str_contains($postRedirect, '//')
        && !str_contains($postRedirect, '\\')
    ) ? $postRedirect : null;

    $redirect = empty($user['email_verified_at'])
        ? '/verify-email-pending.php'
        : ($safeRedirect ?? $redirectMap[$user['role']] ?? '/index.php');

    echo json_encode([
        'success'  => true,
        'redirect' => $redirect,
    ]);
    exit;

} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'login');
}
