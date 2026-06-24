<?php
declare(strict_types=1);

/**
 * API JSON — notes des apprenants pour un cours (enseignant titulaire).
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/TeacherGradesService.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$courseId  = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$teacherId = (int)$_SESSION['user_id'];

if ($courseId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Identifiant de cours non valide.']);
    exit;
}

try {
    $service = new TeacherGradesService(Database::getInstance());
    $course  = $service->assertCourseOwnership($courseId, $teacherId);

    if (!$course) {
        echo json_encode(['success' => false, 'message' => 'Cours introuvable ou non assigné.']);
        exit;
    }

    echo json_encode([
        'success'                 => true,
        'course'                  => [
            'id'           => (int)$course['id'],
            'title'        => $course['title'],
            'module_title' => $course['module_title'],
        ],
        'lesson_grades'           => $service->fetchLessonGrades($courseId),
        'certification_attempts'  => $service->fetchCertificationAttempts($courseId),
        'module_certificates'     => $service->fetchModuleCertificates($courseId),
        'enrolled_students'       => $service->fetchEnrolledStudents($courseId),
    ]);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'get-grades.php');
}
