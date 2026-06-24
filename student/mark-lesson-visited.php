<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

// requireCsrf();

$lessonId = (int)($_POST['lesson_id'] ?? 0);
$courseId = (int)($_POST['course_id'] ?? 0);
$studentId = (int)$_SESSION['user_id'];

if ($lessonId <= 0 || $courseId <= 0 || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
    exit;
}

try {
    $pdo = Database::getInstance();
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
]);    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'mark-lesson-visited');
}
