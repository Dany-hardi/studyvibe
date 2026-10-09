<?php
declare(strict_types=1);

/**
 * End-to-end test of the account security features against a running copy of the app (needs APP_DEBUG=true and
 * SMS_DRIVER=log in .env, which a developer machine has). It creates its own throw-away account and removes it at the end.
 *
 *   php tests/integration/account_security_flow.php [http://127.0.0.1:8123]
 *
 * Covers: sign-in, phone verification by SMS code (wrong code, right code, replay, number already taken), two-factor set-up,
 * sign-in with the second step (wrong code, right code, the same code used twice, recovery code used twice), turning it off.
 */

require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/Totp.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
$pdo = Database::getInstance();
$email = 'zz.flow.' . bin2hex(random_bytes(3)) . '@test.local';
$pass = 'Flow-test-9Zq!';
$other = 'zz.flow2.' . bin2hex(random_bytes(3)) . '@test.local';

$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void {
    global $passed, $failed;
    if ($ok) { $passed++; } else { $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }
}

function http(string $jar, string $path, array $post = []): array {
    global $base;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 20,
        CURLOPT_POST => $post !== [], CURLOPT_POSTFIELDS => $post, CURLOPT_FOLLOWLOCATION => false]);
    $body = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    return ['code' => $code, 'json' => json_decode($body, true), 'body' => $body];
}

$mk = function (string $mail) use ($pdo, $pass) {
    $pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved,plan_id) VALUES (?,?,?,'student',NOW(),1,1,NULL)")
        ->execute([$mail, password_hash($pass, PASSWORD_DEFAULT), 'Flow Tester']);
    return (int)$pdo->lastInsertId();
};
$uid = $mk($email);
$uid2 = $mk($other);
$cleanup = function () use ($pdo, $uid, $uid2) {
    $pdo->exec("DELETE FROM users WHERE id IN ($uid, $uid2)");
    $pdo->exec("DELETE FROM sms_outbox WHERE user_id IN ($uid, $uid2)");
    $pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'otp%' OR bucket LIKE 'tfa%'");
};
register_shutdown_function($cleanup);
$pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'otp%' OR bucket LIKE 'tfa%'");

$jar = sys_get_temp_dir() . '/zz_flow_' . bin2hex(random_bytes(3)) . '.jar';
$jar2 = $jar . '2';

// ---- sign in
$r = http($jar, '/login-action.php', ['email' => $email, 'password' => $pass]);
check('password sign-in works', ($r['json']['success'] ?? false) === true && empty($r['json']['requires_2fa']), $r['body']);
check('api refuses an anonymous visitor', http($jar2, '/api/2fa.php', ['action' => 'status'])['code'] === 401);

// ---- phone (only when text messages are switched on: FEATURE_SMS=true)
$smsOn = http($jar, '/api/phone.php', ['action' => 'status'])['code'] !== 404;
if (!$smsOn) {
    echo "(phone checks skipped: FEATURE_SMS is off)\n";
    $pdo->prepare("UPDATE users SET phone_e164 = NULL WHERE id = ?")->execute([$uid]);
} else {
$r = http($jar, '/api/phone.php', ['action' => 'status']);
check('no verified phone at first', ($r['json']['verified'] ?? true) === false);
$r = http($jar, '/api/phone.php', ['action' => 'start', 'phone' => 'abc']);
check('invalid number refused', ($r['json']['error'] ?? '') === 'phone_invalid');
$phone = '6' . random_int(10000000, 99999999);
$r = http($jar, '/api/phone.php', ['action' => 'start', 'phone' => $phone]);
check('code sent', ($r['json']['success'] ?? false) === true && isset($r['json']['dev_code']), $r['body']);
$code = (string)($r['json']['dev_code'] ?? '000000');
$r2 = http($jar, '/api/phone.php', ['action' => 'start', 'phone' => $phone]);
check('second code within a minute refused', ($r2['json']['error'] ?? '') === 'too_soon');
$wrong = $code === '123456' ? '654321' : '123456';
check('wrong code refused', (http($jar, '/api/phone.php', ['action' => 'verify', 'code' => $wrong])['json']['error'] ?? '') === 'code_wrong');
$r = http($jar, '/api/phone.php', ['action' => 'verify', 'code' => $code]);
check('right code accepted', ($r['json']['success'] ?? false) === true, $r['body']);
check('same code cannot be replayed', (http($jar, '/api/phone.php', ['action' => 'verify', 'code' => $code])['json']['success'] ?? true) === false);
check('status shows the verified number', (http($jar, '/api/phone.php', ['action' => 'status'])['json']['verified'] ?? false) === true);

// another account cannot take the same number
http($jar2, '/login-action.php', ['email' => $other, 'password' => $pass]);
$r = http($jar2, '/api/phone.php', ['action' => 'start', 'phone' => $phone]);
check('a number already verified elsewhere is refused', ($r['json']['error'] ?? '') === 'phone_taken');

}

// ---- two-factor set-up
$r = http($jar, '/api/2fa.php', ['action' => 'begin']);
$secret = (string)($r['json']['secret'] ?? '');
check('2FA begin returns a secret and a uri', strlen($secret) === 32 && str_starts_with((string)($r['json']['uri'] ?? ''), 'otpauth://totp/'), $r['body']);
check('a wrong code does not turn 2FA on', (http($jar, '/api/2fa.php', ['action' => 'confirm', 'code' => '000000'])['json']['success'] ?? true) === false);
$step = intdiv(time(), 30);
$r = http($jar, '/api/2fa.php', ['action' => 'confirm', 'code' => Totp::code($secret, $step)]);
check('2FA confirm with a right code', ($r['json']['success'] ?? false) === true && count($r['json']['recovery_codes'] ?? []) === 10, $r['body']);
$recovery = (array)($r['json']['recovery_codes'] ?? []);
check('secret is stored encrypted, not in clear', strpos((string)$pdo->query("SELECT totp_secret_enc FROM users WHERE id=$uid")->fetchColumn(), $secret) === false);

// ---- sign in again: second step
$jar3 = $jar . '3';
$r = http($jar3, '/login-action.php', ['email' => $email, 'password' => $pass]);
check('password step now asks for the second factor', ($r['json']['requires_2fa'] ?? false) === true && !isset($r['json']['redirect']), $r['body']);
check('half signed in is not signed in', http($jar3, '/api/2fa.php', ['action' => 'status'])['code'] === 401);
check('wrong second-step code refused', (http($jar3, '/api/2fa-login.php', ['code' => '000000'])['json']['success'] ?? true) === false);
// the code of the next step is accepted by the window, and is newer than the one used at set-up
$r = http($jar3, '/api/2fa-login.php', ['code' => Totp::code($secret, $step + 1)]);
check('right code finishes the sign-in', ($r['json']['success'] ?? false) === true && !empty($r['json']['redirect']), $r['body']);
check('signed in now', (http($jar3, '/api/2fa.php', ['action' => 'status'])['json']['success'] ?? false) === true);

// the same code cannot sign a second session in
$jar4 = $jar . '4';
http($jar4, '/login-action.php', ['email' => $email, 'password' => $pass]);
$r = http($jar4, '/api/2fa-login.php', ['code' => Totp::code($secret, $step + 1)]);
check('the same code cannot be used twice', ($r['json']['success'] ?? true) === false, $r['body']);

// recovery code: works once
$r = http($jar4, '/api/2fa-login.php', ['code' => $recovery[0]]);
check('a recovery code signs in', ($r['json']['success'] ?? false) === true, $r['body']);
$jar5 = $jar . '5';
http($jar5, '/login-action.php', ['email' => $email, 'password' => $pass]);
$r = http($jar5, '/api/2fa-login.php', ['code' => $recovery[0]]);
check('a recovery code works only once', ($r['json']['success'] ?? true) === false, $r['body']);

// five wrong codes end the second step
$jar6 = $jar . '6';
http($jar6, '/login-action.php', ['email' => $email, 'password' => $pass]);
$last = null;
for ($i = 0; $i < 6; $i++) { $last = http($jar6, '/api/2fa-login.php', ['code' => '000000']); }
check('too many wrong codes close the second step', ($last['json']['expired'] ?? false) === true, json_encode($last['json']));

// ---- turning it off needs password and code
$pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'tfa%'");
// the codes of the last minute were used above; let the clock-based test use a fresh one
$pdo->exec("UPDATE users SET totp_last_step = 0 WHERE id = $uid");
$fresh = Totp::code($secret, intdiv(time(), 30));
$r = http($jar3, '/api/2fa.php', ['action' => 'disable', 'password' => 'wrong', 'code' => $fresh]);
check('disable refused with a wrong password', ($r['json']['error'] ?? '') === 'password_wrong');
$r = http($jar3, '/api/2fa.php', ['action' => 'disable', 'password' => $pass, 'code' => $fresh]);
check('disable works with password and code', ($r['json']['success'] ?? false) === true, $r['body']);
check('2FA is off in the database', (int)$pdo->query("SELECT totp_enabled_at IS NULL FROM users WHERE id=$uid")->fetchColumn() === 1);

foreach (glob($jar . '*') ?: [] as $f) { @unlink($f); }
echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
