<?php
declare(strict_types=1);

/**
 * Student number (matricule) rules, shared by every endpoint that accepts one.
 *
 *   YY L NNN(N)   two digits for the year the student joined, one letter, then three or four digits (6 or 7 characters).
 *
 * The year cannot be in the future. Whether the number really belongs to the student is theirs to answer for,
 * so nothing else is checked, except that no two accounts may hold the same matricule.
 */
final class Matricule
{
    public const PATTERN = '/^(\d{2})([A-Z])(\d{3,4})$/';

    public static function normalize(string $raw): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($raw)) ?? '');
    }

    /** @return string|null error code (empty | format | future) or NULL when the value is acceptable */
    public static function check(string $normalized, ?int $currentYear = null): ?string
    {
        if ($normalized === '') {
            return 'empty';
        }
        if (!preg_match(self::PATTERN, $normalized, $m)) {
            return 'format';
        }
        $year = (int)($currentYear ?? date('Y'));
        return (2000 + (int)$m[1]) > $year ? 'future' : null;
    }

    /** @param bool $olderOnly count only accounts created before this one: the first holder keeps the number */
    public static function isTaken(PDO $pdo, string $normalized, int $exceptUserId, bool $olderOnly = false): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE matricule = :m AND id ' . ($olderOnly ? '<' : '<>') . ' :id LIMIT 1');
        $stmt->execute(['m' => $normalized, 'id' => $exceptUserId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function message(string $code): string
    {
        return [
            'empty'  => 'Le numéro de matricule ne peut pas être vide.',
            'format' => 'Ce matricule ne semble pas valide. Vérifiez-le et réessayez.',
            'future' => 'L’année indiquée au début de ce matricule n’est pas encore arrivée. Vérifiez les deux premiers chiffres.',
            'taken'  => 'Ce matricule est déjà enregistré sur un autre compte. Vérifiez-le et corrigez-le si vous vous êtes trompé. S’il est bien le vôtre, contactez l’administration.',
        ][$code] ?? 'Matricule invalide.';
    }
}
