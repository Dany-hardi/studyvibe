<?php
declare(strict_types=1);

require_once __DIR__ . '/EmailQueue.php';

/**
 * Tells the students of a course, by email, that a live evaluation is scheduled (or moved): when, how long, how to take part,
 * and the link. Sent in the background through EmailQueue.
 *
 * Who gets it: active students enrolled in the course whose email address is confirmed. Each student gets one message per
 * version of the session: the key holds the start time and the deadline, so activating, pausing and activating again does not
 * send twice, while moving the exam sends a "new schedule" message.
 */
final class LiveMailNotifier
{
    /** @return array{queued:int,eligible:int} */
    public static function notify(PDO $pdo, int $sessionId): array
    {
        $none = ['queued' => 0, 'eligible' => 0];
        $st = $pdo->prepare(
            "SELECT s.id, s.title, s.session_code, s.start_time, s.is_async, s.async_deadline, s.default_time_limit, s.status, s.integrity_watch,
                    s.course_id, c.title AS course_title, t.name AS teacher_name
             FROM live_eval_sessions s
             JOIN courses c ON c.id = s.course_id
             LEFT JOIN users t ON t.id = s.teacher_id
             WHERE s.id = :id"
        );
        $st->execute(['id' => $sessionId]);
        $s = $st->fetch(PDO::FETCH_ASSOC);
        if (!$s || (int)$s['status'] !== 1) {
            return $none;
        }

        $q = $pdo->prepare("SELECT time_limit FROM live_eval_questions WHERE session_id = :id");
        $q->execute(['id' => $sessionId]);
        $limits = $q->fetchAll(PDO::FETCH_COLUMN);
        $seconds = 0;
        foreach ($limits as $l) {
            $seconds += $l !== null ? (int)$l : (int)$s['default_time_limit'];
        }
        $session = [
            'title' => (string)$s['title'], 'course_title' => (string)$s['course_title'], 'start_time' => (string)$s['start_time'],
            'is_async' => (int)$s['is_async'], 'async_deadline' => $s['async_deadline'], 'n_questions' => count($limits),
            'minutes' => (int)ceil($seconds / 60), 'integrity_watch' => (int)$s['integrity_watch'], 'teacher_name' => (string)($s['teacher_name'] ?? ''),
        ];

        $students = $pdo->prepare(
            "SELECT u.id, u.name, u.email, u.lang
             FROM enrollments e JOIN users u ON u.id = e.student_id
             WHERE e.course_id = :c AND u.role = 'student' AND u.is_active = 1 AND u.email_verified_at IS NOT NULL"
        );
        $students->execute(['c' => $s['course_id']]);

        $version = substr(md5($s['start_time'] . '|' . (int)$s['is_async'] . '|' . ($s['async_deadline'] ?? '')), 0, 10);
        $link = rtrim((string)APP_URL, '/') . '/live-session.php?code=' . rawurlencode((string)$s['session_code']);
        $seen = $pdo->prepare("SELECT 1 FROM email_outbox WHERE dedupe_key LIKE :k LIMIT 1");

        $out = $none;
        foreach ($students->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $out['eligible']++;
            // Already told about an earlier version of this session? Then this one is an update.
            $seen->execute(['k' => "live:{$s['id']}:{$u['id']}:%"]);
            $rescheduled = (bool)$seen->fetchColumn();
            $args = [
                'name' => (string)$u['name'], 'session' => $session, 'link' => $link,
                'lang' => ($u['lang'] ?? 'fr') === 'en' ? 'en' : 'fr', 'rescheduled' => $rescheduled,
            ];
            if (EmailQueue::enqueue($pdo, (int)$u['id'], (string)$u['email'], 'live_scheduled', $args, "live:{$s['id']}:{$u['id']}:$version") !== null) {
                $out['queued']++;
            }
        }
        if ($out['queued'] > 0) {
            EmailQueue::kick();
        }
        return $out;
    }
}
