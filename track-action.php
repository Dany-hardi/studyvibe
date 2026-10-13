<?php
declare(strict_types=1);

/**
 * Actions of the candidate's tracking page. POST JSON {action, ...}:
 *   lookup     {reference, email}                e-mails the personal link when they match (the answer never says whether they did)
 *   respond    {t, interview_id, answer, note}   answer = confirm | decline | reschedule
 *   withdraw   {t}                               stops the process
 *   erase      {t}                               deletes the application, the résumé and the history at once
 * The token is the credential; every action is rate limited and checked as coming from this site.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Security.php';
require_once __DIR__ . '/lib/RateLimit.php';
require_once __DIR__ . '/lib/Careers.php';
require_once __DIR__ . '/lib/CareersInterviews.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
Security::requirePostFromSameSite();

$in = json_decode((string)file_get_contents('php://input'), true);
$in = is_array($in) ? $in : [];
$action = (string)($in['action'] ?? '');
$say = static function (array $b, int $code = 200): never { http_response_code($code); echo json_encode($b, JSON_UNESCAPED_UNICODE); exit; };

try {
    $pdo = Database::getInstance();
    $ip = Security::clientIp();
    if (!RateLimit::allow($pdo, 'track:ip:' . hash('sha256', $ip), 60, 3600)) {
        $say(['success' => false, 'error' => 'rate'], 429);
    }

    if ($action === 'lookup') {
        $ref = strtoupper(trim((string)($in['reference'] ?? '')));
        $email = strtolower(trim((string)($in['email'] ?? '')));
        if (!preg_match('/^CA-[A-Z0-9]{4,8}$/', $ref) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $say(['success' => false, 'error' => 'invalid'], 422);
        }
        if (!RateLimit::allow($pdo, 'track:lookup:ip:' . hash('sha256', $ip), 8, 3600) || !RateLimit::allow($pdo, 'track:lookup:mail:' . hash('sha256', $email), 4, 86400)) {
            $say(['success' => false, 'error' => 'rate'], 429);
        }
        $st = $pdo->prepare('SELECT * FROM career_applications WHERE reference = :r AND email = :e');
        $st->execute(['r' => $ref, 'e' => $email]);
        $app = $st->fetch(PDO::FETCH_ASSOC);
        if ($app) {
            register_shutdown_function(static function () use ($app): void {
                if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
                try { CareersMail::magicLink($app); } catch (Throwable $e) { error_log('[careers] magic link: ' . $e->getMessage()); }
            });
        }
        $say(['success' => true]);
    }

    $token = strtolower(trim((string)($in['t'] ?? '')));
    $app = Careers::byToken($pdo, $token);
    if (!$app) {
        $say(['success' => false, 'error' => 'not_found'], 404);
    }

    switch ($action) {
        case 'respond':
            $r = CareersInterviews::respond($pdo, $token, (int)($in['interview_id'] ?? 0), (string)($in['answer'] ?? ''), (string)($in['note'] ?? ''));
            if (!$r['ok']) {
                $say(['success' => false, 'error' => $r['error']], $r['error'] === 'not_found' ? 404 : 422);
            }
            $notify = [$r['app'], $r['status'], (string)($in['note'] ?? ''), $r['interview']];
            register_shutdown_function(static function () use ($notify): void {
                if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
                try { CareersMail::candidateActed(Database::getInstance(), $notify[0], $notify[1], Careers::line($notify[2], 500), $notify[3]); } catch (Throwable $e) { error_log('[careers] notify: ' . $e->getMessage()); }
            });
            $say(['success' => true, 'status' => $r['status']]);

        case 'withdraw':
            $r = Careers::withdraw($pdo, $token);
            $say(['success' => $r['ok']] + ($r['ok'] ? [] : ['error' => $r['error']]), $r['ok'] ? 200 : 422);

        case 'erase':
            $info = $app;
            $r = Careers::deleteApplication($pdo, (int)$app['id']);
            if ($r['ok']) {
                CareersMail::candidateActed($pdo, $info, 'erased');
                $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (NULL, 'career_erased_by_candidate', :d, :ip)")
                    ->execute(['d' => 'Application ' . $info['reference'] . ' erased by the candidate', 'ip' => $ip]);
            }
            $say(['success' => $r['ok']], $r['ok'] ? 200 : 422);

        default:
            $say(['success' => false, 'error' => 'invalid'], 400);
    }
} catch (Throwable $e) {
    logServerError($e, 'track-action:' . $action);
    $say(['success' => false, 'error' => 'server'], 500);
}
