<?php
/**
 * StudyVibe Academic LMS - Student Study Session Tracker
 *
 * This controller logs active time spent by a student on a specific lesson,
 * aggregates cumulative seconds, and triggers progress-related badges.
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

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

$lessonId = (int)($_POST['lesson_id'] ?? 0);
$seconds  = (int)($_POST['seconds'] ?? 0);
$studentId = (int)$_SESSION['user_id'];

if ($lessonId <= 0 || $seconds <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

// =========================================================================
// SECTION 2: TRACKING SESSION RECORD TRANSACTION & BADGES DISPATCH
// =========================================================================

try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare("
        INSERT INTO study_sessions (student_id, lesson_id, seconds_spent, session_date)
        VALUES (:sid, :lid, :sec, CURDATE())
        ON DUPLICATE KEY UPDATE seconds_spent = seconds_spent + :sec2
    ");
    $stmt->execute(['sid' => $studentId, 'lid' => $lessonId, 'sec' => $seconds, 'sec2' => $seconds]);

    // Badge: 1h d'étude cumulée
    $totalStmt = $pdo->prepare("SELECT COALESCE(SUM(seconds_spent),0) FROM study_sessions WHERE student_id = :sid");
    $totalStmt->execute(['sid' => $studentId]);
    if ((int)$totalStmt->fetchColumn() >= 3600) {
        $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_type) VALUES (:sid, 'study_hour')")
            ->execute(['sid' => $studentId]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException) {
    echo json_encode(['success' => false]);
}
