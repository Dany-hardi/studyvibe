<?php
declare(strict_types=1);

require_once __DIR__ . '/LiveResults.php';
require_once __DIR__ . '/Notifications.php';

/**
 * A student's contestation of a result the teacher cancelled.
 *
 * The rules: only a cancelled result can be contested, once; the student writes what happened (20 to 2000 characters) and can read
 * their own copy (their answers, not the answer key) to do so; the teacher answers by restoring the result or keeping the
 * cancellation, with a written reason; that answer is final. Both sides are told by email and by notification.
 *
 * Access: a signed-in student who owns the registration, or anybody holding the signed link from the email (guests have no account).
 */
final class Contests
{
    public const MIN = 20;
    public const MAX = 2000;

    public static function token(int $registrationId): string
    {
        return hash_hmac('sha256', 'contest:' . $registrationId, APP_SECRET);
    }

    public static function url(int $registrationId): string
    {
        return rtrim((string)APP_URL, '/') . '/student/contest-result.php?registration_id=' . $registrationId . '&token=' . self::token($registrationId);
    }

    /** The registration with what the contest page needs, or null. */
    public static function registration(PDO $pdo, int $registrationId): ?array
    {
        $st = $pdo->prepare(
            "SELECT r.id, r.session_id, r.name, r.email, r.student_id, r.cancelled_at, r.cancelled_reason,
                    s.title AS session_title, s.teacher_id, s.shuffle_options, s.start_time,
                    c.title AS course_title, t.name AS teacher_name, t.email AS teacher_email, t.lang AS teacher_lang, u.lang AS student_lang
             FROM live_eval_registrations r
             JOIN live_eval_sessions s ON s.id = r.session_id
             JOIN courses c ON c.id = s.course_id
             LEFT JOIN users t ON t.id = s.teacher_id
             LEFT JOIN users u ON u.id = r.student_id OR (r.student_id IS NULL AND u.email = r.email)
             WHERE r.id = :id LIMIT 1"
        );
        $st->execute(['id' => $registrationId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** May this visitor see and use the contest page of that registration? */
    public static function mayAccess(array $reg, ?array $user, string $token): bool
    {
        if ($token !== '' && hash_equals(self::token((int)$reg['id']), $token)) {
            return true;
        }
        if ($user && ($user['role'] ?? '') === 'student') {
            return ((int)($reg['student_id'] ?? 0) === (int)$user['id']) || (strcasecmp((string)$reg['email'], (string)$user['email']) === 0);
        }
        return false;
    }

    public static function find(PDO $pdo, int $registrationId): ?array
    {
        $st = $pdo->prepare("SELECT * FROM result_contests WHERE registration_id = :r");
        $st->execute(['r' => $registrationId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array{ok:bool,error?:string} errors: not_found, not_cancelled, already, too_short, too_long */
    public static function submit(PDO $pdo, int $registrationId, string $message): array
    {
        $reg = self::registration($pdo, $registrationId);
        if ($reg === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if ($reg['cancelled_at'] === null) {
            return ['ok' => false, 'error' => 'not_cancelled'];
        }
        $message = trim($message);
        $len = mb_strlen($message);
        if ($len < self::MIN) {
            return ['ok' => false, 'error' => 'too_short'];
        }
        if ($len > self::MAX) {
            return ['ok' => false, 'error' => 'too_long'];
        }
        try {
            $pdo->prepare("INSERT INTO result_contests (registration_id, message) VALUES (:r, :m)")->execute(['r' => $registrationId, 'm' => $message]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'already'];   // the unique key: one contestation per result
            }
            throw $e;
        }

        // The teacher is told in the dashboard and by email
        if (!empty($reg['teacher_id'])) {
            Notifications::send($pdo, (int)$reg['teacher_id'], 'contest', 'Contestation : ' . $reg['name'], $reg['session_title'] . ' — ' . mb_strimwidth($message, 0, 120, '…'), '/teacher/dashboard.php');
            if (!empty($reg['teacher_email'])) {
                EmailQueue::enqueue($pdo, (int)$reg['teacher_id'], (string)$reg['teacher_email'], 'contest_received', [
                    'teacher' => (string)$reg['teacher_name'], 'student' => (string)$reg['name'], 'session_title' => (string)$reg['session_title'],
                    'message' => $message, 'lang' => ($reg['teacher_lang'] ?? 'fr') === 'en' ? 'en' : 'fr',
                ], 'contest:' . $registrationId . ':received');
                EmailQueue::kick();
            }
        }
        return ['ok' => true];
    }

    /**
     * The teacher's final answer. Accepting restores the result; keeping the cancellation needs a written reason.
     *
     * @return array{ok:bool,error?:string} errors: not_found, already_resolved, reason_needed
     */
    public static function resolve(PDO $pdo, int $contestId, int $teacherId, bool $accept, string $response): array
    {
        $response = trim(mb_substr($response, 0, 1000));
        $st = $pdo->prepare(
            "SELECT k.id, k.registration_id, k.status FROM result_contests k
             JOIN live_eval_registrations r ON r.id = k.registration_id
             JOIN live_eval_sessions s ON s.id = r.session_id
             WHERE k.id = :id AND s.teacher_id = :t"
        );
        $st->execute(['id' => $contestId, 't' => $teacherId]);
        $k = $st->fetch(PDO::FETCH_ASSOC);
        if (!$k) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if ($k['status'] !== 'open') {
            return ['ok' => false, 'error' => 'already_resolved'];
        }
        if (!$accept && mb_strlen($response) < 10) {
            return ['ok' => false, 'error' => 'reason_needed'];
        }

        $rid = (int)$k['registration_id'];
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare(
                "UPDATE result_contests SET status = :s, teacher_response = :r, resolved_at = NOW(), resolved_by = :t WHERE id = :id AND status = 'open'"
            );
            $upd->execute(['s' => $accept ? 'accepted' : 'rejected', 'r' => $response !== '' ? $response : null, 't' => $teacherId, 'id' => $contestId]);
            if ($upd->rowCount() !== 1) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'already_resolved'];
            }
            if ($accept) {
                LiveResults::restore($pdo, $rid, $teacherId, false);   // no separate "restored" email: the decision email says it
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $reg = self::registration($pdo, $rid);
        if ($reg) {
            $lang = ($reg['student_lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
            EmailQueue::enqueue($pdo, $reg['student_id'] !== null ? (int)$reg['student_id'] : null, (string)$reg['email'], 'contest_decision', [
                'name' => (string)$reg['name'], 'session_title' => (string)$reg['session_title'], 'course_title' => (string)$reg['course_title'],
                'accepted' => $accept, 'response' => $response, 'teacher' => (string)($reg['teacher_name'] ?? ''), 'lang' => $lang, 'url' => self::url($rid),
            ], 'contest:' . $rid . ':decision');
            if (!empty($reg['student_id'])) {
                Notifications::send($pdo, (int)$reg['student_id'], 'contest', $accept ? 'Contestation acceptée' : 'Décision définitive', (string)$reg['session_title'], '/student/dashboard.php');
            }
            EmailQueue::kick();
        }
        return ['ok' => true];
    }

    /**
     * The student's own copy: the questions and what they answered, in the order they saw the options. No answer key: the copy
     * is there to explain their side, the correction comes back with the result if the teacher restores it.
     *
     * @return array<int,array{n:int,text:string,type:string,options:array<string,string>,picked:string,picked_text:string}>
     */
    public static function copy(PDO $pdo, array $reg): array
    {
        $st = $pdo->prepare(
            "SELECT q.id AS question_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option, q.question_type, a.selected_option
             FROM live_eval_questions q
             LEFT JOIN live_eval_answers a ON a.question_id = q.id AND a.registration_id = :r
             WHERE q.session_id = :s ORDER BY q.sort_order, q.id"
        );
        $st->execute(['r' => $reg['id'], 's' => $reg['session_id']]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $i => $row) {
            $row['selected_option'] = (string)($row['selected_option'] ?? '');
            $seen = LiveScoring::asSeen($row, $row['selected_option'], (int)$reg['id'], !empty($reg['shuffle_options']));
            $written = $row['question_type'] === 'written';
            $options = [];
            if (!$written) {
                foreach (['A', 'B', 'C', 'D'] as $l) {
                    $t = trim((string)$seen['option_' . strtolower($l)]);
                    if ($t !== '') {
                        $options[$l] = $t;
                    }
                }
            }
            $pick = (string)$seen['selected_option'];
            $out[] = [
                'n' => $i + 1, 'text' => (string)$row['question_text'], 'type' => $written ? 'written' : 'mcq', 'options' => $options,
                'picked' => $written ? '' : $pick, 'picked_text' => $written ? $pick : ($options[$pick] ?? ''),
            ];
        }
        return $out;
    }
}
