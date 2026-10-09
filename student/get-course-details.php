<?php
/**
 * StudyVibe Academic LMS - Student Course Details Provider
 *
 * This controller serves the details of a course, its chapters, and associated
 * lessons including current student completion progress status.
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

if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé.'
    ]);
    exit;
}

$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$studentId = $_SESSION['user_id'];

if ($courseId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Identifiant de cours non valide.'
    ]);
    exit;
}

// =========================================================================
// SECTION 2: ENROLLMENT & COURSE SYLLABUS DATA INGESTION
// =========================================================================

try {
    $pdo = Database::getInstance();

    // Verify enrollment
    $enrollStmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = :student_id AND course_id = :course_id");
    $enrollStmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
    if (!$enrollStmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Vous n\'êtes pas inscrit à ce cours.'
        ]);
        exit;
    }

    // Fetch Course details & enrollment last_lesson_id
    $courseStmt = $pdo->prepare("
        SELECT c.id, c.title, m.title AS module_title, e.last_lesson_id
        FROM courses c
        JOIN modules m ON c.module_id = m.id
        LEFT JOIN enrollments e ON e.course_id = c.id AND e.student_id = :student_id
        WHERE c.id = :id
    ");
    $courseStmt->execute(['id' => $courseId, 'student_id' => $studentId]);
    $course = $courseStmt->fetch();

    // Fetch Chapters
    $chapterStmt = $pdo->prepare("SELECT id, title FROM chapters WHERE course_id = :course_id ORDER BY sort_order ASC, id ASC");
    $chapterStmt->execute(['course_id' => $courseId]);
    $chapters = $chapterStmt->fetchAll();

    $flow = LessonFlow::status($pdo, (int)$studentId, $courseId);

    foreach ($chapters as &$ch) {
        // Fetch Lessons for this chapter
        $lessonStmt = $pdo->prepare("
            SELECT l.id, l.title, l.content_type, l.quiz_deadline,
                   COALESCE(lp.completed, 0) AS completed
            FROM lessons l
            LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.student_id = :student_id
            WHERE l.chapter_id = :chapter_id
            ORDER BY l.sort_order ASC, l.id ASC
        ");
        $lessonStmt->execute([
            'student_id' => $studentId,
            'chapter_id' => $ch['id']
        ]);
        $ch['lessons'] = $lessonStmt->fetchAll();
        foreach ($ch['lessons'] as &$lesRow) {
            $st = $flow[(int)$lesRow['id']] ?? ['locked' => false, 'blocker' => null];
            $lesRow['locked']  = $st['locked'] ? 1 : 0;
            $lesRow['blocker'] = $st['blocker'];
        }
        unset($lesRow);
    }
    unset($ch);

    echo json_encode([
        'success' => true,
        'course' => $course,
        'chapters' => $chapters
    ]);
    exit;

} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'get-course-details.php');
}
