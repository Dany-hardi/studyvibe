<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$teacherId = (int)$_SESSION['user_id'];

try {
    $pdo = Database::getInstance();

    $stmt = $pdo->prepare("
        SELECT c.id, c.title,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS enrollments,
               (SELECT COALESCE(AVG(e.progress_percent), 0) FROM enrollments e WHERE e.course_id = c.id) AS avg_progress,
               (SELECT COALESCE(ROUND(SUM(ca.passed)/NULLIF(COUNT(*),0)*100,1), 0)
                FROM certification_attempts ca WHERE ca.course_id = c.id) AS pass_rate,
               (SELECT COALESCE(AVG(ca.score), 0) FROM certification_attempts ca WHERE ca.course_id = c.id) AS avg_score
        FROM courses c
        WHERE c.teacher_id = :tid
        ORDER BY c.title ASC
    ");
    $stmt->execute(['tid' => $teacherId]);
    echo json_encode(['success' => true, 'courses' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'get-stats.php');
}
