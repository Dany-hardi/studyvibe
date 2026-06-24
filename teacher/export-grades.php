<?php
declare(strict_types=1);

/**
 * Export Excel des notes apprenants pour un cours (enseignant).
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/SpreadsheetExporter.php';
require_once __DIR__ . '/../lib/TeacherGradesService.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    header('Location: /index.php');
    exit;
}

$courseId  = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$teacherId = (int)$_SESSION['user_id'];

if ($courseId <= 0) {
    http_response_code(400);
    exit('Identifiant de cours non valide.');
}

try {
    $service = new TeacherGradesService(Database::getInstance());
    $course  = $service->assertCourseOwnership($courseId, $teacherId);

    if (!$course) {
        http_response_code(403);
        exit('Cours introuvable ou non assigné.');
    }

    $sheets   = $service->buildGradeSheets($courseId, (string)$course['title']);
    $slug     = preg_replace('/[^a-z0-9_-]+/i', '_', (string)$course['title']) ?: 'cours';
    $filename = 'notes_' . mb_strtolower($slug) . '_' . date('Y-m-d');

    auditLog('export_teacher_grades', "Cours #{$courseId}");
    SpreadsheetExporter::sendDownload($filename, $sheets);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'export-grades.php');
}
