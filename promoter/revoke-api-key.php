<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

// requireCsrf();

$id = (int)($_POST['key_id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Clé invalide.']);
    exit;
}

try {
    $pdo = Database::getInstance();
    $pdo->prepare("UPDATE api_keys SET is_active = 0 WHERE id = :id")->execute(['id' => $id]);
    auditLog('api_key_revoked', "Key #{$id}");
    echo json_encode(['success' => true, 'message' => 'Clé révoquée.']);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'revoke-api-key');
}
