<?php
declare(strict_types=1);

/**
 * The installed app and its notifications, end to end: message encryption (RFC 8291) and VAPID signing checked with an independent
 * decryptor and openssl_verify, the subscription API (who may call it, bad keys, device limit), every in-app notification also queued as
 * a push, the worker (delivered, device gone, temporary failure), emails that mirror into notifications, and the manifest and service
 * worker. Needs the app running (default http://127.0.0.1:8123). Throw-away rows zz.pwatest.*@test.local are removed at the end.
 *
 *   php tests/integration/pwa_push.php [http://127.0.0.1:8123]
 */
putenv('SV_NO_BACKGROUND_MAIL=1');
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/WebPush.php';
require_once __DIR__ . '/../../lib/PushQueue.php';
require_once __DIR__ . '/../../lib/Notifications.php';
require_once __DIR__ . '/../../lib/EmailQueue.php';

$root = dirname(__DIR__, 2);
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }

/** Independent receiver: decrypts what WebPush::encrypt produced, from the subscriber's private key. */
function decryptPush(string $body, $uaKey, string $auth): string {
    $salt = substr($body, 0, 16); $idLen = ord($body[20]); $asPub = substr($body, 21, $idLen); $cipher = substr($body, 21 + $idLen);
    $d = openssl_pkey_get_details($uaKey);
    $uaPub = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $asPub;
    $peer = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n");
    $secret = openssl_pkey_derive($peer, $uaKey);
    $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $uaPub . $asPub, $auth);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $plain = openssl_decrypt(substr($cipher, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($cipher, -16));
    return $plain === false ? '' : rtrim($plain, "\x02");
}
function newDevice(): array {
    $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $d = openssl_pkey_get_details($k);
    $pub = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $auth = random_bytes(16);
    return ['key' => $k, 'p256dh' => WebPush::b64u($pub), 'auth' => WebPush::b64u($auth), 'authRaw' => $auth, 'endpoint' => 'https://push.example.test/send/' . bin2hex(random_bytes(10))];
}
function api(string $jar, string $base, string $method, string $q, ?array $json = null, array $headers = []): array {
    $ch = curl_init($base . '/api/push.php' . $q);
    $h = $headers; if ($json !== null) { $h[] = 'Content-Type: application/json'; }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $h, CURLOPT_CUSTOMREQUEST => $method]);
    if ($json !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json)); }
    $body = (string)curl_exec($ch);
    return ['code' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'json' => json_decode($body, true), 'body' => $body];
}

// ================================================================ keys, encryption, signing
check('web push is configured (VAPID keys in .env)', WebPush::isConfigured());
check('the public key is a 65-byte point', strlen(WebPush::unb64u(WebPush::publicKey())) === 65 && WebPush::unb64u(WebPush::publicKey())[0] === "\x04");
$dev = newDevice();
$msg = json_encode(['title' => 'Nouvelle leçon', 'body' => 'Intégrales — é ü ✓ ' . str_repeat('x', 300), 'url' => '/student/dashboard.php'], JSON_UNESCAPED_UNICODE);
$enc = WebPush::encrypt($msg, $dev['p256dh'], $dev['auth']);
check('the receiver decrypts exactly what was sent (RFC 8291)', decryptPush($enc, $dev['key'], $dev['authRaw']) === $msg);
check('the record header is well formed (salt, 4096, 65-byte key id)', unpack('N', substr($enc, 16, 4))[1] === 4096 && ord($enc[20]) === 65 && $enc[21] === "\x04");
check('two messages never share a salt or a sender key', substr(WebPush::encrypt($msg, $dev['p256dh'], $dev['auth']), 0, 16) !== substr($enc, 0, 16));
check('a device with the wrong keys is refused', (function () { try { WebPush::encrypt('x', 'AAAA', 'BBBB'); return false; } catch (InvalidArgumentException $e) { return true; } })());
$jwt = WebPush::vapidJwt('https://push.example.test');
[$h64, $c64, $s64] = explode('.', $jwt);
$claims = json_decode(WebPush::unb64u($c64), true);
check('the VAPID token names the push service and expires within 24 h', ($claims['aud'] ?? '') === 'https://push.example.test' && ($claims['exp'] ?? 0) > time() && ($claims['exp'] ?? 0) <= time() + 86400 && str_starts_with((string)($claims['sub'] ?? ''), 'mailto:'));
$sigRaw = WebPush::unb64u($s64);
$derSig = (function (string $raw): string { $enc = fn(string $i) => (ord($i[0]) & 0x80 ? "\0" : '') . $i; $r = $enc(ltrim(substr($raw, 0, 32), "\0") ?: "\0"); $s = $enc(ltrim(substr($raw, 32), "\0") ?: "\0"); $body = "\x02" . chr(strlen($r)) . $r . "\x02" . chr(strlen($s)) . $s; return "\x30" . chr(strlen($body)) . $body; })($sigRaw);
$pubPem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . WebPush::unb64u(WebPush::publicKey())), 64, "\n") . "-----END PUBLIC KEY-----\n";
check('the VAPID signature verifies against the public key', strlen($sigRaw) === 64 && openssl_verify($h64 . '.' . $c64, $derSig, $pubPem, OPENSSL_ALGO_SHA256) === 1);

// ================================================================ fixtures
$pw = 'Pwa-test-9Zq!';
$pdo->exec("DELETE FROM email_outbox WHERE dedupe_key LIKE 'pwatest:%'");
$pdo->exec("DELETE FROM users WHERE email LIKE 'zz.pwatest.%@test.local'");
$mk = function (string $n, string $role) use ($pdo, $pw): int {
    $pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved,plan_id,tour_seen_at,lang) VALUES (?,?,?,?,NOW(),1,1,NULL,NOW(),'fr')")
        ->execute(["zz.pwatest.$n@test.local", password_hash($pw, PASSWORD_DEFAULT), ucfirst($n), $role]);
    return (int)$pdo->lastInsertId();
};
$A = $mk('ama', 'student'); $B = $mk('biyik', 'student');
$jarA = tempnam(sys_get_temp_dir(), 'pwa'); $jarB = tempnam(sys_get_temp_dir(), 'pwa'); $jarX = tempnam(sys_get_temp_dir(), 'pwa');
$login = function (string $jar, string $n) use ($base, $pw): bool {
    $ch = curl_init($base . '/login-action.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['email' => "zz.pwatest.$n@test.local", 'password' => $pw])]);
    $r = json_decode((string)curl_exec($ch), true);
    return ($r['success'] ?? false) === true;
};
check('students sign in', $login($jarA, 'ama') && $login($jarB, 'biyik'));

// ================================================================ the API
check('anonymous visitors get 401', api($jarX, $base, 'GET', '?action=state')['code'] === 401);
$st = api($jarA, $base, 'GET', '?action=state');
check('state returns the public key and the device count', ($st['json']['configured'] ?? false) === true && ($st['json']['publicKey'] ?? '') === WebPush::publicKey() && ($st['json']['devices'] ?? -1) === 0);
$sub = ['action' => 'subscribe', 'endpoint' => $dev['endpoint'], 'keys' => ['p256dh' => $dev['p256dh'], 'auth' => $dev['auth']]];
check('a request from another website is refused', api($jarA, $base, 'POST', '', $sub, ['Sec-Fetch-Site: cross-site'])['code'] === 403);
check('anonymous subscribe is refused', api($jarX, $base, 'POST', '', $sub)['code'] === 401);
check('keys of the wrong size are refused', api($jarA, $base, 'POST', '', ['action' => 'subscribe', 'endpoint' => $dev['endpoint'], 'keys' => ['p256dh' => 'AAAA', 'auth' => $dev['auth']]])['code'] === 422);
check('an http endpoint is refused', api($jarA, $base, 'POST', '', ['action' => 'subscribe', 'endpoint' => 'http://push.example.test/x', 'keys' => $sub['keys']])['code'] === 422);
$ok = api($jarA, $base, 'POST', '', $sub);
check('a valid subscription is stored', ($ok['json']['success'] ?? false) === true && ($ok['json']['devices'] ?? 0) === 1, $ok['body']);
check('subscribing twice keeps one device', (api($jarA, $base, 'POST', '', $sub)['json']['devices'] ?? 0) === 1);
$other = api($jarB, $base, 'POST', '', $sub);
check('the same device moves to the account that signs in on it', (int)$pdo->query("SELECT user_id FROM push_subscriptions WHERE endpoint_hash = '" . hash('sha256', $dev['endpoint']) . "'")->fetchColumn() === $B);
api($jarA, $base, 'POST', '', $sub);                 // and back to A for the rest of the tests
check('unsubscribe removes only that device', (api($jarA, $base, 'POST', '', ['action' => 'unsubscribe', 'endpoint' => $dev['endpoint']])['json']['devices'] ?? -1) === 0);
for ($i = 0; $i < 10; $i++) { $d = newDevice(); PushQueue::subscribe($pdo, $A, $d['endpoint'], $d['p256dh'], $d['auth']); }
check('a user keeps at most 8 devices (the newest)', PushQueue::deviceCount($pdo, $A) === 8);
$pdo->exec("DELETE FROM push_subscriptions WHERE user_id = $A");
$dev = newDevice();
api($jarA, $base, 'POST', '', ['action' => 'subscribe', 'endpoint' => $dev['endpoint'], 'keys' => ['p256dh' => $dev['p256dh'], 'auth' => $dev['auth']]]);

// ================================================================ every notification is also a push
$before = (int)$pdo->query("SELECT COUNT(*) FROM push_outbox WHERE user_id IN ($A, $B)")->fetchColumn();
Notifications::send($pdo, $B, 'lesson_update', 'Leçon mise à jour !', 'Cours X', '/student/dashboard.php?course_id=1');
check('a user with no device gets no push row', (int)$pdo->query("SELECT COUNT(*) FROM push_outbox WHERE user_id = $B")->fetchColumn() === 0);
Notifications::send($pdo, $A, 'lesson_update', 'Leçon mise à jour !', 'Le cours « Maths » a une nouvelle leçon : é ✓', '/student/dashboard.php?course_id=7');
Notifications::send($pdo, $A, 'x', 'Lien dangereux', 'b', 'https://evil.example/phish');
check('a user with a device gets one push row per notification', (int)$pdo->query("SELECT COUNT(*) FROM push_outbox WHERE user_id = $A")->fetchColumn() === 2);
$payloads = array_map(fn($p) => json_decode($p, true), $pdo->query("SELECT payload FROM push_outbox WHERE user_id = $A ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
check('the push carries title, body and the same link', $payloads[0]['title'] === 'Leçon mise à jour !' && str_contains($payloads[0]['body'], '✓') && $payloads[0]['url'] === '/student/dashboard.php?course_id=7' && $payloads[0]['tag'] === 'lesson_update');
check('an outside link is never put in a push (it points home)', $payloads[1]['url'] === '/');

// ================================================================ the worker
$sent = [];
WebPush::$transport = function (string $endpoint, array $headers, string $body) use (&$sent): array { $sent[] = compact('endpoint', 'headers', 'body'); return ['status' => 201]; };
$n = PushQueue::work($pdo);
check('the worker delivers both', $n === 2 && count($sent) === 2, "n=$n sent=" . count($sent));
check('each request is signed and encrypted for the device', count($sent) === 2 && $sent[0]['endpoint'] === $dev['endpoint'] && ($sent[0]['headers']['Content-Encoding'] ?? '') === 'aes128gcm' && str_starts_with($sent[0]['headers']['Authorization'] ?? '', 'vapid t=') && str_contains($sent[0]['headers']['Authorization'], 'k=' . WebPush::publicKey()) && (int)($sent[0]['headers']['TTL'] ?? 0) > 0);
check('the device can read the notification', ($sent[0]['body'] ?? '') !== '' && (json_decode(decryptPush($sent[0]['body'], $dev['key'], $dev['authRaw']), true)['title'] ?? '') === 'Leçon mise à jour !');
check('delivered rows are marked sent', (int)$pdo->query("SELECT COUNT(*) FROM push_outbox WHERE user_id = $A AND status = 'sent'")->fetchColumn() === 2);
check('a second run sends nothing again', PushQueue::work($pdo) === 0);

Notifications::send($pdo, $A, 'x', 'Appareil disparu', 'b', '/');
WebPush::$transport = fn() => ['status' => 410];
PushQueue::work($pdo);
check('a device the browser dropped (410) is forgotten', PushQueue::deviceCount($pdo, $A) === 0);

$dev = newDevice(); PushQueue::subscribe($pdo, $A, $dev['endpoint'], $dev['p256dh'], $dev['auth']);
Notifications::send($pdo, $A, 'x', 'Panne passagère', 'b', '/');
WebPush::$transport = fn() => ['status' => 503];
PushQueue::work($pdo); $row = $pdo->query("SELECT status, attempts FROM push_outbox WHERE user_id = $A ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('a temporary failure (503) is retried, not lost', $row['status'] === 'queued' && (int)$row['attempts'] === 1 && PushQueue::deviceCount($pdo, $A) === 1);
PushQueue::work($pdo); PushQueue::work($pdo);
$row = $pdo->query("SELECT status, attempts FROM push_outbox WHERE user_id = $A ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('...and given up after 3 attempts', $row['status'] === 'failed' && (int)$row['attempts'] === 3);
WebPush::$transport = null;

// ================================================================ emails that mirror into notifications
$pdo->exec("DELETE FROM notifications WHERE user_id = $A");
$args = ['name' => 'Ama', 'session' => ['title' => 'Examen L3', 'course_title' => 'Programmation Web', 'start_time' => date('Y-m-d H:i:s', time() + 3600), 'is_async' => 0], 'link' => 'https://studyvibe.example/live-session.php?code=abc123', 'lang' => 'fr', 'rescheduled' => false];
$q1 = EmailQueue::enqueue($pdo, $A, 'zz.pwatest.ama@test.local', 'live_scheduled', $args, 'pwatest:live:1');
$n1 = $pdo->query("SELECT type, title, body, link FROM notifications WHERE user_id = $A")->fetchAll(PDO::FETCH_ASSOC);
check('an evaluation announcement also becomes a notification, with the same link', $q1 !== null && count($n1) === 1 && $n1[0]['type'] === 'live_scheduled' && str_contains($n1[0]['body'], 'Examen L3') && $n1[0]['link'] === '/live-session.php?code=abc123', json_encode($n1));
EmailQueue::enqueue($pdo, $A, 'zz.pwatest.ama@test.local', 'live_scheduled', $args, 'pwatest:live:1');
check('the same email queued twice makes one notification', (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $A")->fetchColumn() === 1);
EmailQueue::enqueue($pdo, $A, 'zz.pwatest.ama@test.local', 'live_reminder', $args + ['minutes_left' => 15], 'pwatest:remind:1');
check('the 15-minute reminder also becomes a notification', (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $A AND type = 'live_reminder' AND title LIKE '%15%'")->fetchColumn() === 1);
check('a guest (no account) creates no notification and no error', (function () use ($pdo, $args) { try { EmailQueue::enqueue($pdo, null, 'zz.pwatest.guest@test.local', 'live_scheduled', $args, 'pwatest:live:g'); return true; } catch (Throwable $e) { return false; } })());
Notifications::mirror($pdo, null, 'x', 't');
check('mirror ignores a missing recipient', true);

// every mail that goes to a person is paired with a notification in the file that sends it
$pairs = [
    'signup-action.php' => 'Mailer::welcome', 'api/2fa.php' => 'Mailer::securityNotice', 'promoter/issue-certificate.php' => 'Mailer::certification',
    'student/submit-final-exam.php' => 'Mailer::certification', 'student/submit-lesson-quiz.php' => 'Mailer::quizResults', 'lib/LessonProgressionHelper.php' => 'Mailer::sendLessonContentUpdated',
    'api/teacher-dispatch-live-emails.php' => 'Mailer::sendLiveEvalResults', 'lib/LiveMailQueue.php' => 'Mailer::sendLiveEvalResults', 'teacher/dashboard.php' => 'Mailer::courseCreatedByTeacher',
    'promoter/update-teacher.php' => 'Mailer::courseAssignmentChanged', 'promoter/manage-user.php' => 'Mailer::teacherApproved', 'promoter/send-direct-message.php' => 'Mailer::directMessage',
    'promoter/send-keys-broadcast.php' => 'Mailer::sendEnrollmentKeys', 'promoter/send-newsletter.php' => 'Mailer::newsletter', 'lib/AdminUsers.php' => 'Mailer::accountCreatedByAdmin',
    'lib/Assignments.php' => 'EmailQueue::enqueue', 'lib/Contests.php' => 'EmailQueue::enqueue', 'lib/LiveResults.php' => 'EmailQueue::enqueue', 'lib/StudentRequests.php' => 'EmailQueue::enqueue', 'lib/LessonQA.php' => 'EmailQueue::enqueue',
    'lib/LiveMailNotifier.php' => 'EmailQueue::enqueue', 'lib/LiveReminders.php' => 'EmailQueue::enqueue',
];
foreach ($pairs as $file => $mailCall) {
    $src = (string)file_get_contents("$root/$file");
    $viaQueue = in_array($file, ['lib/LiveMailNotifier.php', 'lib/LiveReminders.php'], true);          // these two are mirrored inside EmailQueue::enqueue
    check("$file: $mailCall is paired with a notification", str_contains($src, $mailCall) && ($viaQueue || str_contains($src, 'Notifications::')), $file);
}
check('EmailQueue mirrors the announcement and the reminder itself', str_contains((string)file_get_contents("$root/lib/EmailQueue.php"), "'live_scheduled', 'live_reminder'"));

// ================================================================ manifest, service worker, pages
$mf = json_decode((string)file_get_contents("$root/manifest.webmanifest"), true);
check('the manifest is valid and installable (name, start url, standalone, 192 and 512 icons, a maskable one)', is_array($mf) && ($mf['display'] ?? '') === 'standalone' && !empty($mf['start_url']) && !empty($mf['name'])
    && count(array_filter($mf['icons'] ?? [], fn($i) => $i['sizes'] === '192x192')) >= 1 && count(array_filter($mf['icons'] ?? [], fn($i) => $i['sizes'] === '512x512')) >= 2 && count(array_filter($mf['icons'] ?? [], fn($i) => ($i['purpose'] ?? '') === 'maskable')) === 1);
$missing = array_filter(array_map(fn($i) => $i['src'], $mf['icons'] ?? []), fn($s) => !is_file($root . $s));
check('every icon of the manifest exists', $missing === [], implode(',', $missing));
check('the shortcuts point to real pages', count($mf['shortcuts'] ?? []) >= 2 && is_file($root . '/student/dashboard.php') && is_file($root . '/evaluations.php'));
$sw = (string)file_get_contents("$root/sw.js");
check('the service worker handles push, clicks and a rotated subscription', str_contains($sw, "'push'") && str_contains($sw, "'notificationclick'") && str_contains($sw, "'pushsubscriptionchange'") && is_file("$root/assets/img/badge-96.png"));
check('the service worker never caches /api/ or non-GET requests', str_contains($sw, "req.method !== 'GET'") && !str_contains($sw, "'/api/'"));
foreach (['/' => 'the home page', '/evaluations.php' => 'the evaluations page', '/join.php?course=1' => 'the join page'] as $path => $label) {
    $ch = curl_init($base . $path); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30]); $html = (string)curl_exec($ch);
    check("$label loads the install pop-up script and the manifest", str_contains($html, '/assets/js/pwa.js') && str_contains($html, 'manifest.webmanifest') && str_contains($html, 'name="sv-auth"'));
}
foreach (['install', 'push', 'ios', 'settings'] as $img) { check("pop-up illustration $img exists", is_file("$root/assets/img/pwa/$img.svg")); }
$ch = curl_init($base . '/sw.js'); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30]); $r = (string)curl_exec($ch);
check('the service worker is served as JavaScript from the root scope', (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200 && stripos($r, 'javascript') !== false);

// ================================================================ clean up
$pdo->exec("DELETE FROM email_outbox WHERE dedupe_key LIKE 'pwatest:%'");
$pdo->exec("DELETE FROM users WHERE email LIKE 'zz.pwatest.%@test.local'");
foreach ([$jarA, $jarB, $jarX] as $j) { @unlink($j); }
echo "\n" . $passed . ' passed, ' . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
