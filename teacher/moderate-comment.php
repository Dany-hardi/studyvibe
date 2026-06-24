<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

// requireCsrf();

$commentId = (int)($_POST['comment_id'] ?? 0);
$hidden    = isset($_POST['is_hidden']) ? (int)$_POST['is_hidden'] : 1;

if ($commentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Commentaire invalide.']);
    exit;
}

try {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("
        SELECT lc.id FROM lesson_comments lc
        JOIN lessons l ON l.id = lc.lesson_id
        JOIN chapters ch ON ch.id = l.chapter_id
        JOIN courses c ON c.id = ch.course_id
        WHERE lc.id = :cid AND c.teacher_id = :tid
    ");
    $stmt->execute(['cid' => $commentId, 'tid' => $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Commentaire introuvable.']);
        exit;
    }

    $pdo->prepare("UPDATE lesson_comments SET is_hidden = :h WHERE id = :id")
        ->execute(['h' => $hidden ? 1 : 0, 'id' => $commentId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'moderate-comment');
}
