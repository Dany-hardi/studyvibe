<?php
declare(strict_types=1);

/**
 * The ATS around the pipeline: screening stored on applications (and an administrator's choice never undone), buckets, bulk actions,
 * interviews (schedule, move, cancel, the candidate's answers, calendar file), the e-mails (interview design is its own), and every task
 * of the 24/7 worker: screening, expiry, reminders (day before, two hours before, chase), automatic decline, retention, closing alert, digest.
 * Everything created here is deleted at the end.
 *
 *   php tests/integration/careers_ats.php
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../Mailer.php';
require_once __DIR__ . '/../../lib/CareersWorker.php';

$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what; }
$tag = 'zz-ats-' . bin2hex(random_bytes(3));
$mail = [];
Mailer::$capture = function ($to, $subject, $html) use (&$mail) { $mail[] = [$to, $subject, $html]; };
$pdo->prepare("INSERT INTO users (email, password, name, role, email_verified_at, is_active, is_approved, lang) VALUES (?, ?, 'ATS Test', 'promoter', NOW(), 1, 1, 'en')")->execute([$tag . '@studyvibe-test.local', password_hash('x', PASSWORD_DEFAULT)]);
$admin = (int)$pdo->lastInsertId();
$jobs = []; $apps = [];
$resetState = function () use ($pdo) { $pdo->exec("DELETE FROM career_state WHERE name LIKE 'try:%' OR name LIKE 'closing:%' OR name = 'digest_date'"); };

function mkApp(PDO $pdo, ?int $jobId, string $name, string $text, array $o = []): int
{
    global $tag, $apps;
    $ref = 'CA-' . strtoupper(substr(md5($name . $tag . microtime()), 0, 6));
    $pdo->prepare("INSERT INTO career_applications (reference, job_id, job_title, full_name, email, phone, location, years_experience, education_level, skills, current_title, cover_letter, consent_at, lang, track_token, talent_consent, created_at)
        VALUES (?, ?, ?, ?, ?, '+237600000000', ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)")
        ->execute([$ref, $jobId, $o['title'] ?? '', $name, strtolower(str_replace(' ', '.', $name)) . ".$tag@studyvibe-test.local", $o['loc'] ?? 'Douala', $o['years'] ?? null, $o['edu'] ?? '', $o['skills'] ?? '', $o['ctitle'] ?? '', $o['cover'] ?? '',
                   $o['lang'] ?? 'en', bin2hex(random_bytes(20)), $o['talent'] ?? 0, $o['created'] ?? date('Y-m-d H:i:s')]);
    $id = (int)$pdo->lastInsertId();
    $apps[] = $id;
    $pdo->prepare("REPLACE INTO career_application_texts (application_id, resume_text, chars, via) VALUES (?, ?, ?, 'pdf')")->execute([$id, $text, mb_strlen($text)]);
    return $id;
}
function app(PDO $pdo, int $id): array { $s = $pdo->prepare('SELECT * FROM career_applications WHERE id = ?'); $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC); }

try {
    $j = Careers::saveJob($pdo, $admin, null, ['department' => 'engineering', 'title' => "Backend Dev $tag", 'status' => 'open', 'summary' => 'Build and run the platform APIs for thousands of students.', 'description' => 'x', 'location' => 'Douala, Cameroon', 'work_mode' => 'hybrid',
        'must_have' => "php\nmysql", 'nice_have' => 'docker', 'min_years' => 3, 'min_education' => 'bachelor', 'screen_threshold' => 35, 'auto_decline_days' => 2]);
    $jobId = (int)$j['id']; $jobs[] = $jobId;
    $good = "Backend developer with 6 years of experience in PHP and MySQL, REST APIs and Docker. Bachelor degree in computer science. " . str_repeat('Built reliable web platforms. ', 8);
    $bad = "Hairdresser with five years in a salon in Douala. Cuts, colour and styling for all hair types. Friendly, punctual, loves working with people. " . str_repeat('Customer care is my priority. ', 6);

    // ---------- screening on stored applications
    $a1 = mkApp($pdo, $jobId, 'Good Candidate', $good, ['years' => 6, 'edu' => 'bachelor', 'skills' => 'PHP, MySQL', 'title' => "Backend Dev $tag", 'ctitle' => 'Backend developer']);
    $a2 = mkApp($pdo, $jobId, 'Bad Candidate', $bad, ['years' => 5, 'edu' => 'highschool', 'skills' => 'Hair', 'title' => "Backend Dev $tag", 'ctitle' => 'Hairdresser']);
    $r1 = CareersScreening::screenApplication($pdo, $a1); $r2 = CareersScreening::screenApplication($pdo, $a2);
    check('screening stores score, verdict and report on the application', $r1['verdict'] === 'strong' && app($pdo, $a1)['screen_state'] === 'done' && json_decode((string)app($pdo, $a1)['screen_report'], true)['score'] === $r1['score']);
    check('a clearly unrelated application goes to the screened_out bucket', $r2['bucket'] === 'screened_out' && app($pdo, $a2)['bucket'] === 'screened_out');
    check('and the trail says so (written by the engine, not a person)', (int)$pdo->query("SELECT COUNT(*) FROM career_application_events WHERE application_id = $a2 AND kind = 'screen' AND actor_id IS NULL")->fetchColumn() === 1);
    check('the held-back list returns it with its missing keywords', in_array('php', json_decode((string)(Careers::applications($pdo, ['bucket' => 'screened_out', 'q' => $tag])[0]['rep_missing'] ?? '[]'), true), true));
    check('the inbox list does not show it', !in_array($a2, array_column(Careers::applications($pdo, ['q' => $tag]), 'id'), true));

    // an administrator's choice stands against a re-run
    Careers::moveBucket($pdo, $a2, $admin, 'inbox');
    $pdo->prepare('UPDATE career_jobs SET screen_version = screen_version + 1 WHERE id = ?')->execute([$jobId]);
    $out = CareersWorker::run($pdo, 20);
    check('the worker re-reads applications when the posting criteria change', $out['screened'] >= 2 && (int)app($pdo, $a1)['screen_version'] === CareersScreening::stamp(Careers::job($pdo, $jobId)));
    check('a bucket chosen by an administrator is not undone by the re-run', app($pdo, $a2)['bucket'] === 'inbox');
    $a3 = mkApp($pdo, $jobId, 'Third Candidate', $bad, ['years' => 1, 'title' => "Backend Dev $tag"]);
    $out = CareersWorker::run($pdo, 20);
    check('the worker screens an application nobody has screened yet', app($pdo, $a3)['screen_state'] === 'done' && app($pdo, $a3)['bucket'] === 'screened_out');
    $a4 = mkApp($pdo, $jobId, 'Scanned Candidate', '', []);                 // no text at all: a scanned PDF
    CareersScreening::screenApplication($pdo, $a4);
    check('an unreadable résumé is never held back: a person must read it', app($pdo, $a4)['bucket'] === 'inbox' && app($pdo, $a4)['screen_verdict'] === 'review');

    // ---------- buckets, bulk
    check('bulk archive moves applications and stamps archived_at', Careers::bulk($pdo, [$a3], $admin, 'archive')['done'] === 1 && app($pdo, $a3)['bucket'] === 'archived' && app($pdo, $a3)['archived_at'] !== null);
    check('bulk restore puts them back in the inbox', Careers::bulk($pdo, [$a3], $admin, 'restore')['done'] === 1 && app($pdo, $a3)['bucket'] === 'inbox');
    check('bulk refuses an unknown action and an empty selection', !Careers::bulk($pdo, [$a3], $admin, 'explode')['ok'] && !Careers::bulk($pdo, [], $admin, 'archive')['ok']);
    $st = Careers::setStatus($pdo, $a3, $admin, 'reviewing');
    check('working on a held-back application takes it out of the screening section', $st['ok']);
    $a5 = mkApp($pdo, $jobId, 'Fifth Candidate', $bad, ['years' => 1]); CareersScreening::screenApplication($pdo, $a5);
    Careers::setStatus($pdo, $a5, $admin, 'reviewing');
    check('a status change from the screening section moves it to the pipeline', app($pdo, $a5)['bucket'] === 'inbox');
    check('bulk delete erases applications and their text', Careers::bulk($pdo, [$a5], $admin, 'delete')['done'] === 1 && !Careers::exists($pdo, $a5) && !$pdo->query("SELECT 1 FROM career_application_texts WHERE application_id = $a5")->fetchColumn());

    // ---------- interviews
    $when = date('Y-m-d H:i', time() + 3 * 86400);
    $bad1 = CareersInterviews::save($pdo, $a1, $admin, ['scheduled_at' => date('Y-m-d H:i', time() - 3600), 'mode' => 'video', 'duration_min' => 45]);
    check('an interview in the past is refused', !$bad1['ok'] && $bad1['fields']['scheduled_at'] === 'past');
    $bad2 = CareersInterviews::save($pdo, $a1, $admin, ['scheduled_at' => $when, 'mode' => 'onsite', 'duration_min' => 45, 'location' => '']);
    check('an on-site interview needs an address', !$bad2['ok'] && $bad2['fields']['location'] === 'required');
    $mail = [];
    $iv = CareersInterviews::save($pdo, $a1, $admin, ['scheduled_at' => $when, 'mode' => 'video', 'duration_min' => 45, 'location' => 'meet.example.org/abc', 'interviewer' => 'Dr. Tester', 'instructions' => "Bring your laptop.\nBe on time."]);
    check('an interview is scheduled, the application moves to "interview" and the candidate is invited', $iv['ok'] && $iv['mailed'] && app($pdo, $a1)['status'] === 'interview' && count($mail) === 1);
    $m = $mail[0]; $html = (string)$m[2];
    check('the invitation is addressed in the candidate\'s language with the role in the subject', $m[1] === "Interview invitation: Backend Dev $tag");
    check('the interview e-mail has its own design (night banner, calendar tile) and shares nothing with the standard theme', str_contains($html, '#0B1437') && str_contains($html, 'INTERVIEW INVITATION') && !str_contains($html, 'sv-btn') && !str_contains($html, '#B5482A'));
    check('it carries the date tile, the time range, the details, the instructions and the calendar links', str_contains($html, strtoupper(date('M', strtotime($when)))) && str_contains($html, date('H:i', strtotime($when))) && str_contains($html, 'Dr. Tester') && str_contains($html, 'Bring your laptop') && str_contains($html, 'calendar.google.com') && str_contains($html, 'ics='));
    check('its button leads to the tracking page, never to an action', str_contains($html, 'track.php?t=' . app($pdo, $a1)['track_token']) && !str_contains($html, 'action=confirm'));
    $ivRow = CareersInterviews::get($pdo, (int)$iv['id']);
    $ics = CareersInterviews::ics($ivRow, Careers::application($pdo, $a1), 'en');
    check('the calendar file is valid iCalendar with the right UTC time and a 30-minute alarm', str_contains($ics, 'BEGIN:VCALENDAR') && str_contains($ics, 'DTSTART:' . gmdate('Ymd\THis\Z', strtotime($ivRow['scheduled_at']))) && str_contains($ics, 'TRIGGER:-PT30M') && str_contains($ics, "\r\n") && str_contains($ics, 'END:VEVENT'));
    foreach (explode("\r\n", $ics) as $line) { if (strlen($line) > 75) { check('ICS lines are folded at 75 octets', false); break; } }
    $mail = [];
    $mv = CareersInterviews::save($pdo, $a1, $admin, ['id' => $iv['id'], 'scheduled_at' => date('Y-m-d H:i', time() + 4 * 86400), 'mode' => 'video', 'duration_min' => 60, 'location' => 'https://meet.example.org/abc']);
    check('moving an interview tells the candidate, with the old time struck through', $mv['ok'] && $mv['rescheduled'] && str_contains((string)$mail[0][2], 'line-through') && str_contains((string)$mail[0][2], 'NEW TIME'));
    $tok = (string)app($pdo, $a1)['track_token'];
    check('the candidate\'s answer needs a valid token and a real answer', !CareersInterviews::respond($pdo, 'nope', (int)$iv['id'], 'confirm')['ok'] && !CareersInterviews::respond($pdo, $tok, (int)$iv['id'], 'maybe')['ok']);
    check('asking for another time requires a message', CareersInterviews::respond($pdo, $tok, (int)$iv['id'], 'reschedule', '')['error'] === 'note_required');
    $c = CareersInterviews::respond($pdo, $tok, (int)$iv['id'], 'confirm');
    check('the candidate confirms: status, trail (visible to them) and answer time are recorded', $c['ok'] && CareersInterviews::get($pdo, (int)$iv['id'])['status'] === 'confirmed' && (int)$pdo->query("SELECT COUNT(*) FROM career_application_events WHERE application_id = $a1 AND kind = 'candidate' AND visible = 1")->fetchColumn() === 1);
    $other = mkApp($pdo, $jobId, 'Other Candidate', $good);
    check('a candidate cannot answer another candidate\'s interview', CareersInterviews::respond($pdo, (string)app($pdo, $other)['track_token'], (int)$iv['id'], 'decline')['error'] === 'not_found');
    $mail = [];
    CareersMail::candidateActed($pdo, app($pdo, $a1), 'confirmed', '', CareersInterviews::get($pdo, (int)$iv['id']));
    check('administrators are told when a candidate answers', count($mail) >= 1 && str_contains($mail[0][1], 'Entretien confirmé'));
    $trail = Careers::publicTrail($pdo, $a1);
    check('the candidate\'s trail shows only visible events (stage moves, interview, their answer), never notes, scores or e-mails', count($trail) >= 3 && !in_array('screen', array_column($trail, 'kind'), true) && !in_array('note', array_column($trail, 'kind'), true));
    Careers::addNote($pdo, $a1, $admin, 'Internal opinion');
    check('an internal note stays invisible to the candidate', !str_contains(json_encode(Careers::publicTrail($pdo, $a1)), 'Internal opinion'));

    // ---------- reminders (worker)
    $resetState();
    $pdo->prepare("UPDATE career_interviews SET scheduled_at = ?, invited_at = ?, status = 'confirmed', reminder24_at = NULL, reminder2_at = NULL, chase_at = NULL WHERE id = ?")->execute([date('Y-m-d H:i:s', time() + 20 * 3600), date('Y-m-d H:i:s', time() - 3 * 86400), $iv['id']]);
    $mail = []; $out = CareersWorker::run($pdo, 5);
    $rem = array_values(array_filter($mail, fn($m) => str_contains($m[1], 'Tomorrow')));
    check('the day-before reminder goes out once, in the interview design', $out['reminded'] === 1 && count($rem) === 1 && str_contains((string)$rem[0][2], 'TOMORROW'));
    $mail = []; CareersWorker::run($pdo, 5);
    check('and is not sent twice', count(array_filter($mail, fn($m) => str_contains($m[1], 'Tomorrow'))) === 0);
    $pdo->prepare("UPDATE career_interviews SET scheduled_at = ? WHERE id = ?")->execute([date('Y-m-d H:i:s', time() + 100 * 60), $iv['id']]);
    $mail = []; CareersWorker::run($pdo, 5);
    $rem = array_values(array_filter($mail, fn($m) => str_contains($m[1], 'In 2 hours')));
    check('the two-hours-before reminder goes out when the time comes', count($rem) === 1 && str_contains((string)$rem[0][2], '#FFB020'));
    $pdo->prepare("UPDATE career_interviews SET scheduled_at = ?, status = 'scheduled', reminder24_at = NULL, reminder2_at = NULL, chase_at = NULL, invited_at = ? WHERE id = ?")->execute([date('Y-m-d H:i:s', time() + 5 * 86400), date('Y-m-d H:i:s', time() - 3 * 86400), $iv['id']]);
    $mail = []; CareersWorker::run($pdo, 5);
    $rem = array_values(array_filter($mail, fn($m) => str_contains($m[1], 'Please confirm')));
    check('an invitation unanswered for 48 hours gets one nudge', count($rem) === 1 && CareersInterviews::get($pdo, (int)$iv['id'])['chase_at'] !== null);
    $pdo->prepare("UPDATE career_interviews SET scheduled_at = ?, status = 'confirmed', reminder24_at = NULL, reminder2_at = NULL WHERE id = ?")->execute([date('Y-m-d H:i:s', time() + 20 * 3600), $iv['id']]);
    Careers::setStatus($pdo, $a1, $admin, 'rejected');
    $mail = []; CareersWorker::run($pdo, 5);
    check('a candidate who left the process gets no reminder and the interview is closed', count(array_filter($mail, fn($m) => str_contains($m[1], 'Tomorrow') || str_contains($m[1], 'In 2 hours'))) === 0 && CareersInterviews::get($pdo, (int)$iv['id'])['status'] === 'cancelled');
    $iv2 = CareersInterviews::save($pdo, $a4, $admin, ['scheduled_at' => date('Y-m-d H:i', time() + 86400), 'mode' => 'phone', 'duration_min' => 30], false);
    $pdo->prepare("UPDATE career_interviews SET scheduled_at = ? WHERE id = ?")->execute([date('Y-m-d H:i:s', time() - 7200), $iv2['id']]);
    CareersWorker::run($pdo, 5);
    check('an interview whose time has passed is marked completed', CareersInterviews::get($pdo, (int)$iv2['id'])['status'] === 'completed');
    $iv3 = CareersInterviews::save($pdo, $a3, $admin, ['scheduled_at' => date('Y-m-d H:i', time() + 86400 * 2), 'mode' => 'phone', 'duration_min' => 30]);
    $mail = [];
    $cx = CareersInterviews::cancel($pdo, (int)$iv3['id'], $admin, true);
    check('cancelling tells the candidate in the muted design', $cx['ok'] && $cx['mailed'] && str_contains((string)$mail[0][2], 'INTERVIEW CANCELLED') && CareersInterviews::get($pdo, (int)$iv3['id'])['status'] === 'cancelled');

    // ---------- automatic decline, expiry, closing alert, retention, digest
    $a6 = mkApp($pdo, $jobId, 'Held Long Ago', $bad, ['years' => 0, 'lang' => 'fr']); CareersScreening::screenApplication($pdo, $a6);
    $pdo->prepare("UPDATE career_applications SET bucket_changed_at = NOW() - INTERVAL 3 DAY WHERE id = ?")->execute([$a6]);
    $mail = []; $out = CareersWorker::run($pdo, 5);
    $x = app($pdo, $a6);
    check('a held-back application nobody reviewed is declined politely after the posting\'s delay and archived', $out['declined'] >= 1 && $x['status'] === 'rejected' && $x['bucket'] === 'archived');
    $dm = array_values(array_filter($mail, fn($m) => str_contains((string)$m[0], 'held.long.ago')));
    check('the candidate gets the polite message in their language (French here)', count($dm) === 1 && str_contains((string)$dm[0][2], 'Merci du temps passé'));
    $a7 = mkApp($pdo, $jobId, 'Looked At', $bad, ['years' => 0]); CareersScreening::screenApplication($pdo, $a7);
    Careers::addNote($pdo, $a7, $admin, 'Keep'); $pdo->prepare("UPDATE career_applications SET bucket_changed_at = NOW() - INTERVAL 9 DAY WHERE id = ?")->execute([$a7]);
    CareersWorker::run($pdo, 5);
    check('an application an administrator already worked on is not declined automatically', app($pdo, $a7)['status'] === 'new' && app($pdo, $a7)['bucket'] === 'screened_out');

    $jx = Careers::saveJob($pdo, $admin, null, ['department' => 'engineering', 'title' => "Closing $tag", 'status' => 'open', 'summary' => 'Closes soon so the worker must warn the administrators.', 'description' => 'x', 'closes_at' => date('Y-m-d', strtotime('+2 day'))]);
    $jobs[] = (int)$jx['id']; $resetState(); $mail = [];
    $out = CareersWorker::run($pdo, 5);
    check('a posting closing within 3 days is announced to the administrators once', $out['alerts'] >= 1 && count(array_filter($mail, fn($m) => str_contains($m[1], 'Offre bientôt terminée'))) >= 1);
    $mail = []; CareersWorker::run($pdo, 5);
    check('and not announced again', count(array_filter($mail, fn($m) => str_contains($m[1], 'Offre bientôt terminée'))) === 0);
    $pdo->prepare("UPDATE career_jobs SET closes_at = ? WHERE id = ?")->execute([date('Y-m-d', strtotime('-1 day')), $jx['id']]);
    $out = CareersWorker::run($pdo, 5);
    check('a posting past its closing date is closed by the worker', $out['expired'] >= 1 && Careers::job($pdo, (int)$jx['id'])['status'] === 'closed');

    $old = mkApp($pdo, $jobId, 'Very Old Rejected', $good); $pdo->prepare("UPDATE career_applications SET status = 'rejected', status_changed_at = NOW() - INTERVAL 13 MONTH WHERE id = ?")->execute([$old]);
    $pool = mkApp($pdo, $jobId, 'Old In Pool', $good, ['talent' => 1]); $pdo->prepare("UPDATE career_applications SET status = 'rejected', status_changed_at = NOW() - INTERVAL 13 MONTH WHERE id = ?")->execute([$pool]);
    $hired = mkApp($pdo, $jobId, 'Old Hired', $good); $pdo->prepare("UPDATE career_applications SET status = 'hired', status_changed_at = NOW() - INTERVAL 40 MONTH WHERE id = ?")->execute([$hired]);
    $out = CareersWorker::run($pdo, 5);
    check('retention: after 12 months an application of a person out of the process is erased', !Careers::exists($pdo, $old) && $out['purged'] >= 1);
    check('retention: a talent-pool consent keeps it longer, and a hired person is never purged', Careers::exists($pdo, $pool) && Careers::exists($pdo, $hired));
    check('every purge is audited', (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'career_retention_purge' AND created_at > NOW() - INTERVAL 1 MINUTE")->fetchColumn() >= 1);

    $resetState(); $mail = [];
    // force the digest's time window
    $d = new ReflectionMethod(CareersWorker::class, 'digest');
    if ((int)date('Hi') >= 730) {
        $n = $d->invoke(null, $pdo);
        $dm = array_values(array_filter($mail, fn($m) => str_contains($m[1], 'le point du jour')));
        check('the daily digest goes to the administrators when something waits, once a day', $n >= 1 && count($dm) >= 1 && $d->invoke(null, $pdo) === 0);
    } else {
        check('the daily digest waits for 07:30', $d->invoke(null, $pdo) === 0);
    }

    // ---------- the candidate's own actions
    $w = mkApp($pdo, $jobId, 'Withdrawing Person', $good);
    $mail = [];
    check('a candidate can withdraw: status, interviews closed, administrators told', Careers::withdraw($pdo, (string)app($pdo, $w)['track_token'])['ok'] && app($pdo, $w)['status'] === 'withdrawn' && count($mail) >= 1);
    check('a withdrawn application cannot be withdrawn twice', !Careers::withdraw($pdo, (string)app($pdo, $w)['track_token'])['ok']);
    check('the token must be exactly 40 hex characters', Careers::byToken($pdo, 'zz') === null && Careers::byToken($pdo, str_repeat('a', 40)) === null && Careers::byToken($pdo, (string)app($pdo, $w)['track_token']) !== null);
    check('every application has a distinct token', (int)$pdo->query("SELECT COUNT(DISTINCT track_token) = COUNT(*) FROM career_applications")->fetchColumn() === 1);
} finally {
    foreach ($jobs as $jid) { $pdo->prepare('DELETE FROM career_applications WHERE job_id = ?')->execute([$jid]); $pdo->prepare('DELETE FROM career_jobs WHERE id = ?')->execute([$jid]); }
    foreach ($apps as $id) { $pdo->prepare('DELETE FROM career_applications WHERE id = ?')->execute([$id]); }
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$admin]);
    $pdo->exec("DELETE FROM career_state WHERE name LIKE 'try:%' OR name LIKE 'closing:%' OR name = 'digest_date'");
}
echo "careers ats: $passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL: $f\n"; }
exit($failed ? 1 : 0);
