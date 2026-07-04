<?php
/**
 * StudyVibe Academic LMS - Notification Data Provider
 *
 * This controller serves list of unread notification payloads and counts
 * for the logged-in user session.
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
// SECTION 1: AUTHENTICATION
// =========================================================================

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Notifications.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

// =========================================================================
// SECTION 2: FETCH NOTIFICATIONS PAYLOAD
// =========================================================================

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
