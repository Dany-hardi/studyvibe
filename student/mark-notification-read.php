<?php
/**
 * StudyVibe Academic LMS - Mark Notification Read Controller
 *
 * This controller processes requests by students to mark a specific notification
 * (or all notifications) as read, returning the updated unread notification count.
 *
 * PHP version 8.2
 *
 * @category  Controller
 * @package   StudyVibe\Student
 * @author    StudyVibe Team <development@studyvibe.academic>
 * @copyright 2026 StudyVibe
 * @license   Proprietary
 * @link      https://studyvibe.academic
 */

declare(strict_types=1);

// =========================================================================
// SECTION 1: AUTHENTICATION & INPUT PARAMETERS SECURITY
// =========================================================================

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Notifications.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$id = (int)($_POST['notification_id'] ?? 0);
$all = isset($_POST['mark_all']) && $_POST['mark_all'] === '1';

// =========================================================================
// SECTION 2: MARK NOTIFICATIONS READ TRANSACTION
// =========================================================================

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
