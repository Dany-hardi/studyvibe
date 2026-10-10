<?php
declare(strict_types=1);

/**
 * The teacher answers a student's question under a lesson. The student is told by notification and email (first answer only).
 *   POST comment_id, reply_text
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/LessonQA.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}
Security::requirePostFromSameSite();

$commentId = (int)($_POST['comment_id'] ?? 0);

try {
    $res = LessonQA::reply(Database::getInstance(), (int)$_SESSION['user_id'], $commentId, (string)($_POST['reply_text'] ?? ''));
    if (!$res['ok']) {
        $msg = ['not_found' => 'Commentaire introuvable.', 'empty' => 'Réponse invalide.', 'too_long' => 'Réponse trop longue (max ' . LessonQA::MAX_REPLY . ' caractères).'][$res['error']] ?? 'Réponse invalide.';
        echo json_encode(['success' => false, 'error' => $res['error'], 'message' => $msg]);
        exit;
    }
    auditLog('comment_replied', "Comment #{$commentId}");
    echo json_encode(['success' => true, 'message' => 'Réponse publiée. L’étudiant est prévenu.']);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'reply-comment');
}
