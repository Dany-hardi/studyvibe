<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

// requireCsrf();

$id    = (int)($_POST['module_id'] ?? 0);
$title = trim((string)($_POST['module_title'] ?? ''));
$desc  = trim((string)($_POST['module_desc'] ?? ''));

if ($id <= 0 || $title === '') {
    echo json_encode(['success' => false, 'message' => 'Données invalides.']);
    exit;
}

try {
    $pdo = Database::getInstance();
    $pdo->prepare("UPDATE modules SET title = :t, description = :d WHERE id = :id")
        ->execute(['t' => $title, 'd' => $desc, 'id' => $id]);
    auditLog('module_updated', "Module #{$id}");
    echo json_encode(['success' => true, 'message' => 'Module mis à jour.']);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'update-module');
}
