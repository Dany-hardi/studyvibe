<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}


// requireCsrf();
$label = trim((string)($_POST['label'] ?? 'Clé API'));
if ($label === '') $label = 'Clé API';

try {
    $rawKey  = bin2hex(random_bytes(32));
    $keyHash = hash('sha256', $rawKey);

    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare("INSERT INTO api_keys (key_hash, label, created_by) VALUES (:hash, :label, :uid)");
    $stmt->execute(['hash' => $keyHash, 'label' => $label, 'uid' => (int)$_SESSION['user_id']]);

    auditLog('api_key_created', $label);
    echo json_encode(['success' => true, 'api_key' => $rawKey, 'label' => $label]);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'create-api-key.php');
}
