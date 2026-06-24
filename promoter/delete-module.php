<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

// requireCsrf();

$id = (int)($_POST['module_id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Module invalide.']);
    exit;
}

try {
    $pdo = Database::getInstance();
    $count = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE module_id = :id");
    $count->execute(['id' => $id]);
    if ((int)$count->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Impossible : des cours sont liés à ce module.']);
        exit;
    }
    $pdo->prepare("DELETE FROM modules WHERE id = :id")->execute(['id' => $id]);
    auditLog('module_deleted', "Module #{$id}");
    echo json_encode(['success' => true, 'message' => 'Module supprimé.']);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'delete-module');
}
