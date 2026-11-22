<?php
declare(strict_types=1);

/**
 * The super teacher role and the access rules around it (needs the app running, default http://127.0.0.1:8123).
 *   - a super teacher sees every course, read only: nothing of another teacher's course can be changed;
 *   - they create their own quizzes on any course, and only they can change those quizzes;
 *   - an ordinary teacher still sees only their own courses, and cannot write into another teacher's quiz
 *     (adding a question to a session that is not yours used to be accepted).
 * Throw-away rows zz.super.*@test.local are removed at the end.
 *
 *   php tests/integration/super_teacher.php [http://127.0.0.1:8123]
 */

require_once __DIR__ . '/../../auth.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }

$pw = 'Super-test-7Kp!';
$tag = 'zz.super';
$cleanup = function () use ($pdo, $tag) {
    $pdo->exec("DELETE s FROM live_eval_sessions s JOIN courses c ON c.id = s.course_id WHERE c.title LIKE 'ZZ super%'");
    $pdo->exec("DELETE FROM courses WHERE title LIKE 'ZZ super%'");
    $pdo->exec("DELETE FROM users WHERE email LIKE '$tag.%@test.local'");
};
$cleanup();
register_shutdown_function($cleanup);

$mod = (int)$pdo->query("SELECT id FROM modules LIMIT 1")->fetchColumn();
$mk = function (string $n, string $role) use ($pdo, $pw, $tag): int {
    $pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved,plan_id,tour_seen_at) VALUES (?,?,?,?,NOW(),1,1,NULL,NOW())")
        ->execute(["$tag.$n@test.local", password_hash($pw, PASSWORD_DEFAULT), 'ZZ ' . ucfirst($n), $role]);
    return (int)$pdo->lastInsertId();
};
$owner = $mk('owner', 'teacher');
$other = $mk('other', 'teacher');
$super = $mk('boss', 'super_teacher');
$mkCourse = function (string $title, int $t) use ($pdo, $mod): int {
    $pdo->prepare("INSERT INTO courses (module_id,teacher_id,created_by,title,description,svg_icon,is_published) VALUES (?,?,?,?,'d','',1)")->execute([$mod, $t, $t, $title]);
    return (int)$pdo->lastInsertId();
};
$courseA = $mkCourse('ZZ super course A', $owner);
$courseB = $mkCourse('ZZ super course B', $other);
$pdo->prepare("INSERT INTO live_eval_sessions (course_id,teacher_id,title,session_code,start_time,end_time,default_time_limit,status) VALUES (?,?,'ZZ super owner quiz',?,NOW() + INTERVAL 1 DAY,NOW() + INTERVAL 2 DAY,30,0)")
    ->execute([$courseA, $owner, 'zzsp' . bin2hex(random_bytes(3))]);
$ownerQuiz = (int)$pdo->lastInsertId();

// ---- the helpers
check('the role column accepts super_teacher', $pdo->query("SELECT role FROM users WHERE id = $super")->fetchColumn() === 'super_teacher');
check('a super teacher counts as a teacher', isTeacherRole('super_teacher') && isSuperTeacher('super_teacher') && !isSuperTeacher('teacher'));
check('a super teacher can view any course', canViewCourse($pdo, $super, 'super_teacher', $courseA) && canViewCourse($pdo, $super, 'super_teacher', $courseB));
check('a super teacher cannot edit another teacher\'s course', !canEditCourse($pdo, $super, 'super_teacher', $courseA));
check('the owner can view and edit their course', canViewCourse($pdo, $owner, 'teacher', $courseA) && canEditCourse($pdo, $owner, 'teacher', $courseA));
check('another teacher can neither view nor edit it', !canViewCourse($pdo, $other, 'teacher', $courseA) && !canEditCourse($pdo, $other, 'teacher', $courseA));
check('a student is never a teacher', !isTeacherRole('student') && !canEditCourse($pdo, $owner, 'student', $courseA));

// ---- over HTTP
$tmp = sys_get_temp_dir();
function http(string $jar, string $base, string $path, array $post = []): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60, CURLOPT_HEADER => true]);
    if ($post !== []) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
    }
    $raw = (string)curl_exec($ch);
    $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $head = substr($raw, 0, $hs);
    preg_match('/^Location:\s*(\S+)/mi', $head, $m);
    return ['code' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'location' => $m[1] ?? '', 'body' => substr($raw, $hs)];
}
$login = function (string $n) use ($base, $tmp, $pw, $tag): array {
    $jar = "$tmp/zz_super_$n"; @unlink($jar);
    $r = http($jar, $base, '/login-action.php', ['email' => "$tag.$n@test.local", 'password' => $pw]);
    return [$jar, $r];
};
// a POST to the dashboard, with the CSRF token of the page
$dash = function (string $jar, int $course, array $post) use ($base): array {
    $page = http($jar, $base, "/teacher/dashboard.php?course_id=$course");
    preg_match('/name="csrf_token" value="([^"]+)"/', $page['body'], $m);
    return http($jar, $base, "/teacher/dashboard.php?course_id=$course&action=" . urlencode($post['action']), $post + ['csrf_token' => $m[1] ?? '']);
};
$count = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();

[$jS, $r] = $login('boss');
$lj = json_decode($r['body'], true) ?: [];
check('a super teacher is sent to the teacher dashboard after login', str_contains($r['location'] . ($lj['redirect'] ?? ''), '/teacher/dashboard.php'), $r['body']);
[$jO] = $login('owner');
[$jX] = $login('other');

$p = http($jS, $base, '/teacher/dashboard.php');
check('the teacher dashboard opens for a super teacher', $p['code'] === 200 && str_contains($p['body'], 'ZZ super course A') && str_contains($p['body'], 'ZZ super course B'), (string)$p['code']);
$p = http($jS, $base, "/teacher/dashboard.php?course_id=$courseA");
check('another teacher\'s course opens read only, with its quizzes', $p['code'] === 200 && str_contains($p['body'], 'ZZ super owner quiz') && preg_match('/Lecture seule|Read only/', $p['body']) === 1);
$p = http($jX, $base, "/teacher/dashboard.php?course_id=$courseA");
check('an ordinary teacher does not see another teacher\'s course', !str_contains($p['body'], 'ZZ super owner quiz'));

// nothing of the course can be changed by the super teacher
$chapters = $count("SELECT COUNT(*) FROM chapters WHERE course_id = $courseA");
$r = $dash($jS, $courseA, ['action' => 'add_chapter', 'chapter_title' => 'ZZ intrusion']);
check('adding a chapter to another teacher\'s course is refused', str_contains($r['location'], 'error=read_only') && $count("SELECT COUNT(*) FROM chapters WHERE course_id = $courseA") === $chapters, $r['location']);
$r = $dash($jS, $courseA, ['action' => 'edit_course', 'course_title' => 'ZZ super hijacked', 'module_id' => $mod]);
check('editing another teacher\'s course is refused', $pdo->query("SELECT title FROM courses WHERE id = $courseA")->fetchColumn() === 'ZZ super course A');
$dash($jS, $courseA, ['action' => 'delete_live_session', 'session_id' => $ownerQuiz]);
check('deleting the owner\'s quiz is refused', $count("SELECT COUNT(*) FROM live_eval_sessions WHERE id = $ownerQuiz") === 1);
$q = ['action' => 'add_live_question', 'session_id' => $ownerQuiz, 'question_type' => 'mcq', 'question_text' => 'ZZ injected', 'option_a' => 'a', 'option_b' => 'b', 'option_c' => 'c', 'option_d' => 'd', 'correct_option' => 'A'];
$dash($jS, $courseA, $q);
check('adding a question to the owner\'s quiz is refused (super teacher)', $count("SELECT COUNT(*) FROM live_eval_questions WHERE session_id = $ownerQuiz") === 0);
$dash($jX, $courseB, $q);
check('adding a question to another teacher\'s quiz from your own course is refused (was accepted before)', $count("SELECT COUNT(*) FROM live_eval_questions WHERE session_id = $ownerQuiz") === 0);

// but the super teacher creates their own quiz on that course, and fills it
$r = $dash($jS, $courseA, ['action' => 'add_live_session', 'live_title' => 'ZZ super boss quiz', 'eval_mode' => 'async', 'default_time_limit' => 45, 'pace' => 'sync']);
$mine = (int)$pdo->query("SELECT id FROM live_eval_sessions WHERE title = 'ZZ super boss quiz' AND course_id = $courseA AND teacher_id = $super")->fetchColumn();
check('a super teacher creates their own quiz on another teacher\'s course', $mine > 0, $r['location']);
$dash($jS, $courseA, ['session_id' => $mine, 'question_text' => 'ZZ own question'] + $q);
check('and adds questions to it', $mine > 0 && $count("SELECT COUNT(*) FROM live_eval_questions WHERE session_id = $mine") === 1);

// the owner sees that quiz on their course, read only
$p = http($jO, $base, "/teacher/dashboard.php?course_id=$courseA");
check('the owner sees the super teacher\'s quiz on their course, read only', str_contains($p['body'], 'ZZ super boss quiz') && preg_match('/id="room-' . $mine . '"[^>]*data-readonly="1"/s', $p['body']) === 1);
$dash($jO, $courseA, ['action' => 'delete_live_session', 'session_id' => $mine]);
check('the owner cannot delete the super teacher\'s quiz', $count("SELECT COUNT(*) FROM live_eval_sessions WHERE id = $mine") === 1);
$dash($jO, $courseA, ['action' => 'add_chapter', 'chapter_title' => 'ZZ owner chapter']);
check('the owner still edits their own course', $count("SELECT COUNT(*) FROM chapters WHERE course_id = $courseA AND title = 'ZZ owner chapter'") === 1);

foreach (['boss', 'owner', 'other'] as $n) { @unlink("$tmp/zz_super_$n"); }
echo "super teacher: $passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
