<?php
declare(strict_types=1);

require_once __DIR__ . '/WebPush.php';

/**
 * Push notifications for the installed app. Every in-app notification (Notifications::send) is also queued here when the
 * user has at least one device subscribed; lib/email-worker.php (cron every minute, and started right after a request)
 * sends the queue. A device that answers "gone" (404/410) is forgotten, a temporary error is retried a few times.
 */
final class PushQueue
{
    private const MAX_ATTEMPTS = 3;
    private const MAX_DEVICES = 8;

    public static function hasDevice(PDO $pdo, int $userId): bool
    {
        $st = $pdo->prepare('SELECT 1 FROM push_subscriptions WHERE user_id = :u LIMIT 1');
        $st->execute(['u' => $userId]);
        return (bool)$st->fetchColumn();
    }

    /** Queues a push for a user. Never throws: push must not break the action that caused it. Returns the row id or null. */
    public static function enqueue(PDO $pdo, int $userId, string $title, string $body = '', ?string $url = null, ?string $tag = null): ?int
    {
        try {
            if (!self::hasDevice($pdo, $userId)) {
                return null;
            }
            $payload = json_encode([
                'title' => mb_strimwidth($title, 0, 90, '…'),
                'body'  => mb_strimwidth($body, 0, 180, '…'),
                'url'   => ($url !== null && str_starts_with($url, '/') && !str_starts_with($url, '//')) ? $url : '/',
                'tag'   => $tag,
            ], JSON_UNESCAPED_UNICODE);
            $pdo->prepare("INSERT INTO push_outbox (user_id, payload) VALUES (:u, :p)")->execute(['u' => $userId, 'p' => $payload]);
            $id = (int)$pdo->lastInsertId();
            require_once __DIR__ . '/EmailQueue.php';
            EmailQueue::kick();                 // the same background worker sends mail and push
            return $id;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Registers (or refreshes) a device for a user. Returns false on invalid keys. */
    public static function subscribe(PDO $pdo, int $userId, string $endpoint, string $p256dh, string $auth, string $ua = ''): bool
    {
        $pub = WebPush::unb64u($p256dh);
        if (!preg_match('#^https://[^\s]{10,590}$#', $endpoint) || strlen($pub) !== 65 || strlen(WebPush::unb64u($auth)) < 16) {
            return false;
        }
        $hash = hash('sha256', $endpoint);
        $pdo->prepare(
            "INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent)
             VALUES (:u, :e, :h, :k, :a, :ua)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent), failures = 0"
        )->execute(['u' => $userId, 'e' => $endpoint, 'h' => $hash, 'k' => $p256dh, 'a' => $auth, 'ua' => mb_substr($ua, 0, 255)]);
        // keep the most recent devices only
        $pdo->prepare(
            "DELETE FROM push_subscriptions WHERE user_id = :u AND id NOT IN (SELECT id FROM (SELECT id FROM push_subscriptions WHERE user_id = :u2 ORDER BY id DESC LIMIT " . self::MAX_DEVICES . ") t)"
        )->execute(['u' => $userId, 'u2' => $userId]);
        return true;
    }

    public static function unsubscribe(PDO $pdo, int $userId, string $endpoint): void
    {
        $pdo->prepare('DELETE FROM push_subscriptions WHERE user_id = :u AND endpoint_hash = :h')->execute(['u' => $userId, 'h' => hash('sha256', $endpoint)]);
    }

    public static function deviceCount(PDO $pdo, int $userId): int
    {
        $st = $pdo->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = :u');
        $st->execute(['u' => $userId]);
        return (int)$st->fetchColumn();
    }

    /** Sends what is queued. @return int number of messages delivered to at least one device */
    public static function work(PDO $pdo, int $max = 300): int
    {
        if (!WebPush::isConfigured() && WebPush::$transport === null) {
            return 0;
        }
        $rows = $pdo->query("SELECT id, user_id, payload, attempts FROM push_outbox WHERE status = 'queued' ORDER BY id ASC LIMIT " . (int)$max)->fetchAll(PDO::FETCH_ASSOC);
        $subs = $pdo->prepare('SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = :u');
        $okDev = $pdo->prepare('UPDATE push_subscriptions SET last_ok_at = NOW(), failures = 0 WHERE id = :i');
        $badDev = $pdo->prepare('UPDATE push_subscriptions SET failures = failures + 1 WHERE id = :i');
        $delDev = $pdo->prepare('DELETE FROM push_subscriptions WHERE id = :i');
        $done = 0;
        foreach ($rows as $r) {
            $subs->execute(['u' => $r['user_id']]);
            $devices = $subs->fetchAll(PDO::FETCH_ASSOC);
            $delivered = 0;
            $retry = false;
            $err = null;
            foreach ($devices as $d) {
                $res = WebPush::send($d, (string)$r['payload']);
                if ($res['ok']) { $delivered++; $okDev->execute(['i' => $d['id']]); continue; }
                if ($res['gone']) { $delDev->execute(['i' => $d['id']]); continue; }
                $err = $res['error'] ?: ('HTTP ' . $res['status']);
                $badDev->execute(['i' => $d['id']]);
                $retry = true;
            }
            $attempts = (int)$r['attempts'] + 1;
            if ($delivered > 0 || (!$retry)) {
                $pdo->prepare("UPDATE push_outbox SET status = 'sent', attempts = :a, sent_at = NOW(), last_error = NULL WHERE id = :i")->execute(['a' => $attempts, 'i' => $r['id']]);
                if ($delivered > 0) { $done++; }
            } elseif ($attempts >= self::MAX_ATTEMPTS) {
                $pdo->prepare("UPDATE push_outbox SET status = 'failed', attempts = :a, last_error = :e WHERE id = :i")->execute(['a' => $attempts, 'e' => mb_substr((string)$err, 0, 250), 'i' => $r['id']]);
            } else {
                $pdo->prepare("UPDATE push_outbox SET attempts = :a, last_error = :e WHERE id = :i")->execute(['a' => $attempts, 'e' => mb_substr((string)$err, 0, 250), 'i' => $r['id']]);
            }
        }
        $pdo->exec("DELETE FROM push_outbox WHERE status <> 'queued' AND created_at < NOW() - INTERVAL 14 DAY");
        return $done;
    }
}
