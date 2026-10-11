<?php
declare(strict_types=1);

require_once __DIR__ . '/TwoFactor.php';
require_once __DIR__ . '/RateLimit.php';
require_once __DIR__ . '/Security.php';

/**
 * The second lock on the control center. There is no password here: the door opens with proof, given together, that the person
 * holds (1) the authenticator app of the account and (2) its mailbox.
 *
 *   1. a code made of 8 digits, sent to the verified e-mail address of the account (valid 10 minutes, single use, 5 tries, kept only as an HMAC)
 *   2. a current code from the authenticator app (recovery codes are NOT accepted, and a code already used elsewhere is refused)
 *
 * The answer to a wrong attempt never says which of the two was wrong. Attempts are limited per account and per address, every
 * request, success and failure is written to the audit trail, and the account is told by e-mail each time the center is opened.
 *
 * Once open, the session is marked: bound to the browser (user agent) and to the network (IPv4 /24, IPv6 /48), closed after 20 minutes
 * without a request, and after 2 hours whatever happens. "Lock now" closes it at once; so does signing out. If the account has no
 * authenticator app, a weak APP_SECRET, or no verified e-mail address, it cannot be opened at all (closed by default).
 */
final class ControlUnlock
{
    public const IDLE_SECONDS = 1200;
    public const MAX_SECONDS = 7200;
    public const CODE_MINUTES = 10;
    private const MAX_TRIES = 5;

    /** Reasons this account cannot open the center right now ([] = it can try). */
    public static function problems(PDO $pdo, array $user): array
    {
        $out = [];
        if (!Security::secretIsStrong() && (!defined('APP_DEBUG') || APP_DEBUG !== 'true')) {
            $out[] = 'weak_secret';
        }
        if (empty($user['email_verified_at'])) {
            $out[] = 'no_email';
        }
        if (!TwoFactor::isEnabled($pdo, (int)$user['id'])) {
            $out[] = 'no_2fa';
        }
        return $out;
    }

    // ---- the state of the session ---------------------------------------------------------------------------------

    public static function isUnlocked(int $userId): bool
    {
        $u = $_SESSION['ops_unlock'] ?? null;
        if (!is_array($u) || (int)($u['uid'] ?? 0) !== $userId) {
            return false;
        }
        $now = time();
        if ($now - (int)$u['at'] > self::MAX_SECONDS || $now - (int)$u['seen'] > self::IDLE_SECONDS || !hash_equals((string)$u['bind'], self::bind())) {
            unset($_SESSION['ops_unlock']);
            return false;
        }
        $_SESSION['ops_unlock']['seen'] = $now;
        return true;
    }

    /** Seconds before the open center closes by itself (idle or absolute limit, whichever comes first). */
    public static function secondsLeft(): int
    {
        $u = $_SESSION['ops_unlock'] ?? null;
        if (!is_array($u)) {
            return 0;
        }
        return max(0, min((int)$u['at'] + self::MAX_SECONDS, (int)$u['seen'] + self::IDLE_SECONDS) - time());
    }

    public static function lock(?array $user = null): void
    {
        if (isset($_SESSION['ops_unlock'])) {
            unset($_SESSION['ops_unlock']);
            auditLog('ops_locked', $user ? 'by ' . $user['email'] : '');
        }
    }

    // ---- step 1: the code by e-mail -------------------------------------------------------------------------------

    /** @return array{ok:bool,error?:string,retry_after?:int,email_masked?:string,dev_code?:string} */
    public static function sendCode(PDO $pdo, array $user, string $ip): array
    {
        $uid = (int)$user['id'];
        if (self::problems($pdo, $user) !== []) {
            auditLog('ops_unlock_blocked', 'cannot request a code: ' . implode(',', self::problems($pdo, $user)));
            return ['ok' => false, 'error' => 'blocked'];
        }
        if (!RateLimit::allow($pdo, "ops:code:$uid:min", 1, 60)) {
            return ['ok' => false, 'error' => 'too_soon', 'retry_after' => RateLimit::retryAfter($pdo, "ops:code:$uid:min", 60)];
        }
        if (!RateLimit::allow($pdo, "ops:code:$uid:hour", 5, 3600) || !RateLimit::allow($pdo, 'ops:code:ip:' . hash('sha256', $ip), 10, 3600)) {
            return ['ok' => false, 'error' => 'too_many', 'retry_after' => 600];
        }

        $code = str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE ops_unlock_codes SET consumed_at = NOW() WHERE user_id = :u AND consumed_at IS NULL")->execute(['u' => $uid]);
        $pdo->prepare("INSERT INTO ops_unlock_codes (user_id, code_hash, ip, expires_at) VALUES (:u, :h, :ip, NOW() + INTERVAL " . self::CODE_MINUTES . " MINUTE)")
            ->execute(['u' => $uid, 'h' => self::hash($uid, $code), 'ip' => substr($ip, 0, 45)]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
        auditLog('ops_unlock_requested', 'code sent to ' . self::mask((string)$user['email']));

        $lang = in_array($user['lang'] ?? 'fr', ['fr', 'en'], true) ? (string)($user['lang'] ?? 'fr') : 'fr';
        $sent = Mailer::opsCode((string)$user['email'], (string)$user['name'], $code, self::CODE_MINUTES, $ip, $lang);
        if (!$sent) {
            if (APP_DEBUG === 'true' && !Mailer::isConfigured()) {
                return ['ok' => true, 'email_masked' => self::mask((string)$user['email']), 'dev_code' => $code];   // development only: no mail server
            }
            $pdo->prepare("UPDATE ops_unlock_codes SET consumed_at = NOW() WHERE id = :i")->execute(['i' => $id]);
            return ['ok' => false, 'error' => 'mail_failed'];
        }
        return ['ok' => true, 'email_masked' => self::mask((string)$user['email'])];
    }

    // ---- step 2: both proofs --------------------------------------------------------------------------------------

    /** @return array{ok:bool,error?:string} error is 'invalid' (whatever the cause), 'too_many' or 'blocked' */
    public static function unlock(PDO $pdo, array $user, string $emailCode, string $totp, string $ip): array
    {
        $uid = (int)$user['id'];
        if (!RateLimit::allow($pdo, "ops:unlock:$uid", 6, 900) || !RateLimit::allow($pdo, 'ops:unlock:ip:' . hash('sha256', $ip), 20, 3600)) {
            auditLog('ops_unlock_throttled', 'too many attempts');
            return ['ok' => false, 'error' => 'too_many'];
        }
        if (self::problems($pdo, $user) !== []) {
            return ['ok' => false, 'error' => 'blocked'];
        }
        $emailCode = preg_replace('/\D/', '', $emailCode) ?? '';
        $totp = preg_replace('/\D/', '', $totp) ?? '';
        $fail = function (string $why) use ($ip): array {
            auditLog('ops_unlock_failed', $why);   // the reason stays in the log, never in the answer
            return ['ok' => false, 'error' => 'invalid'];
        };
        if (strlen($emailCode) !== 8 || strlen($totp) !== 6) {
            return $fail('malformed codes');
        }

        // The e-mailed code first: it has its own try counter, and the authenticator code is only spent when this one is right
        $st = $pdo->prepare("SELECT id, code_hash, attempts FROM ops_unlock_codes WHERE user_id = :u AND consumed_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $st->execute(['u' => $uid]);
        $row = $st->fetch();
        if (!$row) {
            return $fail('no active e-mail code');
        }
        $pdo->prepare("UPDATE ops_unlock_codes SET attempts = attempts + 1" . ((int)$row['attempts'] + 1 >= self::MAX_TRIES ? ", consumed_at = IF(consumed_at IS NULL, NOW(), consumed_at)" : '') . " WHERE id = :i")
            ->execute(['i' => (int)$row['id']]);
        if (!hash_equals((string)$row['code_hash'], self::hash($uid, $emailCode))) {
            return $fail('wrong e-mail code (try ' . ((int)$row['attempts'] + 1) . ' of ' . self::MAX_TRIES . ')');
        }
        if (!TwoFactor::verifyAuthenticator($pdo, $uid, $totp)) {
            return $fail('wrong authenticator code');
        }
        // Single use: only the request that flips the row wins
        $use = $pdo->prepare("UPDATE ops_unlock_codes SET consumed_at = NOW() WHERE id = :i AND consumed_at IS NULL");
        $use->execute(['i' => (int)$row['id']]);
        if ($use->rowCount() !== 1) {
            return $fail('e-mail code already used');
        }

        session_regenerate_id(true);
        $now = time();
        $_SESSION['ops_unlock'] = ['uid' => $uid, 'at' => $now, 'seen' => $now, 'bind' => self::bind()];
        RateLimit::reset($pdo, "ops:unlock:$uid");
        auditLog('ops_unlock_ok', 'control center opened');

        $lang = in_array($user['lang'] ?? 'fr', ['fr', 'en'], true) ? (string)($user['lang'] ?? 'fr') : 'fr';
        $en = $lang === 'en';
        Mailer::securityNotice((string)$user['email'], (string)$user['name'],
            ($en ? 'The control center was opened with your account' : 'Le centre de contrôle a été ouvert avec votre compte') . ' — IP ' . $ip . ', ' . date('d/m/Y H:i'), $lang);
        return ['ok' => true];
    }

    // ---- helpers -------------------------------------------------------------------------------------------------

    private static function hash(int $userId, string $code): string
    {
        return hash_hmac('sha256', 'ops-unlock|' . $userId . '|' . $code, (string)APP_SECRET);
    }

    /** What ties an open session to this browser and network. */
    private static function bind(): string
    {
        $ip = function_exists('getClientIp') ? getClientIp() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if (str_contains($ip, ':')) {
            $packed = @inet_pton($ip);
            $net = $packed !== false ? bin2hex(substr($packed, 0, 6)) : $ip;                 // IPv6: first 48 bits
        } else {
            $net = implode('.', array_slice(explode('.', $ip), 0, 3));                       // IPv4: the /24
        }
        return hash_hmac('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . $net, (string)APP_SECRET);
    }

    public static function mask(string $email): string
    {
        [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($u, 0, 1) . str_repeat('•', max(1, min(6, mb_strlen($u) - 1))) . '@' . $d;
    }
}
