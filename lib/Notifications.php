<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Notifications Service
 * 
 * Handles the creation, fetching, counting, and marking of notifications 
 * for all user roles. Provides real-time unread alert counting.
 * 
 * @package    StudyVibe
 * @subpackage Lib
 * @author     Advanced Engineering Team
 */
class Notifications
{
    /**
     * Dispatches/stores a notification alert to a specific recipient user.
     * 
     * @param PDO         $pdo      Database connection instance.
     * @param int         $userId   The target user primary key.
     * @param string      $type     The alert category (e.g. 'exam', 'general').
     * @param string      $title    Short header description of the notification.
     * @param string      $body     Complete notification description/body.
     * @param string|null $link     Optional redirect action path.
     * @return void
     */
    public static function send(PDO $pdo, int $userId, string $type, string $title, string $body = '', ?string $link = null): void
    {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, body, link)
            VALUES (:uid, :type, :title, :body, :link)
        ");
        $stmt->execute([
            'uid'   => $userId,
            'type'  => $type,
            'title' => $title,
            'body'  => $body,
            'link'  => $link,
        ]);
        // The installed app also gets it as a push, when this user has a device subscribed (never breaks the caller).
        require_once __DIR__ . '/PushQueue.php';
        PushQueue::enqueue($pdo, $userId, $title, $body, $link, $type);
    }

    /**
     * The in-app (and push) twin of an email: same moment, same person. Never throws, ignores a missing recipient,
     * so a mail path can call it without caring whether the person has an account or the table is reachable.
     */
    public static function mirror(PDO $pdo, ?int $userId, string $type, string $title, string $body = '', ?string $link = null): void
    {
        if (!$userId) {
            return;
        }
        try {
            self::send($pdo, $userId, $type, $title, $body, $link);
        } catch (Throwable $e) {
            error_log('Notification mirror failed: ' . $e->getMessage());
        }
    }

    /**
     * Lists notifications for a given user, prioritising unread alerts.
     * 
     * @param PDO $pdo    Database connection instance.
     * @param int $userId Target user primary key.
     * @param int $limit  Max notification records to retrieve.
     * @return array List of notification record arrays.
     */
    public static function listUnread(PDO $pdo, int $userId, int $limit = 20): array
    {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications
            WHERE user_id = :uid
            ORDER BY is_read ASC, created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue('uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Counts the total number of unread alerts currently waiting for a user.
     * 
     * @param PDO $pdo    Database connection instance.
     * @param int $userId Target user primary key.
     * @return int Unread notification count.
     */
    public static function countUnread(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
        $stmt->execute(['uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Marks a specific notification ID as read by the recipient.
     * 
     * @param PDO $pdo            Database connection instance.
     * @param int $userId         Security verification user primary key.
     * @param int $notificationId Notification target primary key.
     * @return void
     */
    public static function markRead(PDO $pdo, int $userId, int $notificationId): void
    {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid")
            ->execute(['id' => $notificationId, 'uid' => $userId]);
    }

    /**
     * Marks all currently unread notifications for a user as read.
     * 
     * @param PDO $pdo    Database connection instance.
     * @param int $userId Target user primary key.
     * @return void
     */
    public static function markAllRead(PDO $pdo, int $userId): void
    {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0")
            ->execute(['uid' => $userId]);
    }
}
