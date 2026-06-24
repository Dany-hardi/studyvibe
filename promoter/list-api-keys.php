<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

try {
    $pdo = Database::getInstance();
    $rows = $pdo->query("
        SELECT ak.id, ak.label, ak.is_active, ak.created_at, u.name AS created_by_name
        FROM api_keys ak
        LEFT JOIN users u ON u.id = ak.created_by
        ORDER BY ak.id DESC
    ")->fetchAll();
    echo json_encode(['success' => true, 'keys' => $rows]);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'list-api-keys');
}
