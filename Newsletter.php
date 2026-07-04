<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Newsletter & Campaign Management Service
 * 
 * Manages newsletter subscription lifecycles, active subscriber list queries, 
 * target-audience resolution checks (all subscribers vs student portal users), 
 * unsubscribe token mapping, and audit logging of broadcast history.
 * 
 * @package    StudyVibe
 * @author     Advanced Engineering Team
 */
class Newsletter
{
    // =========================================================================
    // SECTION 1: SUBSCRIPTION LIFECYCLE MUTATIONS
    // =========================================================================

    /**
     * Subscribes a new email address or reactivates an existing subscription.
     * 
     * @param PDO      $pdo    Database connection instance.
     * @param string   $email  The subscriber email address.
     * @param string   $name   The subscriber name.
     * @param int|null $userId Link to user account id if authenticated.
     * @return bool True if execution succeeded, false on invalid format or DB errors.
     */
    public static function subscribe(PDO $pdo, string $email, string $name = '', ?int $userId = null): bool
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Generate unique security token for unsubscribe link queries
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

    /**
     * Deactivates a subscriber account using a unique unsubscribe token.
     * 
     * @param PDO    $pdo   Database connection instance.
     * @param string $token Unique unsubscribe verification token.
     * @return bool True if a subscriber was successfully deactivated.
     */
    public static function unsubscribe(PDO $pdo, string $token): bool
    {
        $stmt = $pdo->prepare("
            UPDATE newsletter_subscribers SET is_active = 0
            WHERE unsubscribe_token = :token AND is_active = 1
        ");
        $stmt->execute(['token' => $token]);
        return $stmt->rowCount() > 0;
    }

    // =========================================================================
    // SECTION 2: SUBSCRIBER QUERIES
    // =========================================================================

    /**
     * Retrieves all active subscribers.
     * 
     * @param PDO $pdo Database connection instance.
     * @return array List of subscriber row arrays.
     */
    public static function getActiveSubscribers(PDO $pdo): array
    {
        return $pdo->query("
            SELECT id, email, name, user_id, subscribed_at
            FROM newsletter_subscribers
            WHERE is_active = 1
            ORDER BY subscribed_at DESC
        ")->fetchAll();
    }

    // =========================================================================
    // SECTION 3: AUDIENCE RESOLUTION LOGIC
    // =========================================================================

    /**
     * Resolves the list of recipients based on the targeted audience selection.
     * 
     * @param PDO    $pdo      Database connection instance.
     * @param string $audience Target selection ('subscribers' | 'students' | 'all').
     * @return array List of unique recipients with keys [email, name, token].
     */
    public static function resolveRecipients(PDO $pdo, string $audience): array
    {
        $recipients = [];

        // Include subscribers if requested
        if ($audience === 'subscribers' || $audience === 'all') {
            foreach (self::getActiveSubscribers($pdo) as $sub) {
                $recipients[$sub['email']] = [
                    'email' => $sub['email'],
                    'name'  => $sub['name'] ?: 'Abonné',
                    'token' => self::getTokenForEmail($pdo, $sub['email']),
                ];
            }
        }

        // Include student portal users if requested
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

    /**
     * Retrieves the unsubscribe token for a given email address.
     * 
     * @param PDO    $pdo   Database connection instance.
     * @param string $email Target email.
     * @return string Unsubscribe token, or empty string if not found.
     */
    private static function getTokenForEmail(PDO $pdo, string $email): string
    {
        $stmt = $pdo->prepare("SELECT unsubscribe_token FROM newsletter_subscribers WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => strtolower($email)]);
        $token = $stmt->fetchColumn();
        return $token ? (string)$token : '';
    }

    /**
     * Guarantees that a student has an unsubscribe token, subscribing them if not present.
     * 
     * @param PDO    $pdo    Database connection instance.
     * @param int    $userId Student user ID.
     * @param string $email  Student email.
     * @param string $name   Student name.
     * @return string Resolved unsubscribe token.
     */
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

    // =========================================================================
    // SECTION 4: HISTORICAL AUDIT & LOGGING
    // =========================================================================

    /**
     * Logs the details of a sent newsletter campaign.
     * 
     * @param PDO    $pdo            Database connection instance.
     * @param int    $sentBy         The ID of the administrator/promoter who sent the email.
     * @param string $subject        Newsletter subject.
     * @param string $body           HTML email body.
     * @param int    $recipientCount Total number of recipients.
     * @return void
     */
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

    /**
     * Retrieves historical records of past newsletter campaigns.
     * 
     * @param PDO $pdo   Database connection instance.
     * @param int $limit Maximum number of campaign records to return.
     * @return array List of campaign history rows.
     */
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
