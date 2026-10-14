<?php
declare(strict_types=1);

/**
 * Free-pace evaluations: the bucket of drafts, the per-question clocks, the lock, the unanswered review, the final submission,
 * the end of the session, and what the browser is (not) allowed to see. Throw-away rows, removed at the end.
 *
 *   php tests/integration/live_paced.php
 */
putenv('SV_NO_BACKGROUND_MAIL=1');
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/LivePaced.php';
require_once __DIR__ . '/../../lib/LiveMailQueue.php';

$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }

$tag = 'zz.paced.' . bin2hex(random_bytes(2));
$cleanup = function () use ($pdo, $tag) {
    $pdo->exec("DELETE FROM live_eval_sessions WHERE title LIKE 'ZZ paced%'");
    $pdo->exec("DELETE FROM courses WHERE title = 'ZZ paced course'");
    $pdo->exec("DELETE FROM users WHERE email LIKE '" . $tag . "%'");
    $pdo->exec("DELETE FROM live_eval_mail_queue WHERE registration_id NOT IN (SELECT id FROM live_eval_registrations)");
};
register_shutdown_function($cleanup);

$mod = (int)$pdo->query("SELECT id FROM modules LIMIT 1")->fetchColumn();
if ($mod === 0) { $pdo->exec("INSERT INTO modules (title) VALUES ('ZZ module')"); $mod = (int)$pdo->lastInsertId(); }
$pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved) VALUES (?,?,?,?,NOW(),1,1)")->execute([$tag . '.t@test.local', 'x', 'Prof', 'teacher']);
$teacher = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO courses (module_id,teacher_id,created_by,title,description,svg_icon,is_published) VALUES (?,?,?,'ZZ paced course','d','',1)")->execute([$mod, $teacher, $teacher]);
$course = (int)$pdo->lastInsertId();
$mkSession = function (string $title, string $start, string $end, int $free = 1, ?int $default = 30) use ($pdo, $course, $teacher): int {
    $pdo->prepare("INSERT INTO live_eval_sessions (course_id,teacher_id,title,session_code,start_time,end_time,default_time_limit,status,free_pace) VALUES (?,?,?,?,$start,$end,?,1,?)")
        ->execute([$course, $teacher, $title, bin2hex(random_bytes(6)), $default ?? 30, $free]);
    return (int)$pdo->lastInsertId();
};
$sid = $mkSession('ZZ paced main', 'NOW() - INTERVAL 5 MINUTE', 'NOW() + INTERVAL 2 HOUR');
$addQ = function (int $sid, string $type, string $text, int $sort, ?int $limit = null, string $correct = 'B') use ($pdo): int {
    $pdo->prepare("INSERT INTO live_eval_questions (session_id,question_text,option_a,option_b,option_c,option_d,correct_option,sort_order,question_type,time_limit,code_lang,code_reference,code_notes)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$sid, $text, $type === 'mcq' ? 'a' : '', $type === 'mcq' ? 'b' : '', $type === 'mcq' ? 'c' : '', $type === 'mcq' ? 'd' : '',
                   in_array($type, ['code', 'open'], true) ? 'CODE_OK' : $correct, $sort, $type, $limit, $type === 'code' ? 'php' : null,
                   in_array($type, ['code', 'open'], true) ? 'SECRET reference solution' : null, in_array($type, ['code', 'open'], true) ? 'SECRET notes' : null]);
    return (int)$pdo->lastInsertId();
};
$qM = $addQ($sid, 'mcq', 'Pick b', 1, 60);
$qW = $addQ($sid, 'written', 'Type 42', 2, null, '42|forty-two');
$qO = $addQ($sid, 'open', 'Explain LSP', 3, 120);
$qC = $addQ($sid, 'code', 'Write add', 4, 5);          // a limit written on a code question is ignored
$reg = function (int $sid, string $name) use ($pdo, $tag): int {
    $pdo->prepare("INSERT INTO live_eval_registrations (session_id,name,email,score) VALUES (?,?,?,NULL)")->execute([$sid, $name, $tag . '.' . strtolower($name) . '@test.local']);
    return (int)$pdo->lastInsertId();
};
$r1 = $reg($sid, 'Ana');
$session = LivePaced::session($pdo, $sid);

// ---- the limits
$qs = LivePaced::questions($pdo, $sid);
check('a QCM has its own limit', LivePaced::limitOf($qs[0], $session) === 60);
check('a question without a time takes the default of the session', LivePaced::limitOf($qs[1], $session) === 30);
check('a code question has no limit, whatever is stored', LivePaced::limitOf($qs[3], $session) === null);
check('a written-answer question is timed', LivePaced::limitOf($qs[2], $session) === 120);

// ---- what the browser gets
$state = LivePaced::state($pdo, $session, $r1);
$json = json_encode($state);
check('the window is open and the four questions are listed', $state['window'] === 'open' && count($state['questions']) === 4);
check('no reference, note or right answer in the state', !str_contains($json, 'SECRET') && !str_contains($json, 'correct_option') && !str_contains($json, 'code_reference'));
check('a code question tells its language and tool profile', ($state['questions'][3]['code_lang'] ?? '') === 'php' && ($state['questions'][3]['profile']['semicolons'] ?? false) === true && $state['questions'][3]['limit'] === null);
check('a QCM gives its options in the order shown', count($state['questions'][0]['options']) === 4 && count($state['questions'][0]['option_keys']) === 4);

// ---- the clock of a timed question runs only while it is open
$o = LivePaced::open($pdo, $session, $r1, $qM);
check('opening a question starts its clock', $o['ok'] && $o['limit'] === 60 && $o['used'] === 0 && $o['left'] === 60);
$pdo->exec("UPDATE live_eval_registrations SET current_since = NOW() - INTERVAL 40 SECOND WHERE id = $r1");
$p = LivePaced::ping($pdo, $session, $r1);
check('40 seconds open are charged to the question', $p['timing']['used'] >= 40 && $p['timing']['used'] <= 42 && $p['timing']['left'] <= 20);
$pdo->exec("UPDATE live_eval_registrations SET current_since = NOW() - INTERVAL 500 SECOND WHERE id = $r1");
LivePaced::settle($pdo, $r1);
$used = (int)$pdo->query("SELECT seconds_used FROM live_eval_drafts WHERE registration_id = $r1 AND question_id = $qM")->fetchColumn();
check('a long silence is capped (closed laptop, outage): at most 90 s are charged', $used <= 41 + LivePaced::GAP_CAP + 2 && $used >= 41 + LivePaced::GAP_CAP - 2, (string)$used);
// the other question, left alone, keeps its time
LivePaced::open($pdo, $session, $r1, $qW);
$pdo->exec("UPDATE live_eval_registrations SET current_since = NOW() - INTERVAL 10 SECOND WHERE id = $r1");
LivePaced::settle($pdo, $r1);
check('switching question stops the first clock and starts the second', (int)$pdo->query("SELECT seconds_used FROM live_eval_drafts WHERE registration_id = $r1 AND question_id = $qM")->fetchColumn() === $used
    && (int)$pdo->query("SELECT seconds_used FROM live_eval_drafts WHERE registration_id = $r1 AND question_id = $qW")->fetchColumn() >= 10);

// ---- the lock
$pdo->exec("UPDATE live_eval_drafts SET seconds_used = 60 WHERE registration_id = $r1 AND question_id = $qM");
LivePaced::open($pdo, $session, $r1, $qM);
$s = LivePaced::save($pdo, $session, $r1, $qM, 'A');
check('a save just after the budget ends is still accepted (grace)', $s['ok'] === true);
$pdo->exec("UPDATE live_eval_drafts SET seconds_used = 90 WHERE registration_id = $r1 AND question_id = $qM");
$s = LivePaced::save($pdo, $session, $r1, $qM, 'C');
check('a save long after the budget is refused: the question is locked, the draft stays', $s['ok'] === false && $s['error'] === 'locked' && $pdo->query("SELECT answer FROM live_eval_drafts WHERE registration_id = $r1 AND question_id = $qM")->fetchColumn() === 'A');
check('the state shows it locked', LivePaced::state($pdo, $session, $r1)['questions'][0]['locked'] === true);
LivePaced::open($pdo, $session, $r1, $qC);
$pdo->exec("UPDATE live_eval_registrations SET current_since = NOW() - INTERVAL 80 SECOND WHERE id = $r1");
$s = LivePaced::save($pdo, $session, $r1, $qC, "function add(\$a, \$b) {\n    return \$a + \$b;\n}\n");
check('a code question never locks, however long it stays open', $s['ok'] === true && $s['locked'] === false && $s['limit'] === null && $s['left'] === null);

// ---- what is accepted
check('a letter outside A-D is refused for a QCM', LivePaced::save($pdo, $session, $r1, $qM, 'Z')['ok'] === false);
LivePaced::save($pdo, $session, $r1, $qW, str_repeat('x', 400));
check('a short written answer is cut to 255 characters', mb_strlen((string)$pdo->query("SELECT answer FROM live_eval_drafts WHERE registration_id = $r1 AND question_id = $qW")->fetchColumn()) === 255);
LivePaced::save($pdo, $session, $r1, $qW, '42');
check('a question of another session cannot be written', LivePaced::save($pdo, $session, $r1, 99999999, 'x')['error'] === 'question');
LivePaced::save($pdo, $session, $r1, $qO, '');

// ---- submitting: the unanswered review first
$res = LivePaced::submit($pdo, $session, $r1, false);
check('with one question unanswered, nothing is sent and the list comes back', $res['ok'] === false && $res['error'] === 'unanswered' && $res['unanswered'] === [$qO] && $res['answered'] === 3);
check('nothing was written to the real answers yet', (int)$pdo->query("SELECT COUNT(*) FROM live_eval_answers WHERE registration_id = $r1")->fetchColumn() === 0);
LivePaced::save($pdo, $session, $r1, $qO, 'A subtype must be usable wherever its parent is expected.');
$res = LivePaced::submit($pdo, $session, $r1, false);
check('everything answered: the submission goes through', $res['ok'] === true && $res['answered'] === 4 && $res['total'] === 4);
check('the QCM and written answers became real answers', $pdo->query("SELECT selected_option FROM live_eval_answers WHERE registration_id = $r1 AND question_id = $qM")->fetchColumn() === 'A'
    && $pdo->query("SELECT selected_option FROM live_eval_answers WHERE registration_id = $r1 AND question_id = $qW")->fetchColumn() === '42');
check('the code and written-answer questions went to the marking queue', LiveCodeReview::pendingCount($pdo, $r1) === 2 && $res['pending_ai'] === 2);
check('the provisional score counts the finished ones only and the email waits for the marking', (float)$pdo->query("SELECT score FROM live_eval_registrations WHERE id = $r1")->fetchColumn() === 25.0
    && (int)$pdo->query("SELECT COUNT(*) FROM live_eval_mail_queue WHERE registration_id = $r1")->fetchColumn() === 0);
check('a second submission does nothing', LivePaced::submit($pdo, $session, $r1, true)['already'] === true);
check('after the submission the bucket is closed', LivePaced::save($pdo, $session, $r1, $qW, '43')['error'] === 'submitted' && LivePaced::open($pdo, $session, $r1, $qW)['error'] === 'submitted');

// ---- forced submission with unanswered questions
$r2 = $reg($sid, 'Ben');
LivePaced::save($pdo, $session, $r2, $qM, 'B');
$res = LivePaced::submit($pdo, $session, $r2, true);
check('the student can submit with unanswered questions when they confirm', $res['ok'] === true && $res['answered'] === 1 && (float)$pdo->query("SELECT score FROM live_eval_registrations WHERE id = $r2")->fetchColumn() === 25.0);
check('the results email of a student without marked answers is queued at once', (int)$pdo->query("SELECT COUNT(*) FROM live_eval_mail_queue WHERE registration_id = $r2")->fetchColumn() === 1);

// ---- the window
$sidWait = $mkSession('ZZ paced waiting', 'NOW() + INTERVAL 1 HOUR', 'NOW() + INTERVAL 3 HOUR');
$addQ($sidWait, 'mcq', 'Q', 1, 30);
$rw = $reg($sidWait, 'Wen');
$sWait = LivePaced::session($pdo, $sidWait);
$st = LivePaced::state($pdo, $sWait, $rw);
check('before the start the room only waits: no question is sent', $st['window'] === 'waiting' && $st['questions'] === [] && $st['clock']['seconds_to_start'] > 3000);
check('nothing can be opened or saved before the start', LivePaced::open($pdo, $sWait, $rw, 1)['error'] === 'not_started');

// ---- the end of the session hands in what is in the bucket
$sidEnd = $mkSession('ZZ paced ended', 'NOW() - INTERVAL 3 HOUR', 'NOW() + INTERVAL 1 HOUR');
$qE = $addQ($sidEnd, 'mcq', 'Q1', 1, 30, 'A'); $qE2 = $addQ($sidEnd, 'written', 'Q2', 2, 30, 'ok');
$sEnd = LivePaced::session($pdo, $sidEnd);
$ra = $reg($sidEnd, 'Ada'); $rb = $reg($sidEnd, 'Bob');
LivePaced::save($pdo, $sEnd, $ra, $qE, 'A'); LivePaced::save($pdo, $sEnd, $ra, $qE2, 'ok');
$pdo->exec("UPDATE live_eval_sessions SET end_time = NOW() - INTERVAL 1 MINUTE WHERE id = $sidEnd");
$sEnd = LivePaced::session($pdo, $sidEnd);
check('after the end the window is closed', LivePaced::window($pdo, $sEnd) === 'closed');
check('and nothing more is accepted', LivePaced::save($pdo, $sEnd, $ra, $qE, 'B')['error'] === 'closed');
$n = LivePaced::sweep($pdo);
check('the sweep hands in the student who had answers', $n >= 1 && $pdo->query("SELECT submitted_at IS NOT NULL FROM live_eval_registrations WHERE id = $ra")->fetchColumn() == 1 && (float)$pdo->query("SELECT score FROM live_eval_registrations WHERE id = $ra")->fetchColumn() === 100.0);
check('a registered student who never answered is left alone', $pdo->query("SELECT submitted_at FROM live_eval_registrations WHERE id = $rb")->fetchColumn() === null);
$ping = LivePaced::ping($pdo, $sEnd, $rb);
check('a ping after the end reports the session as over', ($ping['window'] ?? '') === 'closed');

// ---- an asynchronous free-pace session is open until its deadline
$sidA = $mkSession('ZZ paced async', 'NOW() - INTERVAL 1 DAY', 'NOW() - INTERVAL 1 DAY');
$pdo->exec("UPDATE live_eval_sessions SET is_async = 1, async_deadline = NOW() + INTERVAL 2 DAY WHERE id = $sidA");
check('a free-pace homework is open until its deadline', LivePaced::window($pdo, LivePaced::session($pdo, $sidA)) === 'open');
$pdo->exec("UPDATE live_eval_sessions SET async_deadline = NOW() - INTERVAL 1 MINUTE WHERE id = $sidA");
check('and closed after it', LivePaced::window($pdo, LivePaced::session($pdo, $sidA)) === 'closed');

// ---- tools profile and the PHP syntax check
check('the tool profile follows the language', LiveCodeReview::toolProfile('python')['family'] === 'python' && LiveCodeReview::toolProfile('sql')['family'] === 'sql' && LiveCodeReview::toolProfile('java')['semicolons'] === true && LiveCodeReview::toolProfile('klingon')['family'] === 'plain');
$bad = LivePaced::lintPhp("function f(\$a) {\n  return \$a +;\n}");
check('php -l finds a syntax error with its line', $bad['checked'] === false || (count($bad['errors']) === 1 && $bad['errors'][0]['line'] === 2));
$good = LivePaced::lintPhp("function f(\$a) { return \$a; }");
check('valid PHP passes', $good['checked'] === false || $good['errors'] === []);

echo "live paced: $passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL: $f\n"; }
exit($failed ? 1 : 0);
