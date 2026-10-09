<?php
declare(strict_types=1);

require_once __DIR__ . '/SmsGateway.php';

/**
 * Text messages that are not urgent to the second (an exam announced to a whole class) go through a queue, so the teacher's
 * click answers at once and a background process sends them at the provider's pace, retrying the ones that fail for a
 * passing reason. Verification codes are different: the person is waiting, so they are sent immediately with sendNow().
 *
 * Every message is a row in sms_outbox. dedupe_key makes "send this once" safe to call twice.
 */
final class SmsQueue
{
    public const MAX_ATTEMPTS = 3;
    private const BATCH = 25;

    /** Adds a message to the queue. Returns the row id, or null when this dedupe_key was already queued. */
    public static function enqueue(PDO $pdo, ?int $userId, string $toE164, string $kind, string $body, ?string $dedupeKey = null): ?int
    {
        $st = $pdo->prepare(
            "INSERT IGNORE INTO sms_outbox (user_id, to_phone, kind, body, dedupe_key, status, next_try_at)
             VALUES (:u, :p, :k, :b, :d, 'queued', NOW())"
        );
        $st->execute(['u' => $userId, 'p' => $toE164, 'k' => $kind, 'b' => SmsGateway::plain($body), 'd' => $dedupeKey]);
        return $st->rowCount() === 1 ? (int)$pdo->lastInsertId() : null;
    }

    /**
     * Sends right now and records the result. $logBody is what is kept in the database (a verification code is never kept).
     *
     * @return array{ok:bool,id?:string,error?:string,retry?:bool}
     */
    public static function sendNow(PDO $pdo, ?int $userId, string $toE164, string $kind, string $body, string $logBody, ?string $ip = null): array
    {
        $pdo->prepare("INSERT INTO sms_outbox (user_id, to_phone, kind, body, status, attempts, ip) VALUES (:u, :p, :k, :b, 'sending', 1, :ip)")
            ->execute(['u' => $userId, 'p' => $toE164, 'k' => $kind, 'b' => $logBody, 'ip' => $ip]);
        $rowId = (int)$pdo->lastInsertId();
        $res = SmsGateway::send($toE164, SmsGateway::plain($body));
        $pdo->prepare("UPDATE sms_outbox SET status = :s, provider_id = :pid, error = :e, sent_at = IF(:ok = 1, NOW(), NULL) WHERE id = :id")
            ->execute([
                's'   => $res['ok'] ? 'sent' : 'failed',
                'pid' => $res['id'] ?? null,
                'e'   => isset($res['error']) ? substr($res['error'], 0, 250) : null,
                'ok'  => $res['ok'] ? 1 : 0,
                'id'  => $rowId,
            ]);
        return $res;
    }

    /** Starts the background sender (at most one every 3 seconds). Rows stay queued if it cannot start; cron picks them up. */
    public static function kick(): void
    {
        $flag = dirname(__DIR__) . '/uploads/live_cache/sms_kick.flag';
        @mkdir(dirname($flag), 0755, true);
        $fh = @fopen($flag, 'c');
        if (!$fh) {
            return;
        }
        $go = false;
        if (flock($fh, LOCK_EX | LOCK_NB)) {
            $go = (time() - (int)@filemtime($flag)) >= 3 || filesize($flag) === 0;
            if ($go) {
                ftruncate($fh, 0); fwrite($fh, (string)time()); fflush($fh); @touch($flag);
            }
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        if (!$go || !function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
            return;
        }
        $php = PHP_BINARY && is_executable(PHP_BINARY) && !str_contains(PHP_BINARY, 'apache') && !str_contains(PHP_BINARY, 'fpm') ? PHP_BINARY : (trim((string)@shell_exec('command -v php')) ?: null);
        $script = realpath(__DIR__ . '/sms-worker.php');
        if (!$php || !$script) {
            return;
        }
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script);
        if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
            @pclose(@popen('start /B "" ' . $cmd, 'r'));
        } else {
            @exec($cmd . ' > /dev/null 2>&1 &');
        }
    }

    /** Sends queued messages until none is due. Returns how many were sent. */
    public static function work(PDO $pdo, int $maxRows = 500): int
    {
        // A worker that died mid-send leaves its rows "sending": give them back after 5 minutes
        $pdo->exec("UPDATE sms_outbox SET status = 'queued' WHERE status = 'sending' AND kind <> 'otp' AND created_at < NOW() - INTERVAL 5 MINUTE AND (sent_at IS NULL)");
        $sent = 0;
        $handled = 0;
        while ($handled < $maxRows) {
            $ids = $pdo->query(
                "SELECT id FROM sms_outbox WHERE status = 'queued' AND (next_try_at IS NULL OR next_try_at <= NOW()) ORDER BY id ASC LIMIT " . self::BATCH
            )->fetchAll(PDO::FETCH_COLUMN);
            if (!$ids) {
                break;
            }
            foreach ($ids as $id) {
                // Claim the row: if two workers race, only one UPDATE changes it
                $claim = $pdo->prepare("UPDATE sms_outbox SET status = 'sending', attempts = attempts + 1 WHERE id = :id AND status = 'queued'");
                $claim->execute(['id' => $id]);
                if ($claim->rowCount() !== 1) {
                    continue;
                }
                $handled++;
                $row = $pdo->query("SELECT to_phone, body, attempts FROM sms_outbox WHERE id = " . (int)$id)->fetch(PDO::FETCH_ASSOC);
                $res = SmsGateway::send((string)$row['to_phone'], (string)$row['body']);
                if ($res['ok']) {
                    $sent++;
                    $pdo->prepare("UPDATE sms_outbox SET status = 'sent', provider_id = :p, sent_at = NOW(), error = NULL WHERE id = :id")
                        ->execute(['p' => $res['id'] ?? null, 'id' => $id]);
                } elseif (!empty($res['retry']) && (int)$row['attempts'] < self::MAX_ATTEMPTS) {
                    $wait = 30 * ((int)$row['attempts'] ** 2);
                    $pdo->prepare("UPDATE sms_outbox SET status = 'queued', error = :e, next_try_at = NOW() + INTERVAL :w SECOND WHERE id = :id")
                        ->execute(['e' => substr((string)($res['error'] ?? ''), 0, 250), 'w' => $wait, 'id' => $id]);
                } else {
                    $pdo->prepare("UPDATE sms_outbox SET status = 'failed', error = :e WHERE id = :id")
                        ->execute(['e' => substr((string)($res['error'] ?? ''), 0, 250), 'id' => $id]);
                }
            }
        }
        return $sent;
    }
}
