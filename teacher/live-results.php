<?php
declare(strict_types=1);

/**
 * Review of a finished live evaluation, for the teacher who owns it.
 *
 *   GET  ?session_id=12                         the table: matricule, name, email, mark, integrity assessment
 *   POST action=prompt_seen&session_id=12       the "exam finished" window will not appear again for this session
 *   POST action=cancel&registration_id=5&reason=...    cancels a student's result (the student is told by email)
 *   POST action=restore&registration_id=5               puts it back (the student is told by email)
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/LiveResults.php';
require_once __DIR__ . '/../lib/Security.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}
$teacherId = (int)$_SESSION['user_id'];
$pdo = Database::getInstance();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requirePostFromSameSite();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'cancel' || $action === 'restore') {
            $rid = (int)($_POST['registration_id'] ?? 0);
            $res = $action === 'cancel'
                ? LiveResults::cancel($pdo, $rid, $teacherId, (string)($_POST['reason'] ?? ''))
                : LiveResults::restore($pdo, $rid, $teacherId);
            if ($res['ok']) {
                auditLog('live_result_' . $action, "Registration #{$rid}");
            }
            echo json_encode($res + ['success' => $res['ok']]);
            exit;
        }
        if ($action === 'prompt_seen') {
            $sid = (int)($_POST['session_id'] ?? 0);
            $pdo->prepare("UPDATE live_eval_sessions SET results_prompted_at = NOW() WHERE id = :id AND teacher_id = :t")->execute(['id' => $sid, 't' => $teacherId]);
            echo json_encode(['success' => true]);
            exit;
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
        exit;
    }

    $sid = (int)($_GET['session_id'] ?? 0);
    $st = $pdo->prepare(
        "SELECT s.*, c.title AS course_title FROM live_eval_sessions s JOIN courses c ON c.id = s.course_id WHERE s.id = :id AND s.teacher_id = :t"
    );
    $st->execute(['id' => $sid, 't' => $teacherId]);
    $session = $st->fetch(PDO::FETCH_ASSOC);
    if (!$session) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Séance introuvable.']);
        exit;
    }
    $review = LiveResults::review($pdo, $session);
    echo json_encode([
        'success' => true,
        'session' => ['id' => (int)$session['id'], 'title' => $session['title'], 'course' => $session['course_title'], 'integrity_watch' => (int)$session['integrity_watch'] === 1],
        'total_questions' => $review['total_questions'],
        'summary' => $review['summary'],
        'rows' => $review['rows'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    logServerError($e, 'teacher/live-results');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}
