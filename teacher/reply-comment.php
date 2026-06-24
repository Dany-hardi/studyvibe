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
$reply     = trim((string)($_POST['reply_text'] ?? ''));

if ($commentId <= 0 || $reply === '') {
    echo json_encode(['success' => false, 'message' => 'Réponse invalide.']);
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

    $pdo->prepare("
        UPDATE lesson_comments SET teacher_reply = :reply, replied_by = :uid, replied_at = NOW()
        WHERE id = :id
    ")->execute(['reply' => $reply, 'uid' => $_SESSION['user_id'], 'id' => $commentId]);

    auditLog('comment_replied', "Comment #{$commentId}");
    echo json_encode(['success' => true, 'message' => 'Réponse publiée.']);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'reply-comment');
}
