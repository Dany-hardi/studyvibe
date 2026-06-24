<?php
declare(strict_types=1);

class Notifications
{
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
    }

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

    public static function countUnread(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
        $stmt->execute(['uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function markRead(PDO $pdo, int $userId, int $notificationId): void
    {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid")
            ->execute(['id' => $notificationId, 'uid' => $userId]);
    }

    public static function markAllRead(PDO $pdo, int $userId): void
    {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0")
            ->execute(['uid' => $userId]);
    }
}
