<?php
declare(strict_types=1);

/**
 * Jetons de réinitialisation mot de passe et vérification email.
 */
class AuthTokens
{
    private const RESET_HOURS = 2;
    private const VERIFY_HOURS = 48;

    public static function createPasswordReset(PDO $pdo, int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + self::RESET_HOURS * 3600);

        $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL")
            ->execute(['uid' => $userId]);

        $stmt = $pdo->prepare("
            INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
            VALUES (:uid, :hash, :exp)
        ");
        $stmt->execute(['uid' => $userId, 'hash' => $hash, 'exp' => $expires]);

        return $token;
    }

    public static function validatePasswordReset(PDO $pdo, string $token): ?array
    {
        $hash = hash('sha256', $token);
        $stmt = $pdo->prepare("
            SELECT prt.*, u.email, u.name
            FROM password_reset_tokens prt
            JOIN users u ON u.id = prt.user_id
            WHERE prt.token_hash = :hash AND prt.used_at IS NULL AND prt.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function consumePasswordReset(PDO $pdo, string $token): void
    {
        $hash = hash('sha256', $token);
        $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = :hash")
            ->execute(['hash' => $hash]);
    }

    public static function createEmailVerification(PDO $pdo, int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + self::VERIFY_HOURS * 3600);

        $pdo->prepare("UPDATE email_verification_tokens SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL")
            ->execute(['uid' => $userId]);

        $stmt = $pdo->prepare("
            INSERT INTO email_verification_tokens (user_id, token_hash, expires_at)
            VALUES (:uid, :hash, :exp)
        ");
        $stmt->execute(['uid' => $userId, 'hash' => $hash, 'exp' => $expires]);

        return $token;
    }

    public static function verifyEmail(PDO $pdo, string $token): bool
    {
        $hash = hash('sha256', $token);
        $stmt = $pdo->prepare("
            SELECT user_id FROM email_verification_tokens
            WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $uid = (int)$row['user_id'];
        $pdo->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = :id")->execute(['id' => $uid]);
        $pdo->prepare("UPDATE email_verification_tokens SET used_at = NOW() WHERE token_hash = :hash")
            ->execute(['hash' => $hash]);

        return true;
    }
}
