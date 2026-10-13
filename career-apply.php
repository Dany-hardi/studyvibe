<?php
declare(strict_types=1);

/**
 * Receives an application (POST multipart from career.php).
 *
 *   slug, full_name, email, phone, location, linkedin_url, portfolio_url, availability, salary_expectation, cover_letter, consent,
 *   resume (file), _t (signed time the form was drawn), website_url (honeypot, must stay empty), source
 *
 * Answers JSON {success, reference, name, email} or {success:false, error, fields:{name:code}, message}. Without JavaScript the
 * browser is sent back to the posting page with ?sent=REFERENCE.
 * Protection: same-site check, honeypot, minimum fill time, per-address and per-person limits. The e-mails go out after the answer.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Security.php';
require_once __DIR__ . '/lib/RateLimit.php';
require_once __DIR__ . '/lib/Careers.php';
require_once __DIR__ . '/lib/Analytics.php';
require_once __DIR__ . '/lib/CareersWorker.php';

$wantsJson = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

$reply = static function (array $body, int $code = 200) use ($wantsJson): never {
    if (!$wantsJson && !empty($body['success'])) {
        header('Location: /career.php?slug=' . rawurlencode((string)($body['slug'] ?? '')) . '&sent=' . rawurlencode((string)$body['reference']), true, 303);
        exit;
    }
    if (!$wantsJson) {
        header('Location: /career.php?slug=' . rawurlencode((string)($body['slug'] ?? '')) . '&error=' . rawurlencode((string)($body['error'] ?? 'server')), true, 303);
        exit;
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
};

Security::requirePostFromSameSite();

$slug = strtolower(trim((string)($_POST['slug'] ?? '')));
$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';

// A request bigger than post_max_size arrives empty: tell the person the file is too big.
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $reply(['success' => false, 'error' => 'validation', 'fields' => ['resume' => 'too_big'], 'slug' => $slug], 413);
}

try {
    $pdo = Database::getInstance();
    $ip = Security::clientIp();

    // Bots: the hidden field is filled, or the form was sent in under three seconds, or its signature is wrong. They get a normal-looking answer.
    $bot = trim((string)($_POST['website_url'] ?? '')) !== '';
    $ts = explode('.', (string)($_POST['_t'] ?? ''), 2);
    if (count($ts) !== 2 || !ctype_digit($ts[0])) {
        $bot = true;
    } else {
        $expect = hash_hmac('sha256', 'career-form|' . $ts[0], defined('APP_SECRET') ? (string)APP_SECRET : 'studyvibe');
        if (!hash_equals($expect, $ts[1]) || (time() - (int)$ts[0]) < 3) {
            $bot = true;
        }
    }
    if ($bot) {
        usleep(random_int(300000, 900000));
        $reply(['success' => true, 'reference' => Careers::OPEN_CODE . date('y') . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5)), 'name' => '', 'email' => '', 'slug' => $slug]);
    }

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!RateLimit::allow($pdo, 'career:ip:' . hash('sha256', $ip), 8, 3600)
        || ($email !== '' && !RateLimit::allow($pdo, 'career:mail:' . hash('sha256', $email), 4, 86400))) {
        $reply(['success' => false, 'error' => 'rate', 'message' => 'rate', 'slug' => $slug], 429);
    }

    $job = null;
    if ($slug !== Careers::SPONTANEOUS) {
        $job = Careers::jobBySlug($pdo, $slug);
        if (!$job) {
            $reply(['success' => false, 'error' => 'closed', 'message' => 'closed', 'slug' => $slug], 404);
        }
    }

    $in = $_POST;
    $in['consent'] = !empty($_POST['consent']);
    $res = Careers::submit($pdo, $job, $in, $_FILES['resume'] ?? [], $lang, $ip);
    if (!$res['ok']) {
        $code = match ($res['error']) { 'duplicate' => 409, 'closed' => 410, default => 422 };
        $reply($res + ['success' => false, 'slug' => $slug], $code);
    }

    $appId = (int)$res['id'];
    $name = Careers::line($in['full_name'] ?? '', 120);
    Analytics::hit('career_apply');

    // Answer first; reading the résumé, the screening and the mail server are slow and must not hold the person's screen.
    register_shutdown_function(static function () use ($appId): void {
        try {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            require_once __DIR__ . '/Mailer.php';
            require_once __DIR__ . '/lib/CareersScreening.php';
            $pdo = Database::getInstance();
            $screen = CareersScreening::screenApplication($pdo, $appId);
            $st = $pdo->prepare('SELECT * FROM career_applications WHERE id = :i');
            $st->execute(['i' => $appId]);
            $app = $st->fetch(PDO::FETCH_ASSOC);
            if ($app) {
                CareersMail::received($app);
                // Applications the screening held back wait for the daily digest; the others are announced at once.
                if (!$screen || $screen['bucket'] === 'inbox') {
                    CareersMail::notifyAdmins($pdo, $app, $screen);
                }
            }
        } catch (Throwable $e) {
            error_log('[careers] after application ' . $appId . ': ' . $e->getMessage());
        }
    });

    $reply(['success' => true, 'reference' => $res['reference'], 'name' => $name, 'email' => $email, 'slug' => $slug, 'track' => '/track.php?t=' . $res['token']]);
} catch (Throwable $e) {
    logServerError($e, 'career-apply');
    $reply(['success' => false, 'error' => 'server', 'message' => 'server', 'slug' => $slug], 500);
}
