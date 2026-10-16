<?php
declare(strict_types=1);

/**
 * Leaving an evaluation and coming back, end to end (HTTP, with separate cookie jars standing for "another tab", "the browser was
 * closed" and "another device"):
 *   asynchronous: back on the very question left, within 24 h, with the time it had (time away is not charged); after 24 h of silence the
 *                 unfinished attempt starts over; a finished attempt survives opening the link and is only wiped by Retake or by entering again;
 *   live:         the registration and the answers already given survive, the student lands on the question running now; Quit keeps
 *                 a registration that holds answers;
 *   guests:       entering again with the same email finds the same registration and its answers.
 * Needs the app running (default http://127.0.0.1:8123). Throw-away rows zz.rsm.* are removed at the end.
 *
 *   php tests/integration/resume_evaluation.php [http://127.0.0.1:8123]
 */
putenv('SV_NO_BACKGROUND_MAIL=1');
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/LiveResume.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }

// ---------------------------------------------------------------- the rules, without the network
$base0 = ['score' => null, 'cancelled_at' => null, 'async_q_id' => 5, 'idle_seconds' => 3600];
check('unfinished and recent: resume', LiveResume::shouldRestart($base0, 2, false, false, time()) === false && LiveResume::shouldRestart($base0, 2, false, true, time()) === false);
check('unfinished and silent for 25 h: start over', LiveResume::shouldRestart(['idle_seconds' => 25 * 3600] + $base0, 2, false, false, time()) === true);
check('exactly 24 h is still resumable', LiveResume::shouldRestart(['idle_seconds' => 86400] + $base0, 2, false, false, time()) === false);
check('a registration with nothing started is never "stale"', LiveResume::shouldRestart(['idle_seconds' => 90 * 3600, 'async_q_id' => null] + $base0, 0, false, false, time()) === false);
check('finished: opening the link keeps it, entering again or Retake restarts', LiveResume::shouldRestart(['score' => 80.0] + $base0, 3, false, false, time()) === false && LiveResume::shouldRestart(['score' => 80.0] + $base0, 3, false, true, time()) === true && LiveResume::shouldRestart(['score' => 80.0] + $base0, 3, true, false, time()) === true);
check('time away is not charged only past the 20 s grace', LiveResume::awayShift(['idle_seconds' => 10] + $base0, time()) === 0 && LiveResume::awayShift(['idle_seconds' => 300] + $base0, time()) === 300);

// ---------------------------------------------------------------- fixtures
$pw = 'Rsm-test-9Zq!';
$pdo->exec("DELETE FROM users WHERE email LIKE 'zz.rsm.%@test.local' OR email LIKE 'zz.rsm.%@gmail.com'");
$mod = (int)$pdo->query("SELECT id FROM modules LIMIT 1")->fetchColumn();
$mk = function (string $n, string $role) use ($pdo, $pw): int {
    $pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved,plan_id,matricule,tour_seen_at,lang) VALUES (?,?,?,?,NOW(),1,1,NULL,?,NOW(),'fr')")
        ->execute(["zz.rsm.$n@test.local", password_hash($pw, PASSWORD_DEFAULT), 'Rsm ' . ucfirst($n), $role, $role === 'student' ? '26R' . random_int(100, 999) : null]);
    return (int)$pdo->lastInsertId();
};
$T = $mk('teacher', 'teacher'); $S = $mk('ama', 'student');
$pdo->prepare("INSERT INTO courses (module_id,teacher_id,created_by,title,description,svg_icon,is_published) VALUES (?,?,?,'ZZ rsm course','d','',1)")->execute([$mod, $T, $T]);
$course = (int)$pdo->lastInsertId();
$mkSession = function (string $code, bool $async) use ($pdo, $course, $T): array {
    $pdo->prepare("INSERT INTO live_eval_sessions (course_id,teacher_id,title,session_code,start_time,end_time,default_time_limit,status,is_async,async_deadline) VALUES (?,?,?,?,?,?,60,1,?,?)")
        ->execute([$course, $T, "ZZ rsm $code", $code, date('Y-m-d H:i:s', time() - 10), date('Y-m-d H:i:s', time() + 7200), $async ? 1 : 0, $async ? date('Y-m-d H:i:s', time() + 3 * 86400) : null]);
    $sid = (int)$pdo->lastInsertId();
    $qs = [];
    foreach ([1, 2, 3] as $i) {
        $pdo->prepare("INSERT INTO live_eval_questions (session_id,question_text,option_a,option_b,option_c,option_d,correct_option,time_limit,sort_order,question_type) VALUES (?,?,?,?,?,?,'A',?,?,'mcq')")
            ->execute([$sid, "Question $i ?", 'Vrai', 'Faux', 'Peut-être', 'Autre', 60, $i]);
        $qs[] = (int)$pdo->lastInsertId();
    }
    return [$sid, $qs];
};
$sfx = substr(md5(uniqid('', true)), 0, 6);          // a new code per run: the session data is cached by code for a few seconds
$CA = 'zzrsmasync' . $sfx; $CL = 'zzrsmlive' . $sfx;
[$AS, $AQ] = $mkSession($CA, true);
[$LS, $LQ] = $mkSession($CL, false);
$jars = [];
$jar = function (string $name) use (&$jars): string { return $jars[$name] ??= tempnam(sys_get_temp_dir(), 'rsm'); };
$req = function (string $jar, string $path, array $post = [], bool $follow = false) use ($base): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => $follow]);
    if ($post !== []) { curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]); }
    $body = (string)curl_exec($ch);
    return ['code' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'json' => json_decode($body, true), 'body' => $body, 'loc' => (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL)];
};
$login = fn(string $j) => ($req($j, '/login-action.php', ['email' => 'zz.rsm.ama@test.local', 'password' => $pw])['json']['success'] ?? false) === true;
$poll = fn(string $j, string $code) => $req($j, "/api/live-eval-poll.php?code=$code&action=poll_quiz")['json'] ?? [];
$answer = fn(string $j, string $code, int $q, string $o = 'A') => $req($j, '/api/live-eval-poll.php', ['action' => 'submit_answer', 'code' => $code, 'question_id' => $q, 'selected_option' => $o])['json'] ?? [];
$one = fn(string $sql) => $pdo->query($sql)->fetchColumn();
$regOf = fn(int $sid) => (int)$one("SELECT id FROM live_eval_registrations WHERE session_id = $sid AND student_id = $S");
$answers = fn(int $rid) => (int)$one("SELECT COUNT(*) FROM live_eval_answers WHERE registration_id = $rid");

check('the student signs in', $login($jar('a1')));

// ================================================================ ASYNCHRONOUS
$req($jar('a1'), "/live-session.php?code={$CA}", ['register_live' => 1]);
$R = $regOf($AS);
check('entering registers the student', $R > 0);
$p = $poll($jar('a1'), $CA);
check('question 1 first, with its full time', ($p['current_question_index'] ?? -1) === 0 && ($p['question']['seconds_left'] ?? 0) >= 55, json_encode($p));
check('the answer is saved', ($answer($jar('a1'), $CA, $AQ[0])['success'] ?? false) === true);
$p = $poll($jar('a1'), $CA);
check('then question 2', ($p['current_question_index'] ?? -1) === 1);
check('the running question and its start are kept on the registration', (int)$one("SELECT async_q_id FROM live_eval_registrations WHERE id = $R") === $AQ[1] && $one("SELECT async_q_started_at FROM live_eval_registrations WHERE id = $R") !== null);

// the student worked 40 s on question 2, left, and comes back 5 minutes later from another device
$pdo->exec("UPDATE live_eval_registrations SET async_q_started_at = NOW() - INTERVAL 340 SECOND, last_activity = NOW() - INTERVAL 300 SECOND WHERE id = $R");
check('another device signs in', $login($jar('a2')));
$page = $req($jar('a2'), "/live-session.php?code={$CA}");
check('opening the link again does not wipe the attempt', $answers($R) === 1 && $page['code'] === 200, 'answers=' . $answers($R));
$p = $poll($jar('a2'), $CA);
check('back on question 2, the one left', ($p['current_question_index'] ?? -1) === 1 && ($p['question']['id'] ?? 0) === $AQ[1], json_encode($p));
$left = (int)($p['question']['seconds_left'] ?? -1);
check('...with the time it had (about 20 s of 60, the 5 minutes away not charged)', $left >= 16 && $left <= 24, "left=$left");
check('...and the answer to question 1 is still there', $answers($R) === 1);

// signed in with no PHP session of its own (cookie lost): the registration is found from the account
$p = $poll($jar('a3-nosession'), $CA);
check('without any sign-in the poll still says "not registered"', ($p['not_registered'] ?? false) === true);
check('another device signs in once more', $login($jar('a3-nosession')));
$p = $poll($jar('a3-nosession'), $CA);
check('a signed-in student is recognised by the poll alone (no page load needed)', ($p['success'] ?? false) === true && ($p['current_question_index'] ?? -1) === 1);

// 24 hours of silence: the unfinished attempt is dropped
$pdo->exec("UPDATE live_eval_registrations SET last_activity = NOW() - INTERVAL 25 HOUR WHERE id = $R");
$req($jar('a4'), "/live-session.php?code={$CA}"); $login($jar('a4'));
$page = $req($jar('a4'), "/live-session.php?code={$CA}");
check('after 25 h of silence the attempt starts over', $answers($R) === 0 && $one("SELECT async_q_id FROM live_eval_registrations WHERE id = $R") === null, 'answers=' . $answers($R));
$p = $poll($jar('a4'), $CA);
check('...from question 1', ($p['current_question_index'] ?? -1) === 0 && ($p['question']['seconds_left'] ?? 0) >= 55);

// finished attempt
foreach ($AQ as $i => $q) { $answer($jar('a4'), $CA, $q, $i < 2 ? 'A' : 'B'); $poll($jar('a4'), $CA); }
$p = $poll($jar('a4'), $CA);
$score = $one("SELECT score FROM live_eval_registrations WHERE id = $R");
check('answering everything finishes the attempt with a score', ($p['is_finished'] ?? false) === true && $score !== null && abs((float)$score - 66.67) < 0.01, "score=$score");
$req($jar('a4'), "/live-session.php?code={$CA}");
check('opening the link of a finished attempt keeps the result', $one("SELECT score FROM live_eval_registrations WHERE id = $R") !== null && $answers($R) === 3);
$req($jar('a4'), "/live-session.php?code={$CA}&restart=1");
check('Retake (restart=1) starts a new attempt', $one("SELECT score FROM live_eval_registrations WHERE id = $R") === null && $answers($R) === 0);
foreach ($AQ as $q) { $answer($jar('a4'), $CA, $q); $poll($jar('a4'), $CA); } $poll($jar('a4'), $CA);
$req($jar('a4'), "/live-session.php?code={$CA}", ['register_live' => 1]);
check('entering again through the form after finishing starts a new attempt', $one("SELECT score FROM live_eval_registrations WHERE id = $R") === null && $answers($R) === 0);

// ================================================================ QUIT KEEPS WHAT HAS BEEN ANSWERED
$p = $poll($jar('a4'), $CA); $answer($jar('a4'), $CA, (int)($p['question']['id'] ?? 0));
$req($jar('a4'), "/live-session.php?code={$CA}&action=disconnect");
check('Quit keeps a registration that holds answers', $regOf($AS) === $R && $answers($R) === 1);
$req($jar('a4'), "/live-session.php?code={$CA}", ['register_live' => 1]);
check('...and coming back resumes it', $regOf($AS) === $R && $answers($R) === 1);
$pdo->exec("DELETE FROM live_eval_answers WHERE registration_id = $R");
$pdo->exec("UPDATE live_eval_registrations SET async_q_id = NULL, async_q_started_at = NULL WHERE id = $R");
$req($jar('a4'), "/live-session.php?code={$CA}&action=disconnect");
check('Quit forgets a registration with nothing answered', $regOf($AS) === 0);

// ================================================================ GUEST, ASYNCHRONOUS
$g = 'zz.rsm.guest@gmail.com';
$req($jar('g1'), "/live-session.php?code={$CA}", ['register_live' => 1, 'email' => $g, 'name' => 'Invité Rsm']);
$gR = (int)$one("SELECT id FROM live_eval_registrations WHERE session_id = $AS AND email = '$g'");
check('a guest enters', $gR > 0 && $one("SELECT student_id FROM live_eval_registrations WHERE id = $gR") === null);
$poll($jar('g1'), $CA); $p = $poll($jar('g1'), $CA);
$answer($jar('g1'), $CA, (int)$p['question']['id']);
$req($jar('g2'), "/live-session.php?code={$CA}", ['register_live' => 1, 'email' => $g, 'name' => 'Invité Rsm']);   // browser closed, enters again
check('entering again with the same email finds the same registration and keeps the answer', (int)$one("SELECT COUNT(*) FROM live_eval_registrations WHERE session_id = $AS AND email = '$g'") === 1 && $answers($gR) === 1);
$p = $poll($jar('g2'), $CA);
check('the guest is back on question 2', ($p['current_question_index'] ?? -1) === 1, json_encode($p));

// ================================================================ LIVE (SYNCHRONOUS)
$req($jar('l1'), "/live-session.php?code={$CL}", ['register_live' => 1]);   // l1 not signed in as a student yet: guest form needs an email, so sign in first
$login($jar('l1'));
$req($jar('l1'), "/live-session.php?code={$CL}", ['register_live' => 1]);
$LR = $regOf($LS);
check('the student enters the live session', $LR > 0);
sleep(3);                                                          // the session data is cached for 2 s
$p = $poll($jar('l1'), $CL);
check('question 1 is running', ($p['current_question_index'] ?? -1) === 0 && ($p['already_answered'] ?? true) === false, json_encode($p));
check('the answer is saved', ($answer($jar('l1'), $CL, $LQ[0])['success'] ?? false) === true);
$login($jar('l2'));                                                // the tab was closed, another one opens (no page load, only the poll)
$p = $poll($jar('l2'), $CL);
check('back in the room without any new entry: the same question, already answered', ($p['success'] ?? false) === true && ($p['current_question_index'] ?? -1) === 0 && ($p['already_answered'] ?? false) === true, json_encode($p));
$pdo->exec("UPDATE live_eval_sessions SET start_time = NOW() - INTERVAL 70 SECOND WHERE id = $LS");   // the class moved on: question 2 now
sleep(3);
$p = $poll($jar('l2'), $CL);
check('the class moved to question 2: the student is synchronised with it', ($p['current_question_index'] ?? -1) === 1 && ($p['already_answered'] ?? true) === false, json_encode($p));
check('the answer to question 1 is kept', $answers($LR) === 1);
$req($jar('l2'), "/live-session.php?code={$CL}&action=disconnect");
check('Quit during a live session keeps the registration that holds answers', $regOf($LS) === $LR && $answers($LR) === 1);
$page = $req($jar('l3'), "/live-session.php?code={$CL}"); $login($jar('l3'));
$page = $req($jar('l3'), "/live-session.php?code={$CL}");
check('opening the live link again never resets anything', $page['code'] === 200 && $regOf($LS) === $LR && $answers($LR) === 1);
$pdo->exec("UPDATE live_eval_sessions SET attempt_round = 2 WHERE id = $LS");           // the teacher moved the session to a new date
$page = $req($jar('l3'), "/live-session.php?code={$CL}");
check('a live session moved to a new date starts again for who sat it before', $page['code'] === 302 && $regOf($LS) === $LR && $answers($LR) === 0
    && (int)$pdo->query("SELECT attempt_round FROM live_eval_registrations WHERE id = $LR")->fetchColumn() === 2);
$pdo->exec("INSERT INTO live_eval_answers (registration_id, question_id, selected_option) SELECT $LR, id, 'A' FROM live_eval_questions WHERE session_id = $LS LIMIT 1");
$req($jar('l3'), "/live-session.php?code={$CL}");
check('and only once: opening the link again in the same round keeps the answers', $answers($LR) === 1);

// ================================================================ the evaluations page offers "Resume"
$login($jar('a5'));
$req($jar('a5'), "/live-session.php?code={$CA}", ['register_live' => 1]);
$R = $regOf($AS);          // the earlier Quit deleted the empty registration: this is a new one
$p = $poll($jar('a5'), $CA); $answer($jar('a5'), $CA, (int)$p['question']['id']);
$html = $req($jar('a5'), '/evaluations.php')['body'];
preg_match('/data-id="' . $AS . '"[^>]*data-state="([a-z]+)"/', $html, $m);
check('the evaluations page marks the started evaluation "in progress" (Resume)', ($m[1] ?? '') === 'inprog', $m[1] ?? 'no card');
$pdo->exec("UPDATE live_eval_registrations SET last_activity = NOW() - INTERVAL 30 HOUR WHERE id = $R");
$html = $req($jar('a5'), '/evaluations.php')['body'];
preg_match('/data-id="' . $AS . '"[^>]*data-state="([a-z]+)"/', $html, $m);
check('...and "to do" again once the 24 hours have passed', ($m[1] ?? '') === 'todo', $m[1] ?? 'no card');

// ================================================================ clean up
$pdo->exec("DELETE FROM live_eval_sessions WHERE id IN ($AS, $LS)");
$pdo->exec("DELETE FROM courses WHERE id = $course");
$pdo->exec("DELETE FROM users WHERE email LIKE 'zz.rsm.%@test.local' OR email LIKE 'zz.rsm.%@gmail.com'");
foreach ($jars as $j) { @unlink($j); }
echo "\n" . $passed . ' passed, ' . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
