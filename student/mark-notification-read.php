<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Notifications.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

// requireCsrf();

$id = (int)($_POST['notification_id'] ?? 0);
$all = isset($_POST['mark_all']) && $_POST['mark_all'] === '1';

try {
    $pdo = Database::getInstance();
    $uid = (int)$_SESSION['user_id'];
    if ($all) {
        Notifications::markAllRead($pdo, $uid);
    } elseif ($id > 0) {
        Notifications::markRead($pdo, $uid, $id);
    }
    echo json_encode(['success' => true, 'unread_count' => Notifications::countUnread($pdo, $uid)]);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'mark-notification-read');
}
