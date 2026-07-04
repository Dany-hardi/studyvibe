<?php
/**
 * StudyVibe Academic LMS - Student Academic Transcript Data Provider
 *
 * This controller compiles course progress percentages, completed lesson scores,
 * and failed certification exam attempts for a student's profile.
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

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$studentId = (int)$_SESSION['user_id'];

// =========================================================================
// SECTION 2: FETCH TRANSCRIPT RECORDS
// =========================================================================

try {
    $pdo = Database::getInstance();

    $stmt = $pdo->prepare("
        SELECT c.title AS course_title, m.title AS module_title,
               e.progress_percent,
               (SELECT MAX(ca.score) FROM certification_attempts ca
                WHERE ca.student_id = e.student_id AND ca.course_id = e.course_id) AS best_score,
               (SELECT COUNT(*) FROM certification_attempts ca
                WHERE ca.student_id = e.student_id AND ca.course_id = e.course_id) AS attempts
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        JOIN modules m ON m.id = c.module_id
        WHERE e.student_id = :sid
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute(['sid' => $studentId]);
    $courses = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT l.title AS lesson_title, c.title AS course_title, lp.score, lp.completed_at
        FROM lesson_progress lp
        JOIN lessons l ON l.id = lp.lesson_id
        JOIN chapters ch ON ch.id = l.chapter_id
        JOIN courses c ON c.id = ch.course_id
        WHERE lp.student_id = :sid AND lp.completed = 1 AND lp.score IS NOT NULL
        ORDER BY lp.completed_at DESC
        LIMIT 50
    ");
    $stmt->execute(['sid' => $studentId]);
    $lessonScores = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT ca.id, ca.score, ca.attempted_at, ca.total_questions,
               c.title AS course_title, m.title AS module_title
        FROM certification_attempts ca
        JOIN courses c ON c.id = ca.course_id
        JOIN modules m ON m.id = c.module_id
        WHERE ca.student_id = :sid AND ca.passed = 0
        ORDER BY ca.attempted_at DESC
    ");
    $stmt->execute(['sid' => $studentId]);
    $failedAttempts = $stmt->fetchAll();

    foreach ($failedAttempts as &$fa) {
        $fa['report_url'] = '/student/certification-report.php?attempt_id=' . (int)$fa['id'];
    }
    unset($fa);

    echo json_encode([
        'success'           => true,
        'courses'           => $courses,
        'lesson_scores'     => $lessonScores,
        'failed_attempts'   => $failedAttempts,
        'releve_pdf_url'    => '/student/releve.php',
    ]);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'get-transcript.php');
}
