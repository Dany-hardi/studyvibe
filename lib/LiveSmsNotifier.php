<?php
declare(strict_types=1);

require_once __DIR__ . '/SmsQueue.php';

/**
 * Tells the students of a course, by SMS, that a live evaluation has been scheduled (or moved).
 *
 * Who gets it: students enrolled in the course who have a verified phone number, have not turned reminder SMS off,
 * and whose account is active. Each student gets one message per version of the session: the key includes the start time
 * and the deadline, so activating, pausing and activating again does not send twice, while moving the exam sends an update.
 */
final class LiveSmsNotifier
{
    /** @return array{queued:int,eligible:int,skipped_no_phone:int} */
    public static function notify(PDO $pdo, int $sessionId): array
    {
        $st = $pdo->prepare(
            "SELECT s.id, s.title, s.session_code, s.start_time, s.end_time, s.is_async, s.async_deadline, s.default_time_limit, s.status,
                    s.course_id, c.title AS course_title,
                    (SELECT COUNT(*) FROM live_eval_questions q WHERE q.session_id = s.id) AS n_questions
             FROM live_eval_sessions s JOIN courses c ON c.id = s.course_id WHERE s.id = :id"
        );
        $st->execute(['id' => $sessionId]);
        $s = $st->fetch(PDO::FETCH_ASSOC);
        $none = ['queued' => 0, 'eligible' => 0, 'skipped_no_phone' => 0];
        if (!SmsGateway::enabled()) {
            return $none;
        }
        if (!$s || (int)$s['status'] !== 1) {
            return $none;
        }

        $students = $pdo->prepare(
            "SELECT u.id, u.name, u.lang, u.phone_e164, u.sms_opt_in
             FROM enrollments e JOIN users u ON u.id = e.student_id
             WHERE e.course_id = :c AND u.role = 'student' AND u.is_active = 1"
        );
        $students->execute(['c' => $s['course_id']]);

        $version = substr(md5($s['start_time'] . '|' . (int)$s['is_async'] . '|' . ($s['async_deadline'] ?? '')), 0, 10);
        $link = rtrim((string)APP_URL, '/') . '/live-session.php?code=' . rawurlencode((string)$s['session_code']);

        $out = $none;
        foreach ($students->fetchAll(PDO::FETCH_ASSOC) as $u) {
            if (empty($u['phone_e164']) || !(int)$u['sms_opt_in']) {
                $out['skipped_no_phone']++;
                continue;
            }
            $out['eligible']++;
            $body = self::message($s, (string)$u['name'], $link, ($u['lang'] ?? 'fr') === 'en' ? 'en' : 'fr');
            if (SmsQueue::enqueue($pdo, (int)$u['id'], (string)$u['phone_e164'], 'live_eval', $body, "live:{$s['id']}:{$u['id']}:$version") !== null) {
                $out['queued']++;
            }
        }
        if ($out['queued'] > 0) {
            SmsQueue::kick();
        }
        return $out;
    }

    /** The text, with the schedule, how to join and the link. Accents are removed later by SmsGateway::plain(). */
    public static function message(array $s, string $fullName, string $link, string $lang): string
    {
        $first = trim(explode(' ', trim($fullName))[0] ?? '') ?: ($lang === 'en' ? 'student' : 'etudiant(e)');
        $title = (string)$s['title'];
        $course = (string)$s['course_title'];
        $n = (int)($s['n_questions'] ?? 0);

        if ((int)$s['is_async']) {
            $when = !empty($s['async_deadline']) ? date('d/m/Y H:i', strtotime((string)$s['async_deadline'])) : null;
            if ($lang === 'en') {
                return "StudyVibe: Hello $first, an evaluation is open: \"$title\" ($course)" . ($n ? ", $n questions" : '') . ($when ? ", until $when" : '')
                    . ". To join: 1) open the link 2) sign in or enter your name and email 3) start. You can do it any time before the deadline. $link";
            }
            return "StudyVibe : Bonjour $first, une evaluation est ouverte : \"$title\" ($course)" . ($n ? ", $n questions" : '') . ($when ? ", jusqu'au $when" : '')
                . ". Pour participer : 1) ouvrez le lien 2) connectez-vous ou saisissez votre nom et e-mail 3) commencez. A faire avant la date limite. $link";
        }

        $date = date('d/m/Y', strtotime((string)$s['start_time']));
        $time = date('H:i', strtotime((string)$s['start_time']));
        if ($lang === 'en') {
            return "StudyVibe: Hello $first, a live evaluation is scheduled: \"$title\" ($course) on $date at $time" . ($n ? ", $n questions" : '')
                . ". To join: 1) open the link a few minutes early 2) sign in or enter your name and email 3) wait in the room, do not close the page. Everyone starts at the same second. $link";
        }
        return "StudyVibe : Bonjour $first, une evaluation en direct est programmee : \"$title\" ($course) le $date a $time" . ($n ? ", $n questions" : '')
            . ". Pour participer : 1) ouvrez le lien quelques minutes avant 2) connectez-vous ou saisissez votre nom et e-mail 3) patientez dans la salle sans fermer la page. Tout le monde demarre a la meme seconde. $link";
    }
}
