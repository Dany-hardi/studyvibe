<?php
/**
 * StudyVibe Academic LMS - Student Statistical Data Fetcher
 *
 * This endpoint compiles and returns academic statistics for the current logged-in student,
 * including completed courses count, average exam score, total study hours, and badges earned.
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
// SECTION 1: AUTHENTICATION & SESSION RETRIEVAL
// =========================================================================

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$studentId = (int)$_SESSION['user_id'];

// =========================================================================
// SECTION 2: STATISTICAL METRICS QUERIES & TRANSACTIONS
// =========================================================================

try {
    $pdo = Database::getInstance();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = :sid AND progress_percent = 100");
    $stmt->execute(['sid' => $studentId]);
    $completedCourses = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(AVG(score), 0) FROM certification_attempts WHERE student_id = :sid");
    $stmt->execute(['sid' => $studentId]);
    $avgScore = round((float)$stmt->fetchColumn(), 1);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(seconds_spent), 0) FROM study_sessions WHERE student_id = :sid");
    $stmt->execute(['sid' => $studentId]);
    $totalSeconds = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE student_id = :sid");
    $stmt->execute(['sid' => $studentId]);
    $certCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT badge_type, earned_at FROM student_badges WHERE student_id = :sid ORDER BY earned_at DESC");
    $stmt->execute(['sid' => $studentId]);
    $badges = $stmt->fetchAll();

    echo json_encode([
        'success'           => true,
        'completed_courses' => $completedCourses,
        'avg_score'         => $avgScore,
        'study_time'        => [
            'hours'         => (int)floor($totalSeconds / 3600),
            'minutes'       => (int)floor(($totalSeconds % 3600) / 60),
            'total_seconds' => $totalSeconds,
        ],
        'certificates'      => $certCount,
        'badges'            => $badges,
    ]);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'get-stats.php');
}
