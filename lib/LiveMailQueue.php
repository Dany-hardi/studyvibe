<?php
declare(strict_types=1);

/**
 * Results emails of live evaluations.
 *
 * Sending a mail takes 1 to 3 seconds (a TLS session with the SMTP server). When a 300-student exam ends, every student's
 * request used to send its own mail before answering, so hundreds of web workers sat waiting on the mail server at the
 * exact moment everybody needed the page. Now the request only writes one row (a few milliseconds) and a background
 * process sends the mails at its own pace.
 */
final class LiveMailQueue
{
    /** Parallel sending processes. Raise only if the SMTP provider allows it. */
    private const WORKERS = 4;
    private const MAX_ATTEMPTS = 3;

    public static function enqueue(PDO $pdo, int $registrationId, array $payload): void
    {
        // One mail per participant: a second call (page refresh, race) does nothing
        $pdo->prepare("INSERT IGNORE INTO live_eval_mail_queue (registration_id, payload) VALUES (:r, :p)")
            ->execute(['r' => $registrationId, 'p' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
        self::kick();
    }

    /** Starts the background sender, at most once every 3 seconds however many students finish at once. */
    public static function kick(): void
    {
        $flag = __DIR__ . '/../uploads/live_cache/mail_kick.flag';
        $fh = @fopen($flag, 'c');
        if (!$fh) {
            return;
        }
        $go = false;
        if (flock($fh, LOCK_EX | LOCK_NB)) {
            $go = (time() - (int)@filemtime($flag)) >= 3 || filesize($flag) === 0;
            if ($go) {
                ftruncate($fh, 0); fwrite($fh, (string)time()); fflush($fh);
                @touch($flag);
            }
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        if (!$go) {
            return;
        }
        $php    = self::phpBinary();
        $script = realpath(__DIR__ . '/live-mail-worker.php');
        if (!$php || !$script || !self::canSpawn()) {
            return;   // rows stay queued; the next kick, or `php lib/live-mail-worker.php` from cron, sends them
        }
        for ($i = 1; $i <= self::WORKERS; $i++) {
            $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . $i;
            if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
                @pclose(@popen('start /B "" ' . $cmd, 'r'));
            } else {
                @exec($cmd . ' > /dev/null 2>&1 &');
            }
        }
    }

    private static function canSpawn(): bool
    {
        $off = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        return function_exists('exec') && !in_array('exec', $off, true);
    }

    private static function phpBinary(): ?string
    {
        if (defined('PHP_CLI_BIN') && is_file((string)PHP_CLI_BIN)) {
            return (string)PHP_CLI_BIN;
        }
        foreach ([PHP_BINDIR . '/php', '/usr/local/bin/php', '/usr/bin/php', '/opt/lampp/bin/php'] as $c) {
            if (@is_executable($c)) {
                return $c;
            }
        }
        return null;
    }

    /** Worker loop. Several can run side by side: each takes one row at a time with an atomic claim. */
    public static function work(PDO $pdo, int $slot): void
    {
        require_once __DIR__ . '/../Mailer.php';
        $token = bin2hex(random_bytes(8));
        $idle = 0;
        $deadline = time() + 1800;   // a worker never lives longer than 30 minutes

        // Rows left 'sending' by a crashed worker go back to the queue
        $pdo->exec("UPDATE live_eval_mail_queue SET status='pending', claim=NULL WHERE status='sending' AND created_at < (NOW() - INTERVAL 15 MINUTE)");

        while ($idle < 6 && time() < $deadline) {
            $claimed = $pdo->prepare("
                UPDATE live_eval_mail_queue SET status='sending', claim=:c, attempts=attempts+1
                WHERE status='pending' AND (next_try_at IS NULL OR next_try_at <= NOW())
                ORDER BY id LIMIT 1
            ");
            $claimed->execute(['c' => $token]);
            if ($claimed->rowCount() === 0) {
                // Nothing ready. If failed mails are waiting for their retry time, stay alive for them.
                $waiting = (int)$pdo->query("SELECT COUNT(*) FROM live_eval_mail_queue WHERE status='pending'")->fetchColumn();
                if ($waiting > 0) {
                    sleep(3);
                    continue;
                }
                $idle++;
                usleep(500000);
                continue;
            }
            $idle = 0;
            $row = $pdo->prepare("SELECT * FROM live_eval_mail_queue WHERE claim = :c AND status='sending' LIMIT 1");
            $row->execute(['c' => $token]);
            $job = $row->fetch(PDO::FETCH_ASSOC);
            if (!$job) {
                continue;
            }
            $p = json_decode((string)$job['payload'], true) ?: [];
            $ok = false;
            try {
                $ok = Mailer::sendLiveEvalResults(
                    (string)$p['email'], (string)$p['name'], (string)$p['session_title'],
                    (int)$p['correct'], (int)$p['total'], (array)$p['qas'], (int)$job['registration_id']
                );
            } catch (Throwable $e) {
                $p['err'] = $e->getMessage();
            }
            if ($ok) {
                $pdo->prepare("UPDATE live_eval_mail_queue SET status='sent', sent_at=NOW(), claim=NULL WHERE id=:id")->execute(['id' => $job['id']]);
            } else {
                $err = substr((string)(Mailer::getLastError() ?? ($p['err'] ?? 'unknown')), 0, 250);
                $final = (int)$job['attempts'] >= self::MAX_ATTEMPTS;
                $pdo->prepare("UPDATE live_eval_mail_queue SET status=:s, claim=NULL, last_error=:e, next_try_at = NOW() + INTERVAL 90 SECOND WHERE id=:id")
                    ->execute(['s' => $final ? 'failed' : 'pending', 'e' => $err, 'id' => $job['id']]);
            }
            usleep(100000);   // gentle on the SMTP provider
        }
    }
}
