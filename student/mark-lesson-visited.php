<?php
/**
 * StudyVibe Academic LMS - Mark Lesson Visited Handler
 *
 * This controller records the student's last visited lesson within a course enrollment,
 * enabling auto-resuming from the exact position on subsequent dashboard visits.
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
require_once __DIR__ . '/../lib/LessonFlow.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$lessonId = (int)($_POST['lesson_id'] ?? 0);
$courseId = (int)($_POST['course_id'] ?? 0);
$studentId = (int)$_SESSION['user_id'];

if ($lessonId <= 0 || $courseId <= 0 || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
    exit;
}

// =========================================================================
// SECTION 2: LAST VISITED LESSON STATE UPDATE
// =========================================================================

try {
    $pdo = Database::getInstance();
    if (LessonFlow::blockerFor($pdo, $studentId, $courseId, $lessonId) !== null) {
        echo json_encode(['success' => false, 'code' => 'locked']);
        exit;
    }
    $stmt = $pdo->prepare("
        UPDATE enrollments e
        JOIN lessons l ON l.id = :lid
        JOIN chapters ch ON ch.id = l.chapter_id AND ch.course_id = :cid
        SET e.last_lesson_id = :lid2
        WHERE e.student_id = :sid AND e.course_id = :cid2
    ");
    $stmt->execute([
        'lid'  => $lessonId,
        'lid2' => $lessonId,
        'cid'  => $courseId,
        'cid2' => $courseId,
        'sid'  => $studentId,
    ]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'mark-lesson-visited');
}
