<?php
declare(strict_types=1);

/**
 * The administrator's power over accounts: creating any account (student, teacher, administrator; typed password, generated password
 * or an invitation), and deleting any account, which only the administrator can do (guard rails, teachers' courses handed over or
 * deleted on purpose, files removed). Needs the app running (default http://127.0.0.1:8123) and the database from .env. Creates
 * throw-away rows (zz.admtest.*@test.local: they never receive mail) and removes them.
 *
 *   php tests/integration/admin_users.php [http://127.0.0.1:8123]
 */

putenv('SV_NO_BACKGROUND_MAIL=1');
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../Mailer.php';
require_once __DIR__ . '/../../lib/AdminUsers.php';
require_once __DIR__ . '/../../lib/MediaStore.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }

// ---- the real administrators are put aside for the "last administrator" test and restored whatever happens
$realAdmins = $pdo->query("SELECT id FROM users WHERE role = 'promoter' AND email NOT LIKE 'zz.admtest.%' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
$pw = 'Adm-test-9Zq!';
$pdo->exec("DELETE FROM users WHERE email LIKE 'zz.admtest.%@test.local'");
$cleanup = function () use ($pdo, $realAdmins) {
    foreach ($realAdmins as $id) { $pdo->exec("UPDATE users SET is_active = 1 WHERE id = " . (int)$id); }
    $pdo->exec("DELETE FROM courses WHERE title LIKE 'ZZ adm %'");
    $pdo->exec("DELETE FROM live_eval_sessions WHERE title LIKE 'ZZ adm %'");
    $pdo->exec("DELETE FROM users WHERE email LIKE 'zz.admtest.%@test.local'");
    $pdo->exec("DELETE FROM email_outbox WHERE to_email LIKE 'zz.admtest.%'");
    $pdo->exec("DELETE FROM api_keys WHERE label LIKE 'ZZ adm %'");
    $pdo->exec("DELETE FROM newsletter_campaigns WHERE subject LIKE 'ZZ adm %'");
    $pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'adminusers:%'");
    foreach (glob(sys_get_temp_dir() . '/zz_jar_adm_*') ?: [] as $f) { @unlink($f); }
};
register_shutdown_function($cleanup);

$mk = function (string $n, string $role, array $o = []) use ($pdo, $pw): int {
    $pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved,plan_id,matricule,tour_seen_at) VALUES (?,?,?,?,NOW(),?,1,NULL,?,NOW())")
        ->execute(["zz.admtest.$n@test.local", password_hash($pw, PASSWORD_DEFAULT), ucfirst($n), $role, $o['active'] ?? 1, $o['matricule'] ?? null]);
    return (int)$pdo->lastInsertId();
};
$adminA = $mk('adminA', 'promoter'); $adminB = $mk('adminB', 'promoter');
$t1 = $mk('teacher1', 'teacher'); $t2 = $mk('teacher2', 'teacher'); $tIdle = $mk('teacheridle', 'teacher', ['active' => 0]);
$s1 = $mk('student1', 'student', ['matricule' => '25G101']); $s2 = $mk('student2', 'student', ['matricule' => '25G102']);

function http(string $jar, string $base, string $path, array $post = [], array $headers = []): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $headers]);
    if ($post !== []) { curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]); }
    $body = (string)curl_exec($ch);
    return ['code' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'json' => json_decode($body, true), 'body' => $body];
}
$jar = fn(string $n) => sys_get_temp_dir() . "/zz_jar_adm_$n";
$login = function (string $name, string $email = null, string $pass = null) use ($base, $jar, $pw): array {
    @unlink($jar($name));
    return http($jar($name), $base, '/login-action.php', ['email' => $email ?? "zz.admtest.$name@test.local", 'password' => $pass ?? $pw]);
};
foreach (['adminA', 'adminB', 'teacher1', 'student1'] as $n) { check("$n signs in", ($login($n)['json']['success'] ?? false) === true); }
$create = fn(string $who, array $p, array $h = []) => http($jar($who), $base, '/promoter/create-user.php', $p, $h);
$manage = fn(string $who, array $p, array $h = []) => http($jar($who), $base, '/promoter/manage-user.php', $p, $h);
$one = fn(string $sql) => $pdo->query($sql)->fetchColumn();

// ================================================================ creating accounts
$mod = (int)$one("SELECT id FROM modules LIMIT 1");
$mkCourse = function (int $teacher, string $title) use ($pdo, $mod): int {
    $pdo->prepare("INSERT INTO courses (module_id,teacher_id,created_by,title,description,svg_icon,is_published) VALUES (?,?,?,?,'d','',1)")->execute([$mod, $teacher, $teacher, $title]);
    return (int)$pdo->lastInsertId();
};
$cA = $mkCourse($t1, 'ZZ adm course A'); $cB = $mkCourse($t1, 'ZZ adm course B');

$r = $create('adminA', ['name' => 'Marie Fotso', 'email' => 'zz.admtest.newstudent@test.local', 'role' => 'student', 'mode' => 'password', 'password' => 'Un-mot-de-passe-9!', 'matricule' => '26a 123', 'lang' => 'en', 'courses' => [$cA, $cB]]);
check('the administrator creates a student', ($r['json']['success'] ?? false) === true && $r['json']['user']['role'] === 'student' && $r['json']['enrolled'] === 2, $r['body']);
$row = $pdo->query("SELECT * FROM users WHERE email = 'zz.admtest.newstudent@test.local'")->fetch(PDO::FETCH_ASSOC);
check('the account is ready at once: confirmed, active, approved, with the matricule normalised, the language and a plan', $row && $row['email_verified_at'] !== null && (int)$row['is_active'] === 1 && (int)$row['is_approved'] === 1 && $row['matricule'] === '26A123' && $row['lang'] === 'en' && $row['plan_id'] !== null && $row['signup_source'] === 'admin');
check('...and enrolled in the chosen courses', (int)$one("SELECT COUNT(*) FROM enrollments WHERE student_id = {$row['id']}") === 2);
check('the typed password is never sent back', !str_contains($r['body'], 'Un-mot-de-passe'));
check('the new student can sign in with it', ($login('newstudent', 'zz.admtest.newstudent@test.local', 'Un-mot-de-passe-9!')['json']['success'] ?? false) === true);
check('the audit log records who created whom', (int)$one("SELECT COUNT(*) FROM audit_logs WHERE action = 'user_created_by_admin' AND user_id = $adminA AND details LIKE '%newstudent%'") === 1);

$r = $create('adminA', ['name' => 'Paul Tchoua', 'email' => 'zz.admtest.newteacher@test.local', 'role' => 'teacher', 'mode' => 'generate']);
$gen = (string)($r['json']['password'] ?? '');
check('a teacher with a generated password: shown once, strong, and it works', ($r['json']['success'] ?? false) && strlen($gen) >= 12 && PasswordPolicy::check($gen, 'x@y.z') === null && ($login('newteacher', 'zz.admtest.newteacher@test.local', $gen)['json']['success'] ?? false) === true, $r['body']);
check('an administrator-created teacher is approved and reaches the teacher dashboard', (int)$one("SELECT is_approved FROM users WHERE email = 'zz.admtest.newteacher@test.local'") === 1 && http($jar('newteacher'), $base, '/teacher/dashboard.php')['code'] === 200);
check('two generated passwords differ', AdminUsers::generatePassword() !== AdminUsers::generatePassword());

$r = $create('adminA', ['name' => 'Chi Nkoa', 'email' => 'zz.admtest.invited@test.local', 'role' => 'student', 'mode' => 'invite']);
$uid = (int)$one("SELECT id FROM users WHERE email = 'zz.admtest.invited@test.local'");
$tok = $pdo->query("SELECT TIMESTAMPDIFF(HOUR, NOW(), expires_at) h FROM password_reset_tokens WHERE user_id = $uid AND used_at IS NULL")->fetch(PDO::FETCH_ASSOC);
check('an invitation: no password anywhere in the answer, a link valid about 72 hours', ($r['json']['success'] ?? false) && array_key_exists('password', $r['json']) && $r['json']['password'] === null && $r['json']['invited'] === true && $tok && (int)$tok['h'] >= 70 && (int)$tok['h'] <= 72, $r['body']);
check('nobody can sign in to an invited account before the person chooses a password', ($login('invited', 'zz.admtest.invited@test.local', 'Un-mot-de-passe-9!')['json']['success'] ?? true) === false);
$mails = [];
Mailer::$capture = function ($to, $subject, $html) use (&$mails) { $mails[] = ['to' => $to, 'subject' => $subject, 'html' => $html]; };
$x = AdminUsers::create($pdo, $adminA, ['name' => 'Eve Mbarga', 'email' => 'zz.admtest.invite2@test.local', 'role' => 'student', 'mode' => 'invite', 'lang' => 'fr', 'courses' => [$cA]]);
$m = $mails[0] ?? ['html' => '', 'subject' => ''];
check('the invitation email: link to choose the password, validity, role and the courses', $x['ok'] && str_contains($m['html'], '/reset-password.php?token=') && str_contains($m['html'], '72 heures') && str_contains($m['html'], 'apprenant') && str_contains($m['html'], 'ZZ adm course A') && str_contains($m['subject'], 'invitation'), $m['subject']);
$mails = [];
$x = AdminUsers::create($pdo, $adminA, ['name' => 'Joe Eto', 'email' => 'zz.admtest.welcome@test.local', 'role' => 'teacher', 'mode' => 'password', 'password' => 'Autre-mot-de-passe-7!', 'welcome' => true, 'lang' => 'en']);
check('"account ready" email in English, without any password in it', $x['ok'] && count($mails) === 1 && str_contains($mails[0]['html'], 'Your account is ready') && !str_contains($mails[0]['html'], 'Autre-mot-de-passe') && !str_contains($mails[0]['html'], '/reset-password.php'));
Mailer::$capture = null;
$x = AdminUsers::create($pdo, $adminA, ['name' => 'Ana Admin', 'email' => 'zz.admtest.newadmin@test.local', 'role' => 'promoter', 'mode' => 'password', 'password' => 'Encore-un-autre-5!']);
check('another administrator can be created', $x['ok'] && $one("SELECT role FROM users WHERE email = 'zz.admtest.newadmin@test.local'") === 'promoter' && $one("SELECT plan_id FROM users WHERE email = 'zz.admtest.newadmin@test.local'") === null);

$bad = fn(array $p) => $create('adminA', $p + ['name' => 'Test Test', 'email' => 'zz.admtest.bad@test.local', 'role' => 'student', 'mode' => 'password', 'password' => 'Un-mot-de-passe-9!'])['json'];
check('invalid email', ($bad(['email' => 'pas-un-email'])['error'] ?? '') === 'email');
check('the same address twice, even in other letter case', ($bad(['email' => 'ZZ.ADMTEST.NEWSTUDENT@test.local'])['error'] ?? '') === 'email_taken');
check('a weak password is refused', ($bad(['password' => 'password'])['error'] ?? '') === 'password' && ($bad(['password' => 'court'])['error'] ?? '') === 'password');
check('only the three real roles', ($bad(['role' => 'admin'])['error'] ?? '') === 'role' && ($bad(['role' => 'scolarite'])['error'] ?? '') === 'role' && ($bad(['role' => ''])['error'] ?? '') === 'role');
check('a bad, a future and a taken matricule are refused', ($bad(['matricule' => 'abc'])['error'] ?? '') === 'matricule' && ($bad(['matricule' => '99A123'])['error'] ?? '') === 'matricule' && ($bad(['matricule' => '25G101'])['error'] ?? '') === 'matricule');
check('a name that is too short', ($bad(['name' => 'A'])['error'] ?? '') === 'name');
check('an unknown password mode', ($bad(['mode' => 'magic'])['error'] ?? '') === 'mode');
check('nothing was created by the refused requests', (int)$one("SELECT COUNT(*) FROM users WHERE email = 'zz.admtest.bad@test.local'") === 0);
check('only an administrator can create accounts', $create('teacher1', ['name' => 'Hack Er', 'email' => 'zz.admtest.hack@test.local', 'role' => 'promoter', 'mode' => 'generate'])['code'] === 403 && $create('student1', ['name' => 'Hack Er', 'email' => 'zz.admtest.hack@test.local', 'role' => 'promoter', 'mode' => 'generate'])['code'] === 403
    && http(sys_get_temp_dir() . '/zz_jar_adm_anon', $base, '/promoter/create-user.php', ['name' => 'x y', 'email' => 'zz.admtest.hack@test.local', 'role' => 'student', 'mode' => 'generate'])['code'] === 403 && (int)$one("SELECT COUNT(*) FROM users WHERE email = 'zz.admtest.hack@test.local'") === 0);
check('a request that comes from another website is refused', $create('adminA', ['name' => 'Cross Site', 'email' => 'zz.admtest.cross@test.local', 'role' => 'student', 'mode' => 'generate'], ['Sec-Fetch-Site: cross-site'])['code'] === 403 && (int)$one("SELECT COUNT(*) FROM users WHERE email = 'zz.admtest.cross@test.local'") === 0);

// ================================================================ deleting: who may, and the only place that does it
$files = [];
$root = realpath(__DIR__ . '/../..');
$scan = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($scan as $f) {
    $p = $f->getPathname();
    if (!str_ends_with($p, '.php') || preg_match('~/(tests|vendor|node_modules|\.git|uploads)/~', substr($p, strlen($root)))) { continue; }
    if (preg_match('/DELETE\s+(?:\w+\s+)?FROM\s+`?users`?\b/i', (string)file_get_contents($p))) { $files[] = substr($p, strlen($root) + 1); }
}
check('exactly one place in the application deletes users: the administrator library', $files === ['lib/AdminUsers.php'], json_encode($files));
check('a teacher cannot delete a user', $manage('teacher1', ['action' => 'delete', 'user_id' => $s1])['code'] === 403 && (int)$one("SELECT COUNT(*) FROM users WHERE id = $s1") === 1);
check('a student cannot delete a user, not even themselves', $manage('student1', ['action' => 'delete', 'user_id' => $s1])['code'] === 403 && $manage('student1', ['action' => 'delete', 'user_id' => $s2])['code'] === 403 && (int)$one("SELECT COUNT(*) FROM users WHERE id IN ($s1, $s2)") === 2);
check('a cross-site request to delete is refused', $manage('adminA', ['action' => 'delete', 'user_id' => $s2], ['Sec-Fetch-Site: cross-site'])['code'] === 403 && (int)$one("SELECT COUNT(*) FROM users WHERE id = $s2") === 1);
check('an administrator cannot delete their own account', ($manage('adminA', ['action' => 'delete', 'user_id' => $adminA])['json']['success'] ?? true) === false && (int)$one("SELECT COUNT(*) FROM users WHERE id = $adminA") === 1);
check('an unknown user', ($manage('adminA', ['action' => 'delete', 'user_id' => 99999999])['json']['error'] ?? '') === 'not_found');
$imp = $manage('adminA', ['action' => 'impact', 'user_id' => $t1])['json'];
check('the impact of deleting a teacher is shown: courses, sessions, who is enrolled', ($imp['success'] ?? false) && $imp['impact']['counts']['courses'] === 2 && $imp['impact']['needs_decision'] === true && count($imp['impact']['teachers']) >= 1);
check('only an administrator can see it', $manage('teacher1', ['action' => 'impact', 'user_id' => $t1])['code'] === 403);

// ---- a student
$avatarFile = 'zz_adm_avatar.png'; $im = imagecreatetruecolor(8, 8); ob_start(); imagepng($im); $png = (string)ob_get_clean();
file_put_contents(MediaStore::path('avatar', $avatarFile), $png); MediaStore::persist($pdo, 'avatar/' . $avatarFile, MediaStore::path('avatar', $avatarFile), 'image/png');
$pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?")->execute([$avatarFile, $s2]);
$pdo->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?,?)")->execute([$s2, $cA]);
$pdo->prepare("INSERT INTO chapters (course_id,title,sort_order) VALUES (?,'Ch',1)")->execute([$cA]); $ch = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO lessons (chapter_id,title,content_type,text_content,sort_order,has_assignment,is_compulsory) VALUES (?,'L','text','x',1,1,0)")->execute([$ch]); $les = (int)$pdo->lastInsertId();
$subFile = 'zz_adm_sub.txt'; file_put_contents(MediaStore::path('assignment', $subFile), 'devoir'); MediaStore::persist($pdo, 'assignment/' . $subFile, MediaStore::path('assignment', $subFile), 'text/plain');
$pdo->prepare("INSERT INTO lesson_assignment_submissions (lesson_id,student_id,student_name,student_matricule,submission_type,submitted_file_path,submitted_file_name,submitted_at,attempt_count) VALUES (?,?,?,?, 'file',?,?,NOW(),1)")->execute([$les, $s2, 'S', 'm', $subFile, 'devoir.txt']);
$pdo->prepare("INSERT INTO lesson_comments (lesson_id,user_id,comment_text) VALUES (?,?,'Une question')")->execute([$les, $s2]);
$pdo->prepare("INSERT INTO email_outbox (user_id,to_email,template,args,dedupe_key,status) VALUES (?,?,'live_scheduled','{}','zz-adm-1','queued')")->execute([$s2, 'zz.admtest.student2@test.local']);
$pdo->prepare("INSERT INTO live_eval_sessions (course_id,teacher_id,title,session_code,start_time,end_time,default_time_limit,status) VALUES (?,?,'ZZ adm session',?,NOW(),NOW() + INTERVAL 1 HOUR,30,1)")->execute([$cA, $t1, 'ZA' . bin2hex(random_bytes(3))]); $sess = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO live_eval_registrations (session_id,name,email,student_id,score) VALUES (?,?,?,?,75)")->execute([$sess, 'Student2', 'zz.admtest.student2@test.local', $s2]);
$r = $manage('adminA', ['action' => 'delete', 'user_id' => $s2]);
check('the administrator deletes a student', ($r['json']['success'] ?? false) === true && (int)$one("SELECT COUNT(*) FROM users WHERE id = $s2") === 0, $r['body']);
check('their enrolments, hand-ins and comments are gone', (int)$one("SELECT COUNT(*) FROM enrollments WHERE student_id = $s2") === 0 && (int)$one("SELECT COUNT(*) FROM lesson_assignment_submissions WHERE student_id = $s2") === 0 && (int)$one("SELECT COUNT(*) FROM lesson_comments WHERE user_id = $s2") === 0);
check('their files are removed (avatar, hand-in), from the disk and from the database copy', !is_file(MediaStore::path('avatar', $avatarFile)) && !is_file(MediaStore::path('assignment', $subFile)) && (int)$one("SELECT COUNT(*) FROM media_chunks WHERE media_key IN ('avatar/$avatarFile','assignment/$subFile')") === 0);
check('nothing queued reaches them any more', (int)$one("SELECT COUNT(*) FROM email_outbox WHERE dedupe_key = 'zz-adm-1'") === 0);
check('their exam result stays in the teacher\'s records, without an account', (int)$one("SELECT COUNT(*) FROM live_eval_registrations WHERE session_id = $sess AND student_id IS NULL AND score = 75") === 1);
check('the audit log says who deleted whom', (int)$one("SELECT COUNT(*) FROM audit_logs WHERE action = 'user_deleted' AND user_id = $adminA AND details LIKE '%student2%'") === 1);
check('the others were not touched', (int)$one("SELECT COUNT(*) FROM users WHERE id IN ($s1, $t1, $t2)") === 3);

// ---- a teacher who owns courses
$r = $manage('adminA', ['action' => 'delete', 'user_id' => $t1]);
check('a teacher with courses is NOT deleted by accident: the administrator must decide', ($r['json']['success'] ?? true) === false && ($r['json']['error'] ?? '') === 'needs_decision' && (int)$one("SELECT COUNT(*) FROM users WHERE id = $t1") === 1 && (int)$one("SELECT COUNT(*) FROM courses WHERE teacher_id = $t1") === 2, $r['body']);
check('the courses cannot be handed to a student, to an inactive teacher or to the teacher himself', ($manage('adminA', ['action' => 'delete', 'user_id' => $t1, 'reassign_to' => $s1])['json']['error'] ?? '') === 'bad_reassign' && ($manage('adminA', ['action' => 'delete', 'user_id' => $t1, 'reassign_to' => $tIdle])['json']['error'] ?? '') === 'bad_reassign' && ($manage('adminA', ['action' => 'delete', 'user_id' => $t1, 'reassign_to' => $t1])['json']['error'] ?? '') === 'bad_reassign');
$pdo->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?,?)")->execute([$s1, $cA]);
$pdo->prepare("INSERT INTO api_keys (key_hash,label,created_by) VALUES (?,?,?)")->execute([str_repeat('a', 64), 'ZZ adm key', $t1]);
$r = $manage('adminA', ['action' => 'delete', 'user_id' => $t1, 'reassign_to' => $t2]);
check('handing everything to another teacher: the teacher goes, courses, sessions and students stay', ($r['json']['success'] ?? false) === true && (int)$one("SELECT COUNT(*) FROM users WHERE id = $t1") === 0
    && (int)$one("SELECT COUNT(*) FROM courses WHERE teacher_id = $t2 AND title LIKE 'ZZ adm course%'") === 2 && (int)$one("SELECT COUNT(*) FROM live_eval_sessions WHERE id = $sess AND teacher_id = $t2") === 1 && (int)$one("SELECT COUNT(*) FROM enrollments WHERE student_id = $s1 AND course_id = $cA") === 1
    && (int)$one("SELECT COUNT(*) FROM live_eval_registrations WHERE session_id = $sess") === 1, $r['body']);
check('what the teacher created for the platform passes to the administrator who deleted', (int)$one("SELECT created_by FROM api_keys WHERE label = 'ZZ adm key'") === $adminA);

$c3 = $mkCourse($t2, 'ZZ adm course C');
$pdo->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?,?)")->execute([$s1, $c3]);
$pdo->prepare("INSERT INTO live_eval_sessions (course_id,teacher_id,title,session_code,start_time,end_time,default_time_limit,status) VALUES (?,?,'ZZ adm session two',?,NOW(),NOW() + INTERVAL 1 HOUR,30,1)")->execute([$c3, $t2, 'ZB' . bin2hex(random_bytes(3))]); $sess2 = (int)$pdo->lastInsertId();
$r = $manage('adminA', ['action' => 'delete', 'user_id' => $t2, 'cascade' => '1']);
check('deleting a teacher WITH their courses, on purpose: courses, sessions and enrolments go too', ($r['json']['success'] ?? false) === true && (int)$one("SELECT COUNT(*) FROM courses WHERE id IN ($cA, $cB, $c3)") === 0 && (int)$one("SELECT COUNT(*) FROM live_eval_sessions WHERE id IN ($sess, $sess2)") === 0 && (int)$one("SELECT COUNT(*) FROM enrollments WHERE student_id = $s1 AND course_id IN ($cA, $c3)") === 0, $r['body']);
check('the student of those courses is still there', (int)$one("SELECT COUNT(*) FROM users WHERE id = $s1") === 1);
$teacherNoCourses = $mk('teacher3', 'teacher');
check('a teacher with nothing to hand over is deleted straight away', ($manage('adminA', ['action' => 'delete', 'user_id' => $teacherNoCourses])['json']['success'] ?? false) === true);

// ---- administrators
$pdo->prepare("INSERT INTO newsletter_campaigns (subject, body_html, sent_by) VALUES ('ZZ adm campaign', '<p>x</p>', ?)")->execute([$adminB]);
$r = $manage('adminA', ['action' => 'delete', 'user_id' => $adminB]);
check('an administrator can delete another administrator; their campaigns pass to the one who deletes', ($r['json']['success'] ?? false) === true && (int)$one("SELECT sent_by FROM newsletter_campaigns WHERE subject = 'ZZ adm campaign'") === $adminA, $r['body']);
foreach ($realAdmins as $id) { $pdo->exec("UPDATE users SET is_active = 0 WHERE id = " . (int)$id); }
$pdo->exec("UPDATE users SET is_active = 0 WHERE email = 'zz.admtest.newadmin@test.local'");   // now adminA is the only active administrator
$adminC = $mk('adminC', 'promoter', ['active' => 0]);
check('the last active administrator cannot be deleted (by anybody)', (AdminUsers::delete($pdo, $adminC, $adminA)['error'] ?? '') === 'last_admin' && (int)$one("SELECT COUNT(*) FROM users WHERE id = $adminA") === 1);
foreach ($realAdmins as $id) { $pdo->exec("UPDATE users SET is_active = 1 WHERE id = " . (int)$id); }

echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
