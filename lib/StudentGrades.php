<?php
declare(strict_types=1);

require_once __DIR__ . '/Assignments.php';

/**
 * "Mes notes": the marks a student has obtained, course by course (docs/NEXT_UPDATES_PLAN.md, 5.5).
 *
 * Three kinds of marks, each brought to /20 so they can be read side by side:
 *   - assignments: only RELEASED marks (a held mark, or a copy in the grading bucket, shows as "being marked" and counts nowhere);
 *     the course figure is the total of the points over the total of the maxima
 *   - evaluations (live or free pace): the score of each sitting that was not cancelled, as a percentage; the course figure is the average
 *   - final exam: the best attempt, as a percentage
 * The course mark is the plain average of the kinds the student has. No weighting is applied (the teacher has not set any): the page
 * says so, and the detail of every mark is listed under it.
 */
final class StudentGrades
{
    /** @return array<int,array<string,mixed>> one entry per enrolled course, in enrolment order */
    public static function forStudent(PDO $pdo, int $studentId, string $email): array
    {
        $st = $pdo->prepare(
            "SELECT c.id, c.title FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.student_id = :s ORDER BY e.enrolled_at DESC"
        );
        $st->execute(['s' => $studentId]);
        $courses = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $courses[(int)$c['id']] = ['id' => (int)$c['id'], 'title' => (string)$c['title'], 'assignments' => [], 'evaluations' => [], 'exam' => null, 'pending' => 0];
        }
        if (!$courses) {
            return [];
        }
        $ids = implode(',', array_keys($courses));

        // Assignments
        $a = $pdo->prepare(
            "SELECT ch.course_id, l.id AS lesson_id, l.title, l.assignment_title, l.assignment_max_score, l.assignment_deadline, l.assignment_allow_resubmit,
                    s.submitted_at, s.score, s.graded_at, s.released_at, s.revision_requested_at, s.in_marking_at
             FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id
             LEFT JOIN lesson_assignment_submissions s ON s.lesson_id = l.id AND s.student_id = :s
             WHERE l.has_assignment = 1 AND ch.course_id IN ($ids)
             ORDER BY ch.sort_order, l.sort_order, l.id"
        );
        $a->execute(['s' => $studentId]);
        foreach ($a->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $sub = $r['submitted_at'] !== null ? $r : null;
            $status = Assignments::studentPipeline($r, $sub);
            $released = $status === 'released' || ($status === 'revision' && Assignments::isReleased($sub));
            $courses[(int)$r['course_id']]['assignments'][] = [
                'title' => (string)($r['assignment_title'] ?: $r['title']), 'status' => $status,
                'score' => $released ? (float)$r['score'] : null, 'max' => (float)$r['assignment_max_score'],
            ];
            if ($status === 'in_marking') {
                $courses[(int)$r['course_id']]['pending']++;
            }
        }

        // Evaluations (a cancelled result counts nowhere)
        $e = $pdo->prepare(
            "SELECT s.course_id, s.title, r.score, r.registered_at FROM live_eval_registrations r JOIN live_eval_sessions s ON s.id = r.session_id
             WHERE (r.student_id = :s OR r.email = :e) AND r.cancelled_at IS NULL AND r.score IS NOT NULL AND s.course_id IN ($ids)
             ORDER BY r.registered_at"
        );
        $e->execute(['s' => $studentId, 'e' => $email]);
        foreach ($e->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $courses[(int)$r['course_id']]['evaluations'][] = ['title' => (string)$r['title'], 'percent' => (float)$r['score'], 'date' => (string)$r['registered_at']];
        }

        // Final exam: the best attempt
        $x = $pdo->prepare("SELECT course_id, MAX(score) AS best, COUNT(*) AS n FROM certification_attempts WHERE student_id = :s AND course_id IN ($ids) GROUP BY course_id");
        $x->execute(['s' => $studentId]);
        foreach ($x->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $courses[(int)$r['course_id']]['exam'] = ['percent' => (float)$r['best'], 'attempts' => (int)$r['n']];
        }

        foreach ($courses as &$c) {
            $c += self::summary($c);
        }
        return array_values($courses);
    }

    /**
     * The figures of one course, each on /20, and their plain average. Pure (unit-tested).
     *
     * @return array{asg20:?float,eval20:?float,exam20:?float,mark20:?float,parts:int}
     */
    public static function summary(array $c): array
    {
        $pts = 0.0;
        $max = 0.0;
        foreach ($c['assignments'] as $a) {
            if ($a['score'] !== null && $a['max'] > 0) {
                $pts += $a['score'];
                $max += $a['max'];
            }
        }
        $asg = $max > 0 ? round($pts / $max * 20, 2) : null;
        $ev = $c['evaluations'] ? round(array_sum(array_column($c['evaluations'], 'percent')) / count($c['evaluations']) / 5, 2) : null;
        $ex = $c['exam'] !== null ? round($c['exam']['percent'] / 5, 2) : null;
        $parts = array_values(array_filter([$asg, $ev, $ex], fn($v) => $v !== null));
        return [
            'asg20' => $asg, 'eval20' => $ev, 'exam20' => $ex,
            'mark20' => $parts ? round(array_sum($parts) / count($parts), 2) : null, 'parts' => count($parts),
        ];
    }
}
