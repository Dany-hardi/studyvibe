<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}


// requireCsrf();
$lessonId = (int)($_POST['lesson_id'] ?? 0);
$text     = trim((string)($_POST['comment_text'] ?? ''));
$parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

if ($lessonId <= 0 || $text === '') {
    echo json_encode(['success' => false, 'message' => 'Commentaire invalide.']);
    exit;
}

if (mb_strlen($text) > 2000) {
    echo json_encode(['success' => false, 'message' => 'Commentaire trop long (max 2000 caractères).']);
    exit;
}

try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare("INSERT INTO lesson_comments (lesson_id, user_id, comment_text, parent_id) VALUES (:lid, :uid, :text, :pid)");
    $stmt->execute([
        'lid'  => $lessonId,
        'uid'  => (int)$_SESSION['user_id'],
        'text' => $text,
        'pid'  => $parentId ?: null,
    ]);

    auditLog('comment_posted', "Lesson #{$lessonId}");
    $user = getCurrentUser();

    echo json_encode([
        'success' => true,
        'comment' => [
            'id'           => (int)$pdo->lastInsertId(),
            'author_name'  => $user['name'] ?? 'Utilisateur',
            'author_role'  => $_SESSION['user_role'],
            'comment_text' => $text,
            'created_at'   => date('Y-m-d H:i:s'),
        ],
    ]);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'post-comment.php');
}
