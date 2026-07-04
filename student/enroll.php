<?php
/**
 * StudyVibe Academic LMS - Course Enrollment Handler
 *
 * This controller processes requests by students to enroll in a course.
 * It verifies enrollment keys (if required) and schedules constraints.
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
$providedKey = isset($_POST['enrollment_key']) ? trim((string)$_POST['enrollment_key']) : null;

if ($courseId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Identifiant de cours non valide.'
    ]);
    exit;
}

// =========================================================================
// SECTION 2: COURSE VALIDATION & STATUS CHECKS
// =========================================================================

try {
    $pdo = Database::getInstance();

    // Fetch the course and its enrollment key
    $stmt = $pdo->prepare("SELECT id, enrollment_key, start_date, end_date, is_published FROM courses WHERE id = :id");
    $stmt->execute(['id' => $courseId]);
    $course = $stmt->fetch();

    if (!$course) {
        echo json_encode([
            'success' => false,
            'message' => 'Cours introuvable.'
        ]);
        exit;
    }

    $scheduleError = CourseSchedule::canEnroll($course);
    if ($scheduleError) {
        echo json_encode(['success' => false, 'message' => $scheduleError]);
        exit;
    }

    // Check key requirements
    if ($course['enrollment_key'] !== null && $course['enrollment_key'] !== '') {
        if ($providedKey !== $course['enrollment_key']) {
            echo json_encode([
                'success' => false,
                'message' => 'Clé d\'inscription incorrecte.'
            ]);
            exit;
        }
    }

    // =========================================================================
    // SECTION 3: STUDENT ENROLLMENT RECORD TRANSACTION
    // =========================================================================

    // Enroll the student
    $stmt = $pdo->prepare("
        INSERT INTO enrollments (student_id, course_id, progress_percent)
        VALUES (:student_id, :course_id, 0)
        ON DUPLICATE KEY UPDATE enrolled_at = enrolled_at
    ");
    $stmt->execute([
        'student_id' => $_SESSION['user_id'],
        'course_id' => $courseId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Inscription validée.'
    ]);
    exit;

} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'enroll.php');
}
