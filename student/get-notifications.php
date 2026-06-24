<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Notifications.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

try {
    $pdo = Database::getInstance();
    $uid = (int)$_SESSION['user_id'];
    echo json_encode([
        'success'       => true,
        'notifications' => Notifications::listUnread($pdo, $uid),
        'unread_count'  => Notifications::countUnread($pdo, $uid),
    ]);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'get-notifications');
}
