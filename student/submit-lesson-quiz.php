<?php
declare(strict_types=1);

/**
 * Soumission du mini-quiz de leçon — une question à la fois.
 * POST: lesson_id, question_id, answer (A-D) | no_quiz=true
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Notifications.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode de requête non autorisée.']);
    exit;
}


// requireCsrf();
$lessonId   = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
$questionId = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
$noQuiz       = isset($_POST['no_quiz']) && $_POST['no_quiz'] === 'true';
$markComplete = isset($_POST['mark_complete']) && $_POST['mark_complete'] === 'true';
$studentId  = $_SESSION['user_id'];

if ($lessonId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Identifiant de leçon non valide.']);
    exit;
}

try {
    $pdo = Database::getInstance();

    // Vérifier que la leçon existe et récupérer le cours parent
    $stmt = $pdo->prepare("
        SELECT l.id, l.quiz_deadline, c.id AS course_id
        FROM lessons l
        JOIN chapters ch ON l.chapter_id = ch.id
        JOIN courses c ON ch.course_id = c.id
        WHERE l.id = :id
    ");
    $stmt->execute(['id' => $lessonId]);
    $lessonData = $stmt->fetch();

    if (!$lessonData) {
        echo json_encode(['success' => false, 'message' => 'Leçon introuvable.']);
        exit;
    }

    $courseId = (int)$lessonData['course_id'];

    // Vérifier l'inscription au cours
    $enrollStmt = $pdo->prepare(
        "SELECT id FROM enrollments WHERE student_id = :student_id AND course_id = :course_id"
    );
    $enrollStmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
    if (!$enrollStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas inscrit au cours correspondant.']);
        exit;
    }

    // Progression de la leçon pour vérifier si elle est déjà complétée
    $progressStmt = $pdo->prepare("
        SELECT completed FROM lesson_progress WHERE student_id = :student_id AND lesson_id = :lesson_id
    ");
    $progressStmt->execute(['student_id' => $studentId, 'lesson_id' => $lessonId]);
    $completedVal = $progressStmt->fetchColumn();
    $completed = $completedVal && (int)$completedVal === 1;

    // Gating : Bloquer si la leçon n'est pas complétée et que la date limite du quiz est dépassée
    if (!$completed && !empty($lessonData['quiz_deadline']) && strtotime($lessonData['quiz_deadline']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Le délai pour soumettre vos réponses à cette leçon/quiz a expiré.']);
        exit;
    }

    if (!isLessonContentConsumed($pdo, $studentId, $lessonId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Vous devez terminer la lecture ou la vidéo avant de valider cette leçon.',
        ]);
        exit;
    }

    // Marquer la leçon complétée (sans quiz ou action manuelle de l'étudiant)
    if ($noQuiz || $markComplete) {
        completeLesson($pdo, $studentId, $lessonId, $courseId);
        echo json_encode([
            'success'         => true,
            'lesson_complete' => true,
            'progress_percent'=> getCourseProgress($pdo, $studentId, $courseId),
        ]);
        exit;
    }

    if ($questionId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Identifiant de question non valide.']);
        exit;
    }

    if (!isLessonContentConsumed($pdo, $studentId, $lessonId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Terminez d\'abord la lecture ou la vidéo pour accéder à l\'évaluation.',
        ]);
        exit;
    }

    $studentAnswer = isset($_POST['answer']) ? strtoupper(trim((string)$_POST['answer'])) : '';
    if (!in_array($studentAnswer, ['A', 'B', 'C', 'D'], true)) {
        echo json_encode(['success' => false, 'message' => 'Réponse non valide.']);
        exit;
    }

    // Charger la question et vérifier qu'elle appartient à la leçon
    $stmt = $pdo->prepare("
        SELECT id, correct_option, option_a, option_b, option_c, option_d
        FROM lesson_questions
        WHERE id = :qid AND lesson_id = :lid
    ");
    $stmt->execute(['qid' => $questionId, 'lid' => $lessonId]);
    $question = $stmt->fetch();

    if (!$question) {
        echo json_encode(['success' => false, 'message' => 'Question introuvable.']);
        exit;
    }

    $correctOption = strtoupper((string)$question['correct_option']);
    $isCorrect     = $studentAnswer === $correctOption;
    $optionKey     = 'option_' . strtolower($correctOption);
    $correctText   = $question[$optionKey] ?? '';

    // Enregistrer la réponse (correcte ou incorrecte et l'option choisie)
    $pdo->prepare("
        INSERT INTO lesson_question_answers (student_id, question_id, answered_correctly, selected_option)
        VALUES (:sid, :qid, :correct1, :sel1)
        ON DUPLICATE KEY UPDATE answered_correctly = :correct2, selected_option = :sel2, answered_at = CURRENT_TIMESTAMP
    ")->execute([
        'sid'      => $studentId,
        'qid'      => $questionId,
        'correct1' => $isCorrect ? 1 : 0,
        'sel1'     => $studentAnswer,
        'correct2' => $isCorrect ? 1 : 0,
        'sel2'     => $studentAnswer
    ]);

    // Compter les questions totales vs répondues (correctes ou incorrectes)
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_questions WHERE lesson_id = :lid");
    $countStmt->execute(['lid' => $lessonId]);
    $totalQuestions = (int)$countStmt->fetchColumn();

    $answeredStmt = $pdo->prepare("
        SELECT COUNT(*) FROM lesson_question_answers lqa
        JOIN lesson_questions lq ON lqa.question_id = lq.id
        WHERE lqa.student_id = :sid AND lq.lesson_id = :lid
    ");
    $answeredStmt->execute(['sid' => $studentId, 'lid' => $lessonId]);
    $answeredCount = (int)$answeredStmt->fetchColumn();

    // Compter le nombre de bonnes réponses pour calculer le score final
    $correctStmt = $pdo->prepare("
        SELECT COUNT(*) FROM lesson_question_answers lqa
        JOIN lesson_questions lq ON lqa.question_id = lq.id
        WHERE lqa.student_id = :sid AND lq.lesson_id = :lid AND lqa.answered_correctly = 1
    ");
    $correctStmt->execute(['sid' => $studentId, 'lid' => $lessonId]);
    $correctCount = (int)$correctStmt->fetchColumn();

    $lessonComplete = $totalQuestions > 0 && $answeredCount >= $totalQuestions;
    $calculatedScore = $totalQuestions > 0 ? (int)round(($correctCount / $totalQuestions) * 100) : 100;

    if ($lessonComplete) {
        completeLesson($pdo, $studentId, $lessonId, $courseId, $calculatedScore);

        // Envoi de la copie des réponses par email à l'étudiant
        try {
            $studentStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = :sid");
            $studentStmt->execute(['sid' => $studentId]);
            $studentInfo = $studentStmt->fetch();

            $infoStmt = $pdo->prepare("
                SELECT l.title AS lesson_title, c.title AS course_title
                FROM lessons l
                JOIN chapters ch ON l.chapter_id = ch.id
                JOIN courses c ON ch.course_id = c.id
                WHERE l.id = :lid
            ");
            $infoStmt->execute(['lid' => $lessonId]);
            $lessonCourseInfo = $infoStmt->fetch();

            $qaStmt = $pdo->prepare("
                SELECT lq.question_text, lq.option_a, lq.option_b, lq.option_c, lq.option_d, lq.correct_option, lqa.selected_option, lqa.answered_correctly
                FROM lesson_questions lq
                LEFT JOIN lesson_question_answers lqa ON lqa.question_id = lq.id AND lqa.student_id = :sid
                WHERE lq.lesson_id = :lid
                ORDER BY lq.id ASC
            ");
            $qaStmt->execute(['sid' => $studentId, 'lid' => $lessonId]);
            $quizQas = $qaStmt->fetchAll();

            if ($studentInfo && $lessonCourseInfo && !empty($quizQas)) {
                require_once __DIR__ . '/../Mailer.php';
                Mailer::quizResults(
                    $studentInfo['email'],
                    $studentInfo['name'],
                    $lessonCourseInfo['lesson_title'],
                    $lessonCourseInfo['course_title'],
                    $calculatedScore,
                    $quizQas
                );
            }
        } catch (Exception $mailEx) {
            // Sécurité : ne pas faire planter la requête en cas de problème de connexion mail
        }
    }

    echo json_encode([
        'success'          => true,
        'correct'          => $isCorrect,
        'correct_option'   => $correctOption,
        'correct_text'     => $correctText,
        'lesson_complete'  => $lessonComplete,
        'remaining_count'  => max(0, $totalQuestions - $answeredCount),
        'progress_percent' => $lessonComplete ? getCourseProgress($pdo, $studentId, $courseId) : null,
    ]);

} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'submit-lesson-quiz.php');
}

/** Vérifie que l'étudiant a consommé le contenu pédagogique de la leçon. */
function isLessonContentConsumed(PDO $pdo, int $studentId, int $lessonId): bool
{
    $stmt = $pdo->prepare(
        "SELECT content_consumed, completed FROM lesson_progress WHERE student_id = :student_id AND lesson_id = :lesson_id"
    );
    $stmt->execute(['student_id' => $studentId, 'lesson_id' => $lessonId]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    return (int)$row['content_consumed'] === 1 || (int)$row['completed'] === 1;
}

/** Marque la leçon complétée et met à jour la progression du cours. */
function completeLesson(PDO $pdo, int $studentId, int $lessonId, int $courseId, int $score = 100): void
{
    $pdo->prepare("
        INSERT INTO lesson_progress (student_id, lesson_id, completed, score)
        VALUES (:student_id, :lesson_id, 1, :score1)
        ON DUPLICATE KEY UPDATE completed = 1, score = :score2
    ")->execute([
        'student_id' => $studentId,
        'lesson_id'  => $lessonId,
        'score1'     => $score,
        'score2'     => $score
    ]);

    $progressPercent = getCourseProgress($pdo, $studentId, $courseId);

    $pdo->prepare("
        UPDATE enrollments SET progress_percent = :progress_percent
        WHERE student_id = :student_id AND course_id = :course_id
    ")->execute([
        'progress_percent' => $progressPercent,
        'student_id'       => $studentId,
        'course_id'        => $courseId,
    ]);

    $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_type) VALUES (:sid, 'first_lesson')")
        ->execute(['sid' => $studentId]);

    if ($progressPercent >= 100) {
        $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_type) VALUES (:sid, 'course_complete')")
            ->execute(['sid' => $studentId]);
    }

    // Envoi d'une notification in-app
    $lstmt = $pdo->prepare("SELECT title FROM lessons WHERE id = :id");
    $lstmt->execute(['id' => $lessonId]);
    $lessonTitle = $lstmt->fetchColumn() ?: 'Leçon';
    Notifications::send($pdo, $studentId, 'lesson_complete', 'Leçon complétée !', "Félicitations, vous avez complété la leçon \"$lessonTitle\" avec un score de $score%.");
}

/** Calcule le pourcentage de progression d'un cours pour un étudiant. */
function getCourseProgress(PDO $pdo, int $studentId, int $courseId): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM lessons l
        JOIN chapters ch ON l.chapter_id = ch.id
        WHERE ch.course_id = :course_id
    ");
    $stmt->execute(['course_id' => $courseId]);
    $totalLessons = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT lp.lesson_id)
        FROM lesson_progress lp
        JOIN lessons l ON lp.lesson_id = l.id
        JOIN chapters ch ON l.chapter_id = ch.id
        WHERE lp.student_id = :student_id AND ch.course_id = :course_id AND lp.completed = 1
    ");
    $stmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
    $completedLessons = (int)$stmt->fetchColumn();

    return $totalLessons > 0 ? (int)round(($completedLessons / $totalLessons) * 100) : 100;
}
