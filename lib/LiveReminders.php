<?php
declare(strict_types=1);

require_once __DIR__ . '/EmailQueue.php';

/**
 * The reminder email of a LIVE evaluation: 15 minutes before it starts, every student who can take part is told that it will soon
 * begin, with the link and the instructions. Asynchronous evaluations get no reminder (the students can take them at any time; they
 * were told when the evaluation was created).
 *
 * Who: the active students of the course whose email is confirmed, plus anybody already registered in the waiting room with an email
 * address (a guest without an account, for example). One message per person, whatever the number of ways they are known.
 *
 * When: dispatch() looks for activated live sessions that start within the next LEAD_MINUTES and have not been reminded for their
 * current start time. It is called
 *   - by the background sender (lib/email-worker.php), which a cron job should start every minute (see DEPLOYMENT.md);
 *   - by tick() on ordinary page loads (teacher and student dashboards, the waiting room), at most once every 20 seconds, so the
 *     reminder still leaves on time when nothing else is scheduled.
 * Sending twice is impossible: reminder_version on the session and the dedupe key of each message both guard it. Moving the exam to
 * another time changes the version, so a new reminder leaves 15 minutes before the new start.
 */
final class LiveReminders
{
    public const LEAD_MINUTES = 15;
    private const TICK_SECONDS = 20;

    /** Identifies one start time of a session. */
    public static function version(string $startTime): string
    {
        return substr(md5($startTime), 0, 10);
    }

    /**
     * Cheap and safe to call on any page: does the work at most every 20 seconds, never throws.
     */
    public static function tick(PDO $pdo): void
    {
        try {
            $flag = dirname(__DIR__) . '/uploads/live_cache/reminder_tick.flag';
            if (!is_dir(dirname($flag))) {
                @mkdir(dirname($flag), 0755, true);
            }
            if ((time() - (int)@filemtime($flag)) < self::TICK_SECONDS) {
                return;
            }
            @touch($flag);
            $r = self::dispatch($pdo);
            if ($r['queued'] > 0) {
                EmailQueue::kick();
            }
        } catch (Throwable $e) {
            error_log('LiveReminders::tick ' . $e->getMessage());
        }
    }

    /**
     * Queues the reminders that are due now.
     *
     * @return array{sessions:int,queued:int}
     */
    public static function dispatch(PDO $pdo, ?int $now = null): array
    {
        $now ??= time();
        $st = $pdo->prepare(
            "SELECT id, start_time, reminder_version FROM live_eval_sessions
             WHERE status = 1 AND is_async = 0 AND start_time > :now AND start_time <= :horizon"
        );
        $st->execute(['now' => date('Y-m-d H:i:s', $now), 'horizon' => date('Y-m-d H:i:s', $now + self::LEAD_MINUTES * 60)]);
        $out = ['sessions' => 0, 'queued' => 0];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) {
            if ((string)$s['reminder_version'] === self::version((string)$s['start_time'])) {
                continue;   // already reminded for this start time
            }
            $r = self::queueFor($pdo, (int)$s['id'], false, $now);
            $out['sessions']++;
            $out['queued'] += $r['queued'];
        }
        return $out;
    }

    /**
     * Queues the reminder of one session for everybody concerned and records that it was done for its current start time.
     * Called by dispatch() and by LiveMailNotifier when an exam is activated inside the 15 minutes before its start.
     *
     * @return array{queued:int,eligible:int}
     */
    public static function queueFor(PDO $pdo, int $sessionId, bool $fromActivation = false, ?int $now = null): array
    {
        require_once __DIR__ . '/LiveMailNotifier.php';
        $out = ['queued' => 0, 'eligible' => 0];
        $d = LiveMailNotifier::describe($pdo, $sessionId);
        if ($d === null || (int)$d['row']['status'] !== 1 || (int)$d['row']['is_async'] === 1) {
            return $out;
        }
        $now ??= time();
        $ts = strtotime((string)$d['row']['start_time']);
        if ($ts === false || $ts <= $now) {
            return $out;   // already started: a reminder would be late
        }
        $version = self::version((string)$d['row']['start_time']);
        $left = max(1, (int)ceil(($ts - $now) / 60));
        $announced = substr(md5($d['row']['start_time'] . '|0|'), 0, 10);   // the version key of the announcement of this same start time

        foreach (self::recipients($pdo, $sessionId, (int)$d['row']['course_id']) as $r) {
            $out['eligible']++;
            $key = 'liveremind:' . $sessionId . ':' . substr(md5(strtolower($r['email'])), 0, 10) . ':' . $version;
            // Told about ANOTHER start time of this session before (an announcement or a reminder)? Then this one says the time moved.
            $prior = $pdo->prepare("SELECT dedupe_key FROM email_outbox WHERE to_email = :e AND (dedupe_key LIKE :a OR dedupe_key LIKE :b)");
            $prior->execute(['e' => $r['email'], 'a' => "liveremind:$sessionId:%", 'b' => "live:$sessionId:%"]);
            $moved = false;
            foreach ($prior->fetchAll(PDO::FETCH_COLUMN) as $k) {
                $suffix = substr((string)$k, (int)strrpos((string)$k, ':') + 1);
                if ($suffix !== $version && $suffix !== $announced) {
                    $moved = true;
                }
            }
            $args = [
                'name' => $r['name'], 'session' => $d['session'], 'link' => $d['link'], 'lang' => $r['lang'],
                'minutes_left' => $left, 'rescheduled' => $moved,
            ];
            if (EmailQueue::enqueue($pdo, $r['user_id'], $r['email'], 'live_reminder', $args, $key) !== null) {
                $out['queued']++;
            }
        }
        $pdo->prepare("UPDATE live_eval_sessions SET reminder_version = :v WHERE id = :id")->execute(['v' => $version, 'id' => $sessionId]);
        if ($out['queued'] > 0) {
            EmailQueue::kick();
        }
        return $out;
    }

    /**
     * Everybody who should get the reminder, one entry per email address.
     *
     * @return array<int,array{user_id:?int,name:string,email:string,lang:string}>
     */
    public static function recipients(PDO $pdo, int $sessionId, int $courseId): array
    {
        $list = [];
        $enrolled = $pdo->prepare(
            "SELECT u.id, u.name, u.email, u.lang FROM enrollments e JOIN users u ON u.id = e.student_id
             WHERE e.course_id = :c AND u.role = 'student' AND u.is_active = 1 AND u.email_verified_at IS NOT NULL"
        );
        $enrolled->execute(['c' => $courseId]);
        foreach ($enrolled->fetchAll(PDO::FETCH_ASSOC) as $u) {
            if (filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
                $list[strtolower($u['email'])] = ['user_id' => (int)$u['id'], 'name' => (string)$u['name'], 'email' => (string)$u['email'], 'lang' => ($u['lang'] ?? 'fr') === 'en' ? 'en' : 'fr'];
            }
        }
        // People already in the waiting room (including guests who have no account)
        $reg = $pdo->prepare(
            "SELECT r.name, r.email, r.student_id, u.lang, u.is_active
             FROM live_eval_registrations r LEFT JOIN users u ON u.id = r.student_id
             WHERE r.session_id = :s AND r.cancelled_at IS NULL AND r.email <> ''"
        );
        $reg->execute(['s' => $sessionId]);
        foreach ($reg->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $k = strtolower((string)$r['email']);
            if (isset($list[$k]) || !filter_var($r['email'], FILTER_VALIDATE_EMAIL) || ($r['student_id'] !== null && (int)($r['is_active'] ?? 1) === 0)) {
                continue;
            }
            $list[$k] = ['user_id' => $r['student_id'] !== null ? (int)$r['student_id'] : null, 'name' => (string)$r['name'], 'email' => (string)$r['email'], 'lang' => ($r['lang'] ?? 'fr') === 'en' ? 'en' : 'fr'];
        }
        return array_values($list);
    }
}
