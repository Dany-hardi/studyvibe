<?php
declare(strict_types=1);

/**
 * Rules for joining a live evaluation without being logged in: a working email on an accepted domain,
 * plus a lookup telling whether that email belongs to a StudyVibe account.
 */
final class LiveGuest
{
    public const ALLOWED_DOMAINS = ['gmail.com', 'icloud.com', 'facsciences-uy1.cm'];

    public static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function isValidFormat(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isAllowedDomain(string $email): bool
    {
        $at = strrpos($email, '@');
        return $at !== false && in_array(substr($email, $at + 1), self::ALLOWED_DOMAINS, true);
    }

    /** Returns an error message for the user, or null when the email may be used. */
    public static function check(string $email): ?string
    {
        if ($email === '') {
            return "Veuillez saisir votre adresse e-mail.";
        }
        if (!self::isValidFormat($email)) {
            return "Cette adresse e-mail ne semble pas valide.";
        }
        if (!self::isAllowedDomain($email)) {
            return "Seules les adresses se terminant par gmail.com, icloud.com ou facsciences-uy1.cm sont acceptées.";
        }
        return null;
    }

    /** @return array{id:int,name:string}|null the active StudyVibe student/user with this email */
    public static function findUser(PDO $pdo, string $email): ?array
    {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = :email AND is_active = 1 LIMIT 1");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ? ['id' => (int)$row['id'], 'name' => (string)$row['name']] : null;
    }
}
