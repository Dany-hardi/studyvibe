<?php
declare(strict_types=1);

/**
 * Archives and the tracking of participants: snapshots of a session (once per content, tamper-proof), of an excluded participant, the rotation of the
 * logs, the mark kept as a count over the number of questions, the presence measured by the database clock, the timeline of events.
 *
 *   php tests/integration/archive.php
 */
putenv('SV_NO_BACKGROUND_MAIL=1');
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/LivePaced.php';
require_once __DIR__ . '/../../lib/LiveParticipants.php';
require_once __DIR__ . '/../../lib/LiveEvents.php';
require_once __DIR__ . '/../../lib/Archive.php';

$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }

$tag = 'zz.arch.' . bin2hex(random_bytes(2));
register_shutdown_function(function () use ($pdo, $tag) {
    $pdo->exec("DELETE FROM archives WHERE title LIKE 'ZZ arch%' OR title LIKE 'ZZ Reg%'");
    $pdo->exec("DELETE FROM live_eval_sessions WHERE title LIKE 'ZZ arch%'");
    $pdo->exec("DELETE FROM courses WHERE title = 'ZZ arch course'");
    $pdo->exec("DELETE FROM users WHERE email LIKE '" . $tag . "%'");
    $pdo->exec("DELETE FROM audit_logs WHERE action = 'zz_arch_test'");
    $pdo->exec("DELETE FROM server_errors WHERE context = 'zz_arch_test'");
    $pdo->exec("DELETE FROM archives WHERE kind = 'logs' AND title LIKE '%2001-01%'");
});

$mod = (int)$pdo->query("SELECT id FROM modules LIMIT 1")->fetchColumn();
if ($mod === 0) { $pdo->exec("INSERT INTO modules (title) VALUES ('ZZ module')"); $mod = (int)$pdo->lastInsertId(); }
$pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved) VALUES (?,?,?,?,NOW(),1,1)")->execute([$tag . '.t@test.local', 'x', 'Prof', 'teacher']);
$teacher = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO courses (module_id,teacher_id,created_by,title,description,svg_icon,is_published) VALUES (?,?,?,'ZZ arch course','d','',1)")->execute([$mod, $teacher, $teacher]);
$course = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO live_eval_sessions (course_id,teacher_id,title,session_code,start_time,end_time,default_time_limit,status,free_pace) VALUES (?,?,'ZZ arch exam',?,NOW() - INTERVAL 3 HOUR,NOW() + INTERVAL 1 HOUR,30,1,1)")->execute([$course, $teacher, bin2hex(random_bytes(6))]);
$sid = (int)$pdo->lastInsertId();
$q = [];
foreach ([['mcq', 'Pick b', 'B'], ['mcq', 'Pick a', 'A'], ['code', 'Write add', 'CODE_OK'], ['mcq', 'Pick c', 'C']] as $i => [$type, $text, $correct]) {
    $pdo->prepare("INSERT INTO live_eval_questions (session_id,question_text,option_a,option_b,option_c,option_d,correct_option,sort_order,question_type,time_limit,code_lang,code_reference)
                   VALUES (?,?,?,?,?,?,?,?,?,60,?,?)")->execute([$sid, $text, 'a', 'b', 'c', 'd', $correct, $i + 1, $type, $type === 'code' ? 'php' : null, $type === 'code' ? 'SECRET ref' : null]);
    $q[] = (int)$pdo->lastInsertId();
}
$mk = function (string $name) use ($pdo, $sid, $tag): int {
    $pdo->prepare("INSERT INTO live_eval_registrations (session_id,name,email) VALUES (?,?,?)")->execute([$sid, $name, $tag . '.' . strtolower($name) . '@test.local']);
    return (int)$pdo->lastInsertId();
};
$ana = $mk('Ana'); $ben = $mk('Ben'); $cy = $mk('Cy');
$session = LivePaced::session($pdo, $sid);

// ---- the mark is a count over the number of questions, kept durably
LivePaced::save($pdo, $session, $ana, $q[0], 'B'); LivePaced::save($pdo, $session, $ana, $q[1], 'A'); LivePaced::save($pdo, $session, $ana, $q[3], 'D');
$res = LivePaced::submit($pdo, $session, $ana, true);
$row = $pdo->query("SELECT score, correct_count, question_count, submitted_at, submit_mode FROM live_eval_registrations WHERE id = $ana")->fetch(PDO::FETCH_ASSOC);
check('the mark is stored as a count over the questions: 2 right out of 4', (int)$row['correct_count'] === 2 && (int)$row['question_count'] === 4 && (float)$row['score'] === 50.0, json_encode($row));
check('the handing in is dated and its mode kept', $row['submitted_at'] !== null && $row['submit_mode'] === 'manual');
$p = array_values(array_filter(LiveParticipants::rows($pdo, $sid), fn($r) => (int)$r['id'] === $ana))[0];
check('the table reads the mark over the number of questions, not over 20', $p['mark'] === 2 && $p['total'] === 4 && $p['percent'] === 50.0);
check('the progress counts the questions answered', $p['n_answered'] === 3);
LivePaced::submit($pdo, $session, $ben, true, 'auto');
check('a copy handed in by the end of the time says so', $pdo->query("SELECT submit_mode FROM live_eval_registrations WHERE id = $ben")->fetchColumn() === 'auto');
$ev = $pdo->query("SELECT event FROM live_eval_events WHERE session_id = $sid ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
check('the timeline records the hand-ins', in_array('submitted', $ev, true) && in_array('auto_submitted', $ev, true));

// old marks (before the migration) are read from the percentage
$pdo->exec("UPDATE live_eval_registrations SET correct_count = NULL, question_count = NULL, score = 75 WHERE id = $cy");
$p = array_values(array_filter(LiveParticipants::rows($pdo, $sid), fn($r) => (int)$r['id'] === $cy))[0];
check('a mark stored only as a percentage still reads as a count', $p['mark'] === 3 && $p['total'] === 4);
$pdo->exec("UPDATE live_eval_registrations SET score = NULL WHERE id = $cy");

// ---- presence follows the database clock
$pdo->exec("UPDATE live_eval_registrations SET last_activity = NOW() - INTERVAL 5 SECOND WHERE id = $cy");
$st = fn(int $id) => array_values(array_filter(LiveParticipants::rows($pdo, $sid), fn($r) => (int)$r['id'] === $id))[0];
check('seen 5 seconds ago is online', $st($cy)['status'] === 'online' && $st($cy)['idle_seconds'] >= 4 && $st($cy)['idle_seconds'] <= 7);
$pdo->exec("UPDATE live_eval_registrations SET last_activity = NOW() - INTERVAL 40 SECOND WHERE id = $cy");
check('seen 40 seconds ago is not online', $st($cy)['status'] !== 'online');
$pdo->exec("SET time_zone = '+05:00'"); $pdo->exec("UPDATE live_eval_registrations SET last_activity = NOW() WHERE id = $cy");
check('the session time zone does not change the verdict (no clash with the web server clock)', $st($cy)['status'] === 'online');
$pdo->exec("SET time_zone = SYSTEM");
LiveEvents::seen($pdo, $cy);
check('the first sight is recorded once', $pdo->query("SELECT first_seen IS NOT NULL FROM live_eval_registrations WHERE id = $cy")->fetchColumn() == 1);

// ---- archives (the session is over now)
$pdo->exec("UPDATE live_eval_sessions SET end_time = NOW() - INTERVAL 1 HOUR WHERE id = $sid");
$a1 = Archive::snapshotSession($pdo, $sid, 'final');
$a2 = Archive::snapshotSession($pdo, $sid, 'manual');
check('a snapshot is taken, and the same content is not stored twice', $a1 > 0 && $a1 === $a2);
$open = Archive::open($pdo, $a1, $teacher);
check('it holds the questions with their references, the registrations, the answers and the timeline', $open && count($open['data']['questions']) === 4 && count($open['data']['registrations']) === 3 && count($open['data']['answers']) >= 3 && count($open['data']['events']) >= 2 && str_contains(json_encode($open['data']['questions']), 'SECRET ref'));
check('another teacher cannot open it', Archive::open($pdo, $a1, $teacher + 99999) === null);
$pdo->exec("UPDATE live_eval_registrations SET name = 'Cy changed' WHERE id = $cy");
$a3 = Archive::snapshotSession($pdo, $sid, 'manual');
check('a change gives a new snapshot', $a3 !== null && $a3 !== $a1);
$pdo->prepare("UPDATE archives SET payload = :p WHERE id = :i")->execute(['p' => gzencode('{"tampered":true}'), 'i' => $a3]);
check('a tampered archive is refused', Archive::open($pdo, $a3, $teacher) === null);
$ar = Archive::snapshotRegistration($pdo, $ben, 'excluded');
$o = Archive::open($pdo, (int)$ar, $teacher);
check('an excluded participant is archived with their copy', $o && ($o['data']['registration']['name'] ?? '') === 'Ben');
$pdo->exec("DELETE FROM live_eval_sessions WHERE id = $sid");
check('deleting the session keeps its archives', Archive::open($pdo, $a1, $teacher) !== null);
check('and they are listed for the teacher', count(array_filter(Archive::listFor($pdo, $teacher), fn($r) => (int)$r['id'] === $a1)) === 1);

// ---- the logs are rotated into the archives, then leave the live tables
$pdo->exec("INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (NULL, 'zz_arch_test', 'old', '127.0.0.1', '2001-01-15 10:00:00'), (NULL, 'zz_arch_test', 'old2', '127.0.0.1', '2001-01-20 10:00:00'), (NULL, 'zz_arch_test', 'recent', '127.0.0.1', NOW())");
$pdo->exec("INSERT INTO server_errors (context, message, where_at, hash, last_at) VALUES ('zz_arch_test', 'old error', 'x.php:1', 'zzarchhash00001', '2001-01-10 10:00:00')");
$moved = Archive::rotateLogs($pdo);
check('old audit rows and old errors are moved', $moved >= 3);
check('they are gone from the live tables, the recent one stays', (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'zz_arch_test'")->fetchColumn() === 1 && (int)$pdo->query("SELECT COUNT(*) FROM server_errors WHERE context = 'zz_arch_test'")->fetchColumn() === 0);
$la = $pdo->query("SELECT id FROM archives WHERE kind = 'logs' AND title LIKE 'audit_logs 2001-01'")->fetchColumn();
$lo = $la ? Archive::open($pdo, (int)$la) : null;
check('the archived rows can be read back', $lo && $lo['meta']['items'] == 2 && count($lo['data']['rows']) === 2);

// ---- server errors are kept in the database with a counter
require_once __DIR__ . '/../../auth.php';
logServerError(new RuntimeException('zz arch boom'), 'zz_arch_test'); logServerError(new RuntimeException('zz arch boom'), 'zz_arch_test');
$e = $pdo->query("SELECT hits FROM server_errors WHERE context = 'zz_arch_test' AND message = 'zz arch boom'")->fetchColumn();
check('a server error is stored once with its counter', (int)$e >= 1);

echo "archive: $passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL: $f\n"; }
exit($failed ? 1 : 0);
