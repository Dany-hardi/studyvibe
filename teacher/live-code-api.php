<?php
declare(strict_types=1);

/**
 * Actions of the code-review page (teacher only, own sessions only): override a verdict, send an answer back to the marking, run the
 * marking of what is waiting. POST with the CSRF token of the page; the answer is JSON.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/LiveCodeReview.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
Security::requirePostFromSameSite();
if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'teacher') {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'forbidden']));
}
if (!hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'csrf']));
}

$pdo = Database::getInstance();
$teacherId = (int)$_SESSION['user_id'];
require_once __DIR__ . '/../lib/RateLimit.php';
if (!RateLimit::allow($pdo, 'codeapi:t:' . $teacherId, 120, 600)) {
    http_response_code(429);
    exit(json_encode(['ok' => false, 'message' => 'rate']));
}

$action = (string)($_POST['action'] ?? '');
$reviewId = (int)($_POST['review_id'] ?? 0);

// the review must belong to a session of this teacher
$owns = static function (PDO $pdo, int $reviewId, int $teacherId): bool {
    $st = $pdo->prepare("SELECT 1 FROM live_eval_code_reviews cr JOIN live_eval_questions q ON q.id = cr.question_id
                         JOIN live_eval_sessions s ON s.id = q.session_id WHERE cr.id = :i AND s.teacher_id = :t");
    $st->execute(['i' => $reviewId, 't' => $teacherId]);
    return (bool)$st->fetchColumn();
};

try {
    if ($action === 'override') {
        if (!$owns($pdo, $reviewId, $teacherId)) {
            http_response_code(404);
            exit(json_encode(['ok' => false, 'message' => 'not_found']));
        }
        $correct = (string)($_POST['correct'] ?? '') === '1';
        LiveCodeReview::override($pdo, $reviewId, $correct, (string)($_POST['note'] ?? ''), $teacherId);
        auditLog('live_code_override', 'review ' . $reviewId . ' -> ' . ($correct ? 'correct' : 'incorrect'));
        exit(json_encode(['ok' => true]));
    }
    if ($action === 'requeue') {
        if (!$owns($pdo, $reviewId, $teacherId)) {
            http_response_code(404);
            exit(json_encode(['ok' => false, 'message' => 'not_found']));
        }
        if (!RateLimit::allow($pdo, 'codeapi:requeue:' . $teacherId, 60, 3600)) {
            http_response_code(429);
            exit(json_encode(['ok' => false, 'message' => 'rate']));
        }
        LiveCodeReview::requeue($pdo, $reviewId);
        @set_time_limit(120);
        LiveCodeReview::processOne($pdo, $reviewId);
        exit(json_encode(['ok' => true]));
    }
    if ($action === 'process') {
        // marks what is waiting for this teacher's sessions (a few at a time)
        $sid = (int)($_POST['session_id'] ?? 0);
        $st = $pdo->prepare("SELECT cr.id FROM live_eval_code_reviews cr JOIN live_eval_questions q ON q.id = cr.question_id
                             JOIN live_eval_sessions s ON s.id = q.session_id WHERE s.id = :s AND s.teacher_id = :t AND cr.status = 'pending' ORDER BY cr.id LIMIT 3");
        $st->execute(['s' => $sid, 't' => $teacherId]);
        @set_time_limit(240);
        // a postponed answer (back-off, not configured) is made due again by the teacher's own request
        $pdo->prepare("UPDATE live_eval_code_reviews cr JOIN live_eval_questions q ON q.id = cr.question_id JOIN live_eval_sessions s ON s.id = q.session_id
                       SET cr.next_attempt_at = NULL WHERE s.id = :s AND s.teacher_id = :t AND cr.status = 'pending'")->execute(['s' => $sid, 't' => $teacherId]);
        $n = 0;
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $n += LiveCodeReview::processOne($pdo, (int)$id) ? 1 : 0;
        }
        exit(json_encode(['ok' => true, 'marked' => $n, 'configured' => AnthropicClient::isConfigured()]));
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'unknown_action']);
} catch (Throwable $e) {
    logServerError($e, 'live-code-api');
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'server']);
}
