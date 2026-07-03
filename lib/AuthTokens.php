<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Authorization & Security Token Service
 * 
 * Manages the generation, validation, and consumption of security tokens 
 * for email verification and password reset workflows. Tokens are generated 
 * cryptographically and stored as SHA-256 hashes to prevent database leak compromise.
 * 
 * @package    StudyVibe
 * @subpackage Lib
 * @author     Advanced Engineering Team
 */
class AuthTokens
{
    /** @var int Lifespan duration for password reset tokens in hours */
    private const RESET_HOURS = 2;

    /** @var int Lifespan duration for email verification tokens in hours */
    private const VERIFY_HOURS = 48;

    /**
     * Generates a new password reset token for a user and invalidates previous ones.
     * 
     * @param PDO $pdo    Database connection instance.
     * @param int $userId Target user primary key.
     * @return string The raw cryptographically secure token to send in the email.
     */
    public static function createPasswordReset(PDO $pdo, int $userId): string
    {
        // Generate raw security token
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + self::RESET_HOURS * 3600);

        // Revoke active, unconsumed password reset tokens for this user
        $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL")
            ->execute(['uid' => $userId]);

        // Insert new token entry
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
            VALUES (:uid, :hash, :exp)
        ");
        $stmt->execute(['uid' => $userId, 'hash' => $hash, 'exp' => $expires]);

        return $token;
    }

    /**
     * Validates a password reset token, checking for expiration and previous consumption.
     * 
     * @param PDO    $pdo   Database connection instance.
     * @param string $token Raw token value received from user request.
     * @return array|null The validated token record database row, or null if invalid.
     */
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

    /**
     * Consumes (invalidates) a validated password reset token.
     * 
     * @param PDO    $pdo   Database connection instance.
     * @param string $token Raw token value to mark as consumed.
     * @return void
     */
    public static function consumePasswordReset(PDO $pdo, string $token): void
    {
        $hash = hash('sha256', $token);
        $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE token_hash = :hash")
            ->execute(['hash' => $hash]);
    }

    /**
     * Generates an email verification token, invalidating previous verification requests.
     * 
     * @param PDO $pdo    Database connection instance.
     * @param int $userId Target user primary key.
     * @return string The raw cryptographically secure token to send in the verification link.
     */
    public static function createEmailVerification(PDO $pdo, int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + self::VERIFY_HOURS * 3600);

        // Revoke active email verification requests
        $pdo->prepare("UPDATE email_verification_tokens SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL")
            ->execute(['uid' => $userId]);

        // Insert new verification request entry
        $stmt = $pdo->prepare("
            INSERT INTO email_verification_tokens (user_id, token_hash, expires_at)
            VALUES (:uid, :hash, :exp)
        ");
        $stmt->execute(['uid' => $userId, 'hash' => $hash, 'exp' => $expires]);

        return $token;
    }

    /**
     * Verifies an email verification token and marks the corresponding user account verified.
     * 
     * @param PDO    $pdo   Database connection instance.
     * @param string $token Raw token value received from verification request.
     * @return bool True if verification succeeded, false otherwise.
     */
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
        
        // Update user verification timestamp
        $pdo->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = :id")->execute(['id' => $uid]);
        
        // Invalidate token
        $pdo->prepare("UPDATE email_verification_tokens SET used_at = NOW() WHERE token_hash = :hash")
            ->execute(['hash' => $hash]);

        return true;
    }
}
