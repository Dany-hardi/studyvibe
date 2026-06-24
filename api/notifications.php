<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Notifications.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = Database::getInstance();

    if ($method === 'GET') {
        $notifications = Notifications::listUnread($pdo, $userId);
        $count = Notifications::countUnread($pdo, $userId);
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'count' => $count
        ]);
        exit;
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $action = trim((string)($data['action'] ?? ''));

        if ($action === 'mark_all_read') {
            Notifications::markAllRead($pdo, $userId);
            echo json_encode(['success' => true]);
            exit;
        } elseif ($action === 'mark_read') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID invalide.']);
                exit;
            }
            Notifications::markRead($pdo, $userId, $id);
            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
