<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/Newsletter.php';
require_once __DIR__ . '/lib/AuthTokens.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode de requête non autorisée.'
    ]);
    exit;
}

// requireCsrf();

$name = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';
$role = isset($_POST['role']) ? trim((string)$_POST['role']) : '';

if (empty($name) || empty($email) || empty($password) || empty($role)) {
    echo json_encode([
        'success' => false,
        'message' => 'Veuillez remplir tous les champs, y compris le choix du rôle.'
    ]);
    exit;
}

if (!in_array($role, ['student', 'teacher'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Rôle invalide. Choisissez apprenant ou enseignant.'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Adresse électronique invalide.'
    ]);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode([
        'success' => false,
        'message' => 'Le mot de passe doit contenir au moins 6 caractères.'
    ]);
    exit;
}

try {
    $pdo = Database::getInstance();

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Cette adresse électronique est déjà enregistrée.'
        ]);
        exit;
    }

    $isApproved = ($role === 'teacher') ? 0 : 1;
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, is_approved)
        VALUES (:name, :email, :password, :role, :is_approved)
    ");
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password' => $hashedPassword,
        'role' => $role,
        'is_approved' => $isApproved,
    ]);

    $newUserId = (int)$pdo->lastInsertId();
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['user_role'] = $role;

    auditLog('signup', "New {$role}: {$email}");

    $roleLabel = $role === 'teacher' ? 'enseignant' : 'apprenant';
    $verifyToken = AuthTokens::createEmailVerification($pdo, $newUserId);
    $mailSent = Mailer::emailVerification($email, $name, $verifyToken);
    Mailer::welcome($email, $name, $roleLabel);

    if (!$mailSent) {
        auditLog('mail_failed', 'Verification email to ' . $email . ' — ' . (Mailer::getLastError() ?? 'unknown'));
    }

    $newsletterOptIn = isset($_POST['newsletter']) && $_POST['newsletter'] === '1';
    if ($newsletterOptIn) {
        Newsletter::subscribe($pdo, $email, $name, $newUserId);
    }

    echo json_encode([
        'success' => true,
        'redirect' => '/verify-email-pending.php',
        'mail_sent' => $mailSent,
    ]);
    exit;

} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'signup');
}
