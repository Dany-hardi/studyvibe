<?php
declare(strict_types=1);

/**
 * Archives: snapshots kept in the database (so they survive the host's disk being wiped) for review, treatment of complaints and maintenance.
 *
 *  - session   : a whole live evaluation (settings, questions with references, registrations, answers, drafts, code marking, timeline, mail status).
 *                Taken when the session is over, before a reset, a deletion or an exclusion, and on request. Identical content is stored once.
 *  - logs      : the audit log and the server errors older than the retention (moved here by month, then removed from the live tables).
 *  - registration : one participant before they leave a session.
 *  - user / course : the account (without secrets) and the course (lessons, enrolments) as they were; taken before a deletion and, in rotation, by the sweep.
 *  - stats     : one figure sheet per day for the whole platform (users, courses, evaluations, participants, per teacher). Figures only: they stay valid proof of activity.
 *  - attestation : a certificate of activity issued to a teacher (evaluations held, people assessed), publicly verifiable with its code.
 * A payload is gzip-compressed JSON; it is checked against its SHA-256 when read.
 *
 * Governance: an archive is never deleted or rewritten by accident. Every creation, erasure and purge is written to `archive_ledger`, an append-only chain of
 * SHA-256 (verifyLedger() proves nothing was removed or altered), and the database triggers refuse a DELETE or a rewrite unless unlocked().
 * Personal data can still be erased on request (redactPerson(): identities are replaced, figures and marks stay); an archive itself is only purged
 * by the owner, with a written reason, and the purge stays in the ledger.
 */
final class Archive
{
    public const LOG_RETENTION_DAYS = 90;
    public const ERROR_RETENTION_DAYS = 30;

    private static function encode(array $data): string
    {
        return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    private static function rows(PDO $pdo, string $sql, array $params): array
    {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Runs $fn with the protection triggers lifted (erasure and purge procedures only), always locked again afterwards. */
    public static function unlocked(PDO $pdo, callable $fn): mixed
    {
        $pdo->exec("SET @archive_unlock = 1");
        try {
            return $fn();
        } finally {
            $pdo->exec("SET @archive_unlock = 0");
        }
    }

    public static function subjectHash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    /** Appends an entry to the chained ledger. @return string the new chain value */
    public static function ledger(PDO $pdo, int $archiveId, string $event, string $sha, string $note = '', string $actor = 'system'): string
    {
        $locked = (bool)$pdo->query("SELECT GET_LOCK('archive_ledger', 10)")->fetchColumn();
        try {
            $prev = (string)($pdo->query("SELECT `chain` FROM archive_ledger ORDER BY id DESC LIMIT 1")->fetchColumn() ?: str_repeat('0', 64));
            $at = gmdate('Y-m-d H:i:s');
            $note = mb_substr($note, 0, 255);
            $actor = mb_substr($actor, 0, 80);
            $chain = hash('sha256', implode('|', [$prev, $archiveId, $event, $sha, $note, $actor, $at]));
            $pdo->prepare("INSERT INTO archive_ledger (archive_id, event, sha256, note, actor, `at`, prev_chain, `chain`) VALUES (:a, :e, :s, :n, :ac, :at, :p, :c)")
                ->execute(['a' => $archiveId, 'e' => $event, 's' => $sha, 'n' => $note, 'ac' => $actor, 'at' => $at, 'p' => $prev, 'c' => $chain]);
            return $chain;
        } finally {
            if ($locked) { $pdo->query("SELECT RELEASE_LOCK('archive_ledger')"); }
        }
    }

    /**
     * The one place an archive row is written: the row, its ledger entry and the (hashed) people it concerns.
     * @param array{kind:string,ref_id:?int,session_id:?int,teacher_id:?int,title:string,reason:string,items:int,code?:?string} $f
     * @param string[] $emails people the archive concerns, indexed hashed so an erasure request can find it
     */
    private static function store(PDO $pdo, array $f, string $json, array $emails = [], ?array $meta = null): int
    {
        $gz = (string)gzencode($json, 6);
        $hash = hash('sha256', $json);
        $st = $pdo->prepare("INSERT INTO archives (kind, ref_id, session_id, teacher_id, title, reason, items, bytes, sha256, payload, code, meta)
                             VALUES (:k, :r, :s, :t, :title, :reason, :items, :bytes, :h, :p, :code, :meta)");
        $st->bindValue('k', $f['kind']);
        $st->bindValue('r', $f['ref_id'], $f['ref_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue('s', $f['session_id'], $f['session_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue('t', $f['teacher_id'], $f['teacher_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue('title', mb_substr($f['title'], 0, 200));
        $st->bindValue('reason', mb_substr($f['reason'], 0, 32));
        $st->bindValue('items', $f['items'], PDO::PARAM_INT);
        $st->bindValue('bytes', strlen($gz), PDO::PARAM_INT);
        $st->bindValue('h', $hash);
        $st->bindValue('p', $gz, PDO::PARAM_LOB);
        $st->bindValue('code', $f['code'] ?? null);
        $st->bindValue('meta', $meta === null ? null : self::encode($meta));
        $st->execute();
        $id = (int)$pdo->lastInsertId();
        try {
            self::ledger($pdo, $id, 'created', $hash, $f['kind'] . ' · ' . $f['reason']);
            $ins = $pdo->prepare("INSERT IGNORE INTO archive_subjects (archive_id, subject_hash) VALUES (:a, :h)");
            foreach (array_unique(array_filter(array_map(fn($e) => self::subjectHash((string)$e), array_filter($emails)))) as $sh) {
                $ins->execute(['a' => $id, 'h' => $sh]);
            }
        } catch (Throwable $e) {
            error_log('archive ledger: ' . $e->getMessage());   // the archive is kept; backfillLedger() repairs the missing entry
        }
        return $id;
    }

    /** Everything about one session, as plain data. @return array<string,mixed>|null null when the session does not exist */
    public static function collectSession(PDO $pdo, int $sessionId): ?array
    {
        $session = self::rows($pdo, "SELECT * FROM live_eval_sessions WHERE id = :s", ['s' => $sessionId])[0] ?? null;
        if (!$session) {
            return null;
        }
        $p = ['s' => $sessionId];
        return [
            'session' => $session,
            'course' => self::rows($pdo, "SELECT id, title FROM courses WHERE id = :c", ['c' => (int)$session['course_id']])[0] ?? null,
            'questions' => self::rows($pdo, "SELECT * FROM live_eval_questions WHERE session_id = :s ORDER BY sort_order, id", $p),
            'registrations' => self::rows($pdo, "SELECT * FROM live_eval_registrations WHERE session_id = :s ORDER BY id", $p),
            'answers' => self::rows($pdo, "SELECT a.* FROM live_eval_answers a JOIN live_eval_registrations r ON r.id = a.registration_id WHERE r.session_id = :s ORDER BY a.id", $p),
            'drafts' => self::rows($pdo, "SELECT d.* FROM live_eval_drafts d JOIN live_eval_registrations r ON r.id = d.registration_id WHERE r.session_id = :s ORDER BY d.id", $p),
            'code_reviews' => self::rows($pdo, "SELECT c.* FROM live_eval_code_reviews c JOIN live_eval_registrations r ON r.id = c.registration_id WHERE r.session_id = :s ORDER BY c.id", $p),
            'events' => self::rows($pdo, "SELECT * FROM live_eval_events WHERE session_id = :s ORDER BY id", $p),
            'mails' => self::rows($pdo, "SELECT m.id, m.registration_id, m.status, m.attempts, m.last_error, m.created_at, m.sent_at FROM live_eval_mail_queue m JOIN live_eval_registrations r ON r.id = m.registration_id WHERE r.session_id = :s", $p),
        ];
    }

    /**
     * Stores a snapshot of a session. The same content is never stored twice in a row.
     * @return int|null the archive id (the existing one when nothing changed), null when the session does not exist
     */
    public static function snapshotSession(PDO $pdo, int $sessionId, string $reason, ?int $teacherId = null): ?int
    {
        $data = self::collectSession($pdo, $sessionId);
        if ($data === null) {
            return null;
        }
        $json = self::encode($data);
        $hash = hash('sha256', $json);
        $last = self::rows($pdo, "SELECT id, sha256 FROM archives WHERE kind = 'session' AND ref_id = :s ORDER BY id DESC LIMIT 1", ['s' => $sessionId])[0] ?? null;
        if ($last && $last['sha256'] === $hash) {
            return (int)$last['id'];
        }
        $regs = $data['registrations'];
        $meta = [
            'participants' => count($regs),
            'finished' => count(array_filter($regs, fn($r) => $r['score'] !== null)),
            'questions' => count($data['questions']),
            'answers' => count($data['answers']),
            'start' => $data['session']['start_time'] ?? null,
            'end' => $data['session']['end_time'] ?? null,
        ];
        return self::store($pdo, [
            'kind' => 'session', 'ref_id' => $sessionId, 'session_id' => $sessionId, 'teacher_id' => $teacherId ?? (int)$data['session']['teacher_id'],
            'title' => (string)$data['session']['title'], 'reason' => $reason, 'items' => count($regs),
        ], $json, array_column($regs, 'email'), $meta);
    }

    /** One participant before they are removed from a session. */
    public static function snapshotRegistration(PDO $pdo, int $registrationId, string $reason): ?int
    {
        $reg = self::rows($pdo, "SELECT * FROM live_eval_registrations WHERE id = :r", ['r' => $registrationId])[0] ?? null;
        if (!$reg) {
            return null;
        }
        $sess = self::rows($pdo, "SELECT id, title, teacher_id FROM live_eval_sessions WHERE id = :s", ['s' => (int)$reg['session_id']])[0] ?? null;
        $p = ['r' => $registrationId];
        $data = [
            'registration' => $reg,
            'session' => $sess,
            'answers' => self::rows($pdo, "SELECT * FROM live_eval_answers WHERE registration_id = :r", $p),
            'drafts' => self::rows($pdo, "SELECT * FROM live_eval_drafts WHERE registration_id = :r", $p),
            'code_reviews' => self::rows($pdo, "SELECT * FROM live_eval_code_reviews WHERE registration_id = :r", $p),
            'events' => self::rows($pdo, "SELECT * FROM live_eval_events WHERE registration_id = :r ORDER BY id", $p),
        ];
        return self::store($pdo, [
            'kind' => 'registration', 'ref_id' => $registrationId, 'session_id' => (int)($sess['id'] ?? 0), 'teacher_id' => (int)($sess['teacher_id'] ?? 0),
            'title' => (string)($reg['name'] ?? '') . ' · ' . (string)($sess['title'] ?? ''), 'reason' => $reason, 'items' => 1,
        ], self::encode($data), [(string)($reg['email'] ?? '')]);
    }

    /** The list of archives of a teacher, without the payloads. */
    public static function listFor(PDO $pdo, int $teacherId, ?int $sessionId = null): array
    {
        $sql = "SELECT id, kind, ref_id, session_id, title, reason, items, bytes, created_at FROM archives WHERE teacher_id = :t AND kind IN ('session', 'registration')"
             . ($sessionId ? ' AND session_id = :s' : '') . " ORDER BY id DESC LIMIT 300";
        return self::rows($pdo, $sql, $sessionId ? ['t' => $teacherId, 's' => $sessionId] : ['t' => $teacherId]);
    }

    /** @return array{meta:array,data:array}|null null when it is not there or when the stored content no longer matches its SHA-256 */
    public static function open(PDO $pdo, int $id, ?int $teacherId = null): ?array
    {
        $sql = "SELECT id, kind, ref_id, session_id, teacher_id, title, reason, items, bytes, sha256, created_at, payload FROM archives WHERE id = :i" . ($teacherId !== null ? ' AND teacher_id = :t' : '');
        $row = self::rows($pdo, $sql, $teacherId !== null ? ['i' => $id, 't' => $teacherId] : ['i' => $id])[0] ?? null;
        if (!$row) {
            return null;
        }
        $json = @gzdecode((string)$row['payload']);
        if ($json === false || hash('sha256', $json) !== $row['sha256']) {
            return null;
        }
        unset($row['payload']);
        $data = json_decode($json, true);
        return is_array($data) ? ['meta' => $row, 'data' => $data] : null;
    }

    /** Audit log and server errors older than their retention go to the archives (by month) and leave the live tables. @return int rows moved */
    public static function rotateLogs(PDO $pdo, int $auditDays = self::LOG_RETENTION_DAYS, int $errorDays = self::ERROR_RETENTION_DAYS): int
    {
        $moved = 0;
        foreach ([['audit_logs', 'created_at', $auditDays], ['server_errors', 'last_at', $errorDays]] as [$table, $col, $days]) {
            $months = self::rows($pdo, "SELECT DATE_FORMAT($col, '%Y-%m') AS m, COUNT(*) AS n, MAX(id) AS max_id FROM $table WHERE $col < NOW() - INTERVAL :d DAY GROUP BY m ORDER BY m", ['d' => $days]);
            foreach ($months as $m) {
                $rows = self::rows($pdo, "SELECT * FROM $table WHERE $col < NOW() - INTERVAL :d DAY AND DATE_FORMAT($col, '%Y-%m') = :m ORDER BY id LIMIT 20000", ['d' => $days, 'm' => $m['m']]);
                if (!$rows) {
                    continue;
                }
                $json = self::encode(['table' => $table, 'month' => $m['m'], 'rows' => $rows]);
                self::store($pdo, ['kind' => 'logs', 'ref_id' => null, 'session_id' => null, 'teacher_id' => null, 'title' => $table . ' ' . $m['m'], 'reason' => 'rotation', 'items' => count($rows)], $json);
                // removed only after the archive is written, and only the rows that were archived
                $ids = array_map('intval', array_column($rows, 'id'));
                foreach (array_chunk($ids, 1000) as $chunk) {
                    $pdo->exec("DELETE FROM $table WHERE id IN (" . implode(',', $chunk) . ")");
                }
                $moved += count($rows);
            }
        }
        return $moved;
    }

    /** Sessions that are over get their snapshot; once a day the logs are rotated. Meant for the cron worker. @return array<string,mixed> */
    public static function sweep(PDO $pdo): array
    {
        $done = ['sessions' => 0, 'logs' => 0];
        $flag = dirname(__DIR__) . '/uploads/live_cache/archive_sweep.flag';
        if (!is_dir(dirname($flag))) {
            @mkdir(dirname($flag), 0755, true);
        }
        if ((time() - (int)@filemtime($flag)) < 3600) {
            return $done;
        }
        @touch($flag);
        $ids = $pdo->query("SELECT s.id FROM live_eval_sessions s
                            WHERE COALESCE(s.async_deadline, s.end_time) < NOW() - INTERVAL 30 MINUTE
                              AND EXISTS (SELECT 1 FROM live_eval_registrations r WHERE r.session_id = s.id)
                              AND (NOT EXISTS (SELECT 1 FROM archives a WHERE a.kind = 'session' AND a.ref_id = s.id)
                                   OR s.created_at > NOW() - INTERVAL 90 DAY)
                            ORDER BY s.id DESC LIMIT 25")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            try {
                $before = (int)$pdo->query("SELECT COUNT(*) FROM archives WHERE kind = 'session' AND ref_id = " . (int)$id)->fetchColumn();
                self::snapshotSession($pdo, (int)$id, 'final');
                $after = (int)$pdo->query("SELECT COUNT(*) FROM archives WHERE kind = 'session' AND ref_id = " . (int)$id)->fetchColumn();
                $done['sessions'] += $after > $before ? 1 : 0;
            } catch (Throwable $e) {
                error_log('archive session ' . $id . ': ' . $e->getMessage());
            }
        }
        try { $done['governance'] = self::sweepGovernance($pdo); } catch (Throwable $e) { error_log('archive governance: ' . $e->getMessage()); }
        $day = dirname(__DIR__) . '/uploads/live_cache/archive_logs.flag';
        if ((time() - (int)@filemtime($day)) >= 20 * 3600) {
            @touch($day);
            try { $done['logs'] = self::rotateLogs($pdo); } catch (Throwable $e) { error_log('archive logs: ' . $e->getMessage()); }
        }
        return $done;
    }

    // ─────────────────────────────── governance: users, courses, platform figures ───────────────────────────────

    private static function safeRows(PDO $pdo, string $sql, array $params = []): array
    {
        try { return self::rows($pdo, $sql, $params); } catch (PDOException $e) { return []; }   // a table absent on a very old schema
    }

    /** Columns that never go into an archive: credentials, secrets, tokens. */
    private static function stripSecrets(array $row): array
    {
        foreach (array_keys($row) as $k) {
            if (preg_match('/pass|secret|token|hash|totp|otp|recovery|backup_code|api_key|push_|auth_key/i', (string)$k)) {
                unset($row[$k]);
            }
        }
        return $row;
    }

    /** An account as it was (profile, no secrets) with the figures of its activity. @return int|null null when the user does not exist */
    public static function snapshotUser(PDO $pdo, int $userId, string $reason): ?int
    {
        $u = self::safeRows($pdo, "SELECT * FROM users WHERE id = :i", ['i' => $userId])[0] ?? null;
        if (!$u) {
            return null;
        }
        $p = ['i' => $userId];
        $one = fn(string $sql) => (int)(self::safeRows($pdo, $sql, $p)[0]['n'] ?? 0);
        $data = [
            'profile' => self::stripSecrets($u),
            'activity' => [
                'enrollments' => $one("SELECT COUNT(*) n FROM enrollments WHERE student_id = :i"),
                'certificates' => $one("SELECT COUNT(*) n FROM certificates WHERE student_id = :i"),
                'live_participations' => $one("SELECT COUNT(*) n FROM live_eval_registrations WHERE student_id = :i"),
                'courses_taught' => $one("SELECT COUNT(*) n FROM courses WHERE teacher_id = :i"),
                'live_sessions_held' => $one("SELECT COUNT(*) n FROM live_eval_sessions WHERE teacher_id = :i"),
            ],
            'live_results' => self::safeRows($pdo, "SELECT r.session_id, s.title, r.score, r.correct_count, r.question_count, r.submitted_at FROM live_eval_registrations r JOIN live_eval_sessions s ON s.id = r.session_id WHERE r.student_id = :i ORDER BY r.id", $p),
        ];
        $json = self::encode($data);
        $last = self::safeRows($pdo, "SELECT id, sha256 FROM archives WHERE kind = 'user' AND ref_id = :i ORDER BY id DESC LIMIT 1", $p)[0] ?? null;
        if ($last && $last['sha256'] === hash('sha256', $json)) {
            return (int)$last['id'];
        }
        return self::store($pdo, [
            'kind' => 'user', 'ref_id' => $userId, 'session_id' => null, 'teacher_id' => null,
            'title' => (string)($u['role'] ?? '') . ' #' . $userId, 'reason' => $reason, 'items' => 1,
        ], $json, [(string)($u['email'] ?? '')], ['role' => $u['role'] ?? null, 'created_at' => $u['created_at'] ?? null]);
    }

    /** A course as it was: description, lessons, enrolments, certificates. */
    public static function snapshotCourse(PDO $pdo, int $courseId, string $reason): ?int
    {
        $c = self::safeRows($pdo, "SELECT * FROM courses WHERE id = :i", ['i' => $courseId])[0] ?? null;
        if (!$c) {
            return null;
        }
        $p = ['i' => $courseId];
        $enrol = self::safeRows($pdo, "SELECT e.*, u.email AS _email FROM enrollments e LEFT JOIN users u ON u.id = e.student_id WHERE e.course_id = :i ORDER BY e.student_id", $p);
        $emails = array_column($enrol, '_email');
        foreach ($enrol as &$e) { unset($e['_email']); }
        unset($e);
        $data = [
            'course' => $c,
            'lessons' => self::safeRows($pdo, "SELECT l.id, l.chapter_id, l.title, l.content_type FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id WHERE ch.course_id = :i ORDER BY ch.id, l.id", $p),
            'enrollments' => $enrol,
            'certificates' => self::safeRows($pdo, "SELECT * FROM certificates WHERE course_id = :i ORDER BY id", $p),
            'live_sessions' => self::safeRows($pdo, "SELECT id, title, start_time, end_time FROM live_eval_sessions WHERE course_id = :i ORDER BY id", $p),
        ];
        $json = self::encode($data);
        $last = self::safeRows($pdo, "SELECT id, sha256 FROM archives WHERE kind = 'course' AND ref_id = :i ORDER BY id DESC LIMIT 1", $p)[0] ?? null;
        if ($last && $last['sha256'] === hash('sha256', $json)) {
            return (int)$last['id'];
        }
        return self::store($pdo, [
            'kind' => 'course', 'ref_id' => $courseId, 'session_id' => null, 'teacher_id' => (int)($c['teacher_id'] ?? 0) ?: null,
            'title' => (string)($c['title'] ?? 'course'), 'reason' => $reason, 'items' => count($enrol),
        ], $json, array_filter($emails), ['enrolled' => count($enrol), 'lessons' => count($data['lessons'])]);
    }

    /** The day's figures for the whole platform. One sheet per day: figures only, no identity beyond the teacher's name. */
    public static function snapshotStats(PDO $pdo, ?string $day = null): ?int
    {
        $day = $day ?? gmdate('Y-m-d');
        $n = fn(string $sql) => (int)(self::safeRows($pdo, $sql)[0]['n'] ?? 0);
        $roles = [];
        foreach (self::safeRows($pdo, "SELECT role, COUNT(*) total, SUM(is_active = 1) active FROM users GROUP BY role") as $r) {
            $roles[(string)$r['role']] = ['total' => (int)$r['total'], 'active' => (int)$r['active']];
        }
        $teachers = self::safeRows($pdo, "SELECT u.id, u.name,
                (SELECT COUNT(*) FROM courses c WHERE c.teacher_id = u.id) AS courses,
                (SELECT COUNT(*) FROM live_eval_sessions s WHERE s.teacher_id = u.id) AS sessions,
                (SELECT COUNT(*) FROM live_eval_registrations r JOIN live_eval_sessions s ON s.id = r.session_id WHERE s.teacher_id = u.id) AS participants,
                (SELECT COUNT(*) FROM live_eval_registrations r JOIN live_eval_sessions s ON s.id = r.session_id WHERE s.teacher_id = u.id AND r.score IS NOT NULL) AS finished,
                (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE c.teacher_id = u.id) AS students
            FROM users u WHERE u.role = 'teacher' ORDER BY u.id");
        $data = [
            'day' => $day,
            'users' => $roles,
            'courses' => ['total' => $n("SELECT COUNT(*) n FROM courses"), 'published' => $n("SELECT COUNT(*) n FROM courses WHERE is_published = 1")],
            'enrollments' => $n("SELECT COUNT(*) n FROM enrollments"),
            'certificates' => $n("SELECT COUNT(*) n FROM certificates"),
            'live' => [
                'sessions' => $n("SELECT COUNT(*) n FROM live_eval_sessions"),
                'participations' => $n("SELECT COUNT(*) n FROM live_eval_registrations"),
                'finished' => $n("SELECT COUNT(*) n FROM live_eval_registrations WHERE score IS NOT NULL"),
                'distinct_people' => $n("SELECT COUNT(DISTINCT email) n FROM live_eval_registrations"),
                'answers' => $n("SELECT COUNT(*) n FROM live_eval_answers"),
            ],
            'archived' => [
                'sessions' => $n("SELECT COUNT(DISTINCT ref_id) n FROM archives WHERE kind = 'session'"),
                'participations' => $n("SELECT COALESCE(SUM(JSON_EXTRACT(a.meta, '$.participants')), 0) n FROM archives a WHERE a.kind = 'session' AND a.id = (SELECT MAX(b.id) FROM archives b WHERE b.kind = 'session' AND b.ref_id = a.ref_id)"),
            ],
            'teachers' => $teachers,
        ];
        $json = self::encode($data);
        $ref = (int)str_replace('-', '', $day);
        $last = self::safeRows($pdo, "SELECT id, sha256 FROM archives WHERE kind = 'stats' AND ref_id = :r ORDER BY id DESC LIMIT 1", ['r' => $ref])[0] ?? null;
        if ($last && $last['sha256'] === hash('sha256', $json)) {
            return (int)$last['id'];
        }
        return self::store($pdo, ['kind' => 'stats', 'ref_id' => $ref, 'session_id' => null, 'teacher_id' => null, 'title' => 'Platform figures ' . $day, 'reason' => 'daily', 'items' => count($teachers)], $json);
    }

    /** Hourly: figure sheet of the day, a batch of users and courses not yet archived (or archived longest ago), ledger entries missing on old archives. */
    public static function sweepGovernance(PDO $pdo): array
    {
        $out = ['stats' => 0, 'users' => 0, 'courses' => 0, 'ledger' => 0];
        $before = (int)$pdo->query("SELECT COUNT(*) FROM archives WHERE kind = 'stats'")->fetchColumn();
        self::snapshotStats($pdo);
        $out['stats'] = (int)$pdo->query("SELECT COUNT(*) FROM archives WHERE kind = 'stats'")->fetchColumn() - $before;
        $users = self::safeRows($pdo, "SELECT u.id FROM users u LEFT JOIN (SELECT ref_id, MAX(id) AS last_id FROM archives WHERE kind = 'user' GROUP BY ref_id) a ON a.ref_id = u.id
                                       ORDER BY a.last_id IS NOT NULL, a.last_id, u.id LIMIT 100");
        foreach ($users as $u) {
            $c = (int)$pdo->query("SELECT COUNT(*) FROM archives WHERE kind = 'user'")->fetchColumn();
            self::snapshotUser($pdo, (int)$u['id'], 'periodic');
            $out['users'] += (int)$pdo->query("SELECT COUNT(*) FROM archives WHERE kind = 'user'")->fetchColumn() > $c ? 1 : 0;
        }
        $courses = self::safeRows($pdo, "SELECT c.id FROM courses c LEFT JOIN (SELECT ref_id, MAX(id) AS last_id FROM archives WHERE kind = 'course' GROUP BY ref_id) a ON a.ref_id = c.id
                                         ORDER BY a.last_id IS NOT NULL, a.last_id, c.id LIMIT 50");
        foreach ($courses as $c0) {
            $c = (int)$pdo->query("SELECT COUNT(*) FROM archives WHERE kind = 'course'")->fetchColumn();
            self::snapshotCourse($pdo, (int)$c0['id'], 'periodic');
            $out['courses'] += (int)$pdo->query("SELECT COUNT(*) FROM archives WHERE kind = 'course'")->fetchColumn() > $c ? 1 : 0;
        }
        $out['ledger'] = self::backfillLedger($pdo);
        return $out;
    }

    // ─────────────────────────────── ledger: proof that nothing was changed unnoticed ───────────────────────────────

    /** Archives created before the ledger existed (or whose ledger write failed) get their 'created' entry. @return int entries added */
    public static function backfillLedger(PDO $pdo, int $limit = 200): int
    {
        $rows = self::safeRows($pdo, "SELECT a.id, a.sha256, a.kind FROM archives a WHERE NOT EXISTS (SELECT 1 FROM archive_ledger l WHERE l.archive_id = a.id) ORDER BY a.id LIMIT " . (int)$limit);
        foreach ($rows as $r) {
            self::ledger($pdo, (int)$r['id'], 'created', (string)$r['sha256'], $r['kind'] . ' · backfilled');
        }
        return count($rows);
    }

    /**
     * Walks the whole ledger and every archive.
     * @return array{ok:bool,entries:int,archives:int,broken_at:?int,mismatches:int[],missing:int[],head:string}
     *   broken_at = first ledger entry whose chain does not follow; mismatches = archives whose content no longer matches the ledger;
     *   missing = archives the ledger knows that no longer exist and were not purged through the procedure
     */
    public static function verifyLedger(PDO $pdo): array
    {
        $prev = str_repeat('0', 64);
        $broken = null;
        $entries = 0;
        $last = [];      // archive_id => [event, sha256]
        $gone = [];      // archive_id => purged
        $st = $pdo->query("SELECT * FROM archive_ledger ORDER BY id");
        while ($l = $st->fetch(PDO::FETCH_ASSOC)) {
            $entries++;
            $expect = hash('sha256', implode('|', [$prev, $l['archive_id'], $l['event'], $l['sha256'], $l['note'], $l['actor'], $l['at']]));
            if ($broken === null && ($l['prev_chain'] !== $prev || $l['chain'] !== $expect)) {
                $broken = (int)$l['id'];
            }
            $prev = (string)$l['chain'];
            if ($l['event'] === 'purged') { $gone[(int)$l['archive_id']] = true; } else { $last[(int)$l['archive_id']] = (string)$l['sha256']; }
        }
        $mismatch = [];
        $seen = [];
        $a = $pdo->query("SELECT id, sha256, payload FROM archives ORDER BY id");
        $count = 0;
        while ($r = $a->fetch(PDO::FETCH_ASSOC)) {
            $count++;
            $id = (int)$r['id'];
            $seen[$id] = true;
            $json = @gzdecode((string)$r['payload']);
            $okContent = $json !== false && hash('sha256', $json) === $r['sha256'];
            if (!$okContent || (isset($last[$id]) && $last[$id] !== $r['sha256'])) {
                $mismatch[] = $id;
            }
        }
        $missing = array_values(array_filter(array_keys($last), fn($id) => !isset($seen[$id]) && !isset($gone[$id])));
        return ['ok' => $broken === null && !$mismatch && !$missing, 'entries' => $entries, 'archives' => $count, 'broken_at' => $broken, 'mismatches' => $mismatch, 'missing' => $missing, 'head' => $prev];
    }

    // ─────────────────────────────── erasure and purge ───────────────────────────────

    /**
     * Replaces the identity of a person inside decoded archive data (pure function). Rows carrying the email (or the user id) lose their name, email, phone
     * and matricule; free texts equal to the email or the name are replaced; marks, answers and dates stay.
     * @return array{0:mixed,1:int} the data and the number of rows redacted
     */
    public static function redactData(mixed $data, string $email, ?int $userId, string $name = ''): array
    {
        $count = 0;
        $email = strtolower(trim($email));
        $tag = substr(hash('sha256', $email . ($userId ?? '')), 0, 8);
        $walk = function (mixed $node) use (&$walk, &$count, $email, $userId, $name, $tag): mixed {
            if (!is_array($node)) {
                if (is_string($node) && $email !== '' && strcasecmp(trim($node), $email) === 0) { return 'erased-' . $tag . '@erased.invalid'; }
                return $node;
            }
            $isRow = false;
            if ($email !== '' && isset($node['email']) && is_string($node['email']) && strcasecmp(trim($node['email']), $email) === 0) { $isRow = true; }
            if ($userId !== null) {
                foreach (['student_id', 'user_id'] as $k) {
                    if (isset($node[$k]) && (int)$node[$k] === $userId && $node[$k] !== null) { $isRow = true; }
                }
                if (isset($node['profile']['id']) && (int)$node['profile']['id'] === $userId) {   // a 'user' archive: the profile is the identity
                    $node['profile'] = ['id' => $userId, 'role' => $node['profile']['role'] ?? null, 'created_at' => $node['profile']['created_at'] ?? null, 'erased' => true];
                    $count++;
                }
            }
            if ($isRow) {
                foreach (['name', 'full_name', 'first_name', 'last_name', 'email', 'phone', 'phone_number', 'matricule', 'avatar_path', 'ip', 'ip_address', 'user_agent'] as $k) {
                    if (array_key_exists($k, $node)) { $node[$k] = $k === 'email' ? 'erased-' . $tag . '@erased.invalid' : ($k === 'name' ? 'Participant effacé' : null); }
                }
                foreach (['student_id', 'user_id'] as $k) {
                    if ($userId !== null && isset($node[$k]) && (int)$node[$k] === $userId) { $node[$k] = null; }
                }
                $count++;
            }
            foreach ($node as $k => $v) {
                if (is_array($v) || is_string($v)) { $node[$k] = $walk($v); }
            }
            return $node;
        };
        return [$walk($data), $count];
    }

    /**
     * Right to erasure inside the archives: every archive that concerns this person is rewritten without their identity, re-sealed (new SHA-256) and the
     * change is written to the ledger. The figures (counts, marks, dates) stay, so activity remains provable without identifying anyone.
     * @return array{archives:int,rows:int}
     */
    public static function redactPerson(PDO $pdo, string $email, ?int $userId, string $actor, string $reason): array
    {
        $email = strtolower(trim($email));
        $ids = [];
        if ($email !== '') {
            $st = $pdo->prepare("SELECT archive_id FROM archive_subjects WHERE subject_hash = :h");
            $st->execute(['h' => self::subjectHash($email)]);
            $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        }
        if ($userId !== null) {
            $st = $pdo->prepare("SELECT id FROM archives WHERE (kind IN ('user', 'attestation') AND ref_id = :u) OR (kind = 'course' AND teacher_id = :u)");
            $st->execute(['u' => $userId]);
            $ids = array_merge($ids, array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
        }
        $name = '';
        if ($userId !== null) {
            $name = (string)(self::safeRows($pdo, "SELECT name FROM users WHERE id = :i", ['i' => $userId])[0]['name'] ?? '');
        }
        $ids = array_values(array_unique($ids));
        $done = ['archives' => 0, 'rows' => 0];
        foreach ($ids as $id) {
            self::unlocked($pdo, function () use ($pdo, $id, $email, $userId, $name, $actor, $reason, &$done) {
                $row = self::rows($pdo, "SELECT payload, kind, sha256 FROM archives WHERE id = :i", ['i' => $id])[0] ?? null;
                $json = $row ? @gzdecode((string)$row['payload']) : false;
                if ($json === false) {
                    return;
                }
                $data = json_decode($json, true);
                if (!is_array($data)) {
                    return;
                }
                [$new, $n] = self::redactData($data, $email, $userId, $name);
                if ($row['kind'] === 'attestation' && $userId !== null && isset($new['teacher'])) {
                    $new['teacher'] = ['id' => $userId, 'name' => 'Enseignant effacé'];
                    $n++;
                }
                if ($n === 0) {
                    $pdo->prepare("DELETE FROM archive_subjects WHERE archive_id = :a AND subject_hash = :h")->execute(['a' => $id, 'h' => self::subjectHash($email)]);
                    return;
                }
                $newJson = self::encode($new);
                $gz = (string)gzencode($newJson, 6);
                $hash = hash('sha256', $newJson);
                $up = $pdo->prepare("UPDATE archives SET payload = :p, sha256 = :h, bytes = :b, redacted_at = NOW() WHERE id = :i");
                $up->bindValue('p', $gz, PDO::PARAM_LOB);
                $up->bindValue('h', $hash);
                $up->bindValue('b', strlen($gz), PDO::PARAM_INT);
                $up->bindValue('i', $id, PDO::PARAM_INT);
                $up->execute();
                if ($email !== '') {
                    $pdo->prepare("DELETE FROM archive_subjects WHERE archive_id = :a AND subject_hash = :h")->execute(['a' => $id, 'h' => self::subjectHash($email)]);
                }
                self::ledger($pdo, $id, 'redacted', $hash, 'erasure request · ' . mb_substr($reason, 0, 120), $actor);
                $done['archives']++;
                $done['rows'] += $n;
            });
        }
        return $done;
    }

    /**
     * Exceptional removal of one archive. A written reason is required; the ledger keeps the SHA-256 of what was removed and who removed it.
     * @return array{ok:bool,message:string}
     */
    public static function purge(PDO $pdo, int $archiveId, string $reason, string $actor): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 15) {
            return ['ok' => false, 'message' => 'A written reason of at least 15 characters is required.'];
        }
        $row = self::rows($pdo, "SELECT id, kind, sha256, title FROM archives WHERE id = :i", ['i' => $archiveId])[0] ?? null;
        if (!$row) {
            return ['ok' => false, 'message' => 'Archive not found.'];
        }
        self::unlocked($pdo, function () use ($pdo, $row, $reason, $actor) {
            self::ledger($pdo, (int)$row['id'], 'purged', (string)$row['sha256'], $row['kind'] . ' "' . mb_substr((string)$row['title'], 0, 60) . '" · ' . mb_substr($reason, 0, 120), $actor);
            $pdo->prepare("DELETE FROM archive_subjects WHERE archive_id = :i")->execute(['i' => $row['id']]);
            $pdo->prepare("DELETE FROM archives WHERE id = :i")->execute(['i' => $row['id']]);
        });
        return ['ok' => true, 'message' => 'Archive #' . $archiveId . ' purged; the ledger keeps the trace.'];
    }

    // ─────────────────────────────── proof of activity ───────────────────────────────

    /**
     * Figures of a teacher's evaluations, taken from the archives (so they stay true after sessions are deleted). The sessions not archived yet are archived first.
     * @return array{sessions:int,participations:int,finished:int,distinct_people:int,questions:int,answers:int,first:?string,last:?string,list:array}
     */
    public static function teacherActivity(PDO $pdo, int $teacherId): array
    {
        $todo = self::safeRows($pdo, "SELECT s.id FROM live_eval_sessions s WHERE s.teacher_id = :t AND EXISTS (SELECT 1 FROM live_eval_registrations r WHERE r.session_id = s.id)
                                       AND NOT EXISTS (SELECT 1 FROM archives a WHERE a.kind = 'session' AND a.ref_id = s.id) LIMIT 100", ['t' => $teacherId]);
        foreach ($todo as $t) { self::snapshotSession($pdo, (int)$t['id'], 'attestation', $teacherId); }
        $latest = self::rows($pdo, "SELECT a.id, a.ref_id, a.title, a.sha256, a.created_at, a.meta FROM archives a
                                     WHERE a.kind = 'session' AND a.teacher_id = :t AND a.id = (SELECT MAX(b.id) FROM archives b WHERE b.kind = 'session' AND b.ref_id = a.ref_id)
                                     ORDER BY a.ref_id", ['t' => $teacherId]);
        $out = ['sessions' => 0, 'participations' => 0, 'finished' => 0, 'distinct_people' => 0, 'questions' => 0, 'answers' => 0, 'first' => null, 'last' => null, 'list' => []];
        $ids = [];
        foreach ($latest as $a) {
            $m = json_decode((string)$a['meta'], true) ?: [];
            if ((int)($m['participants'] ?? 0) === 0) {
                continue;
            }
            $out['sessions']++;
            $out['participations'] += (int)$m['participants'];
            $out['finished'] += (int)($m['finished'] ?? 0);
            $out['questions'] += (int)($m['questions'] ?? 0);
            $out['answers'] += (int)($m['answers'] ?? 0);
            $d = (string)($m['start'] ?? '');
            if ($d !== '') {
                $out['first'] = $out['first'] === null || $d < $out['first'] ? $d : $out['first'];
                $out['last'] = $out['last'] === null || $d > $out['last'] ? $d : $out['last'];
            }
            $ids[] = (int)$a['id'];
            $out['list'][] = ['archive_id' => (int)$a['id'], 'title' => (string)$a['title'], 'date' => $d, 'participants' => (int)$m['participants'], 'finished' => (int)($m['finished'] ?? 0), 'sha256' => (string)$a['sha256']];
        }
        if ($ids) {
            $out['distinct_people'] = (int)(self::rows($pdo, "SELECT COUNT(DISTINCT subject_hash) n FROM archive_subjects WHERE archive_id IN (" . implode(',', $ids) . ")", [])[0]['n'] ?? 0);
        }
        return $out;
    }

    /** Issues a certificate of activity for a teacher, verifiable publicly with its code. @return array{id:int,code:string} */
    public static function issueAttestation(PDO $pdo, int $teacherId): ?array
    {
        $t = self::safeRows($pdo, "SELECT id, name FROM users WHERE id = :i AND role = 'teacher'", ['i' => $teacherId])[0] ?? null;
        if (!$t) {
            return null;
        }
        $act = self::teacherActivity($pdo, $teacherId);
        $code = 'SVA-' . strtoupper(bin2hex(random_bytes(5)));
        $data = ['teacher' => ['id' => (int)$t['id'], 'name' => (string)$t['name']], 'issued_at' => gmdate('Y-m-d H:i:s') . ' UTC', 'code' => $code, 'activity' => $act,
                 'note' => 'Figures taken from the sealed archives of the platform: each evaluation below can be checked against its SHA-256.'];
        $id = self::store($pdo, [
            'kind' => 'attestation', 'ref_id' => $teacherId, 'session_id' => null, 'teacher_id' => $teacherId, 'title' => 'Attestation ' . $code, 'reason' => 'issued', 'items' => $act['participations'], 'code' => $code,
        ], self::encode($data), [], ['participations' => $act['participations'], 'sessions' => $act['sessions']]);
        return ['id' => $id, 'code' => $code];
    }

    /** @return array{meta:array,data:array,ledger_ok:bool}|null a public lookup by code; null when unknown or the content no longer matches its seal */
    public static function openAttestation(PDO $pdo, string $code): ?array
    {
        if (!preg_match('/^SVA-[A-Z0-9]{10}$/', $code)) {
            return null;
        }
        $row = self::rows($pdo, "SELECT id FROM archives WHERE kind = 'attestation' AND code = :c", ['c' => $code])[0] ?? null;
        $a = $row ? self::open($pdo, (int)$row['id']) : null;
        if (!$a) {
            return null;
        }
        $l = self::rows($pdo, "SELECT sha256 FROM archive_ledger WHERE archive_id = :i ORDER BY id DESC LIMIT 1", ['i' => $a['meta']['id']])[0] ?? null;
        return $a + ['ledger_ok' => $l !== null && $l['sha256'] === $a['meta']['sha256']];
    }
}
