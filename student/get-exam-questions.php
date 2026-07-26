<?php
/**
 * StudyVibe Academic LMS - Student Certification Questions Fetcher
 *
 * This controller validates student enrollment progress, rate limits, and scheduling.
 * It retrieves a randomized set of questions for a course's final assessment
 * with hidden solutions for secure delivery.
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
require_once __DIR__ . '/../lib/CourseSchedule.php';
require_once __DIR__ . '/../lib/ExamSession.php';

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
// SECTION 2: ENROLLMENT & RATE-LIMIT VALIDATION
// =========================================================================

try {
    $pdo = Database::getInstance();

    // Verify enrollment and check if progress = 100%
    $enrollStmt = $pdo->prepare("SELECT progress_percent FROM enrollments WHERE student_id = :student_id AND course_id = :course_id");
    $enrollStmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
    $enrollment = $enrollStmt->fetch();

    if (!$enrollment) {
        echo json_encode([
            'success' => false,
            'message' => 'Vous n\'êtes pas inscrit à ce cours.'
        ]);
        exit;
    }

    if (!hasCompletedAllLessons($studentId, $courseId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Vous devez valider toutes les leçons obligatoires du cours avant d\'accéder à l\'évaluation de certification.'
        ]);
        exit;
    }

    // Vérifier la limite de tentatives (max 3 par 24h)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM certification_attempts
        WHERE student_id = :student_id AND course_id = :course_id
          AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
    $attemptsToday = (int)$stmt->fetchColumn();

    if ($attemptsToday >= 3) {
        echo json_encode([
            'success' => false,
            'message' => 'Vous avez atteint la limite de 3 tentatives par 24 heures. Réessayez demain.'
        ]);
        exit;
    }

    $course = CourseSchedule::fetchCourse($pdo, $courseId);
    if (!$course) {
        echo json_encode(['success' => false, 'message' => 'Cours introuvable.']);
        exit;
    }
    $examBlock = CourseSchedule::canTakeExam($course);
    if ($examBlock) {
        echo json_encode(['success' => false, 'message' => $examBlock]);
        exit;
    }

    // =========================================================================
    // SECTION 3: EXAM TIMELINE SCHEDULING & SECURE QUESTION INGESTION
    // =========================================================================

    $examMinutes = (int)($course['exam_duration_minutes'] ?: 90);
    $session = ExamSession::startOrResume($pdo, $studentId, $courseId, $examMinutes);
    $expiresAt = strtotime($session['expires_at']);
    $secondsLeft = max(0, $expiresAt - time());

    // Fetch certification questions (excluding correct_option for client-side security)
    $stmt = $pdo->prepare("
        SELECT id, question_text, option_a, option_b, option_c, option_d 
        FROM course_questions 
        WHERE course_id = :course_id 
        ORDER BY id ASC
    ");
    $stmt->execute(['course_id' => $courseId]);
    $questions = $stmt->fetchAll();

    // Mélange aléatoire Fisher-Yates
    $n = count($questions);
    for ($i = $n - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$questions[$i], $questions[$j]] = [$questions[$j], $questions[$i]];
    }

    echo json_encode([
        'success'       => true,
        'questions'     => $questions,
        'attempts_used' => $attemptsToday,
        'attempts_left' => max(0, 3 - $attemptsToday),
        'exam_minutes'  => $examMinutes,
        'expires_at'    => $session['expires_at'],
        'seconds_left'  => $secondsLeft,
    ]);
    exit;

} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'get-exam-questions.php');
}
