<?php
declare(strict_types=1);

/**
 * A small "at most N times per window" counter kept in the database, shared by all web workers.
 *
 *   if (!RateLimit::allow($pdo, 'otp:user:12', 5, 3600)) { ...refuse... }
 *
 * allow() counts the hits already recorded in the window and records a new one when there is room.
 */
final class RateLimit
{
    public static function allow(PDO $pdo, string $bucket, int $max, int $windowSeconds): bool
    {
        $bucket = substr($bucket, 0, 190);
        if (mt_rand(1, 100) === 1) {
            $pdo->exec("DELETE FROM rate_hits WHERE hit_at < NOW() - INTERVAL 1 DAY");
        }
        $count = $pdo->prepare("SELECT COUNT(*) FROM rate_hits WHERE bucket = :b AND hit_at >= NOW() - INTERVAL :w SECOND");
        $count->bindValue('b', $bucket);
        $count->bindValue('w', $windowSeconds, PDO::PARAM_INT);
        $count->execute();
        if ((int)$count->fetchColumn() >= $max) {
            return false;
        }
        $pdo->prepare("INSERT INTO rate_hits (bucket, hit_at) VALUES (:b, NOW())")->execute(['b' => $bucket]);
        return true;
    }

    /** Seconds until the oldest hit of the window expires (a hint for "try again in ..."). */
    public static function retryAfter(PDO $pdo, string $bucket, int $windowSeconds): int
    {
        $st = $pdo->prepare("SELECT GREATEST(1, :w - TIMESTAMPDIFF(SECOND, MIN(hit_at), NOW())) FROM rate_hits WHERE bucket = :b AND hit_at >= NOW() - INTERVAL :w2 SECOND");
        $st->bindValue('w', $windowSeconds, PDO::PARAM_INT);
        $st->bindValue('w2', $windowSeconds, PDO::PARAM_INT);
        $st->bindValue('b', substr($bucket, 0, 190));
        $st->execute();
        return (int)($st->fetchColumn() ?: $windowSeconds);
    }

    public static function reset(PDO $pdo, string $bucket): void
    {
        $pdo->prepare("DELETE FROM rate_hits WHERE bucket = :b")->execute(['b' => substr($bucket, 0, 190)]);
    }
}
