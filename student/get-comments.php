<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$lessonId = (int)($_GET['lesson_id'] ?? 0);
if ($lessonId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Leçon invalide.']);
    exit;
}

try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare("
        SELECT lc.*, u.name AS author_name, u.role AS author_role
        FROM lesson_comments lc
        JOIN users u ON u.id = lc.user_id
        WHERE lc.lesson_id = :lid AND lc.is_hidden = 0
        ORDER BY lc.created_at ASC
    ");
    $stmt->execute(['lid' => $lessonId]);
    echo json_encode(['success' => true, 'comments' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'get-comments.php');
}
