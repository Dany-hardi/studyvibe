<?php
declare(strict_types=1);

/**
 * Archives: snapshots kept in the database (so they survive the host's disk being wiped) for review, treatment of complaints and maintenance.
 *
 *  - session   : a whole live evaluation (settings, questions with references, registrations, answers, drafts, code marking, timeline, mail status).
 *                Taken when the session is over, before a reset, a deletion or an exclusion, and on request. Identical content is stored once.
 *  - logs      : the audit log and the server errors older than the retention (moved here by month, then removed from the live tables).
 * A payload is gzip-compressed JSON; it is checked against its SHA-256 when read.
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
        $gz = (string)gzencode($json, 6);
        $st = $pdo->prepare("INSERT INTO archives (kind, ref_id, session_id, teacher_id, title, reason, items, bytes, sha256, payload)
                             VALUES ('session', :r, :s, :t, :title, :reason, :items, :bytes, :h, :p)");
        $st->bindValue('r', $sessionId, PDO::PARAM_INT);
        $st->bindValue('s', $sessionId, PDO::PARAM_INT);
        $st->bindValue('t', $teacherId ?? (int)$data['session']['teacher_id'], PDO::PARAM_INT);
        $st->bindValue('title', mb_substr((string)$data['session']['title'], 0, 200));
        $st->bindValue('reason', mb_substr($reason, 0, 32));
        $st->bindValue('items', count($data['registrations']), PDO::PARAM_INT);
        $st->bindValue('bytes', strlen($gz), PDO::PARAM_INT);
        $st->bindValue('h', $hash);
        $st->bindValue('p', $gz, PDO::PARAM_LOB);
        $st->execute();
        return (int)$pdo->lastInsertId();
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
        $json = self::encode($data);
        $gz = (string)gzencode($json, 6);
        $st = $pdo->prepare("INSERT INTO archives (kind, ref_id, session_id, teacher_id, title, reason, items, bytes, sha256, payload)
                             VALUES ('registration', :r, :s, :t, :title, :reason, 1, :bytes, :h, :p)");
        $st->bindValue('r', $registrationId, PDO::PARAM_INT);
        $st->bindValue('s', (int)($sess['id'] ?? 0), PDO::PARAM_INT);
        $st->bindValue('t', (int)($sess['teacher_id'] ?? 0), PDO::PARAM_INT);
        $st->bindValue('title', mb_substr((string)($reg['name'] ?? '') . ' · ' . (string)($sess['title'] ?? ''), 0, 200));
        $st->bindValue('reason', mb_substr($reason, 0, 32));
        $st->bindValue('bytes', strlen($gz), PDO::PARAM_INT);
        $st->bindValue('h', hash('sha256', $json));
        $st->bindValue('p', $gz, PDO::PARAM_LOB);
        $st->execute();
        return (int)$pdo->lastInsertId();
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
                $gz = (string)gzencode($json, 6);
                $st = $pdo->prepare("INSERT INTO archives (kind, title, reason, items, bytes, sha256, payload) VALUES ('logs', :t, 'rotation', :n, :b, :h, :p)");
                $st->bindValue('t', $table . ' ' . $m['m']);
                $st->bindValue('n', count($rows), PDO::PARAM_INT);
                $st->bindValue('b', strlen($gz), PDO::PARAM_INT);
                $st->bindValue('h', hash('sha256', $json));
                $st->bindValue('p', $gz, PDO::PARAM_LOB);
                $st->execute();
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

    /** Sessions that are over get their snapshot; once a day the logs are rotated. Meant for the cron worker. @return array{sessions:int,logs:int} */
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
                            WHERE s.created_at > NOW() - INTERVAL 90 DAY
                              AND COALESCE(s.async_deadline, s.end_time) < NOW() - INTERVAL 30 MINUTE
                              AND EXISTS (SELECT 1 FROM live_eval_registrations r WHERE r.session_id = s.id)
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
        $day = dirname(__DIR__) . '/uploads/live_cache/archive_logs.flag';
        if ((time() - (int)@filemtime($day)) >= 20 * 3600) {
            @touch($day);
            try { $done['logs'] = self::rotateLogs($pdo); } catch (Throwable $e) { error_log('archive logs: ' . $e->getMessage()); }
        }
        return $done;
    }
}
