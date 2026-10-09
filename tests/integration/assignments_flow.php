<?php
declare(strict_types=1);

/**
 * Assignments, end to end: the rules (deadline, late work, resubmission, file content, identity), marking and feedback, the student's
 * view, who may download a file, and the marks sheet. Needs the app running (default http://127.0.0.1:8123) and the database from
 * .env. Creates throw-away accounts and removes them. Emails are only queued (the test does not start the sender).
 *
 *   php tests/integration/assignments_flow.php [http://127.0.0.1:8123]
 */

putenv('SV_NO_BACKGROUND_MAIL=1');
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/Assignments.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }

// ---------------------------------------------------------------- pure rules
check('dangerous extensions are removed from the list', Assignments::allowedExtensions('pdf, php, exe, DOCX') === ['pdf', 'docx']);
check('an empty list falls back to pdf', Assignments::allowedExtensions('php') === ['pdf']);
$tmp = sys_get_temp_dir();
file_put_contents("$tmp/zz_a.pdf", "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
file_put_contents("$tmp/zz_evil.pdf", "<?php echo 'x';");
check('a real PDF passes', Assignments::contentMatches("$tmp/zz_a.pdf", 'pdf'));
check('PHP source named .pdf is refused', !Assignments::contentMatches("$tmp/zz_evil.pdf", 'pdf'));
$z = new ZipArchive(); $z->open("$tmp/zz_a.docx", ZipArchive::CREATE | ZipArchive::OVERWRITE); $z->addFromString('word/document.xml', '<w/>'); $z->addFromString('[Content_Types].xml', '<t/>'); $z->close();
check('a real DOCX passes', Assignments::contentMatches("$tmp/zz_a.docx", 'docx'));
$z = new ZipArchive(); $z->open("$tmp/zz_b.docx", ZipArchive::CREATE | ZipArchive::OVERWRITE); $z->addFromString('readme.txt', 'hi'); $z->close();
check('a zip that is not a Word document is refused as .docx', !Assignments::contentMatches("$tmp/zz_b.docx", 'docx'));
check('a binary file named .txt is refused', (file_put_contents("$tmp/zz_bin.txt", "a\0b\x01\x02") !== false) && !Assignments::contentMatches("$tmp/zz_bin.txt", 'txt'));
check('a script is never accepted', !Assignments::contentMatches("$tmp/zz_evil.pdf", 'php'));
$open = ['assignment_deadline' => '2999-01-01 00:00:00', 'assignment_allow_late' => 0, 'assignment_allow_resubmit' => 1];
$past = ['assignment_deadline' => '2000-01-01 00:00:00', 'assignment_allow_late' => 0, 'assignment_allow_resubmit' => 1];
$sub = ['submitted_at' => '2026-01-01 10:00:00', 'graded_at' => null, 'revision_requested_at' => null];
check('before the deadline a student can submit', Assignments::state($open, null)['can_submit'] === true);
check('after the deadline, no', Assignments::state($past, null)['reason'] === 'deadline');
check('after the deadline with late work allowed: yes, flagged late', ($s = Assignments::state(array_merge($past, ['assignment_allow_late' => 1]), null)) && $s['can_submit'] && $s['late']);
check('a marked assignment cannot be replaced', Assignments::state($open, ['graded_at' => '2026-01-02'] + $sub)['reason'] === 'graded');
check('unless the teacher asked for a new version, even after the deadline', Assignments::state($past, ['graded_at' => '2026-01-02', 'revision_requested_at' => '2026-01-03 09:00:00'] + $sub)['can_submit'] === true);
check('resubmission can be switched off', Assignments::state(array_merge($open, ['assignment_allow_resubmit' => 0]), $sub)['reason'] === 'locked');
check('scores accept a comma and stay inside the scale', Assignments::parseScore('14,5', 20) === 14.5 && Assignments::parseScore('21', 20) === null && Assignments::parseScore('-1', 20) === null && Assignments::parseScore('abc', 20) === null);

// ---------------------------------------------------------------- fixtures
$pw = 'Flow-test-9Zq!';
$pdo->exec("DELETE FROM users WHERE email LIKE 'zz.asgtest.%@test.local'");
$mod = (int)$pdo->query("SELECT id FROM modules LIMIT 1")->fetchColumn();
$mk = function (string $n, string $role, ?string $mat = null) use ($pdo, $pw): int {
    $pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved,plan_id,matricule,tour_seen_at) VALUES (?,?,?,?,NOW(),1,1,NULL,?,NOW())")
        ->execute(["zz.asgtest.$n@test.local", password_hash($pw, PASSWORD_DEFAULT), ucfirst($n), $role, $mat]);
    return (int)$pdo->lastInsertId();
};
$teacher = $mk('teacher', 'teacher'); $other = $mk('other', 'teacher'); $stu = $mk('student', 'student', '25G777'); $stu2 = $mk('student2', 'student', '25G778'); $nomat = $mk('nomat', 'student');
$pdo->prepare("INSERT INTO courses (module_id,teacher_id,created_by,title,description,svg_icon,is_published) VALUES (?,?,?,'ZZ asg test','d','',1)")->execute([$mod, $teacher, $teacher]);
$course = (int)$pdo->lastInsertId();
foreach ([$stu, $stu2, $nomat] as $u) { $pdo->prepare("INSERT INTO enrollments (student_id,course_id) VALUES (?,?)")->execute([$u, $course]); }
$pdo->prepare("INSERT INTO chapters (course_id,title,sort_order) VALUES (?,'Ch',1)")->execute([$course]);
$chap = (int)$pdo->lastInsertId();
$mkLesson = function (string $title, string $deadline, int $late = 0, int $resub = 1) use ($pdo, $chap): int {
    $pdo->prepare("INSERT INTO lessons (chapter_id,title,content_type,text_content,sort_order,has_assignment,assignment_title,assignment_type,allowed_file_types,assignment_deadline,assignment_max_score,assignment_allow_late,assignment_allow_resubmit)
                   VALUES (?,?,'text','x',1,1,?,'both','pdf,docx',?,20,?,?)")->execute([$chap, $title, $title, $deadline, $late, $resub]);
    return (int)$pdo->lastInsertId();
};
$lOpen = $mkLesson('Devoir ouvert', date('Y-m-d H:i:s', time() + 86400));
$lPast = $mkLesson('Devoir clos', '2020-01-01 00:00:00');
$lLate = $mkLesson('Devoir retard autorisé', '2020-01-01 00:00:00', 1);

$cleanup = function () use ($pdo, $course) {
    $pdo->exec("DELETE FROM courses WHERE id = $course");
    $pdo->exec("DELETE FROM users WHERE email LIKE 'zz.asgtest.%@test.local'");
    $pdo->exec("DELETE FROM email_outbox WHERE to_email LIKE 'zz.asgtest.%'");
    $pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'asg:%'");
    foreach (glob(sys_get_temp_dir() . '/zz_*') ?: [] as $f) { @unlink($f); }
};
register_shutdown_function($cleanup);

function http(string $jar, string $base, string $path, array $post = [], array $files = []): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30, CURLOPT_POST => $post !== [] || $files !== [], CURLOPT_POSTFIELDS => $post + $files]);
    $body = (string)curl_exec($ch);
    return ['code' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'json' => json_decode($body, true), 'body' => $body];
}
$jar = fn(string $n) => sys_get_temp_dir() . "/zz_jar_$n";
foreach (['student' => $stu, 'student2' => $stu2, 'nomat' => $nomat, 'teacher' => $teacher, 'other' => $other] as $n => $_) {
    @unlink($jar($n));
    $r = http($jar($n), $base, '/login-action.php', ['email' => "zz.asgtest.$n@test.local", 'password' => $pw]);
    check("$n can sign in", ($r['json']['success'] ?? false) === true, $r['body']);
}
$submit = fn(string $who, int $lesson, array $extra = [], ?string $file = null, string $mime = 'application/pdf', string $name = 'devoir.pdf') =>
    http($jar($who), $base, '/api/submit-assignment.php', ['lesson_id' => $lesson] + $extra, $file ? ['assignment_file' => new CURLFile($file, $mime, $name)] : []);

// ---------------------------------------------------------------- submitting
check('after the deadline the server refuses (it only used to show the date)', ($r = $submit('student', $lPast, ['assignment_link' => 'https://x.cm']))['json']['error'] === 'closed', $r['body']);
check('late work allowed: accepted and flagged late', ($r = $submit('student', $lLate, ['assignment_link' => 'https://x.cm/p']))['json']['success'] === true && $r['json']['late'] === true, $r['body']);
check('a student with no matricule is asked to complete their profile', ($r = $submit('nomat', $lOpen, ['assignment_link' => 'https://x.cm']))['json']['error'] === 'no_matricule', $r['body']);
check('PHP code renamed .pdf is refused', ($r = $submit('student', $lOpen, [], "$tmp/zz_evil.pdf"))['json']['error'] === 'bad_content', $r['body']);
check('a file type the teacher did not allow is refused', ($r = $submit('student', $lOpen, [], "$tmp/zz_bin.txt", 'text/plain', 'x.txt'))['json']['error'] === 'bad_type', $r['body']);
check('a non-http link is refused', ($r = $submit('student', $lOpen, ['assignment_link' => 'javascript:alert(1)']))['json']['error'] === 'bad_link' || ($r['json']['success'] ?? false) === false, $r['body']);
$r = $submit('student', $lOpen, ['student_name' => 'Quelqu un d autre', 'student_matricule' => '99Z999', 'student_comment' => 'Voici mon travail'], "$tmp/zz_a.pdf");
check('a real PDF is accepted', ($r['json']['success'] ?? false) === true, $r['body']);
$row = $pdo->query("SELECT * FROM lesson_assignment_submissions WHERE lesson_id = $lOpen AND student_id = $stu")->fetch(PDO::FETCH_ASSOC);
check('name and matricule come from the account, not from the form', $row['student_name'] === 'Student' && $row['student_matricule'] === '25G777');
check('the teacher got a notification', (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $teacher AND type = 'assignment'")->fetchColumn() >= 1);
$r = $submit('student', $lOpen, ['assignment_link' => 'https://github.com/x/y']);
check('a new version before the deadline is accepted', ($r['json']['success'] ?? false) === true, $r['body']);
check('the first version is kept in the history', (int)$pdo->query("SELECT COUNT(*) FROM lesson_assignment_history WHERE submission_id = " . (int)$row['id'])->fetchColumn() === 1 && (int)$pdo->query("SELECT attempt_count FROM lesson_assignment_submissions WHERE id = " . (int)$row['id'])->fetchColumn() === 2);
$subId = (int)$row['id'];

// ---------------------------------------------------------------- downloading
$file = (string)$pdo->query("SELECT submitted_file_path FROM lesson_assignment_submissions WHERE lesson_id = $lOpen AND student_id = $stu")->fetchColumn();
check('the file is still on record after the new version (link added to it)', $file !== '');
check('the owner can download their file, as an attachment', ($r = http($jar('student'), $base, '/download.php?type=assignment&file=' . $file))['code'] === 200 && $r['body'] === file_get_contents("$tmp/zz_a.pdf"));
check('another student cannot', http($jar('student2'), $base, '/download.php?type=assignment&file=' . $file)['code'] === 403);
check('a teacher of another course cannot (this used to work)', http($jar('other'), $base, '/download.php?type=assignment&file=' . $file)['code'] === 403);
check('the teacher of the course can', http($jar('teacher'), $base, '/download.php?type=assignment&file=' . $file)['code'] === 200);

// ---------------------------------------------------------------- marking
check('another teacher cannot mark it', (Assignments::grade($pdo, $subId, $other, '15', '')['error'] ?? '') === 'not_found');
check('a mark above the scale is refused', (Assignments::grade($pdo, $subId, $teacher, '25', '')['error'] ?? '') === 'bad_score');
check('the teacher can mark with a comma', Assignments::grade($pdo, $subId, $teacher, '14,5', 'Bon travail. Soignez la conclusion.')['ok'] === true);
$d = http($jar('student'), $base, '/student/get-lesson-details.php?lesson_id=' . $lOpen);
check('the student sees the mark and the feedback in the app', (float)($d['json']['submission']['score'] ?? -1) === 14.5 && str_contains((string)($d['json']['submission']['feedback'] ?? ''), 'conclusion') && ($d['json']['assignment_state']['status'] ?? '') === 'graded', $d['body']);
check('the student is told (queued email + notification)', (int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE template = 'assignment_graded' AND to_email LIKE 'zz.asgtest.student@%'")->fetchColumn() === 1
    && (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $stu AND type = 'assignment_graded'")->fetchColumn() === 1);
check('a marked assignment cannot be replaced by the student', ($r = $submit('student', $lOpen, ['assignment_link' => 'https://x.cm/again']))['json']['error'] === 'graded', $r['body']);
check('asking for a new version needs a note', (Assignments::requestRevision($pdo, $subId, $teacher, 'x')['error'] ?? '') === 'note_needed');
check('the teacher asks for a new version', Assignments::requestRevision($pdo, $subId, $teacher, 'Ajoutez la partie sur les tests.')['ok'] === true);
$pdo->exec("UPDATE lessons SET assignment_deadline = '2020-01-01 00:00:00' WHERE id = $lOpen");   // the deadline is now long gone
check('a new version is possible even after the deadline', ($r = $submit('student', $lOpen, ['assignment_link' => 'https://github.com/x/y/v2']))['json']['success'] === true, $r['body']);
$row = $pdo->query("SELECT score, graded_at, attempt_count, is_late FROM lesson_assignment_submissions WHERE id = $subId")->fetch(PDO::FETCH_ASSOC);
check('the new version starts unmarked, the old mark stays in the history', $row['score'] === null && $row['graded_at'] === null && (int)$row['attempt_count'] === 3
    && (float)$pdo->query("SELECT score FROM lesson_assignment_history WHERE submission_id = $subId AND attempt = 2")->fetchColumn() === 14.5);

// ---------------------------------------------------------------- reports
Assignments::grade($pdo, $subId, $teacher, '16', 'Mieux.');
$sheet = Assignments::marksSheet($pdo, $course);
$byName = array_column($sheet['rows'], null, 1);
check('the marks sheet has one row per enrolled student', count($sheet['rows']) === 3);
check('matricule, name and the mark are in the row', ($byName['Student'][0] ?? '') === '25G777' && in_array(16.0, $byName['Student'], true));
check('a student who did not hand in is marked "Non rendu"', in_array('Non rendu', $byName['Student2'] ?? [], true));
$x = http($jar('teacher'), $base, '/teacher/export-assignment-grades.php?course_id=' . $course . '&format=xlsx');
check('the Excel export downloads with the matricule and the mark', $x['code'] === 200 && str_contains($x['body'], '25G777') && str_contains($x['body'], '>16<'));
check('another teacher cannot export it', http($jar('other'), $base, '/teacher/export-assignment-grades.php?course_id=' . $course . '&format=xlsx')['code'] === 403);
$p = http($jar('teacher'), $base, '/teacher/export-assignment-grades.php?course_id=' . $course . '&format=pdf');
check('the PDF export is a PDF', $p['code'] === 200 && str_starts_with($p['body'], '%PDF'));

echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
