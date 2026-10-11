<?php
declare(strict_types=1);

/**
 * The second lock of the control center (lib/ControlUnlock.php, lib/ControlGate.php). A throw-away promoter with an authenticator app
 * is created, every rule is exercised (both proofs needed, replay, single use, tries, throttle, expiry, binding to browser and network,
 * lock), then the real pages are called through a web server: strangers get a 404, an owner who has not verified is sent to the lock
 * page, the JSON API answers 401. Everything created here is deleted at the end.
 *
 *   php tests/integration/ops_unlock.php
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../Mailer.php';
require_once __DIR__ . '/../../lib/ControlGate.php';
require_once __DIR__ . '/../../lib/ControlUnlock.php';

$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what; }

$email = 'zz.ops.' . bin2hex(random_bytes(3)) . '@studyvibe-test.local';
$secret = Totp::newSecret();
$pdo->prepare("INSERT INTO users (email, password, name, role, plan_id, email_verified_at, is_active, is_approved, lang) VALUES (?, ?, 'Ops Test', 'promoter', NULL, NOW(), 1, 1, 'en')")
    ->execute([$email, password_hash('x', PASSWORD_DEFAULT)]);
$uid = (int)$pdo->lastInsertId();
$user = fn() => $pdo->query("SELECT * FROM users WHERE id = $uid")->fetch();
$studentId = (int)$pdo->query("SELECT id FROM users WHERE role = 'student' AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
$ip = '203.0.113.7';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (test)';
$_SERVER['REMOTE_ADDR'] = $ip;
$mail = [];
Mailer::$capture = function ($to, $subject, $html) use (&$mail) { $mail[] = [$to, $subject, $html]; };
$totp = fn() => Totp::code($secret, intdiv(time(), 30));
$resetLimits = function () use ($pdo, $uid) { $pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'ops:%'"); };
$freshStep = function () use ($pdo, $uid) { $pdo->exec("UPDATE users SET totp_last_step = 0 WHERE id = $uid"); };
$codeFromHtml = function () use (&$mail): string { $m = end($mail); $t = strip_tags((string)$m[2]); return preg_match('/(\d{4}) (\d{4})/', $t, $x) ? $x[1] . $x[2] : ''; };

try {
    // --- who can see the door
    $_SESSION['user_role'] = 'promoter';
    check('a promoter is an owner when no allow-list is set', ControlGate::isOwner($user()));
    check('a student is never an owner', !ControlGate::isOwner(['role' => 'student', 'email' => 'a@b.cd']));

    // --- closed by default
    check('no authenticator app: the center cannot be opened', ControlUnlock::problems($pdo, $user()) === ['no_2fa']);
    $r = ControlUnlock::sendCode($pdo, $user(), $ip);
    check('no code is sent to an account without an authenticator app', !$r['ok'] && $r['error'] === 'blocked' && count($mail) === 0);

    $pdo->prepare("UPDATE users SET totp_secret_enc = ?, totp_enabled_at = NOW(), totp_last_step = 0 WHERE id = ?")->execute([Totp::encrypt($secret), $uid]);
    check('with an authenticator app and a verified address there is nothing in the way', ControlUnlock::problems($pdo, $user()) === []);

    // --- step 1
    $resetLimits();
    $r = ControlUnlock::sendCode($pdo, $user(), $ip);
    check('a code is e-mailed to the account', $r['ok'] && count($mail) === 1 && $mail[0][0] === $email && str_contains($r['email_masked'], '•'));
    $code1 = $codeFromHtml();
    check('the code is 8 digits and is not stored in clear', strlen($code1) === 8 && (int)$pdo->query("SELECT COUNT(*) FROM ops_unlock_codes WHERE user_id = $uid AND code_hash = '$code1'")->fetchColumn() === 0);
    $r2 = ControlUnlock::sendCode($pdo, $user(), $ip);
    check('asking again at once is refused (one per minute)', !$r2['ok'] && $r2['error'] === 'too_soon');

    // --- both proofs are needed
    $r = ControlUnlock::unlock($pdo, $user(), $code1, '000000', $ip);
    check('right e-mail code with a wrong authenticator code does not open it', !$r['ok'] && $r['error'] === 'invalid' && !ControlUnlock::isUnlocked($uid));
    $r = ControlUnlock::unlock($pdo, $user(), '00000000', $totp(), $ip);
    check('wrong e-mail code with a right authenticator code does not open it', !$r['ok'] && $r['error'] === 'invalid' && !ControlUnlock::isUnlocked($uid));
    $step = (int)$pdo->query("SELECT totp_last_step FROM users WHERE id = $uid")->fetchColumn();
    check('a wrong e-mail code does not spend the authenticator code', $step === 0);

    // --- success
    $r = ControlUnlock::unlock($pdo, $user(), substr($code1, 0, 4) . ' ' . substr($code1, 4), $totp(), $ip);
    check('both proofs together open it (spaces in the code are fine)', $r['ok'] === true && ControlUnlock::isUnlocked($uid));
    check('the time left is at most the idle limit', ControlUnlock::secondsLeft() > 1100 && ControlUnlock::secondsLeft() <= ControlUnlock::IDLE_SECONDS);
    check('the owner is told by e-mail that it was opened', count($mail) === 2 && str_contains((string)$mail[1][2], $ip));

    // --- single use and replay
    $resetLimits();
    ControlUnlock::lock($user());
    check('locking closes it at once', !ControlUnlock::isUnlocked($uid));
    $r = ControlUnlock::unlock($pdo, $user(), $code1, $totp(), $ip);
    check('a used e-mail code does not work a second time', !$r['ok']);
    ControlUnlock::sendCode($pdo, $user(), $ip);
    $code2 = $codeFromHtml();
    $r = ControlUnlock::unlock($pdo, $user(), $code2, Totp::code($secret, intdiv(time(), 30)), $ip);
    check('an authenticator code already used cannot be replayed', !$r['ok'] && !ControlUnlock::isUnlocked($uid));
    $freshStep();
    ControlUnlock::sendCode($pdo, $user(), $ip);   // may be refused as too soon: reset and ask again
    $resetLimits();
    ControlUnlock::sendCode($pdo, $user(), $ip);
    $code3 = $codeFromHtml();
    check('a new code replaces the previous one', $code3 !== '' && !ControlUnlock::unlock($pdo, $user(), $code2, $totp(), $ip)['ok']);
    $resetLimits(); $freshStep();

    // --- tries
    ControlUnlock::sendCode($pdo, $user(), $ip);
    $code4 = $codeFromHtml();
    for ($i = 0; $i < 5; $i++) { ControlUnlock::unlock($pdo, $user(), '11111111', '123456', $ip); }
    $resetLimits(); $freshStep();
    check('after five wrong tries the e-mailed code is dead, even with both right', !ControlUnlock::unlock($pdo, $user(), $code4, $totp(), $ip)['ok']);

    // --- throttle
    $resetLimits();
    $res = [];
    for ($i = 0; $i < 8; $i++) { $res[] = ControlUnlock::unlock($pdo, $user(), '22222222', '654321', $ip); }
    check('too many attempts in a row are throttled', ($res[7]['error'] ?? '') === 'too_many');
    $resetLimits(); $freshStep();

    // --- expiry and binding
    ControlUnlock::sendCode($pdo, $user(), $ip);
    $code5 = $codeFromHtml();
    check('opens again with fresh proofs', ControlUnlock::unlock($pdo, $user(), $code5, $totp(), $ip)['ok'] && ControlUnlock::isUnlocked($uid));
    $_SESSION['ops_unlock']['seen'] = time() - 1300;
    check('it closes after 20 minutes without activity', !ControlUnlock::isUnlocked($uid));
    $resetLimits(); $freshStep();
    ControlUnlock::sendCode($pdo, $user(), $ip);
    ControlUnlock::unlock($pdo, $user(), $codeFromHtml(), $totp(), $ip);
    $_SESSION['ops_unlock']['at'] = time() - 7300;
    check('it closes after two hours whatever happens', !ControlUnlock::isUnlocked($uid));
    $resetLimits(); $freshStep();
    ControlUnlock::sendCode($pdo, $user(), $ip);
    ControlUnlock::unlock($pdo, $user(), $codeFromHtml(), $totp(), $ip);
    check('open before the change of browser', ControlUnlock::isUnlocked($uid));
    $_SERVER['HTTP_USER_AGENT'] = 'Another browser';
    check('another browser does not inherit it', !ControlUnlock::isUnlocked($uid));
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (test)';
    $resetLimits(); $freshStep();
    ControlUnlock::sendCode($pdo, $user(), $ip);
    ControlUnlock::unlock($pdo, $user(), $codeFromHtml(), $totp(), $ip);
    $_SERVER['REMOTE_ADDR'] = '198.51.100.20';
    check('another network does not inherit it', !ControlUnlock::isUnlocked($uid));
    $_SERVER['REMOTE_ADDR'] = $ip;
    check('another person (user id) does not inherit it', !ControlUnlock::isUnlocked($uid + 1));

    // --- the trail
    $log = fn(string $a) => (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = '$a'")->fetchColumn();
    check('requests, failures and openings are all in the audit trail', $log('ops_unlock_requested') > 0 && $log('ops_unlock_failed') > 0 && $log('ops_unlock_ok') > 0 && $log('ops_locked') > 0);

    // --- the real pages, through a web server
    $root = dirname(__DIR__, 2);
    $router = sys_get_temp_dir() . '/sv_ops_router_' . getmypid() . '.php';
    file_put_contents($router, '<?php
$root = ' . var_export($root, true) . ';
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH); $file = $root . $path;
if (!is_file($file)) { return false; }
$_SERVER["SCRIPT_NAME"] = $path; $_SERVER["SCRIPT_FILENAME"] = $file; chdir(dirname($file));
require_once $root . "/Database.php"; require_once $root . "/auth.php";
$as = (int)($_GET["as"] ?? 0);
if ($as > 0) { $s = Database::getInstance()->prepare("SELECT role FROM users WHERE id = ?"); $s->execute([$as]); $role = $s->fetchColumn();
  if ($role) { $_SESSION["user_id"] = $as; $_SESSION["user_role"] = $role; $_SESSION["last_regen"] = time(); } }
require $file;');
    $port = random_int(40000, 49000);
    $proc = proc_open(['php', '-S', "127.0.0.1:$port", $router], [['pipe', 'r'], ['file', sys_get_temp_dir() . '/sv_ops_srv.log', 'a'], ['file', sys_get_temp_dir() . '/sv_ops_srv.log', 'a']], $pipes);
    usleep(900000);
    $http = function (string $path) use ($port): array {
        $ch = curl_init("http://127.0.0.1:$port$path");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30]);
        $out = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        return [$code, substr($out, 0, $hs), substr($out, $hs)];
    };
    $base = ControlGate::PATH;
    foreach (['index.php', 'unlock.php', 'api.php?action=state'] as $page) {
        [$c] = $http($base . $page . (str_contains($page, '?') ? '&' : '?') . "as=$studentId");
        check("a student gets a plain 404 on $page", $c === 404);
        [$c] = $http($base . $page);
        check("a visitor gets a plain 404 on $page", $c === 404);
    }
    [$c, $h] = $http($base . "index.php?as=$uid");
    check('an owner who has not verified is sent to the lock page', $c === 302 && stripos($h, 'location: ' . $base . 'unlock.php') !== false);
    [$c, , $b] = $http($base . "api.php?action=state&as=$uid");
    check('the JSON API answers 401 and says it is locked', $c === 401 && str_contains($b, '"locked":true'));
    [$c, , $b] = $http($base . "unlock.php?as=$uid");
    check('the lock page shows the two proofs and no password field', $c === 200 && str_contains($b, 'name="email_code"') && str_contains($b, 'name="totp"') && !str_contains($b, 'type="password"'));
    [$c, , $b] = $http('/promoter/dashboard.php?as=' . $uid);
    check('the admin dashboard shows the link to an owner', $c === 200 && str_contains($b, 'unlock.php') && str_contains($b, 'pm-ops'));
    proc_terminate($proc);
    @unlink($router);
} finally {
    Mailer::$capture = null;
    $pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'ops:%'");
    $pdo->exec("DELETE FROM users WHERE id = $uid");   // cascades to ops_unlock_codes
    $pdo->exec("DELETE FROM audit_logs WHERE action LIKE 'ops_unlock%' AND details LIKE '%" . substr($email, 0, 2) . "%' AND user_id IS NULL");
}
echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
