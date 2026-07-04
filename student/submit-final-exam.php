<?php
/**
 * StudyVibe Academic LMS - Final Exam Submission Processor
 *
 * This controller processes exam responses submitted by the student, verifies gating
 * rules and session validation, computes scores, stores attempts, and automatically
 * issues a PDF certificate upon passing.
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
// SECTION 1: AUTHENTICATION & REQUEST ENTRY GATEKEEPER
// =========================================================================

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Mailer.php';
require_once __DIR__ . '/../lib/CourseSchedule.php';
require_once __DIR__ . '/../lib/ExamSession.php';
require_once __DIR__ . '/../lib/Notifications.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode de requête non autorisée.'
    ]);
    exit;
}

$courseId = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
$studentId = $_SESSION['user_id'];

if ($courseId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Identifiant de cours non valide.'
    ]);
    exit;
}

// =========================================================================
// SECTION 2: ACCESS & TIME-LIMIT GATING CHECKS
// =========================================================================

try {
    $pdo = Database::getInstance();

    // 1. Verify enrollment and progress = 100%
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

    if ((int)$enrollment['progress_percent'] < 100) {
        echo json_encode([
            'success' => false,
            'message' => 'Vous devez terminer le cours à 100% pour passer la certification.'
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

    $sessionError = ExamSession::assertActive($pdo, $studentId, $courseId);
    if ($sessionError) {
        echo json_encode(['success' => false, 'message' => $sessionError]);
        exit;
    }

    // Limite de tentatives (max 3 / 24h) — vérification côté serveur
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM certification_attempts
        WHERE student_id = :student_id AND course_id = :course_id
          AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
    if ((int)$stmt->fetchColumn() >= 3) {
        echo json_encode([
            'success' => false,
            'message' => 'Limite de 3 tentatives par 24 heures atteinte. Réessayez demain.'
        ]);
        exit;
    }

    // =========================================================================
    // SECTION 3: EXAM RESPONSE PROCESSING & SCORE COMPUTATION
    // =========================================================================

    // 2. Fetch correct answers from DB
    $stmt = $pdo->prepare("SELECT id, correct_option FROM course_questions WHERE course_id = :course_id");
    $stmt->execute(['course_id' => $courseId]);
    $questions = $stmt->fetchAll();

    if (empty($questions)) {
        echo json_encode([
            'success' => false,
            'message' => 'Ce cours n\'a pas encore de questions de certification configurées.'
        ]);
        exit;
    }

    $totalQuestions = count($questions);
    $correctCount = 0;

    foreach ($questions as $q) {
        $studentAnswer = isset($_POST['question_' . $q['id']]) ? trim((string)$_POST['question_' . $q['id']]) : '';
        if ($studentAnswer === $q['correct_option']) {
            $correctCount++;
        }
    }

    // Calculate score percentage
    $scorePercent = round(($correctCount / $totalQuestions) * 100, 2);
    $passed = $scorePercent >= 80.00 ? 1 : 0;

    // 3. Log attempt
    $stmt = $pdo->prepare("
        INSERT INTO certification_attempts (student_id, course_id, score, passed, total_questions)
        VALUES (:student_id, :course_id, :score, :passed, :total_questions)
    ");
    $stmt->execute([
        'student_id' => $studentId,
        'course_id' => $courseId,
        'score' => $scorePercent,
        'passed' => $passed,
        'total_questions' => $totalQuestions,
    ]);
    $attemptId = (int)$pdo->lastInsertId();
    ExamSession::markSubmitted($pdo, $studentId, $courseId);

    // =========================================================================
    // SECTION 4: CERTIFICATION GENERATION & TRANSACTION HOOKS
    // =========================================================================

    // 4. If passed, issue Module Certificate
    $certCode = null;
    if ($passed === 1) {
        // Fetch parent module of the course
        $stmt = $pdo->prepare("SELECT module_id FROM courses WHERE id = :id");
        $stmt->execute(['id' => $courseId]);
        $moduleId = (int)$stmt->fetchColumn();

        if ($moduleId > 0) {
            // Check if certificate already exists
            $stmt = $pdo->prepare("SELECT id, certificate_code FROM certificates WHERE student_id = :student_id AND module_id = :module_id");
            $stmt->execute(['student_id' => $studentId, 'module_id' => $moduleId]);
            $existingCert = $stmt->fetch();

            if (!$existingCert) {
                $certCode = 'SV-' . $moduleId . '-' . strtoupper(substr(md5(uniqid((string)$studentId, true)), 0, 8));

                $stmt = $pdo->prepare("
                    INSERT INTO certificates (student_id, module_id, certificate_code)
                    VALUES (:student_id, :module_id, :certificate_code)
                ");
                $stmt->execute([
                    'student_id' => $studentId,
                    'module_id' => $moduleId,
                    'certificate_code' => $certCode
                ]);
            } else {
                $certCode = $existingCert['certificate_code'];
            }

            // Badge + email certification
            $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_type) VALUES (:sid, 'certified')")
                ->execute(['sid' => $studentId]);

            $userStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = :id");
            $userStmt->execute(['id' => $studentId]);
            $studentUser = $userStmt->fetch();

            $modStmt = $pdo->prepare("SELECT title FROM modules WHERE id = :id");
            $modStmt->execute(['id' => $moduleId]);
            $moduleTitle = (string)$modStmt->fetchColumn();

            if ($studentUser && $certCode) {
                Mailer::certification($studentUser['email'], $studentUser['name'], $moduleTitle, $certCode);
                Notifications::send(
                    $pdo,
                    $studentId,
                    'certification',
                    'Certificat obtenu',
                    'Module : ' . $moduleTitle,
                    '/certificate.php?code=' . urlencode($certCode)
                );
            }

            auditLog('certification_passed', "Course #{$courseId}, Score: {$scorePercent}%");
        }
    } else {
        auditLog('certification_failed', "Course #{$courseId}, Score: {$scorePercent}%");
    }

    echo json_encode([
        'success' => true,
        'score' => $scorePercent,
        'passed' => (bool)$passed,
        'certificate_code' => $certCode,
        'attempt_id' => $attemptId,
        'report_url' => !$passed ? '/student/certification-report.php?attempt_id=' . $attemptId : null,
    ]);
    exit;

} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'submit-final-exam.php');
}
