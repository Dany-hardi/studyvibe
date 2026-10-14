<?php
declare(strict_types=1);

/**
 * API of the free-pace exam room (see lib/LivePaced.php). POST only, JSON. The participant is the registration the page stored in the
 * PHP session for this room code, nothing else is accepted.
 *
 *   action = state | open | save | ping | submit | lint_php
 */
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/RateLimit.php';
require_once __DIR__ . '/../lib/LivePaced.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
Security::requirePostFromSameSite();

$out = static function (array $body, int $code = 200): never {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

$code = trim((string)($_POST['code'] ?? ''));
$action = (string)($_POST['action'] ?? '');
$regId = (int)($_SESSION['live_registrations'][$code] ?? 0);
if ($code === '' || $regId <= 0) {
    $out(['ok' => false, 'error' => 'not_registered'], 403);
}

try {
    $pdo = Database::getInstance();
    $st = $pdo->prepare("SELECT s.*, c.title AS course_title FROM live_eval_sessions s JOIN courses c ON c.id = s.course_id WHERE s.session_code = :c");
    $st->execute(['c' => $code]);
    $session = $st->fetch(PDO::FETCH_ASSOC);
    if (!$session || (int)$session['free_pace'] !== 1) {
        $out(['ok' => false, 'error' => 'session'], 404);
    }
    $own = $pdo->prepare("SELECT 1 FROM live_eval_registrations WHERE id = :r AND session_id = :s");
    $own->execute(['r' => $regId, 's' => $session['id']]);
    if (!$own->fetchColumn()) {
        $out(['ok' => false, 'error' => 'not_registered'], 403);
    }
    // presence: the teacher sees who is in the room (the browser pings every 10 seconds)
    $pdo->prepare("UPDATE live_eval_registrations SET last_activity = NOW() WHERE id = :r")->execute(['r' => $regId]);
    require_once __DIR__ . '/../lib/LiveEvalModes.php';
    LiveEvalModes::advance($pdo);

    $ipKey = hash('sha256', Security::clientIp());
    // answers the browser first, then marks the registration's pending code answers in the same process (no model call when nothing is pending)
    $finish = function (array $res, int $regId, bool $work = true) use ($pdo) {
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }   // the long marking must not hold the student's session lock
        ignore_user_abort(true);
        ob_start();
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        header('Content-Type: application/json; charset=utf-8');
        header('Connection: close');
        header('Content-Length: ' . ob_get_length());
        ob_end_flush();
        flush();
        if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
        if ($work) {
            try {
                if (!LiveCodeReview::kick($pdo, $regId, 4)) {      // workers side by side when the host allows it
                    @set_time_limit(240);
                    LiveCodeReview::processFor($pdo, 200, $regId);
                }
            } catch (Throwable $e) { error_log('paced marking: ' . $e->getMessage()); }
        }
        exit;
    };
    switch ($action) {
        case 'state':
            if (!RateLimit::allow($pdo, 'paced:state:' . $regId, 600, 600)) { $out(['ok' => false, 'error' => 'rate'], 429); }
            $out(['ok' => true] + LivePaced::state($pdo, $session, $regId));

        case 'open':
            if (!RateLimit::allow($pdo, 'paced:open:' . $regId, 3000, 3600)) { $out(['ok' => false, 'error' => 'rate'], 429); }
            $out(LivePaced::open($pdo, $session, $regId, (int)($_POST['question_id'] ?? 0)));

        case 'save':
            if (!RateLimit::allow($pdo, 'paced:save:' . $regId, 2400, 600) || !RateLimit::allow($pdo, 'paced:save:ip:' . $ipKey, 6000, 600)) {
                $out(['ok' => false, 'error' => 'rate'], 429);
            }
            $out(LivePaced::save($pdo, $session, $regId, (int)($_POST['question_id'] ?? 0), (string)($_POST['answer'] ?? '')));

        case 'ping':
            if (!RateLimit::allow($pdo, 'paced:ping:' . $regId, 1200, 600)) { $out(['ok' => false, 'error' => 'rate'], 429); }
            $out(LivePaced::ping($pdo, $session, $regId));

        case 'register':
            if (!RateLimit::allow($pdo, 'paced:register:' . $regId, 120, 600)) { $out(['ok' => false, 'error' => 'rate'], 429); }
            $res = LivePaced::register($pdo, $session, $regId, (int)($_POST['question_id'] ?? 0), (string)($_POST['answer'] ?? ''));
            if (!empty($res['queued'])) {
                $finish($res, $regId);   // marking starts now, behind the answer
            }
            $out($res);

        case 'submit':
            if (!RateLimit::allow($pdo, 'paced:submit:' . $regId, 12, 600)) { $out(['ok' => false, 'error' => 'rate'], 429); }
            $res = LivePaced::submit($pdo, $session, $regId, (string)($_POST['confirm'] ?? '') === '1');
            if (!empty($res['ok']) && empty($res['already'])) {
                // the student gets the answer at once; what was not marked yet (code handed in only now) carries on behind
                $finish($res, $regId, (int)($res['pending_ai'] ?? 0) > 0);
            }
            $out($res);

        case 'lint_php':
            if (!RateLimit::allow($pdo, 'paced:lint:' . $regId, 60, 600)) { $out(['ok' => false, 'error' => 'rate'], 429); }
            $out(['ok' => true] + LivePaced::lintPhp((string)($_POST['code'] ?? '')));

        default:
            $out(['ok' => false, 'error' => 'action'], 400);
    }
} catch (Throwable $e) {
    logServerError($e, 'live-eval-paced');
    $out(['ok' => false, 'error' => 'server'], 500);
}
