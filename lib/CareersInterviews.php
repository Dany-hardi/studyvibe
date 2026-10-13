<?php
declare(strict_types=1);

require_once __DIR__ . '/Careers.php';

/**
 * Interviews of the careers pipeline: scheduling, the candidate's answer, reminders' bookkeeping and the calendar file.
 *
 * Life of an interview (career_interviews.status)
 *   scheduled  ─ candidate confirms ─▶ confirmed
 *      │       ─ candidate declines ─▶ declined                      (the administrator decides what to do)
 *      │       ─ asks another time  ─▶ reschedule_requested          (the administrator proposes a new time)
 *      ├─ administrator moves it ───▶ scheduled again (reminders reset, the candidate is told the new time)
 *      ├─ administrator cancels ────▶ cancelled
 *      └─ its end passes ───────────▶ completed (by the worker)
 * Every change is written to the application's trail; those the candidate should see are marked visible for their tracking page.
 * The e-mails (invitation, update, cancellation, reminders) are drawn by InterviewMail, the answers' alerts to administrators by CareersMail.
 */
final class CareersInterviews
{
    public const MODES = ['video', 'onsite', 'phone'];
    public const OPEN = ['scheduled', 'confirmed', 'reschedule_requested'];

    // -----------------------------------------------------------------------------------------------------------------
    // Reading
    // -----------------------------------------------------------------------------------------------------------------

    public static function get(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT * FROM career_interviews WHERE id = :i');
        $st->execute(['i' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Interviews of the workspace's Interviews section: the next 90 days, plus the last 14 days. Each with the candidate's name and posting. */
    public static function list(PDO $pdo): array
    {
        return $pdo->query("
            SELECT i.*, a.full_name, a.email, a.job_title, a.reference, a.status AS app_status, a.id AS app_id
            FROM career_interviews i JOIN career_applications a ON a.id = i.application_id
            WHERE i.scheduled_at BETWEEN NOW() - INTERVAL 14 DAY AND NOW() + INTERVAL 90 DAY
            ORDER BY i.scheduled_at ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Writing (administrator)
    // -----------------------------------------------------------------------------------------------------------------

    /** @return array{0:array<string,string>,1:array<string,mixed>} [errors, clean values] */
    public static function validate(array $in): array
    {
        $e = [];
        $when = trim((string)($in['scheduled_at'] ?? ''));
        $when = str_replace('T', ' ', $when);
        $d = DateTime::createFromFormat('Y-m-d H:i', substr($when, 0, 16));
        if (!$d || $d->format('Y-m-d H:i') !== substr($when, 0, 16)) {
            $e['scheduled_at'] = 'invalid';
        }
        $mode = (string)($in['mode'] ?? 'video');
        if (!in_array($mode, self::MODES, true)) {
            $e['mode'] = 'invalid';
        }
        $dur = (int)($in['duration_min'] ?? 45);
        if ($dur < 10 || $dur > 480) {
            $e['duration_min'] = 'invalid';
        }
        $loc = Careers::line($in['location'] ?? '', 255);
        if ($mode === 'onsite' && $loc === '') {
            $e['location'] = 'required';
        }
        if ($mode === 'video' && $loc !== '' && Careers::url($loc) === '') {
            $e['location'] = 'invalid';
        }
        if ($mode === 'video' && $loc !== '') {
            $loc = Careers::url($loc);
        }
        return [$e, [
            'when' => $d ? $d->format('Y-m-d H:i:00') : '', 'mode' => $mode, 'dur' => $dur, 'loc' => $loc,
            'who' => Careers::line($in['interviewer'] ?? '', 120), 'notes' => Careers::block($in['instructions'] ?? '', 1500),
        ]];
    }

    /**
     * Schedules a new interview, or moves the one whose id is in $in['id'] to another time.
     * @return array{ok:bool,error?:string,fields?:array,message?:string,id?:int,warning?:string,mailed?:bool}
     */
    public static function save(PDO $pdo, int $appId, int $adminId, array $in, bool $notify = true): array
    {
        $app = Careers::application($pdo, $appId);
        if (!$app) {
            return ['ok' => false, 'error' => 'not_found', 'message' => 'Candidature introuvable.'];
        }
        if (in_array($app['status'], ['rejected', 'hired', 'withdrawn'], true)) {
            return ['ok' => false, 'error' => 'invalid', 'message' => 'Cette candidature est clôturée.'];
        }
        [$errors, $v] = self::validate($in);
        if (!$errors && strtotime($v['when']) < time() + 300) {
            $errors['scheduled_at'] = 'past';
        }
        if ($errors) {
            return ['ok' => false, 'error' => 'validation', 'fields' => $errors, 'message' => 'Vérifiez les champs signalés.'];
        }

        // Another interview at the same time with the same interviewer is a warning, never a block.
        $warning = '';
        if ($v['who'] !== '') {
            $c = $pdo->prepare("SELECT a.full_name FROM career_interviews i JOIN career_applications a ON a.id = i.application_id
                WHERE i.interviewer = :w AND i.status IN ('scheduled','confirmed','reschedule_requested') AND i.id <> :me
                  AND i.scheduled_at < :end AND DATE_ADD(i.scheduled_at, INTERVAL i.duration_min MINUTE) > :start LIMIT 1");
            $c->execute(['w' => $v['who'], 'me' => (int)($in['id'] ?? 0), 'start' => $v['when'], 'end' => date('Y-m-d H:i:s', strtotime($v['when']) + $v['dur'] * 60)]);
            $other = $c->fetchColumn();
            $warning = $other ? (string)$other : '';
        }

        $existing = !empty($in['id']) ? self::get($pdo, (int)$in['id']) : null;
        if ($existing && (int)$existing['application_id'] !== $appId) {
            return ['ok' => false, 'error' => 'not_found', 'message' => 'Entretien introuvable.'];
        }
        $oldWhen = $existing ? (string)$existing['scheduled_at'] : null;

        if ($existing) {
            $pdo->prepare("UPDATE career_interviews SET scheduled_at = :w, duration_min = :d, mode = :m, location = :l, interviewer = :who, instructions = :n,
                    status = 'scheduled', candidate_note = NULL, responded_at = NULL, invited_at = :now, reminder24_at = NULL, reminder2_at = NULL, chase_at = NULL
                WHERE id = :i")
                ->execute(['w' => $v['when'], 'd' => $v['dur'], 'm' => $v['mode'], 'l' => $v['loc'], 'who' => $v['who'], 'n' => $v['notes'] ?: null, 'i' => $existing['id'], 'now' => date('Y-m-d H:i:s')]);
            $id = (int)$existing['id'];
        } else {
            // One live interview per application: a new one replaces any still open (the candidate is told through the new invitation).
            self::cancelOpen($pdo, $appId, 'replaced');
            $pdo->prepare("INSERT INTO career_interviews (application_id, scheduled_at, duration_min, mode, location, interviewer, instructions, invited_at, created_by)
                VALUES (:a, :w, :d, :m, :l, :who, :n, :now, :u)")
                ->execute(['a' => $appId, 'w' => $v['when'], 'd' => $v['dur'], 'm' => $v['mode'], 'l' => $v['loc'], 'who' => $v['who'], 'n' => $v['notes'] ?: null, 'u' => $adminId, 'now' => date('Y-m-d H:i:s')]);
            $id = (int)$pdo->lastInsertId();
        }

        if ($app['status'] !== 'interview') {
            $pdo->prepare("UPDATE career_applications SET status = 'interview', status_changed_at = NOW(), reviewed_by = :u WHERE id = :i")->execute(['u' => $adminId, 'i' => $appId]);
            Careers::event($pdo, $appId, $adminId, 'status', (string)$app['status'], 'interview', null, true);
        }
        if ($app['bucket'] !== 'inbox') {
            Careers::moveBucket($pdo, $appId, $adminId, 'inbox');
        }
        Careers::event($pdo, $appId, $adminId, 'interview', null, $existing ? 'rescheduled' : 'scheduled', json_encode(['when' => $v['when'], 'mode' => $v['mode'], 'id' => $id]), true);

        $mailed = false;
        if ($notify) {
            require_once __DIR__ . '/InterviewMail.php';
            $iv = self::get($pdo, $id);
            $app = Careers::application($pdo, $appId);
            $mailed = $existing ? InterviewMail::updated($app, $iv, $oldWhen) : InterviewMail::invite($app, $iv);
            Careers::event($pdo, $appId, $adminId, 'email', null, 'interview', ($mailed ? '' : '[not sent] ') . ($existing ? 'Interview moved' : 'Interview invitation'));
        }
        return ['ok' => true, 'id' => $id, 'mailed' => $mailed, 'warning' => $warning, 'rescheduled' => (bool)$existing];
    }

    public static function cancel(PDO $pdo, int $id, int $adminId, bool $notify = true): array
    {
        $iv = self::get($pdo, $id);
        if (!$iv) {
            return ['ok' => false, 'error' => 'not_found', 'message' => 'Entretien introuvable.'];
        }
        if (!in_array($iv['status'], self::OPEN, true) && $iv['status'] !== 'declined') {
            return ['ok' => false, 'error' => 'invalid', 'message' => 'Cet entretien est déjà terminé ou annulé.'];
        }
        $pdo->prepare("UPDATE career_interviews SET status = 'cancelled' WHERE id = :i")->execute(['i' => $id]);
        $appId = (int)$iv['application_id'];
        Careers::event($pdo, $appId, $adminId, 'interview', null, 'cancelled', json_encode(['when' => $iv['scheduled_at'], 'id' => $id]), true);
        $mailed = false;
        if ($notify) {
            require_once __DIR__ . '/InterviewMail.php';
            $mailed = InterviewMail::cancelled(Careers::application($pdo, $appId), $iv);
            Careers::event($pdo, $appId, $adminId, 'email', null, 'interview', ($mailed ? '' : '[not sent] ') . 'Interview cancelled');
        }
        return ['ok' => true, 'mailed' => $mailed];
    }

    /** Closes every open interview of an application without a message (the application left the process, or a new interview replaces them). */
    public static function cancelOpen(PDO $pdo, int $appId, string $why): void
    {
        $st = $pdo->prepare("SELECT id, scheduled_at FROM career_interviews WHERE application_id = :a AND status IN ('scheduled','confirmed','reschedule_requested','declined')");
        $st->execute(['a' => $appId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pdo->prepare("UPDATE career_interviews SET status = 'cancelled' WHERE id = :i")->execute(['i' => $row['id']]);
            if ($why !== 'replaced') {
                Careers::event($pdo, $appId, null, 'interview', null, 'cancelled', json_encode(['when' => $row['scheduled_at'], 'id' => (int)$row['id'], 'why' => $why]), true);
            }
        }
    }

    public static function complete(PDO $pdo, int $id, int $adminId): array
    {
        $iv = self::get($pdo, $id);
        if (!$iv) {
            return ['ok' => false, 'error' => 'not_found', 'message' => 'Entretien introuvable.'];
        }
        $pdo->prepare("UPDATE career_interviews SET status = 'completed' WHERE id = :i AND status IN ('scheduled','confirmed','reschedule_requested','declined')")->execute(['i' => $id]);
        Careers::event($pdo, (int)$iv['application_id'], $adminId, 'interview', null, 'completed', json_encode(['when' => $iv['scheduled_at'], 'id' => $id]), true);
        return ['ok' => true];
    }

    // -----------------------------------------------------------------------------------------------------------------
    // The candidate's answer (tracking page)
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @param string $action confirm | decline | reschedule
     * @return array{ok:bool,error?:string,status?:string}
     */
    public static function respond(PDO $pdo, string $token, int $interviewId, string $action, string $note = ''): array
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $token) || !in_array($action, ['confirm', 'decline', 'reschedule'], true)) {
            return ['ok' => false, 'error' => 'invalid'];
        }
        $st = $pdo->prepare('SELECT * FROM career_applications WHERE track_token = :t');
        $st->execute(['t' => $token]);
        $app = $st->fetch(PDO::FETCH_ASSOC);
        $iv = $app ? self::get($pdo, $interviewId) : null;
        if (!$app || !$iv || (int)$iv['application_id'] !== (int)$app['id']) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if (!in_array($iv['status'], self::OPEN, true) || strtotime((string)$iv['scheduled_at']) < time()) {
            return ['ok' => false, 'error' => 'closed'];
        }
        $note = Careers::line($note, 500);
        if ($action === 'reschedule' && mb_strlen($note) < 3) {
            return ['ok' => false, 'error' => 'note_required'];
        }
        $new = ['confirm' => 'confirmed', 'decline' => 'declined', 'reschedule' => 'reschedule_requested'][$action];
        $pdo->prepare('UPDATE career_interviews SET status = :s, candidate_note = :n, responded_at = :now WHERE id = :i')
            ->execute(['s' => $new, 'n' => $note !== '' ? $note : null, 'i' => $interviewId, 'now' => date('Y-m-d H:i:s')]);
        Careers::event($pdo, (int)$app['id'], null, 'candidate', null, $new, json_encode(['id' => $interviewId, 'note' => $note]), true);
        return ['ok' => true, 'status' => $new, 'app' => $app, 'interview' => self::get($pdo, $interviewId)];
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Calendar
    // -----------------------------------------------------------------------------------------------------------------

    public static function endsAt(array $iv): int
    {
        return strtotime((string)$iv['scheduled_at']) + (int)$iv['duration_min'] * 60;
    }

    /** An iCalendar file (RFC 5545) the candidate adds to any calendar. Times are written in UTC. */
    public static function ics(array $iv, array $app, string $lang = 'fr'): string
    {
        $en = $lang === 'en';
        $utc = static fn(int $ts): string => gmdate('Ymd\THis\Z', $ts);
        $esc = static fn(string $s): string => str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $s);
        $role = (string)($app['job_title'] ?: ($en ? 'Open application' : 'Candidature spontanée'));
        $desc = ($en ? 'Interview with StudyVibe for: ' : 'Entretien avec StudyVibe pour : ') . $role;
        if (!empty($iv['interviewer'])) {
            $desc .= "\n" . ($en ? 'With: ' : 'Avec : ') . $iv['interviewer'];
        }
        if (!empty($iv['instructions'])) {
            $desc .= "\n\n" . $iv['instructions'];
        }
        $desc .= "\n\n" . ($en ? 'Your application page: ' : 'Votre page de suivi : ') . Careers::trackUrl((string)$app['track_token']);
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//StudyVibe//Careers//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
            'UID:interview-' . $iv['id'] . '-' . substr((string)$app['track_token'], 0, 12) . '@studyvibe',
            'DTSTAMP:' . $utc(time()), 'DTSTART:' . $utc(strtotime((string)$iv['scheduled_at'])), 'DTEND:' . $utc(self::endsAt($iv)),
            'SUMMARY:' . $esc(($en ? 'Interview — ' : 'Entretien — ') . $role . ' · StudyVibe'),
            'DESCRIPTION:' . $esc($desc),
        ];
        if (!empty($iv['location'])) {
            $lines[] = 'LOCATION:' . $esc((string)$iv['location']);
        }
        $lines[] = 'STATUS:' . ($iv['status'] === 'cancelled' ? 'CANCELLED' : 'CONFIRMED');
        array_push($lines, 'BEGIN:VALARM', 'TRIGGER:-PT30M', 'ACTION:DISPLAY', 'DESCRIPTION:' . $esc($en ? 'Interview in 30 minutes' : 'Entretien dans 30 minutes'), 'END:VALARM', 'END:VEVENT', 'END:VCALENDAR');
        // Lines longer than 75 octets are folded
        $out = '';
        foreach ($lines as $l) {
            while (strlen($l) > 74) {
                $cut = 74;
                while ($cut > 0 && (ord($l[$cut]) & 0xC0) === 0x80) {
                    $cut--;      // never cut inside a multi-byte character
                }
                $out .= substr($l, 0, $cut) . "\r\n ";
                $l = substr($l, $cut);
            }
            $out .= $l . "\r\n";
        }
        return $out;
    }

    public static function googleLink(array $iv, array $app, string $lang = 'fr'): string
    {
        $en = $lang === 'en';
        $role = (string)($app['job_title'] ?: ($en ? 'Open application' : 'Candidature spontanée'));
        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action' => 'TEMPLATE', 'text' => ($en ? 'Interview — ' : 'Entretien — ') . $role . ' · StudyVibe',
            'dates' => gmdate('Ymd\THis\Z', strtotime((string)$iv['scheduled_at'])) . '/' . gmdate('Ymd\THis\Z', self::endsAt($iv)),
            'details' => ($iv['instructions'] ?? '') . "\n" . Careers::trackUrl((string)$app['track_token']), 'location' => (string)($iv['location'] ?? ''),
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
