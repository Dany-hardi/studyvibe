<?php
declare(strict_types=1);

/**
 * Gestion des abonnés et campagnes newsletter StudyVibe.
 */
class Newsletter
{
    /** Inscrit ou réactive un abonné. */
    public static function subscribe(PDO $pdo, string $email, string $name = '', ?int $userId = null): bool
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $token = bin2hex(random_bytes(32));

        $stmt = $pdo->prepare("
            INSERT INTO newsletter_subscribers (email, name, user_id, unsubscribe_token, is_active)
            VALUES (:email, :name, :user_id, :token, 1)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                user_id = COALESCE(VALUES(user_id), user_id),
                is_active = 1,
                subscribed_at = CURRENT_TIMESTAMP
        ");

        return $stmt->execute([
            'email'   => $email,
            'name'    => $name,
            'user_id' => $userId,
            'token'   => $token,
        ]);
    }

    /** Désabonne via token unique. */
    public static function unsubscribe(PDO $pdo, string $token): bool
    {
        $stmt = $pdo->prepare("
            UPDATE newsletter_subscribers SET is_active = 0
            WHERE unsubscribe_token = :token AND is_active = 1
        ");
        $stmt->execute(['token' => $token]);
        return $stmt->rowCount() > 0;
    }

    /** Retourne les abonnés actifs. */
    public static function getActiveSubscribers(PDO $pdo): array
    {
        return $pdo->query("
            SELECT id, email, name, user_id, subscribed_at
            FROM newsletter_subscribers
            WHERE is_active = 1
            ORDER BY subscribed_at DESC
        ")->fetchAll();
    }

    /** Résout la liste de destinataires selon l'audience choisie. */
    public static function resolveRecipients(PDO $pdo, string $audience): array
    {
        $recipients = [];

        if ($audience === 'subscribers' || $audience === 'all') {
            foreach (self::getActiveSubscribers($pdo) as $sub) {
                $recipients[$sub['email']] = [
                    'email' => $sub['email'],
                    'name'  => $sub['name'] ?: 'Abonné',
                    'token' => self::getTokenForEmail($pdo, $sub['email']),
                ];
            }
        }

        if ($audience === 'students' || $audience === 'all') {
            $students = $pdo->query("SELECT id, email, name FROM users WHERE role = 'student'")->fetchAll();
            foreach ($students as $s) {
                if (!isset($recipients[$s['email']])) {
                    $recipients[$s['email']] = [
                        'email' => $s['email'],
                        'name'  => $s['name'],
                        'token' => self::ensureTokenForUser($pdo, (int)$s['id'], $s['email'], $s['name']),
                    ];
                }
            }
        }

        return array_values($recipients);
    }

    private static function getTokenForEmail(PDO $pdo, string $email): string
    {
        $stmt = $pdo->prepare("SELECT unsubscribe_token FROM newsletter_subscribers WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => strtolower($email)]);
        $token = $stmt->fetchColumn();
        return $token ? (string)$token : '';
    }

    /** Garantit un token de désabonnement pour un étudiant non encore abonné. */
    private static function ensureTokenForUser(PDO $pdo, int $userId, string $email, string $name): string
    {
        $existing = self::getTokenForEmail($pdo, $email);
        if ($existing !== '') {
            return $existing;
        }
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("
            INSERT INTO newsletter_subscribers (email, name, user_id, unsubscribe_token, is_active)
            VALUES (:email, :name, :user_id, :token, 1)
            ON DUPLICATE KEY UPDATE unsubscribe_token = COALESCE(unsubscribe_token, VALUES(unsubscribe_token))
        ")->execute([
            'email'   => strtolower($email),
            'name'    => $name,
            'user_id' => $userId,
            'token'   => $token,
        ]);
        return $token;
    }

    /** Enregistre une campagne envoyée. */
    public static function logCampaign(PDO $pdo, int $sentBy, string $subject, string $body, int $recipientCount): void
    {
        $pdo->prepare("
            INSERT INTO newsletter_campaigns (subject, body_html, sent_by, recipient_count)
            VALUES (:subject, :body, :sent_by, :count)
        ")->execute([
            'subject'  => $subject,
            'body'     => $body,
            'sent_by'  => $sentBy,
            'count'    => $recipientCount,
        ]);
    }

    /** Historique des campagnes. */
    public static function getCampaigns(PDO $pdo, int $limit = 20): array
    {
        $stmt = $pdo->prepare("
            SELECT nc.*, u.name AS sender_name
            FROM newsletter_campaigns nc
            JOIN users u ON u.id = nc.sent_by
            ORDER BY nc.sent_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
