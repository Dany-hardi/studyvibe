<?php
/**
 * StudyVibe Academic LMS - Mark All Notifications Read Controller
 *
 * This controller marks all unread notifications as read for the currently
 * authenticated user.
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
// SECTION 2: MARK NOTIFICATIONS READ TRANSACTION
// =========================================================================

try {
    $pdo = Database::getInstance();
    $uid = (int)$_SESSION['user_id'];
    Notifications::markAllRead($pdo, $uid);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'mark-all-read');
}
