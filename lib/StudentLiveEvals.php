<?php
declare(strict_types=1);

/**
 * Upcoming and running tele-evaluations for one student.
 *
 * A session shows up as soon as the teacher has launched it (status = 1) on a course the student is
 * enrolled in, and stays until the student has a score or the session closes. Shared by the dashboard
 * page and the polling endpoint so both always agree.
 */
final class StudentLiveEvals
{
    /** @return array<int, array<string, mixed>> sorted by start (live) or deadline (async) */
    public static function upcoming(PDO $pdo, int $studentId, string $email): array
    {
        $stmt = $pdo->prepare("
            SELECT s.id, s.title, s.session_code, s.start_time, s.end_time, s.is_async, s.async_deadline,
                   c.title AS course_title,
                   (SELECT COUNT(*) FROM live_eval_registrations r
                     WHERE r.session_id = s.id AND (r.student_id = :sid2 OR r.email = :em)) AS registered
            FROM live_eval_sessions s
            JOIN courses c ON c.id = s.course_id
            JOIN enrollments e ON e.course_id = c.id AND e.student_id = :sid
            WHERE s.status = 1
              AND ((s.is_async = 0 AND s.end_time >= NOW())
                OR (s.is_async = 1 AND (s.async_deadline IS NULL OR s.async_deadline >= NOW())))
              AND NOT EXISTS (SELECT 1 FROM live_eval_registrations r2
                               WHERE r2.session_id = s.id AND (r2.student_id = :sid3 OR r2.email = :em2) AND r2.score IS NOT NULL)
        ");
        $stmt->execute(['sid' => $studentId, 'sid2' => $studentId, 'sid3' => $studentId, 'em' => $email, 'em2' => $email]);
        $rows = $stmt->fetchAll();

        $now = time();
        foreach ($rows as &$u) {
            $isAsync = (int)$u['is_async'] === 1;
            $start = strtotime((string)$u['start_time']);
            $u['when'] = $isAsync
                ? (!empty($u['async_deadline']) ? strtotime((string)$u['async_deadline']) : PHP_INT_MAX)
                : $start;
            $u['start_ts'] = $isAsync ? 0 : $start;
            $u['seconds_to_start'] = $isAsync ? 0 : max(0, $start - $now);
            $u['is_running'] = $isAsync || $start <= $now;
        }
        unset($u);
        usort($rows, fn($a, $b) => $a['when'] <=> $b['when']);
        return $rows;
    }
}
