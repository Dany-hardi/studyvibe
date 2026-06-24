<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/AuthTokens.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

// requireCsrf();

$token    = trim((string)($_POST['token'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($token === '' || strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Mot de passe invalide (6 caractères minimum).']);
    exit;
}

try {
    $pdo = Database::getInstance();
    $row = AuthTokens::validatePasswordReset($pdo, $token);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Lien expiré ou invalide.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = :pw WHERE id = :id")
        ->execute(['pw' => $hash, 'id' => (int)$row['user_id']]);

    AuthTokens::consumePasswordReset($pdo, $token);
    auditLog('password_reset_done', 'User #' . $row['user_id']);

    echo json_encode(['success' => true, 'message' => 'Mot de passe mis à jour. Vous pouvez vous connecter.']);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'reset-password');
}
