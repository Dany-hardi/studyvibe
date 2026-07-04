<?php
/**
 * StudyVibe Academic LMS - Student Certification Attempts Fetcher
 *
 * This controller fetches the history of certification exam attempts made by the student
 * for a specific course, enforcing access security and determining 24-hour rate limits.
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
// SECTION 1: AUTHENTICATION & INPUT VALIDATION
// =========================================================================

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$courseId = (int)($_GET['course_id'] ?? 0);
if ($courseId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Cours invalide.']);
    exit;
}

// =========================================================================
// SECTION 2: FETCH ATTEMPTS HISTORY & TIME-LIMIT LIMITATIONS
// =========================================================================

try {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("
        SELECT score, passed, total_questions, attempted_at
        FROM certification_attempts
        WHERE student_id = :sid AND course_id = :cid
        ORDER BY attempted_at DESC
        LIMIT 10
    ");
    $stmt->execute(['sid' => $_SESSION['user_id'], 'cid' => $courseId]);
    $attempts = $stmt->fetchAll();

    $recent = $pdo->prepare("
        SELECT COUNT(*) FROM certification_attempts
        WHERE student_id = :sid AND course_id = :cid
          AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $recent->execute(['sid' => $_SESSION['user_id'], 'cid' => $courseId]);
    $used24h = (int)$recent->fetchColumn();

    echo json_encode([
        'success'  => true,
        'attempts' => $attempts,
        'used_24h' => $used24h,
        'left_24h' => max(0, 3 - $used24h),
    ]);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'get-exam-attempts');
}
