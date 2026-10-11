<?php
declare(strict_types=1);

require_once __DIR__ . '/Notifications.php';
require_once __DIR__ . '/EmailQueue.php';
require_once __DIR__ . '/RateLimit.php';
require_once __DIR__ . '/Assignments.php';

/**
 * What a student can ask their teacher, and the teacher's answer. Three kinds:
 *
 *   assignment   "Please review my mark": for an assignment whose mark was RELEASED to the student; once per mark (a new version of the
 *                work is a new mark, so a new request is possible)
 *   live_result  "Please look at my result": for a live evaluation result the student owns, not cancelled (a cancelled result has its own
 *                contestation, lib/Contests.php); once per result
 *   course       a free message to the teacher of a course the student is enrolled in (a complaint, a problem, a question about marking)
 *
 * The teacher answers once (10 to 2000 characters); an answer that changes a mark is made in the marking window, and the text says so.
 * The student is told by notification and email; the teacher too when a request arrives. Nothing here changes a mark by itself.
 */
final class StudentRequests
{
    public const MIN = 20;
    public const MAX = 2000;
    public const MIN_ANSWER = 10;

    /**
     * @param array $in kind, ref_id (assignment: submission id, live_result: registration id), course_id (kind=course), subject (kind=course), message
     * @return array{ok:bool,error?:string,request_id?:int}
     *         errors: kind, not_found, not_released, cancelled, no_result, forbidden, already, too_short, too_long, subject, too_many
     */
    public static function create(PDO $pdo, array $student, array $in): array
    {
        $kind = (string)($in['kind'] ?? '');
        $message = trim((string)($in['message'] ?? ''));
        if (mb_strlen($message) < self::MIN) {
            return ['ok' => false, 'error' => 'too_short'];
        }
        if (mb_strlen($message) > self::MAX) {
            return ['ok' => false, 'error' => 'too_long'];
        }
        $sid = (int)$student['id'];
        $teacherId = 0; $courseId = 0; $ref = null; $version = 0; $subject = ''; $context = '';

        if ($kind === 'assignment') {
            $st = $pdo->prepare(
                "SELECT s.id, s.student_id, s.graded_at, s.released_at, s.attempt_count, l.title AS lesson_title, l.assignment_title, c.id AS course_id, c.title AS course_title, c.teacher_id
                 FROM lesson_assignment_submissions s JOIN lessons l ON l.id = s.lesson_id JOIN chapters ch ON ch.id = l.chapter_id JOIN courses c ON c.id = ch.course_id
                 WHERE s.id = :id"
            );
            $st->execute(['id' => (int)($in['ref_id'] ?? 0)]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if (!$r) { return ['ok' => false, 'error' => 'not_found']; }
            if ((int)$r['student_id'] !== $sid) { return ['ok' => false, 'error' => 'forbidden']; }
            if ($r['graded_at'] === null || $r['released_at'] === null) { return ['ok' => false, 'error' => 'not_released']; }
            $title = (string)($r['assignment_title'] ?: $r['lesson_title']);
            [$teacherId, $courseId, $ref, $version] = [(int)$r['teacher_id'], (int)$r['course_id'], (int)$r['id'], (int)$r['attempt_count']];
            $subject = 'Révision de ma note : ' . $title;
            $context = $title . ' — ' . $r['course_title'];
        } elseif ($kind === 'live_result') {
            $st = $pdo->prepare(
                "SELECT r.id, r.student_id, r.email, r.score, r.cancelled_at, s.title AS session_title, s.teacher_id, c.id AS course_id, c.title AS course_title
                 FROM live_eval_registrations r JOIN live_eval_sessions s ON s.id = r.session_id JOIN courses c ON c.id = s.course_id WHERE r.id = :id"
            );
            $st->execute(['id' => (int)($in['ref_id'] ?? 0)]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if (!$r) { return ['ok' => false, 'error' => 'not_found']; }
            $mine = ((int)($r['student_id'] ?? 0) === $sid) || strcasecmp((string)$r['email'], (string)$student['email']) === 0;
            if (!$mine) { return ['ok' => false, 'error' => 'forbidden']; }
            if ($r['cancelled_at'] !== null) { return ['ok' => false, 'error' => 'cancelled']; }
            if ($r['score'] === null) { return ['ok' => false, 'error' => 'no_result']; }
            [$teacherId, $courseId, $ref] = [(int)$r['teacher_id'], (int)$r['course_id'], (int)$r['id']];
            $subject = 'Mon résultat : ' . $r['session_title'];
            $context = $r['session_title'] . ' — ' . $r['course_title'];
        } elseif ($kind === 'course') {
            $st = $pdo->prepare(
                "SELECT c.id, c.title, c.teacher_id FROM courses c JOIN enrollments e ON e.course_id = c.id AND e.student_id = :s WHERE c.id = :c"
            );
            $st->execute(['s' => $sid, 'c' => (int)($in['course_id'] ?? 0)]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if (!$r || (int)$r['teacher_id'] <= 0) { return ['ok' => false, 'error' => 'not_found']; }
            $subject = trim(mb_substr(trim((string)($in['subject'] ?? '')), 0, 160));
            if (mb_strlen($subject) < 3) { return ['ok' => false, 'error' => 'subject']; }
            [$teacherId, $courseId] = [(int)$r['teacher_id'], (int)$r['id']];
            $context = (string)$r['title'];
        } else {
            return ['ok' => false, 'error' => 'kind'];
        }

        if (!RateLimit::allow($pdo, 'request:' . $sid, 10, 3600)) {
            return ['ok' => false, 'error' => 'too_many'];
        }
        try {
            $pdo->prepare(
                "INSERT INTO student_requests (student_id, teacher_id, course_id, kind, ref_id, ref_version, subject, message) VALUES (:s, :t, :c, :k, :r, :v, :sub, :m)"
            )->execute(['s' => $sid, 't' => $teacherId, 'c' => $courseId, 'k' => $kind, 'r' => $ref, 'v' => $version, 'sub' => mb_substr($subject, 0, 200), 'm' => $message]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'already'];   // one request per mark or result
            }
            throw $e;
        }
        $id = (int)$pdo->lastInsertId();

        $t = $pdo->prepare('SELECT name, email, lang FROM users WHERE id = :id');
        $t->execute(['id' => $teacherId]);
        $teacher = $t->fetch(PDO::FETCH_ASSOC) ?: [];
        Notifications::send($pdo, $teacherId, 'request', 'Demande : ' . $student['name'], $subject . ' — ' . mb_strimwidth($message, 0, 140, '…'),
            '/teacher/dashboard.php?course_id=' . $courseId . '&focus=request-' . $id . '#tab-comments');
        if (!empty($teacher['email'])) {
            EmailQueue::enqueue($pdo, $teacherId, (string)$teacher['email'], 'request_received', [
                'teacher' => (string)$teacher['name'], 'student' => (string)$student['name'], 'subject' => $subject, 'message' => $message, 'context' => $context,
                'lang' => ($teacher['lang'] ?? 'fr') === 'en' ? 'en' : 'fr', 'url' => rtrim((string)APP_URL, '/') . '/teacher/dashboard.php?course_id=' . $courseId . '&focus=request-' . $id . '#tab-comments',
            ], 'request:' . $id . ':received');
            EmailQueue::kick();
        }
        return ['ok' => true, 'request_id' => $id];
    }

    /** @return array{ok:bool,error?:string} errors: not_found, already, too_short, too_long */
    public static function answer(PDO $pdo, int $teacherId, int $requestId, string $response): array
    {
        $st = $pdo->prepare(
            "SELECT q.*, u.name AS student_name, u.email AS student_email, u.lang AS student_lang, c.title AS course_title, t.name AS teacher_name
             FROM student_requests q JOIN users u ON u.id = q.student_id JOIN courses c ON c.id = q.course_id LEFT JOIN users t ON t.id = q.teacher_id
             WHERE q.id = :id AND q.teacher_id = :t"
        );
        $st->execute(['id' => $requestId, 't' => $teacherId]);
        $q = $st->fetch(PDO::FETCH_ASSOC);
        if (!$q) { return ['ok' => false, 'error' => 'not_found']; }
        if ($q['status'] !== 'open') { return ['ok' => false, 'error' => 'already']; }
        $response = trim($response);
        if (mb_strlen($response) < self::MIN_ANSWER) { return ['ok' => false, 'error' => 'too_short']; }
        if (mb_strlen($response) > self::MAX) { return ['ok' => false, 'error' => 'too_long']; }
        $upd = $pdo->prepare("UPDATE student_requests SET status = 'answered', response = :r, answered_at = NOW() WHERE id = :id AND status = 'open'");
        $upd->execute(['r' => $response, 'id' => $requestId]);
        if ($upd->rowCount() !== 1) { return ['ok' => false, 'error' => 'already']; }

        $url = rtrim((string)APP_URL, '/') . '/student/dashboard.php#results';
        Notifications::send($pdo, (int)$q['student_id'], 'request_answer', 'Réponse à votre demande', $q['subject'] . ' — ' . mb_strimwidth($response, 0, 140, '…'), '/student/dashboard.php#results');
        EmailQueue::enqueue($pdo, (int)$q['student_id'], (string)$q['student_email'], 'request_answer', [
            'name' => (string)$q['student_name'], 'subject' => (string)$q['subject'], 'context' => (string)$q['course_title'], 'response' => $response,
            'teacher' => (string)($q['teacher_name'] ?? ''), 'lang' => ($q['student_lang'] ?? 'fr') === 'en' ? 'en' : 'fr', 'url' => $url,
        ], 'request:' . $requestId . ':answer');
        EmailQueue::kick();
        return ['ok' => true];
    }

    /** The teacher's inbox: open requests first (oldest first), then the answered ones (newest first). */
    public static function forTeacher(PDO $pdo, int $teacherId, ?int $courseId = null, int $answeredLimit = 20): array
    {
        $base = "SELECT q.*, u.name AS student_name, u.email AS student_email, u.matricule AS student_matricule, c.title AS course_title
                 FROM student_requests q JOIN users u ON u.id = q.student_id JOIN courses c ON c.id = q.course_id
                 WHERE q.teacher_id = :t" . ($courseId ? ' AND q.course_id = :c' : '');
        $args = ['t' => $teacherId] + ($courseId ? ['c' => $courseId] : []);
        $o = $pdo->prepare($base . " AND q.status = 'open' ORDER BY q.created_at ASC");
        $o->execute($args);
        $a = $pdo->prepare($base . " AND q.status = 'answered' ORDER BY q.answered_at DESC LIMIT " . (int)$answeredLimit);
        $a->execute($args);
        return array_merge($o->fetchAll(PDO::FETCH_ASSOC), $a->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function openCount(PDO $pdo, int $teacherId, ?int $courseId = null): int
    {
        $st = $pdo->prepare("SELECT COUNT(*) FROM student_requests WHERE teacher_id = :t AND status = 'open'" . ($courseId ? ' AND course_id = :c' : ''));
        $st->execute(['t' => $teacherId] + ($courseId ? ['c' => $courseId] : []));
        return (int)$st->fetchColumn();
    }

    public static function forStudent(PDO $pdo, int $studentId): array
    {
        $st = $pdo->prepare(
            "SELECT q.id, q.kind, q.ref_id, q.subject, q.message, q.status, q.response, q.answered_at, q.created_at, c.title AS course_title
             FROM student_requests q JOIN courses c ON c.id = q.course_id WHERE q.student_id = :s ORDER BY q.created_at DESC LIMIT 50"
        );
        $st->execute(['s' => $studentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ids (of submissions or registrations) the student already asked about, for hiding the button. @return array<int,bool> */
    public static function asked(PDO $pdo, int $studentId, string $kind): array
    {
        $st = $pdo->prepare('SELECT ref_id, ref_version FROM student_requests WHERE student_id = :s AND kind = :k AND ref_id IS NOT NULL');
        $st->execute(['s' => $studentId, 'k' => $kind]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int)$r['ref_id'] . ':' . (int)$r['ref_version']] = true;
        }
        return $out;
    }
}
