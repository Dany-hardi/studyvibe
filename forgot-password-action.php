<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/lib/AuthTokens.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

// requireCsrf();

$email = trim((string)($_POST['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => true, 'message' => 'Si cette adresse existe, un email de réinitialisation a été envoyé.']);
    exit;
}

try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = :email AND is_active = 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = AuthTokens::createPasswordReset($pdo, (int)$user['id']);
        $sent  = Mailer::passwordReset($user['email'], $user['name'], $token);
        auditLog('password_reset_requested', $email);
        if (!$sent) {
            auditLog('mail_failed', 'Password reset to ' . $email);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Si cette adresse existe, un email de réinitialisation a été envoyé.',
    ]);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'forgot-password');
}
