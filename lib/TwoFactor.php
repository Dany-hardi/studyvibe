<?php
declare(strict_types=1);

require_once __DIR__ . '/Totp.php';
require_once __DIR__ . '/RateLimit.php';

/**
 * Two-factor authentication with an authenticator app, for every role.
 *
 * Turning it on is two steps so a mistyped secret can never lock somebody out: begin() makes a secret that lives only in
 * the session; the account is switched over by confirm(), after the person typed a valid code from their app. At that
 * moment 10 one-time recovery codes are made and shown once.
 *
 * Signing in: after the password, the account is "half signed in" (a pending marker in the session, no user id) until a
 * code from the app, or a recovery code, is accepted. Wrong codes are limited per account and per address.
 */
final class TwoFactor
{
    public const RECOVERY_COUNT = 10;
    private const PENDING_TTL = 300;      // seconds the second step stays open after the password
    private const MAX_FAILS = 5;          // per 15 minutes, per account

    public static function isEnabled(PDO $pdo, int $userId): bool
    {
        $st = $pdo->prepare("SELECT totp_enabled_at IS NOT NULL FROM users WHERE id = :id");
        $st->execute(['id' => $userId]);
        return (bool)$st->fetchColumn();
    }

    /** @return array{secret:string,uri:string} kept in the session until confirm() */
    public static function begin(array $user): array
    {
        $secret = Totp::newSecret();
        $_SESSION['tfa_setup'] = ['secret' => $secret, 'at' => time(), 'uid' => (int)$user['id']];
        return ['secret' => $secret, 'uri' => Totp::uri($secret, (string)$user['email'])];
    }

    /** @return array{ok:bool,error?:string,recovery_codes?:string[]} */
    public static function confirm(PDO $pdo, int $userId, string $code, string $ip): array
    {
        $setup = $_SESSION['tfa_setup'] ?? null;
        if (!is_array($setup) || (int)$setup['uid'] !== $userId || time() - (int)$setup['at'] > 900) {
            unset($_SESSION['tfa_setup']);
            return ['ok' => false, 'error' => 'setup_expired'];
        }
        if (!self::allowAttempt($pdo, $userId, $ip)) {
            return ['ok' => false, 'error' => 'too_many'];
        }
        $step = Totp::verify((string)$setup['secret'], $code);
        if ($step === null) {
            return ['ok' => false, 'error' => 'code_wrong'];
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET totp_secret_enc = :s, totp_enabled_at = NOW(), totp_last_step = :st WHERE id = :id")
                ->execute(['s' => Totp::encrypt((string)$setup['secret']), 'st' => $step, 'id' => $userId]);
            $codes = self::makeRecoveryCodes($pdo, $userId);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        unset($_SESSION['tfa_setup']);
        RateLimit::reset($pdo, "tfa:user:$userId");
        return ['ok' => true, 'recovery_codes' => $codes];
    }

    /** Turning it off needs the password and a current code (or a recovery code): a stolen session alone is not enough. */
    public static function disable(PDO $pdo, int $userId, string $password, string $code, string $ip): array
    {
        $st = $pdo->prepare("SELECT password FROM users WHERE id = :id");
        $st->execute(['id' => $userId]);
        $hash = (string)$st->fetchColumn();
        if (!self::allowAttempt($pdo, $userId, $ip)) {
            return ['ok' => false, 'error' => 'too_many'];
        }
        if ($hash === '' || !password_verify($password, $hash)) {
            return ['ok' => false, 'error' => 'password_wrong'];
        }
        if (!self::checkSecondFactor($pdo, $userId, $code)) {
            return ['ok' => false, 'error' => 'code_wrong'];
        }
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE users SET totp_secret_enc = NULL, totp_enabled_at = NULL, totp_last_step = 0 WHERE id = :id")->execute(['id' => $userId]);
        $pdo->prepare("DELETE FROM user_recovery_codes WHERE user_id = :id")->execute(['id' => $userId]);
        $pdo->commit();
        RateLimit::reset($pdo, "tfa:user:$userId");
        return ['ok' => true];
    }

    /** New set of recovery codes (the old ones stop working). Needs a current code. */
    public static function regenerateRecovery(PDO $pdo, int $userId, string $code, string $ip): array
    {
        if (!self::allowAttempt($pdo, $userId, $ip)) {
            return ['ok' => false, 'error' => 'too_many'];
        }
        if (!self::checkSecondFactor($pdo, $userId, $code, false)) {
            return ['ok' => false, 'error' => 'code_wrong'];
        }
        $pdo->beginTransaction();
        $codes = self::makeRecoveryCodes($pdo, $userId);
        $pdo->commit();
        return ['ok' => true, 'recovery_codes' => $codes];
    }

    public static function remainingRecovery(PDO $pdo, int $userId): int
    {
        $st = $pdo->prepare("SELECT COUNT(*) FROM user_recovery_codes WHERE user_id = :id AND used_at IS NULL");
        $st->execute(['id' => $userId]);
        return (int)$st->fetchColumn();
    }

    // ---- signing in ----------------------------------------------------------------------------------------------

    /** Called after the password was right. Leaves the session "half signed in". */
    public static function startLoginChallenge(int $userId, string $role, ?string $redirect): void
    {
        session_regenerate_id(true);
        unset($_SESSION['user_id'], $_SESSION['user_role']);
        $_SESSION['tfa_pending'] = ['uid' => $userId, 'role' => $role, 'at' => time(), 'redirect' => $redirect, 'fails' => 0];
    }

    public static function pending(): ?array
    {
        $p = $_SESSION['tfa_pending'] ?? null;
        if (!is_array($p) || time() - (int)$p['at'] > self::PENDING_TTL) {
            unset($_SESSION['tfa_pending']);
            return null;
        }
        return $p;
    }

    /** @return array{ok:bool,error?:string,uid?:int,role?:string,redirect?:?string} */
    public static function finishLogin(PDO $pdo, string $code, string $ip): array
    {
        $p = self::pending();
        if ($p === null) {
            return ['ok' => false, 'error' => 'login_expired'];
        }
        $uid = (int)$p['uid'];
        if (!self::allowAttempt($pdo, $uid, $ip)) {
            unset($_SESSION['tfa_pending']);
            return ['ok' => false, 'error' => 'too_many'];
        }
        if (!self::checkSecondFactor($pdo, $uid, $code)) {
            $_SESSION['tfa_pending']['fails'] = (int)$p['fails'] + 1;
            if ($_SESSION['tfa_pending']['fails'] >= self::MAX_FAILS) {
                unset($_SESSION['tfa_pending']);
                return ['ok' => false, 'error' => 'login_expired'];
            }
            return ['ok' => false, 'error' => 'code_wrong'];
        }
        unset($_SESSION['tfa_pending']);
        RateLimit::reset($pdo, "tfa:user:$uid");
        return ['ok' => true, 'uid' => $uid, 'role' => (string)$p['role'], 'redirect' => $p['redirect'] ?? null];
    }

    // ---- internals -----------------------------------------------------------------------------------------------

    private static function allowAttempt(PDO $pdo, int $userId, string $ip): bool
    {
        return RateLimit::allow($pdo, "tfa:user:$userId", self::MAX_FAILS * 2, 900)
            && RateLimit::allow($pdo, 'tfa:ip:' . hash('sha256', $ip), 30, 3600);
    }

    /** A code from the app, or (when $allowRecovery) an unused recovery code. */
    private static function checkSecondFactor(PDO $pdo, int $userId, string $code, bool $allowRecovery = true): bool
    {
        $st = $pdo->prepare("SELECT totp_secret_enc, totp_last_step FROM users WHERE id = :id AND totp_enabled_at IS NOT NULL");
        $st->execute(['id' => $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $secret = Totp::decrypt((string)$row['totp_secret_enc']);
        if ($secret !== null) {
            $step = Totp::verify($secret, $code, (int)$row['totp_last_step']);
            if ($step !== null) {
                // Only the request that moves the step forward wins: a second use of the same code changes no row
                $upd = $pdo->prepare("UPDATE users SET totp_last_step = :s WHERE id = :id AND totp_last_step < :s2");
                $upd->execute(['s' => $step, 'id' => $userId, 's2' => $step]);
                return $upd->rowCount() === 1;
            }
        }
        if (!$allowRecovery) {
            return false;
        }
        $norm = self::normaliseRecovery($code);
        if ($norm === null) {
            return false;
        }
        $use = $pdo->prepare("UPDATE user_recovery_codes SET used_at = NOW() WHERE user_id = :u AND code_hash = :h AND used_at IS NULL");
        $use->execute(['u' => $userId, 'h' => self::hashRecovery($userId, $norm)]);
        return $use->rowCount() === 1;
    }

    /** @return string[] the new codes in clear, shown to the person once */
    private static function makeRecoveryCodes(PDO $pdo, int $userId): array
    {
        $pdo->prepare("DELETE FROM user_recovery_codes WHERE user_id = :id")->execute(['id' => $userId]);
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // no 0/O/1/I, easy to read from paper
        $ins = $pdo->prepare("INSERT INTO user_recovery_codes (user_id, code_hash) VALUES (:u, :h)");
        $out = [];
        for ($i = 0; $i < self::RECOVERY_COUNT; $i++) {
            $raw = '';
            for ($j = 0; $j < 10; $j++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $ins->execute(['u' => $userId, 'h' => self::hashRecovery($userId, $raw)]);
            $out[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
        }
        return $out;
    }

    private static function normaliseRecovery(string $code): ?string
    {
        $c = strtoupper(preg_replace('/[\s-]+/', '', $code) ?? '');
        return preg_match('/^[A-HJ-NP-Z2-9]{10}$/', $c) === 1 ? $c : null;
    }

    private static function hashRecovery(int $userId, string $raw): string
    {
        return hash_hmac('sha256', $raw, APP_SECRET . '|recovery|' . $userId);
    }

    public static function message(string $code, string $lang = 'fr'): string
    {
        $m = [
            'code_wrong'     => ['Code incorrect. Vérifiez l’heure de votre téléphone et réessayez.', 'Incorrect code. Check your phone’s clock and try again.'],
            'too_many'       => ['Trop de tentatives. Réessayez dans 15 minutes.', 'Too many attempts. Try again in 15 minutes.'],
            'login_expired'  => ['La connexion a expiré. Reconnectez-vous.', 'The sign-in expired. Please sign in again.'],
            'setup_expired'  => ['La configuration a expiré. Recommencez.', 'The setup expired. Please start again.'],
            'password_wrong' => ['Mot de passe incorrect.', 'Incorrect password.'],
        ];
        return ($m[$code] ?? ['Une erreur est survenue.', 'Something went wrong.'])[$lang === 'en' ? 1 : 0];
    }
}
