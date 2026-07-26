<?php
/**
 * StudyVibe Academic LMS - Student Lesson Details Provider
 *
 * This controller fetches detailed learning material contents, associated video items,
 * and remaining quiz questions for a lesson, enforcing gating timeline checks.
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

$lessonId  = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
$studentId = $_SESSION['user_id'];

if ($lessonId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Identifiant de leçon non valide.']);
    exit;
}

// =========================================================================
// SECTION 2: ACCESS GATING & ENROLLMENT VERIFICATION
// =========================================================================

try {
    $pdo = Database::getInstance();

    $stmt = $pdo->prepare("
        SELECT l.id, l.chapter_id, l.title, l.content_type, l.text_content, l.pdf_path, l.video_url, l.sort_order, l.quiz_deadline, l.has_assignment, l.assignment_title, l.assignment_type, l.allowed_file_types, l.assignment_instructions, l.assignment_deadline, c.id AS course_id
        FROM lessons l
        JOIN chapters ch ON l.chapter_id = ch.id
        JOIN courses c ON ch.course_id = c.id
        WHERE l.id = :id
    ");
    $stmt->execute(['id' => $lessonId]);
    $lesson = $stmt->fetch();

    if (!$lesson) {
        echo json_encode(['success' => false, 'message' => 'Leçon introuvable.']);
        exit;
    }

    $enrollStmt = $pdo->prepare(
        "SELECT id FROM enrollments WHERE student_id = :student_id AND course_id = :course_id"
    );
    $enrollStmt->execute(['student_id' => $studentId, 'course_id' => $lesson['course_id']]);
    if (!$enrollStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas inscrit au cours de cette leçon.']);
        exit;
    }

    // Progression de la leçon
    $progressStmt = $pdo->prepare(
        "SELECT completed, content_consumed FROM lesson_progress WHERE student_id = :student_id AND lesson_id = :lesson_id"
    );
    $progressStmt->execute(['student_id' => $studentId, 'lesson_id' => $lessonId]);
    $progressRow = $progressStmt->fetch();
    $completed = $progressRow && (int)$progressRow['completed'] === 1;
    $contentConsumed = $progressRow && (int)($progressRow['content_consumed'] ?? 0) === 1;

    // Gating : Bloquer l'accès si la date limite est passée et que la leçon n'est pas terminée
    if (!$completed && !empty($lesson['quiz_deadline']) && strtotime($lesson['quiz_deadline']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Le délai d\'accès à cette leçon/quiz a expiré.']);
        exit;
    }

    // =========================================================================
    // SECTION 3: LESSON MEDIA & EVALUATION METRICS INGESTION
    // =========================================================================

    // Récupérer les vidéos associées à la leçon
    $vStmt = $pdo->prepare("SELECT * FROM lesson_videos WHERE lesson_id = :lesson_id ORDER BY sort_order ASC, id ASC");
    $vStmt->execute(['lesson_id' => $lessonId]);
    $videos = $vStmt->fetchAll();

    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_questions WHERE lesson_id = :lesson_id");
    $totalStmt->execute(['lesson_id' => $lessonId]);
    $totalQuestions = (int)$totalStmt->fetchColumn();

    $hasQuiz      = $totalQuestions > 0;
    $questions    = [];

    if ($hasQuiz && ($contentConsumed || $completed)) {
        // Questions du quiz — exclure celles déjà répondues (correctes ou incorrectes)
        $qStmt = $pdo->prepare("
            SELECT lq.id, lq.question_text, lq.option_a, lq.option_b, lq.option_c, lq.option_d
            FROM lesson_questions lq
            WHERE lq.lesson_id = :lesson_id
              AND lq.id NOT IN (
                  SELECT lqa.question_id FROM lesson_question_answers lqa
                  WHERE lqa.student_id = :student_id
              )
            ORDER BY lq.id ASC
        ");
        $qStmt->execute(['lesson_id' => $lessonId, 'student_id' => $studentId]);
        $questions = $qStmt->fetchAll();
    }

    $quizComplete = $completed || ($hasQuiz && ($contentConsumed || $completed) && empty($questions));

    // Récupérer le dépôt de devoir éventuel de cet étudiant pour cette leçon
    $submission = null;
    if (!empty($lesson['has_assignment'])) {
        $subStmt = $pdo->prepare("
            SELECT id, submission_type, submitted_file_path, submitted_file_name, submitted_link, student_comment, submitted_at 
            FROM lesson_assignment_submissions 
            WHERE lesson_id = :lid AND student_id = :sid
        ");
        $subStmt->execute(['lid' => $lessonId, 'sid' => $studentId]);
        $submission = $subStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    echo json_encode([
        'success'          => true,
        'lesson'           => $lesson,
        'videos'           => $videos,
        'has_quiz'         => $hasQuiz && !$quizComplete,
        'quiz_complete'    => $quizComplete,
        'questions'        => $questions,
        'completed'        => $completed || $quizComplete,
        'content_consumed' => $contentConsumed || $completed || $quizComplete,
        'submission'       => $submission,
    ]);

} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'get-lesson-details.php');
}
