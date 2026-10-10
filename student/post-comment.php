<?php
/**
 * StudyVibe Academic LMS - Lesson Comment Submission Processor
 *
 * This controller processes comments posted by authenticated users on lessons,
 * performs length and parameter checks, and writes them to the comments table.
 *
 * PHP version 8.2
 *
 * @category  Controller
 * @package   StudyVibe\Student
 * @author    StudyVibe Team <development@studyvibe.academic>
 * @copyright 2026 StudyVibe
 * @license   Proprietary
 * @link      https://studyvibe.academic
 */

declare(strict_types=1);

// =========================================================================
// SECTION 1: AUTHENTICATION & INPUT PARAMETERS SECURITY
// =========================================================================

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/LessonQA.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}
Security::requirePostFromSameSite();   // also refuses anything but POST

$lessonId = (int)($_POST['lesson_id'] ?? 0);
$text     = (string)($_POST['comment_text'] ?? '');
$parentId = isset($_POST['parent_id']) && (int)$_POST['parent_id'] > 0 ? (int)$_POST['parent_id'] : null;

// =========================================================================
// SECTION 2: COMMENT INSERTION (access, length, thread and rate checks live in lib/LessonQA.php; the teacher is notified there)
// =========================================================================

try {
    $pdo  = Database::getInstance();
    $user = getCurrentUser();
    $res  = LessonQA::post($pdo, ['id' => (int)$_SESSION['user_id'], 'role' => (string)$_SESSION['user_role'], 'name' => (string)($user['name'] ?? '')], $lessonId, $text, $parentId);
    if (!$res['ok']) {
        $msg = [
            'not_found' => 'Leçon introuvable.', 'forbidden' => 'Vous ne pouvez pas commenter une leçon d’un cours auquel vous n’êtes pas inscrit.',
            'empty' => 'Commentaire invalide.', 'too_long' => 'Commentaire trop long (max ' . LessonQA::MAX . ' caractères).',
            'bad_parent' => 'Le message auquel vous répondez est introuvable.', 'too_many' => 'Trop de messages en peu de temps. Réessayez dans un moment.',
        ][$res['error']] ?? 'Commentaire invalide.';
        http_response_code(['forbidden' => 403, 'not_found' => 404, 'too_many' => 429][$res['error']] ?? 200);
        echo json_encode(['success' => false, 'error' => $res['error'], 'message' => $msg]);
        exit;
    }
    auditLog('comment_posted', "Lesson #{$lessonId}");
    echo json_encode(['success' => true, 'comment' => $res['comment']]);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'post-comment.php');
}
