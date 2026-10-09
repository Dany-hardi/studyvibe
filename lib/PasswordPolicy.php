<?php
declare(strict_types=1);

/**
 * One rule for every place that accepts a new password (sign-up, reset, accounts created by a promoter).
 *
 * At least 8 characters, not a password everybody tries first, not the account's own email address.
 * Length matters more than symbols, so there is no "must contain a capital" rule.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    private const COMMON = [
        '12345678', '123456789', '1234567890', '123123123', '11111111', '00000000', 'password', 'password1', 'password123',
        'qwertyui', 'qwerty123', 'azertyui', 'azerty123', 'abcd1234', 'iloveyou', 'motdepasse', 'studyvibe', 'studyvibe1',
        'admin123', 'welcome1', 'azertyuiop', 'qwertyuiop', '987654321', 'letmein1',
    ];

    /** @return string|null a French error message, or null when the password is acceptable */
    public static function check(string $password, string $email = ''): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'Le mot de passe doit contenir au moins ' . self::MIN_LENGTH . ' caractères.';
        }
        $lower = mb_strtolower($password);
        if (in_array($lower, self::COMMON, true) || preg_match('/^(.)\1+$/u', $password) === 1) {
            return 'Ce mot de passe est trop courant. Choisissez-en un autre.';
        }
        if ($email !== '' && $lower === mb_strtolower($email)) {
            return 'Le mot de passe ne peut pas être votre adresse e-mail.';
        }
        return null;
    }
}
