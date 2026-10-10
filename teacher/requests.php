<?php
declare(strict_types=1);

/**
 * The teacher's inbox of requests from students, and the answer.
 *
 *   GET  [course_id=]                  open requests first, then the latest answered ones
 *   POST action=answer id=7 response=...   answers (once); the student is told by notification and email
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/StudentRequests.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}
$teacherId = (int)$_SESSION['user_id'];

try {
    $pdo = Database::getInstance();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requirePostFromSameSite();
        if ((string)($_POST['action'] ?? '') !== 'answer') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
            exit;
        }
        $res = StudentRequests::answer($pdo, $teacherId, (int)($_POST['id'] ?? 0), (string)($_POST['response'] ?? ''));
        if (!$res['ok']) {
            $m = ['not_found' => 'Demande introuvable.', 'already' => 'Cette demande a déjà reçu une réponse.', 'too_short' => 'Écrivez une réponse d’au moins ' . StudentRequests::MIN_ANSWER . ' caractères.', 'too_long' => 'Réponse trop longue.'][$res['error']] ?? 'Erreur.';
            http_response_code($res['error'] === 'not_found' ? 404 : 200);
            echo json_encode(['success' => false, 'error' => $res['error'], 'message' => $m]);
            exit;
        }
        auditLog('student_request_answered', 'Request #' . (int)$_POST['id']);
        echo json_encode(['success' => true, 'message' => 'Réponse envoyée. L’étudiant est prévenu.']);
        exit;
    }
    $course = (int)($_GET['course_id'] ?? 0) ?: null;
    echo json_encode(['success' => true, 'open' => StudentRequests::openCount($pdo, $teacherId, $course), 'requests' => StudentRequests::forTeacher($pdo, $teacherId, $course)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    logServerError($e, 'teacher/requests');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}
