<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

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

    // Fetch Course details
    $courseStmt = $pdo->prepare("
        SELECT c.id, c.title, m.title AS module_title 
        FROM courses c
        JOIN modules m ON c.module_id = m.id
        WHERE c.id = :id
    ");
    $courseStmt->execute(['id' => $courseId]);
    $course = $courseStmt->fetch();

    // Fetch Chapters
    $chapterStmt = $pdo->prepare("SELECT id, title FROM chapters WHERE course_id = :course_id ORDER BY sort_order ASC, id ASC");
    $chapterStmt->execute(['course_id' => $courseId]);
    $chapters = $chapterStmt->fetchAll();

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
    }

    echo json_encode([
        'success' => true,
        'course' => $course,
        'chapters' => $chapters
    ]);
    exit;

} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'get-course-details.php');
}
