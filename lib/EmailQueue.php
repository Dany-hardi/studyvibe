<?php
declare(strict_types=1);

require_once __DIR__ . '/../Mailer.php';

/**
 * Emails that go out in the background: the announcement of an evaluation to a whole class, a cancelled or restored result.
 * The teacher's click answers at once; a worker sends the messages at the mail server's pace and retries the ones that fail.
 *
 * A row holds a template name and its data (not the HTML), so the message is drawn with the design of the day when it leaves.
 * dedupe_key makes "send this once" safe to call twice.
 */
final class EmailQueue
{
    public const MAX_ATTEMPTS = 3;
    private const BATCH = 20;

    /** Templates the queue knows how to send, and the Mailer method each one calls. */
    private const TEMPLATES = ['live_scheduled', 'result_cancelled'];

    /** @return int|null the row id, or null when this dedupe_key was already queued */
    public static function enqueue(PDO $pdo, ?int $userId, string $toEmail, string $template, array $args, ?string $dedupeKey = null): ?int
    {
        if (!in_array($template, self::TEMPLATES, true) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $st = $pdo->prepare(
            "INSERT IGNORE INTO email_outbox (user_id, to_email, template, args, dedupe_key, status, next_try_at)
             VALUES (:u, :t, :tpl, :a, :d, 'queued', NOW())"
        );
        $st->execute(['u' => $userId, 't' => $toEmail, 'tpl' => $template, 'a' => json_encode($args, JSON_UNESCAPED_UNICODE), 'd' => $dedupeKey]);
        return $st->rowCount() === 1 ? (int)$pdo->lastInsertId() : null;
    }

    /** Starts the background sender (at most one every 3 seconds). If it cannot start, cron picks the rows up. */
    public static function kick(): void
    {
        if (Mailer::$capture !== null || getenv('SV_NO_BACKGROUND_MAIL') !== false) {
            return;   // tests: nothing may leave the machine
        }
        $flag = dirname(__DIR__) . '/uploads/live_cache/email_kick.flag';
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
        $script = realpath(__DIR__ . '/email-worker.php');
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

    /** Sends the queued emails that are due. Returns how many were sent. */
    public static function work(PDO $pdo, int $maxRows = 500): int
    {
        $pdo->exec("UPDATE email_outbox SET status = 'queued' WHERE status = 'sending' AND created_at < NOW() - INTERVAL 10 MINUTE AND sent_at IS NULL");
        $sent = 0;
        $handled = 0;
        while ($handled < $maxRows) {
            $ids = $pdo->query(
                "SELECT id FROM email_outbox WHERE status = 'queued' AND (next_try_at IS NULL OR next_try_at <= NOW()) ORDER BY id ASC LIMIT " . self::BATCH
            )->fetchAll(PDO::FETCH_COLUMN);
            if (!$ids) {
                break;
            }
            foreach ($ids as $id) {
                $claim = $pdo->prepare("UPDATE email_outbox SET status = 'sending', attempts = attempts + 1 WHERE id = :id AND status = 'queued'");
                $claim->execute(['id' => $id]);
                if ($claim->rowCount() !== 1) {
                    continue;   // another worker took it
                }
                $handled++;
                $row = $pdo->query("SELECT to_email, template, args, attempts FROM email_outbox WHERE id = " . (int)$id)->fetch(PDO::FETCH_ASSOC);
                $ok = false;
                $err = null;
                try {
                    $ok = self::deliver((string)$row['template'], (string)$row['to_email'], (array)json_decode((string)$row['args'], true));
                    if (!$ok) {
                        $err = Mailer::getLastError() ?? 'envoi refusé';
                    }
                } catch (Throwable $e) {
                    $err = substr($e->getMessage(), 0, 200);
                }
                if ($ok) {
                    $sent++;
                    $pdo->prepare("UPDATE email_outbox SET status = 'sent', sent_at = NOW(), error = NULL WHERE id = :id")->execute(['id' => $id]);
                } elseif ((int)$row['attempts'] < self::MAX_ATTEMPTS) {
                    $wait = 60 * ((int)$row['attempts'] ** 2);
                    $pdo->prepare("UPDATE email_outbox SET status = 'queued', error = :e, next_try_at = NOW() + INTERVAL :w SECOND WHERE id = :id")
                        ->execute(['e' => substr((string)$err, 0, 250), 'w' => $wait, 'id' => $id]);
                } else {
                    $pdo->prepare("UPDATE email_outbox SET status = 'failed', error = :e WHERE id = :id")->execute(['e' => substr((string)$err, 0, 250), 'id' => $id]);
                }
            }
        }
        return $sent;
    }

    private static function deliver(string $template, string $to, array $a): bool
    {
        return match ($template) {
            'live_scheduled'   => Mailer::liveEvalScheduled($to, (string)$a['name'], (array)$a['session'], (string)$a['link'], (string)($a['lang'] ?? 'fr'), !empty($a['rescheduled'])),
            'result_cancelled' => Mailer::liveResultCancelled($to, (string)$a['name'], (string)$a['session_title'], (string)$a['course_title'], (string)($a['reason'] ?? ''), (string)($a['teacher'] ?? ''), (string)($a['lang'] ?? 'fr'), !empty($a['restored'])),
            default            => false,
        };
    }
}
