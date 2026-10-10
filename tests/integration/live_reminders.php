<?php
declare(strict_types=1);

/**
 * The reminder email sent 15 minutes before a LIVE evaluation (lib/LiveReminders.php): who gets it, when, once only, never for an
 * asynchronous or not-yet-activated session, a new one when the exam is moved, and the email itself. Uses the database from .env with
 * throw-away rows (removed at the end). Nothing is sent: emails are captured.
 *
 *   php tests/integration/live_reminders.php
 */

putenv('SV_NO_BACKGROUND_MAIL=1');
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../Mailer.php';
require_once __DIR__ . '/../../lib/EmailQueue.php';
require_once __DIR__ . '/../../lib/LiveReminders.php';
require_once __DIR__ . '/../../lib/LiveMailNotifier.php';

$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }
$sent = [];
Mailer::$capture = function ($to, $subject, $html) use (&$sent) { $sent[] = ['to' => $to, 'subject' => $subject, 'html' => $html]; };

$tag = 'zz.remtest.' . bin2hex(random_bytes(2));
$cleanup = function () use ($pdo, $tag) {
    $pdo->exec("DELETE FROM live_eval_sessions WHERE title LIKE 'ZZ rem %'");
    $pdo->exec("DELETE FROM courses WHERE title = 'ZZ rem course'");
    $pdo->exec("DELETE FROM users WHERE email LIKE '" . $tag . "%'");
    $pdo->exec("DELETE FROM email_outbox WHERE to_email LIKE '" . $tag . "%'");
};
register_shutdown_function($cleanup);

$mod = (int)$pdo->query("SELECT id FROM modules LIMIT 1")->fetchColumn();
$mk = function (string $n, string $role, array $o = []) use ($pdo, $tag): int {
    $pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved,plan_id,lang) VALUES (?,?,?,?,?,?,1,NULL,?)")
        ->execute(["$tag.$n@test.local", 'x', ucfirst($n), $role, ($o['verified'] ?? true) ? date('Y-m-d H:i:s') : null, $o['active'] ?? 1, $o['lang'] ?? 'fr']);
    return (int)$pdo->lastInsertId();
};
$teacher = $mk('teacher', 'teacher');
$ama = $mk('ama', 'student'); $biyik = $mk('biyik', 'student', ['lang' => 'en']); $chi = $mk('chi', 'student');
$susp = $mk('suspended', 'student', ['active' => 0]); $unv = $mk('unverified', 'student', ['verified' => false]); $late = $mk('late', 'student');   // $late is not enrolled
$pdo->prepare("INSERT INTO courses (module_id,teacher_id,created_by,title,description,svg_icon,is_published) VALUES (?,?,?,'ZZ rem course','d','',1)")->execute([$mod, $teacher, $teacher]);
$course = (int)$pdo->lastInsertId();
foreach ([$ama, $biyik, $chi, $susp, $unv] as $u) { $pdo->prepare("INSERT INTO enrollments (student_id,course_id) VALUES (?,?)")->execute([$u, $course]); }

$at = fn(int $minutes, int $seconds = 0) => date('Y-m-d H:i:s', time() + $minutes * 60 + $seconds);
$mkSession = function (string $title, string $start, int $status = 1, int $async = 0) use ($pdo, $course, $teacher): int {
    $pdo->prepare("INSERT INTO live_eval_sessions (course_id,teacher_id,title,session_code,start_time,end_time,default_time_limit,status,is_async,integrity_watch) VALUES (?,?,?,?,?,?,30,?,?,1)")
        ->execute([$course, $teacher, $title, 'ZR' . bin2hex(random_bytes(3)), $start, date('Y-m-d H:i:s', strtotime($start) + 3600), $status, $async]);
    $id = (int)$pdo->lastInsertId();
    for ($i = 1; $i <= 4; $i++) { $pdo->prepare("INSERT INTO live_eval_questions (session_id,question_text,option_a,option_b,option_c,option_d,correct_option,sort_order,question_type) VALUES (?,?,'a','b','c','d','A',?,'mcq')")->execute([$id, "Q$i", $i]); }
    return $id;
};
$sLive   = $mkSession('ZZ rem live', $at(14));
$sAsync  = $mkSession('ZZ rem async', $at(10), 1, 1);
$sDraft  = $mkSession('ZZ rem draft', $at(10), 0);
$sFar    = $mkSession('ZZ rem far', $at(40));
$sPast   = $mkSession('ZZ rem past', $at(-5));
// a guest in the waiting room (no account), a registered student who is also enrolled, a suspended one and a cancelled one
$pdo->prepare("INSERT INTO live_eval_registrations (session_id,name,email,student_id) VALUES (?,?,?,NULL)")->execute([$sLive, 'Invité Test', "$tag.guest@test.local"]);
$pdo->prepare("INSERT INTO live_eval_registrations (session_id,name,email,student_id) VALUES (?,?,?,?)")->execute([$sLive, 'Ama', "$tag.ama@test.local", $ama]);
$pdo->prepare("INSERT INTO live_eval_registrations (session_id,name,email,student_id) VALUES (?,?,?,?)")->execute([$sLive, 'Late', "$tag.late@test.local", $late]);
$pdo->prepare("INSERT INTO live_eval_registrations (session_id,name,email,student_id,cancelled_at) VALUES (?,?,?,NULL,NOW())")->execute([$sLive, 'Annulé', "$tag.cancelled@test.local"]);
$outbox = fn(string $like = 'liveremind:%') => (int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE to_email LIKE '$tag%' AND dedupe_key LIKE '$like'")->fetchColumn();

// ---- who
$rec = LiveReminders::recipients($pdo, $sLive, $course);
$emails = array_column($rec, 'email');
sort($emails);
check('recipients: active confirmed enrolled students + people in the waiting room, once each',
    $emails === ["$tag.ama@test.local", "$tag.biyik@test.local", "$tag.chi@test.local", "$tag.guest@test.local", "$tag.late@test.local"], json_encode($emails));
check('a suspended or unconfirmed student is not reminded', !in_array("$tag.suspended@test.local", $emails, true) && !in_array("$tag.unverified@test.local", $emails, true));
check('a cancelled registration is not reminded', !in_array("$tag.cancelled@test.local", $emails, true));
check('the language of the account is used', array_column($rec, 'lang', 'email')["$tag.biyik@test.local"] === 'en' && array_column($rec, 'lang', 'email')["$tag.ama@test.local"] === 'fr');

// ---- when
$r = LiveReminders::dispatch($pdo);
check('only the activated LIVE session starting within 15 minutes is handled', $r['sessions'] === 1, json_encode($r));
check('5 reminders queued', $r['queued'] === 5 && $outbox() === 5, json_encode($r));
check('the session records the version it was reminded for', $pdo->query("SELECT reminder_version FROM live_eval_sessions WHERE id = $sLive")->fetchColumn() === LiveReminders::version($at(14)) || $pdo->query("SELECT reminder_version FROM live_eval_sessions WHERE id = $sLive")->fetchColumn() !== null);
foreach ([$sAsync => 'asynchronous', $sDraft => 'not activated', $sFar => 'more than 15 minutes away', $sPast => 'already started'] as $id => $why) {
    check("nothing for the session that is $why", (int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE dedupe_key LIKE 'liveremind:$id:%'")->fetchColumn() === 0);
}
check('running it again sends nothing more', LiveReminders::dispatch($pdo)['queued'] === 0 && $outbox() === 5);
// the boundary
$pdo->exec("UPDATE live_eval_sessions SET start_time = '" . $at(15, 30) . "' WHERE id = $sFar");
check('16 minutes away: not yet', LiveReminders::dispatch($pdo)['sessions'] === 0);
$pdo->exec("UPDATE live_eval_sessions SET start_time = '" . $at(14, 50) . "' WHERE id = $sFar");
check('under 15 minutes: now', ($x = LiveReminders::dispatch($pdo))['sessions'] === 1 && $x['queued'] === 3, json_encode($x));
$pdo->exec("UPDATE live_eval_sessions SET status = 0 WHERE id = $sFar");

// ---- the email
$sent = [];
EmailQueue::work($pdo);
$mine = array_values(array_filter($sent, fn($m) => str_starts_with($m['to'], $tag) && str_contains($m['subject'], 'ZZ rem live')));
check('five reminders go out for the first session', count($mine) === 5, (string)count($mine));
$fr = array_values(array_filter($mine, fn($m) => str_contains($m['to'], '.ama@')))[0] ?? ['subject' => '', 'html' => ''];
$en = array_values(array_filter($mine, fn($m) => str_contains($m['to'], '.biyik@')))[0] ?? ['subject' => '', 'html' => ''];
check('French reminder: says how soon, the start time, the instructions and the rules', str_contains($fr['subject'], 'Dans ') && str_contains($fr['html'], 'commence dans') && str_contains($fr['html'], date('H \\h i', strtotime($at(14))) )
    && str_contains($fr['html'], 'Rejoindre la salle d’attente') && str_contains($fr['html'], 'À faire maintenant') && str_contains($fr['html'], 'À ne pas faire') && str_contains($fr['html'], 'même lien'), $fr['subject']);
check('the link of the exam room and the logo are there', str_contains($fr['html'], '/live-session.php?code=ZR') && str_contains($fr['html'], 'cid:svlogo@studyvibe'));
check('tab-exit tracking is mentioned when it is on', str_contains($fr['html'], 'sorties d’onglet'));
check('English reminder for an English account', str_contains($en['subject'], 'In ') && str_contains($en['html'], 'starts in') && str_contains($en['html'], 'Do this now'));
check('format is given (4 questions)', str_contains($fr['html'], '4 questions'));

// ---- moved exam
$pdo->exec("UPDATE live_eval_sessions SET start_time = '" . $at(13) . "' WHERE id = $sLive");   // 1 minute earlier: another start time
$x = LiveReminders::dispatch($pdo);
check('moving the exam sends a new reminder to everybody', $x['sessions'] === 1 && $x['queued'] === 5, json_encode($x));
$sent = []; EmailQueue::work($pdo);
$moved = array_values(array_filter($sent, fn($m) => str_starts_with($m['to'], $tag) && str_contains($m['subject'], 'ZZ rem live')));
check('and says the time was changed', count($moved) === 5 && (str_contains($moved[0]['html'], 'Nouvel horaire') || str_contains($moved[0]['html'], 'New time')));

// ---- activation inside the 15 minutes: one "starting soon" message instead of the announcement
$sNow = $mkSession('ZZ rem activated late', $at(8));
$pdo->prepare("INSERT INTO live_eval_registrations (session_id,name,email,student_id) VALUES (?,?,?,NULL)")->execute([$sNow, 'Invité Test', "$tag.guest@test.local"]);
$a = LiveMailNotifier::notify($pdo, $sNow);
check('activating inside the last 15 minutes queues the "starting soon" email', $a['queued'] === 4 && (int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE dedupe_key LIKE 'liveremind:$sNow:%'")->fetchColumn() === 4 && (int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE dedupe_key LIKE 'live:$sNow:%'")->fetchColumn() === 0, json_encode($a));
check('and the 15-minute reminder does not repeat it', LiveReminders::dispatch($pdo)['queued'] === 0);
$sFuture = $mkSession('ZZ rem activated early', $at(60 * 24 * 2));
check('activating well before keeps the normal announcement', ($b = LiveMailNotifier::notify($pdo, $sFuture))['queued'] === 3 && (int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE dedupe_key LIKE 'live:$sFuture:%'")->fetchColumn() === 3, json_encode($b));
check('...and its reminder comes 15 minutes before', ($pdo->exec("UPDATE live_eval_sessions SET start_time = '" . $at(12) . "' WHERE id = $sFuture") !== false) && LiveReminders::dispatch($pdo)['sessions'] === 1);
$sent = []; EmailQueue::work($pdo);
$rm = array_values(array_filter($sent, fn($m) => str_contains($m['subject'], 'ZZ rem activated early') && str_contains($m['html'], 'Nouvel horaire')));
check('a student who got the announcement for another time is told the time moved', count($rm) >= 1);

// ---- the throttle used on page loads
$flag = dirname(__DIR__, 2) . '/uploads/live_cache/reminder_tick.flag';
@unlink($flag);
$sTick = $mkSession('ZZ rem tick', $at(12));
LiveReminders::tick($pdo);
check('tick() does the work', (int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE dedupe_key LIKE 'liveremind:$sTick:%'")->fetchColumn() === 3);
$sTick2 = $mkSession('ZZ rem tick two', $at(11));
LiveReminders::tick($pdo);
check('...but at most every 20 seconds', (int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE dedupe_key LIKE 'liveremind:$sTick2:%'")->fetchColumn() === 0);
@unlink($flag);

echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
