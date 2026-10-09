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
$phoneRaw = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
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

require_once __DIR__ . '/lib/Phone.php';
$phonePending = Phone::normalize($phoneRaw);
if ($phonePending === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Le numéro de téléphone n’est pas valide. Exemple : 6 12 34 56 78 ou +237 612 345 678.'
    ]);
    exit;
}
require_once __DIR__ . '/lib/PasswordPolicy.php';
if (($pwError = PasswordPolicy::check($password, $email)) !== null) {
    echo json_encode([
        'success' => false,
        'message' => $pwError
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
        INSERT INTO users (name, email, password, role, is_approved, plan_id, signup_source, phone_pending)
        VALUES (:name, :email, :password, :role, :is_approved, :plan_id, :src, :phone)
    ");
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password' => $hashedPassword,
        'role' => $role,
        'is_approved' => $isApproved,
        'plan_id' => defaultPlanId($pdo, $role),
        'src' => ($_SESSION['sv_src'] ?? '') !== '' ? substr((string)$_SESSION['sv_src'], 0, 40) : null,
        'phone' => $phonePending,
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
