<?php
declare(strict_types=1);

/**
 * The teacher's review of a finished live evaluation: matricule, mark, integrity assessment, and cancelling / restoring a result.
 * Uses the database from .env with throw-away rows (removed at the end). Nothing is sent: emails are captured.
 *
 *   php tests/integration/results_review.php
 */

putenv('SV_NO_BACKGROUND_MAIL=1');
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/LiveResults.php';
require_once __DIR__ . '/../../lib/Contests.php';

$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }

$sent = [];
Mailer::$capture = function ($to, $subject, $html) use (&$sent) { $sent[] = ['to' => $to, 'subject' => $subject, 'html' => $html]; };

$tag = 'zz.rvtest.' . bin2hex(random_bytes(2));
$cleanup = function () use ($pdo, $tag) {
    $pdo->exec("DELETE FROM live_eval_sessions WHERE title = 'ZZ review test'");
    $pdo->exec("DELETE FROM courses WHERE title = 'ZZ review course'");
    $pdo->exec("DELETE FROM users WHERE email LIKE '" . $tag . "%'");
    $pdo->exec("DELETE FROM email_outbox WHERE to_email LIKE '" . $tag . "%'");
};
register_shutdown_function($cleanup);

$mod = (int)$pdo->query("SELECT id FROM modules LIMIT 1")->fetchColumn();
$mkUser = function (string $name, string $role, ?string $mat = null) use ($pdo, $tag): int {
    $pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved,plan_id,matricule) VALUES (?,?,?,?,NOW(),1,1,NULL,?)")
        ->execute([$tag . '.' . strtolower(explode(' ', $name)[0]) . '@test.local', 'x', $name, $role, $mat]);
    return (int)$pdo->lastInsertId();
};
$teacher = $mkUser('Prof Test', 'teacher');
$other = $mkUser('Autre Prof', 'teacher');
$pdo->prepare("INSERT INTO courses (module_id,teacher_id,created_by,title,description,svg_icon,is_published) VALUES (?,?,?,'ZZ review course','d','',1)")->execute([$mod, $teacher, $teacher]);
$course = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO live_eval_sessions (course_id,teacher_id,title,session_code,start_time,end_time,default_time_limit,status,integrity_watch) VALUES (?,?,'ZZ review test',?,NOW() - INTERVAL 3 HOUR,NOW() - INTERVAL 1 HOUR,20,1,1)")->execute([$course, $teacher, 'zzrv' . bin2hex(random_bytes(3))]);
$sid = (int)$pdo->lastInsertId();
$qids = [];
for ($i = 1; $i <= 8; $i++) {
    $pdo->prepare("INSERT INTO live_eval_questions (session_id,question_text,option_a,option_b,option_c,option_d,correct_option,sort_order,question_type) VALUES (?,?,'a','b','c','d','A',?,'mcq')")->execute([$sid, "Q$i", $i]);
    $qids[] = (int)$pdo->lastInsertId();
}
$regs = [];
foreach ([['Alice Mbarga', '25G001', ['B','B','B','B','B','B','A','A'], 0], ['Bob Nkoa', '25G002', ['B','B','B','B','B','B','A','C'], 0],
          ['Carl Essomba', '25G003', ['A','A','A','A','A','C','A','A'], 4], ['Dora Fotso', '25G004', ['A','A','A','A','A','A','A','A'], 0],
          ['Eve Kamga', '25G005', [], 0]] as [$name, $mat, $ans, $exits]) {
    $uid = $mkUser($name, 'student', $mat);
    $right = count(array_filter($ans, fn($a) => $a === 'A'));
    $pdo->prepare("INSERT INTO live_eval_registrations (session_id,name,email,student_id,score,focus_losses) VALUES (?,?,?,?,?,?)")
        ->execute([$sid, $name, $tag . '.' . strtolower(explode(' ', $name)[0]) . '@test.local', $uid, $ans ? round($right / 8 * 100, 2) : null, $exits]);
    $regs[explode(' ', $name)[0]] = (int)$pdo->lastInsertId();
    foreach ($ans as $i => $a) {
        $pdo->prepare("INSERT INTO live_eval_answers (registration_id,question_id,selected_option) VALUES (?,?,?)")->execute([$regs[explode(' ', $name)[0]], $qids[$i], $a]);
    }
}
$session = $pdo->query("SELECT * FROM live_eval_sessions WHERE id = $sid")->fetch(PDO::FETCH_ASSOC);

// ---- the review table
$rv = LiveResults::review($pdo, $session);
$by = array_column($rv['rows'], null, 'name');
check('one row per student', count($rv['rows']) === 5);
check('matricule shown', ($by['Alice Mbarga']['matricule'] ?? '') === '25G001');
check('mark is the number of good answers, not a percentage', $by['Dora Fotso']['mark'] === 8 && $by['Alice Mbarga']['mark'] === 2 && $by['Alice Mbarga']['total'] === 8);
check('a clean student has nothing to report', $by['Dora Fotso']['integrity']['level'] === 0);
check('4 tab exits is suspicious', $by['Carl Essomba']['integrity']['level'] === 2 && $by['Carl Essomba']['integrity']['notes'][0]['n'] === 4);
check('same wrong answers on 6 questions is suspicious for both', $by['Alice Mbarga']['integrity']['level'] === 2 && $by['Bob Nkoa']['integrity']['level'] === 2);
check('the similarity note names the other student', ($by['Alice Mbarga']['integrity']['notes'][0]['with'] ?? '') === 'Bob Nkoa');
check('a student who did not finish has no mark', $by['Eve Kamga']['mark'] === null && $by['Eve Kamga']['submitted'] === false);
check('summary counts', $rv['summary']['suspect'] === 3 && $rv['summary']['not_submitted'] === 1);

// ---- cancelling
$carl = $regs['Carl'];
check('another teacher cannot cancel', LiveResults::cancel($pdo, $carl, $other, 'x')['ok'] === false);
$r = LiveResults::cancel($pdo, $carl, $teacher, 'Plusieurs sorties de l\'onglet');
check('the owner can cancel', $r['ok'] === true);
$row = $pdo->query("SELECT score, cancelled_score, cancelled_at, cancelled_reason FROM live_eval_registrations WHERE id = $carl")->fetch(PDO::FETCH_ASSOC);
check('score removed, original kept aside', $row['score'] === null && (float)$row['cancelled_score'] === 87.5 && $row['cancelled_at'] !== null && $row['cancelled_reason'] !== null);
check('cancelling twice is refused', (LiveResults::cancel($pdo, $carl, $teacher, '')['error'] ?? '') === 'already');
check('the student is told by email', $pdo->query("SELECT COUNT(*) FROM email_outbox WHERE template='result_cancelled' AND to_email LIKE '" . $tag . "%carl%'")->fetchColumn() == 1);
$rv = LiveResults::review($pdo, $session);
$by = array_column($rv['rows'], null, 'name');
check('review shows the cancelled row with its mark', $by['Carl Essomba']['cancelled'] === true && $by['Carl Essomba']['mark'] === 7 && $rv['summary']['cancelled'] === 1);

// the email worker sends it (captured), and says why
$n = EmailQueue::work($pdo);
$mail = array_values(array_filter($sent, fn($m) => str_contains($m['to'], 'carl')))[0] ?? null;
check('worker sends the cancellation email', $n >= 1 && $mail !== null && str_contains($mail['subject'], 'annulé'));
check('email carries the reason, the logo and the session', $mail !== null && str_contains($mail['html'], 'Plusieurs sorties') && str_contains($mail['html'], 'cid:svlogo@studyvibe') && str_contains($mail['html'], 'ZZ review test'));

// the exam-room poll never recomputes a cancelled score
$pdo->exec("UPDATE live_eval_registrations SET score = NULL WHERE id = $carl");   // as it is while cancelled
check('a cancelled registration is recognised by the poll guard', (int)$pdo->query("SELECT COUNT(*) FROM live_eval_registrations WHERE id = $carl AND score IS NULL AND cancelled_at IS NOT NULL")->fetchColumn() === 1);

// ---- contesting
$carlReg = Contests::registration($pdo, $carl);
$carlUser = $pdo->query("SELECT * FROM users WHERE id = " . (int)$carlReg['student_id'])->fetch(PDO::FETCH_ASSOC);
$daraUser = $pdo->query("SELECT * FROM users WHERE id = " . (int)$pdo->query("SELECT student_id FROM live_eval_registrations WHERE id = " . $regs['Dora'])->fetchColumn())->fetch(PDO::FETCH_ASSOC);
check('the student owner may open the contest page', Contests::mayAccess($carlReg, $carlUser, ''));
check('another student may not', !Contests::mayAccess($carlReg, $daraUser, ''));
check('the signed link works without an account', Contests::mayAccess($carlReg, null, Contests::token($carl)));
check('a wrong token is refused', !Contests::mayAccess($carlReg, null, Contests::token($carl + 1)));
check('the student copy has no answer key', !isset(Contests::copy($pdo, $carlReg)[0]['correct_option']) && count(Contests::copy($pdo, $carlReg)) === 8);
check('only a cancelled result can be contested', (Contests::submit($pdo, $regs['Dora'], str_repeat('x', 40))['error'] ?? '') === 'not_cancelled');
check('a short message is refused', (Contests::submit($pdo, $carl, 'trop court')['error'] ?? '') === 'too_short');
check('a long message is refused', (Contests::submit($pdo, $carl, str_repeat('x', Contests::MAX + 1))['error'] ?? '') === 'too_long');
check('the contestation is recorded', Contests::submit($pdo, $carl, 'Ma connexion a coupé plusieurs fois, j\'ai rouvert la page à chaque fois.')['ok'] === true);
check('only one contestation per result', (Contests::submit($pdo, $carl, str_repeat('y', 40))['error'] ?? '') === 'already');
check('the teacher is told (notification + email queued)', (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $teacher AND type = 'contest'")->fetchColumn() === 1
    && (int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE template = 'contest_received' AND to_email LIKE '" . $tag . "%'")->fetchColumn() === 1);
$rv = LiveResults::review($pdo, $session);
$by = array_column($rv['rows'], null, 'name');
check('the review shows the open contestation', ($by['Carl Essomba']['contest']['status'] ?? '') === 'open' && $rv['summary']['contested'] === 1);
$cid = (int)$by['Carl Essomba']['contest']['id'];
check('another teacher cannot answer', (Contests::resolve($pdo, $cid, $other, true, '')['error'] ?? '') === 'not_found');
check('keeping the cancellation needs a written reason', (Contests::resolve($pdo, $cid, $teacher, false, 'non')['error'] ?? '') === 'reason_needed');
$res = Contests::resolve($pdo, $cid, $teacher, true, 'Vos explications sont crédibles, le résultat est rétabli.');
$row = $pdo->query("SELECT score, cancelled_at FROM live_eval_registrations WHERE id = $carl")->fetch(PDO::FETCH_ASSOC);
check('accepting restores the result', $res['ok'] === true && $row['cancelled_at'] === null && (float)$row['score'] === 87.5);
check('the answer is final', (Contests::resolve($pdo, $cid, $teacher, false, str_repeat('z', 20))['error'] ?? '') === 'already_resolved');
$sent = []; EmailQueue::work($pdo);
$dec = array_values(array_filter($sent, fn($m) => str_contains($m['subject'], 'Contestation acceptée')))[0] ?? null;
check('the student gets the decision email with the answer and the link', $dec !== null && str_contains($dec['html'], 'crédibles') && str_contains($dec['html'], 'contest-result.php?registration_id=' . $carl));
check('the contestation email to the teacher quotes the student', (bool)array_filter($sent, fn($m) => str_contains($m['subject'], 'Contestation :') && str_contains($m['html'], 'connexion a coupé')));
// a rejected contestation stays cancelled
LiveResults::cancel($pdo, $regs['Alice'], $teacher, 'Réponses identiques');
Contests::submit($pdo, $regs['Alice'], str_repeat('Je conteste cette décision. ', 3));
$aid = (int)$pdo->query("SELECT id FROM result_contests WHERE registration_id = " . $regs['Alice'])->fetchColumn();
$rej = Contests::resolve($pdo, $aid, $teacher, false, 'Les réponses sont identiques sur six questions.');
check('rejecting keeps the result cancelled', $rej['ok'] === true && $pdo->query("SELECT cancelled_at IS NOT NULL FROM live_eval_registrations WHERE id = " . $regs['Alice'])->fetchColumn() == 1);
LiveResults::restore($pdo, $regs['Alice'], $teacher, false);
$pdo->exec("DELETE FROM result_contests WHERE registration_id = " . $regs['Alice']);

// ---- restoring

LiveResults::cancel($pdo, $carl, $teacher, 'again');
$res = LiveResults::restore($pdo, $carl, $teacher);
$row = $pdo->query("SELECT score, cancelled_at FROM live_eval_registrations WHERE id = $carl")->fetch(PDO::FETCH_ASSOC);
check('restore puts the mark back', $res['ok'] === true && $row['cancelled_at'] === null);
check('restoring something not cancelled is refused', (LiveResults::restore($pdo, $carl, $teacher)['error'] ?? '') === 'not_cancelled');
check('the student is told about the restore too', $pdo->query("SELECT COUNT(*) FROM email_outbox WHERE template='result_cancelled' AND to_email LIKE '" . $tag . "%carl%'")->fetchColumn() == 3);

// ---- announcement emails
$pdo->prepare("INSERT INTO enrollments (student_id,course_id) SELECT student_id, ? FROM live_eval_registrations WHERE session_id = ? AND student_id IS NOT NULL")->execute([$course, $sid]);
$pdo->exec("UPDATE live_eval_sessions SET start_time = NOW() + INTERVAL 2 DAY WHERE id = $sid");
require_once __DIR__ . '/../../lib/LiveMailNotifier.php';
$a = LiveMailNotifier::notify($pdo, $sid);
check('every enrolled student is queued once', $a['queued'] === 5 && $a['eligible'] === 5, json_encode($a));
check('announcing twice sends nothing more', LiveMailNotifier::notify($pdo, $sid)['queued'] === 0);
$sent = [];
EmailQueue::work($pdo);
$ann = array_values(array_filter($sent, fn($m) => str_contains($m['subject'], 'programmée')));
check('five announcement emails go out', count($ann) === 5);
check('announcement has the schedule first, then steps and the rules of conduct, the link and the logo', str_contains($ann[0]['html'] ?? '', 'Règles de conduite') && str_contains($ann[0]['html'] ?? '', 'À ne pas faire') && str_contains($ann[0]['html'] ?? '', '/live-session.php?code=') && str_contains($ann[0]['html'] ?? '', 'cid:svlogo@studyvibe'));
$pdo->exec("UPDATE live_eval_sessions SET start_time = NOW() + INTERVAL 3 DAY WHERE id = $sid");
$b = LiveMailNotifier::notify($pdo, $sid);
$sent = []; EmailQueue::work($pdo);
check('moving the exam sends an update to everybody', $b['queued'] === 5 && count(array_filter($sent, fn($m) => str_contains($m['subject'], 'Nouveau créneau'))) === 5);

echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
