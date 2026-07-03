<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Notifications REST API Endpoint
 * 
 * Handles real-time retrieval and status manipulation of user notification alerts.
 * Supports GET (fetch count & list) and POST (mark read / mark all read) requests.
 * Enforces authentication validation prior to executing operations.
 * 
 * @package    StudyVibe
 * @subpackage API
 * @author     Advanced Engineering Team
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Notifications.php';

header('Content-Type: application/json');

// =========================================================================
// SECTION 1: SECURITY GATES & AUTHORIZATION CHECKS
// =========================================================================

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// =========================================================================
// SECTION 2: REQUEST METHOD ROUTING
// =========================================================================

try {
    $pdo = Database::getInstance();

    // GET Request: Retrieve list of unread notifications and the total count
    if ($method === 'GET') {
        $notifications = Notifications::listUnread($pdo, $userId);
        $count = Notifications::countUnread($pdo, $userId);
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'count' => $count
        ]);
        exit;
    } 
    // POST Request: Perform mutation actions (mark_read / mark_all_read)
    elseif ($method === 'POST') {
        // Read JSON payload if content type is application/json, fall back to standard POST
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $action = trim((string)($data['action'] ?? ''));

        // Action: Mark all notifications as read
        if ($action === 'mark_all_read') {
            Notifications::markAllRead($pdo, $userId);
            echo json_encode(['success' => true]);
            exit;
        } 
        // Action: Mark a single target notification ID as read
        elseif ($action === 'mark_read') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de notification invalide.']);
                exit;
            }
            Notifications::markRead($pdo, $userId, $id);
            echo json_encode(['success' => true]);
            exit;
        } 
        // Action fallback: unknown parameter passed
        else {
            echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
            exit;
        }
    } 
    // HTTP Method fallback: enforce GET or POST
    else {
        echo json_encode(['success' => false, 'message' => 'Méthode HTTP non autorisée.']);
        exit;
    }
} catch (Exception $e) {
    // Graceful exception capture without revealing server logs
    echo json_encode(['success' => false, 'message' => 'Erreur de traitement des notifications.']);
    exit;
}
