<?php
declare(strict_types=1);

/**
 * Assignments, second step: rubrics, release of marks, annotated copies and the similarity check, end to end.
 * Needs the app running (default http://127.0.0.1:8123) and the database from .env. Creates throw-away accounts (zz.asgadv.*@test.local,
 * which never receive mail) and removes them. Emails are only queued.
 *
 *   php tests/integration/assignments_advanced.php [http://127.0.0.1:8123]
 */

putenv('SV_NO_BACKGROUND_MAIL=1');
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/Assignments.php';
require_once __DIR__ . '/../../lib/AssignmentRubric.php';
require_once __DIR__ . '/../../lib/AssignmentSimilarity.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }

// ================================================================ rubric: pure rules
$good = [['title' => 'Analyse', 'max' => 6, 'levels' => [['label' => 'Faible', 'points' => 1], ['label' => 'Excellent', 'points' => 6]]], ['title' => 'Rédaction', 'max' => '4,5']];
$n = AssignmentRubric::normalize(json_encode($good));
check('a valid rubric is accepted, ids assigned, total added up', $n['ok'] && $n['total'] === 10.5 && $n['criteria'][0]['id'] === 'c1' && $n['criteria'][1]['id'] === 'c2' && $n['criteria'][1]['max'] === 4.5);
check('levels are sorted from the best', $n['criteria'][0]['levels'][0]['label'] === 'Excellent');
check('an empty rubric means "no rubric"', AssignmentRubric::normalize('')['ok'] && AssignmentRubric::normalize('[]')['criteria'] === []);
check('broken JSON is refused', AssignmentRubric::normalize('{nope')['error'] === 'bad_json');
check('a criterion without a title is refused', AssignmentRubric::normalize([['title' => ' ', 'max' => 3]])['error'] === 'bad_title');
check('zero or negative points are refused', AssignmentRubric::normalize([['title' => 'a', 'max' => 0]])['error'] === 'bad_max' && AssignmentRubric::normalize([['title' => 'a', 'max' => -2]])['error'] === 'bad_max');
check('a level above its criterion is refused', AssignmentRubric::normalize([['title' => 'a', 'max' => 3, 'levels' => [['label' => 'x', 'points' => 4]]]])['error'] === 'bad_level');
check('more than 20 criteria are refused', AssignmentRubric::normalize(array_fill(0, 21, ['title' => 'a', 'max' => 1]))['error'] === 'too_many');
check('a total above 1000 is refused', AssignmentRubric::normalize([['title' => 'a', 'max' => 600], ['title' => 'b', 'max' => 600]])['error'] === 'too_big');
check('a duplicated id is replaced by a free one', ($d = AssignmentRubric::normalize([['id' => 'c1', 'title' => 'a', 'max' => 1], ['id' => 'c1', 'title' => 'b', 'max' => 1]])) && $d['criteria'][0]['id'] !== $d['criteria'][1]['id']);
$crit = $n['criteria'];
$sc = AssignmentRubric::score($crit, ['c1' => ['points' => '6', 'comment' => 'Très bien'], 'c2' => ['points' => '3,5']]);
check('the total is added up by the server, with the level name found', $sc['ok'] && $sc['total'] === 9.5 && $sc['rows'][0]['level'] === 'Excellent' && $sc['rows'][1]['level'] === null && $sc['rows'][0]['comment'] === 'Très bien');
check('a criterion left empty is refused', AssignmentRubric::score($crit, ['c1' => ['points' => '6']])['error'] === 'incomplete');
check('points above the maximum are refused', AssignmentRubric::score($crit, ['c1' => ['points' => '7'], 'c2' => ['points' => '1']])['error'] === 'bad_points');
check('negative or text points are refused', AssignmentRubric::score($crit, ['c1' => ['points' => '-1'], 'c2' => ['points' => '1']])['error'] === 'bad_points' && AssignmentRubric::score($crit, ['c1' => ['points' => 'abc'], 'c2' => ['points' => '1']])['error'] === 'bad_points');

// ================================================================ student view: nothing leaks while held
$held = ['graded_at' => '2026-01-02 10:00:00', 'released_at' => null, 'score' => 15, 'feedback' => 'SECRET', 'rubric_scores' => '[{"id":"c1"}]', 'feedback_file_path' => 'x.pdf', 'feedback_file_name' => 'x.pdf', 'submitted_at' => '2026-01-01', 'revision_requested_at' => null];
$v = Assignments::studentView($held);
check('a held mark is hidden from the student, entirely', $v['score'] === null && $v['feedback'] === null && $v['graded_at'] === null && $v['rubric_scores'] === null && $v['feedback_file_url'] === null && $v['awaiting_release'] === true);
$rel = Assignments::studentView(['released_at' => '2026-01-03 10:00:00', 'rubric_scores' => '[{"id":"c1","points":2}]'] + $held);
check('a released mark is shown', $rel['score'] === 15 && $rel['feedback'] === 'SECRET' && $rel['rubric_scores'][0]['points'] === 2 && str_contains((string)$rel['feedback_file_url'], 'assignment_feedback') && $rel['awaiting_release'] === false);
$st = Assignments::studentState(['status' => 'graded', 'can_submit' => false, 'reason' => 'graded'], $held);
check('a held mark counts as "handed in" for the student, and the work stays locked', $st['status'] === 'submitted' && $st['can_submit'] === false && $st['awaiting_release'] === true);

// ================================================================ similarity: pure rules
$w = AssignmentSimilarity::words("Le Théorème de Pythagore : l'hypoténuse, ÉGALE à la somme!");
check('words are lower-case, without accents or punctuation', $w === ['le', 'theoreme', 'de', 'pythagore', 'l', 'hypotenuse', 'egale', 'a', 'la', 'somme']);
$lorem = fn(int $seed, int $n) => implode(' ', array_map(fn($i) => 'mot' . ($seed * 1000 + $i * 7 % 997), range(1, $n)));
$a = AssignmentSimilarity::shingles(AssignmentSimilarity::words($lorem(1, 80)));
$b = AssignmentSimilarity::shingles(AssignmentSimilarity::words($lorem(1, 80)));
$c = AssignmentSimilarity::shingles(AssignmentSimilarity::words($lorem(2, 80)));
check('the same text scores 100 %', AssignmentSimilarity::compare($a, $b)['score'] === 100.0);
check('two different texts score about 0', AssignmentSimilarity::compare($a, $c)['score'] < 5);
$long = AssignmentSimilarity::shingles(AssignmentSimilarity::words($lorem(1, 80) . ' ' . $lorem(3, 80)));
check('a text copied inside a longer one still scores 100 % (containment of the smaller)', AssignmentSimilarity::compare($a, $long)['score'] === 100.0);
$ww = AssignmentSimilarity::words($lorem(1, 80));
check('the shared passage is returned', ($p = AssignmentSimilarity::passages($ww, $a, $b)) && count($p) >= 1 && str_starts_with($p[0], 'mot1007'));
check('levels', AssignmentSimilarity::level(75) === 'high' && AssignmentSimilarity::level(40) === 'medium' && AssignmentSimilarity::level(10) === 'low');
$tmp = sys_get_temp_dir();
$zdoc = new ZipArchive(); $zdoc->open("$tmp/zz_adv.docx", ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zdoc->addFromString('word/document.xml', '<w:document><w:body><w:p><w:r><w:t>Premier paragraphe &amp; accents é</w:t></w:r></w:p><w:p><w:r><w:t>Second paragraphe</w:t></w:r></w:p></w:body></w:document>'); $zdoc->close();
check('the text of a docx is extracted', str_contains((string)AssignmentSimilarity::extractText("$tmp/zz_adv.docx", 'docx'), 'Premier paragraphe & accents é') && str_contains((string)AssignmentSimilarity::extractText("$tmp/zz_adv.docx", 'docx'), "\nSecond"));
file_put_contents("$tmp/zz_adv.ipynb", json_encode(['cells' => [['source' => ["print('bonjour')\n", "x = 2"]], ['source' => 'texte markdown']]]));
check('the code of a notebook is extracted', str_contains((string)AssignmentSimilarity::extractText("$tmp/zz_adv.ipynb", 'ipynb'), "x = 2") && str_contains((string)AssignmentSimilarity::extractText("$tmp/zz_adv.ipynb", 'ipynb'), 'texte markdown'));
check('a picture has no text', AssignmentSimilarity::extractText("$tmp/zz_adv.docx", 'png') === null);
$plainPdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/Contents 4 0 R>>endobj\n4 0 obj<</Length 120>>\nstream\nBT /F1 12 Tf 72 720 Td (Une phrase simple dans un fichier PDF minimal pour tester la lecture) Tj ET\nendstream\nendobj\ntrailer<</Root 1 0 R>>\n%%EOF";
file_put_contents("$tmp/zz_adv_plain.pdf", $plainPdf);
check('a simple PDF is read (pdftotext or the built-in reader)', str_contains((string)AssignmentSimilarity::extractText("$tmp/zz_adv_plain.pdf", 'pdf'), 'phrase simple'));
check('the built-in PDF reader works on its own', str_contains((string)AssignmentSimilarity::pdfFallback("$tmp/zz_adv_plain.pdf"), 'phrase simple'));

// ================================================================ fixtures
$pw = 'Flow-test-9Zq!';
$pdo->exec("DELETE FROM users WHERE email LIKE 'zz.asgadv.%@test.local'");
$mod = (int)$pdo->query("SELECT id FROM modules LIMIT 1")->fetchColumn();
$mk = function (string $n, string $role, ?string $mat = null) use ($pdo, $pw): int {
    $pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved,plan_id,matricule,tour_seen_at) VALUES (?,?,?,?,NOW(),1,1,NULL,?,NOW())")
        ->execute(["zz.asgadv.$n@test.local", password_hash($pw, PASSWORD_DEFAULT), ucfirst($n), $role, $mat]);
    return (int)$pdo->lastInsertId();
};
$teacher = $mk('teacher', 'teacher'); $other = $mk('other', 'teacher');
$stu = [];
foreach (range(1, 6) as $i) { $stu[$i] = $mk("stu$i", 'student', '26A00' . $i); }
$pdo->prepare("INSERT INTO courses (module_id,teacher_id,created_by,title,description,svg_icon,is_published) VALUES (?,?,?,'ZZ asg adv','d','',1)")->execute([$mod, $teacher, $teacher]);
$course = (int)$pdo->lastInsertId();
foreach ($stu as $u) { $pdo->prepare("INSERT INTO enrollments (student_id,course_id) VALUES (?,?)")->execute([$u, $course]); }
$pdo->prepare("INSERT INTO chapters (course_id,title,sort_order) VALUES (?,'Ch',1)")->execute([$course]);
$chap = (int)$pdo->lastInsertId();
$mkLesson = function (string $title, array $extra = []) use ($pdo, $chap): int {
    $pdo->prepare("INSERT INTO lessons (chapter_id,title,content_type,text_content,sort_order,has_assignment,assignment_title,assignment_type,allowed_file_types,assignment_deadline,assignment_max_score,assignment_allow_late,assignment_allow_resubmit,assignment_rubric,assignment_release_mode,is_compulsory)
                   VALUES (?,?,'text','x',1,1,?,'both','pdf,docx,txt,png',?,?,0,1,?,?,0)")
        ->execute([$chap, $title, $title, date('Y-m-d H:i:s', time() + 86400), $extra['max'] ?? 20, $extra['rubric'] ?? null, $extra['mode'] ?? 'immediate']);
    return (int)$pdo->lastInsertId();
};
$lRub = $mkLesson('Barème', ['max' => 10.5, 'rubric' => AssignmentRubric::encode($crit)]);
$lMan = $mkLesson('Publication manuelle', ['mode' => 'manual']);
$lSim = $mkLesson('Ressemblances');
$lFb  = $mkLesson('Copie annotée');
$feedbackFiles = [];
// Lessons open in reading order: the students have finished the earlier ones
foreach ($stu as $u) { foreach ([$lRub, $lMan, $lSim] as $l) { $pdo->prepare("INSERT IGNORE INTO lesson_progress (student_id, lesson_id, completed) VALUES (?,?,1)")->execute([$u, $l]); } }
$cleanup = function () use ($pdo, $course, &$feedbackFiles) {
    $pdo->exec("DELETE FROM courses WHERE id = $course");
    $pdo->exec("DELETE FROM users WHERE email LIKE 'zz.asgadv.%@test.local'");
    $pdo->exec("DELETE FROM email_outbox WHERE to_email LIKE 'zz.asgadv.%'");
    $pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'asg%'");
    foreach (glob(sys_get_temp_dir() . '/zz_adv*') ?: [] as $f) { @unlink($f); }
    foreach (glob(sys_get_temp_dir() . '/zz_jar_adv*') ?: [] as $f) { @unlink($f); }
};
register_shutdown_function($cleanup);

function http(string $jar, string $base, string $path, array $post = [], array $files = []): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 120]);
    if ($post !== [] || $files !== []) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post + $files]);   // never set POSTFIELDS on a GET: curl would turn it into a POST
    }
    $body = (string)curl_exec($ch);
    return ['code' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'json' => json_decode($body, true), 'body' => $body];
}
$jar = fn(string $n) => sys_get_temp_dir() . "/zz_jar_adv_$n";
$users = ['teacher' => $teacher, 'other' => $other] + array_combine(array_map(fn($i) => "stu$i", array_keys($stu)), $stu);
foreach ($users as $name => $_) {
    @unlink($jar($name));
    $r = http($jar($name), $base, '/login-action.php', ['email' => "zz.asgadv.$name@test.local", 'password' => $pw]);
    check("$name can sign in", ($r['json']['success'] ?? false) === true, $r['body']);
}
$submit = fn(string $who, int $lesson, array $extra = [], ?string $file = null, string $name = 'devoir.txt', string $mime = 'text/plain') =>
    http($jar($who), $base, '/api/submit-assignment.php', ['lesson_id' => $lesson] + $extra, $file ? ['assignment_file' => new CURLFile($file, $mime, $name)] : []);
$grade = fn(int $subId, array $post, array $files = [], string $who = 'teacher') => http($jar($who), $base, '/teacher/grade-assignment.php', ['action' => 'grade', 'submission_id' => $subId] + $post, $files);
$subOf = fn(int $lesson, int $student) => (int)$pdo->query("SELECT id FROM lesson_assignment_submissions WHERE lesson_id = $lesson AND student_id = $student")->fetchColumn();
$sub = fn(array $d, string $k) => array_key_exists($k, $d['json']['submission'] ?? []) ? $d['json']['submission'][$k] : '#missing';
$details = fn(string $who, int $lesson) => http($jar($who), $base, '/student/get-lesson-details.php?lesson_id=' . $lesson);

// ================================================================ rubric: end to end
check('a student hands in', ($r = $submit('stu1', $lRub, ['assignment_link' => 'https://x.cm/rapport']))['json']['success'] === true, $r['body']);
$s1 = $subOf($lRub, $stu[1]);
$d = $details('stu1', $lRub);
check('the student sees the rubric before being marked', count($d['json']['assignment_rubric'] ?? []) === 2 && $d['json']['assignment_rubric'][0]['title'] === 'Analyse');
check('...and no mark yet', $sub($d, 'score') === null);
check('marking with an incomplete grid is refused', ($r = $grade($s1, ['rubric_json' => json_encode(['c1' => ['points' => '5']])]))['json']['error'] === 'rubric_incomplete', $r['body']);
check('points above a criterion maximum are refused', ($r = $grade($s1, ['rubric_json' => json_encode(['c1' => ['points' => '9'], 'c2' => ['points' => '1']])]))['json']['error'] === 'rubric_bad_points', $r['body']);
check('the mark is the sum of the criteria, whatever "score" the browser sends', ($r = $grade($s1, ['score' => '1', 'feedback' => 'Bon rapport', 'rubric_json' => json_encode(['c1' => ['points' => '6', 'comment' => 'Analyse solide'], 'c2' => ['points' => '3,5', 'comment' => '']])]))['json']['success'] === true && (float)$r['json']['score'] === 9.5, $r['body']);
$row = $pdo->query("SELECT score, rubric_scores FROM lesson_assignment_submissions WHERE id = $s1")->fetch(PDO::FETCH_ASSOC);
check('the grid with its comments is stored as a snapshot', (float)$row['score'] === 9.5 && str_contains((string)$row['rubric_scores'], 'Analyse solide') && str_contains((string)$row['rubric_scores'], '"level":"Excellent"'));
$d = $details('stu1', $lRub);
check('the student sees the mark and the detail by criterion', (float)($d['json']['submission']['score'] ?? 0) === 9.5 && ($d['json']['submission']['rubric_scores'][0]['comment'] ?? '') === 'Analyse solide' && count($d['json']['submission']['rubric_scores']) === 2);
$pdo->prepare("UPDATE lessons SET assignment_rubric = ? WHERE id = ?")->execute([AssignmentRubric::encode(AssignmentRubric::normalize([['title' => 'Tout autre critère', 'max' => 10.5]])['criteria']), $lRub]);
$row2 = $pdo->query("SELECT score, rubric_scores FROM lesson_assignment_submissions WHERE id = $s1")->fetch(PDO::FETCH_ASSOC);
check('editing the rubric afterwards does not change marks already given', $row2 === $row);
check('another teacher cannot mark it', ($r = $grade($s1, ['rubric_json' => '{}'], [], 'other'))['json']['error'] === 'not_found', $r['body']);
check('a student cannot call the marking endpoint', $grade($s1, ['score' => '20'], [], 'stu2')['code'] === 403);

// ================================================================ release of marks
foreach ([2, 3, 4] as $i) { $submit("stu$i", $lMan, ['assignment_link' => "https://x.cm/$i"]); }
$m2 = $subOf($lMan, $stu[2]); $m3 = $subOf($lMan, $stu[3]); $m4 = $subOf($lMan, $stu[4]);
$r = $grade($m2, ['score' => '15', 'feedback' => 'SECRET-FEEDBACK-XYZ']);
check('in manual mode the mark is saved but not released', $r['json']['success'] === true && $r['json']['released'] === false && $pdo->query("SELECT released_at FROM lesson_assignment_submissions WHERE id = $m2")->fetchColumn() === null);
$grade($m3, ['score' => '12', 'feedback' => 'Autre retour']);
$d = $details('stu2', $lMan);
check('a held mark is invisible in the lesson (mark, feedback, grid, dates)', $sub($d, 'score') === null && $sub($d, 'feedback') === null && $sub($d, 'graded_at') === null
    && !str_contains($d['body'], 'SECRET-FEEDBACK-XYZ') && ($d['json']['submission']['awaiting_release'] ?? false) === true, $d['body']);
check('the student sees "handed in" and cannot send another version', ($d['json']['assignment_state']['status'] ?? '') === 'submitted' && ($d['json']['assignment_state']['can_submit'] ?? true) === false);
$dash = http($jar('stu2'), $base, '/student/dashboard.php');
check('nor in the results page', $dash['code'] === 200 && !str_contains($dash['body'], 'SECRET-FEEDBACK-XYZ'));
check('and the student was not told', (int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE template = 'assignment_graded' AND to_email = 'zz.asgadv.stu2@test.local'")->fetchColumn() === 0
    && (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$stu[2]} AND type = 'assignment_graded'")->fetchColumn() === 0);
$sheet = Assignments::marksSheet($pdo, $course, $lMan);
$byName = array_column($sheet['rows'], null, 1);
check('the teacher still sees held marks in the marks sheet', in_array(15.0, $byName['Stu2'] ?? [], true));
check('another teacher cannot release', ($r = http($jar('other'), $base, '/teacher/grade-assignment.php', ['action' => 'release_all', 'lesson_id' => $lMan]))['json']['released'] === 0, $r['body']);
check('a student cannot release', http($jar('stu4'), $base, '/teacher/grade-assignment.php', ['action' => 'release_all', 'lesson_id' => $lMan])['code'] === 403);
$r = http($jar('teacher'), $base, '/teacher/grade-assignment.php', ['action' => 'release', 'submission_id' => $m2]);
check('one mark can be released alone', $r['json']['released'] === 1 && $pdo->query("SELECT released_at FROM lesson_assignment_submissions WHERE id = $m2")->fetchColumn() !== null && $pdo->query("SELECT released_at FROM lesson_assignment_submissions WHERE id = $m3")->fetchColumn() === null, $r['body']);
$d = $details('stu2', $lMan);
check('now the student sees mark and feedback', (float)($d['json']['submission']['score'] ?? 0) === 15.0 && str_contains($d['body'], 'SECRET-FEEDBACK-XYZ') && ($d['json']['assignment_state']['status'] ?? '') === 'graded');
check('and was told once', (int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE template = 'assignment_graded' AND to_email = 'zz.asgadv.stu2@test.local'")->fetchColumn() === 1);
$r = http($jar('teacher'), $base, '/teacher/grade-assignment.php', ['action' => 'release_all', 'lesson_id' => $lMan]);
check('"release all" releases what is still held, and only that', $r['json']['released'] === 1 && (int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE template = 'assignment_graded' AND to_email = 'zz.asgadv.stu2@test.local'")->fetchColumn() === 1
    && (int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE template = 'assignment_graded' AND to_email = 'zz.asgadv.stu3@test.local'")->fetchColumn() === 1, $r['body']);
check('an unmarked work is never released', (int)$pdo->query("SELECT COUNT(*) FROM lesson_assignment_submissions WHERE id = $m4 AND released_at IS NOT NULL")->fetchColumn() === 0);
$grade($m2, ['score' => '16', 'feedback' => 'Corrigé']);
check('correcting a released mark keeps it released', $pdo->query("SELECT released_at FROM lesson_assignment_submissions WHERE id = $m2")->fetchColumn() !== null && (float)$pdo->query("SELECT score FROM lesson_assignment_submissions WHERE id = $m2")->fetchColumn() === 16.0);
$r = http($jar('teacher'), $base, '/teacher/grade-assignment.php', ['action' => 'withdraw_all', 'lesson_id' => $lMan]);
$d = $details('stu2', $lMan);
check('marks can be withdrawn: hidden again', $r['json']['withdrawn'] === 2 && $sub($d, 'score') === null && ($d['json']['assignment_state']['status'] ?? '') === 'submitted', $r['body']);
$r = $grade($subOf($lMan, $stu[2]), ['score' => '16', 'feedback' => 'x']);
check('after a withdrawal a corrected mark stays held until released again (manual mode)', $r['json']['released'] === false);
http($jar('teacher'), $base, '/teacher/grade-assignment.php', ['action' => 'release_all', 'lesson_id' => $lMan]);
$pdo->exec("UPDATE lessons SET assignment_release_mode = 'immediate' WHERE id = $lMan");

// ================================================================ annotated copy
$pdfFile = "$tmp/zz_adv_fb.pdf"; file_put_contents($pdfFile, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF ANNOTATED");
$pdo->exec("UPDATE lessons SET assignment_release_mode = 'manual' WHERE id = $lFb");
$submit('stu5', $lFb, ['assignment_link' => 'https://x.cm/5']); $submit('stu6', $lFb, ['assignment_link' => 'https://x.cm/6']);
$f5 = $subOf($lFb, $stu[5]); $f6 = $subOf($lFb, $stu[6]);
check('the teacher returns an annotated copy with the mark', ($r = $grade($f5, ['score' => '14', 'feedback' => 'Voir annotations'], ['feedback_file' => new CURLFile($pdfFile, 'application/pdf', 'copie-annotée.pdf')]))['json']['success'] === true, $r['body']);
$fp = (string)$pdo->query("SELECT feedback_file_path FROM lesson_assignment_submissions WHERE id = $f5")->fetchColumn();
check('it is stored with its original name', $fp !== '' && str_ends_with($fp, '.pdf') && (string)$pdo->query("SELECT feedback_file_name FROM lesson_assignment_submissions WHERE id = $f5")->fetchColumn() === 'copie-annotée.pdf');
$url = '/download.php?type=assignment_feedback&file=' . $fp;
check('while the mark is held the student cannot download it', http($jar('stu5'), $base, $url)['code'] === 403);
check('the lesson does not even show its name', !str_contains($details('stu5', $lFb)['body'], 'copie-annot') && !str_contains($details('stu5', $lFb)['body'], $fp));
check('the teacher of the course can', ($r = http($jar('teacher'), $base, $url))['code'] === 200 && $r['body'] === file_get_contents($pdfFile));
check('another teacher cannot', http($jar('other'), $base, $url)['code'] === 403);
check('another student cannot', http($jar('stu6'), $base, $url)['code'] === 403);
check('a visitor who is not signed in cannot', ($ch = curl_init($base . $url)) && curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true]) !== null && curl_exec($ch) !== null && curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 401);
http($jar('teacher'), $base, '/teacher/grade-assignment.php', ['action' => 'release_all', 'lesson_id' => $lFb]);
check('once released the student downloads it, byte for byte', ($r = http($jar('stu5'), $base, $url))['code'] === 200 && $r['body'] === file_get_contents($pdfFile));
$d = $details('stu5', $lFb);
check('and the lesson offers the link', str_contains((string)($d['json']['submission']['feedback_file_url'] ?? ''), $fp) && ($d['json']['submission']['feedback_file_name'] ?? '') === 'copie-annotée.pdf');
check('another student still cannot', http($jar('stu6'), $base, $url)['code'] === 403);
// refused files
$exe = "$tmp/zz_adv_tool.exe"; file_put_contents($exe, 'MZ');
check('an executable is refused', ($r = $grade($f6, ['score' => '10'], ['feedback_file' => new CURLFile($exe, 'application/octet-stream', 'x.exe')]))['json']['error'] === 'fb_bad_type', $r['body']);
$evil = "$tmp/zz_adv_evil.pdf"; file_put_contents($evil, "<?php system('id');");
check('PHP source named .pdf is refused', ($r = $grade($f6, ['score' => '10'], ['feedback_file' => new CURLFile($evil, 'application/pdf', 'x.pdf')]))['json']['error'] === 'fb_bad_content', $r['body']);
$big = "$tmp/zz_adv_big.pdf"; $fh = fopen($big, 'wb'); fwrite($fh, "%PDF-1.4\n"); fseek($fh, 21 * 1024 * 1024); fwrite($fh, 'x'); fclose($fh);
check('a file above 20 MB is refused', ($r = $grade($f6, ['score' => '10'], ['feedback_file' => new CURLFile($big, 'application/pdf', 'big.pdf')]))['json']['error'] === 'fb_too_big', $r['body']);
check('a refused file leaves the mark unsaved', $pdo->query("SELECT score FROM lesson_assignment_submissions WHERE id = $f6")->fetchColumn() === null && $pdo->query("SELECT feedback_file_path FROM lesson_assignment_submissions WHERE id = $f6")->fetchColumn() === null);
// replacing and removing
$pdf2 = "$tmp/zz_adv_fb2.pdf"; file_put_contents($pdf2, "%PDF-1.4\n%%EOF SECOND");
$grade($f5, ['score' => '14', 'feedback' => 'Voir annotations'], ['feedback_file' => new CURLFile($pdf2, 'application/pdf', 'v2.pdf')]);
$fp2 = (string)$pdo->query("SELECT feedback_file_path FROM lesson_assignment_submissions WHERE id = $f5")->fetchColumn();
check('a new annotated copy replaces the old one, which is deleted', $fp2 !== $fp && http($jar('teacher'), $base, '/download.php?type=assignment_feedback&file=' . $fp)['code'] === 404 && http($jar('stu5'), $base, '/download.php?type=assignment_feedback&file=' . $fp2)['code'] === 200);
$grade($f5, ['score' => '14', 'feedback' => 'Voir annotations', 'remove_file' => '1']);
check('the annotated copy can be removed', $pdo->query("SELECT feedback_file_path FROM lesson_assignment_submissions WHERE id = $f5")->fetchColumn() === null && http($jar('stu5'), $base, '/download.php?type=assignment_feedback&file=' . $fp2)['code'] === 404);
// a new version archives the annotated copy
$grade($f5, ['score' => '14', 'feedback' => 'Voir annotations'], ['feedback_file' => new CURLFile($pdf2, 'application/pdf', 'v3.pdf')]);
$fp3 = (string)$pdo->query("SELECT feedback_file_path FROM lesson_assignment_submissions WHERE id = $f5")->fetchColumn();
Assignments::requestRevision($pdo, $f5, $teacher, 'Reprenez la partie 2, svp.');
$submit('stu5', $lFb, ['assignment_link' => 'https://x.cm/5-v2']);
$cur = $pdo->query("SELECT feedback_file_path, released_at, rubric_scores, score FROM lesson_assignment_submissions WHERE id = $f5")->fetch(PDO::FETCH_ASSOC);
check('a new version starts clean: no mark, not released, no annotated copy', $cur['feedback_file_path'] === null && $cur['released_at'] === null && $cur['score'] === null);
check('the old annotated copy is kept in the history', (string)$pdo->query("SELECT feedback_file_path FROM lesson_assignment_history WHERE submission_id = $f5 ORDER BY attempt DESC LIMIT 1")->fetchColumn() === $fp3);
check('the teacher can still download it', http($jar('teacher'), $base, '/download.php?type=assignment_feedback&file=' . $fp3)['code'] === 200);
check('the teacher is not sent the annotated copy of the history by other teachers', http($jar('other'), $base, '/download.php?type=assignment_feedback&file=' . $fp3)['code'] === 403);

// ================================================================ similarity check, end to end
$statement = "Enoncé : rédigez un rapport sur les réseaux locaux en décrivant les protocoles Ethernet et Wi-Fi, leurs avantages, leurs limites, puis proposez une architecture adaptée à un campus universitaire de trois bâtiments.";
$voc = fn(string $p, int $n) => implode(' ', array_map(fn($i) => $p . (($i * 13) % 211), range(1, $n)));
$shared = $voc('commun', 110);
$texts = [
    1 => $statement . "\n" . $voc('alpha', 40) . ' ' . $shared,
    2 => $statement . "\n" . $voc('beta', 30) . ' ' . $shared . ' ' . $voc('beta2', 40),
    3 => $statement . "\n" . $voc('gamma', 150),
    4 => $statement . "\n" . $voc('delta', 150),
];
$lSim2 = $lSim;
foreach ($texts as $i => $txt) {
    $f = "$tmp/zz_adv_t$i.txt"; file_put_contents($f, $txt);
    $r = $submit("stu$i", $lSim, [], $f, "rapport$i.txt");
    check("student $i hands in a text", ($r['json']['success'] ?? false) === true, $r['body']);
}
$png = "$tmp/zz_adv_pic.png"; $im = imagecreatetruecolor(20, 20); imagepng($im, $png);
$r = $submit('stu5', $lSim, [], $png, 'photo.png', 'image/png');
$short = "$tmp/zz_adv_short.txt"; file_put_contents($short, 'trop court');
$r2 = $submit('stu6', $lSim, [], $short, 'court.txt');
check('a picture and a very short text are handed in too', ($r['json']['success'] ?? false) && ($r2['json']['success'] ?? false), $r['body'] . $r2['body']);
check('nothing has been analysed yet', ($r = http($jar('teacher'), $base, '/teacher/assignment-similarity.php?lesson_id=' . $lSim))['json']['checked_at'] === null && $r['json']['pairs'] === [], $r['body']);
check('a student cannot run it', http($jar('stu1'), $base, '/teacher/assignment-similarity.php', ['lesson_id' => $lSim])['code'] === 403);
check('another teacher cannot run it', http($jar('other'), $base, '/teacher/assignment-similarity.php', ['lesson_id' => $lSim])['code'] === 404);
$r = http($jar('teacher'), $base, '/teacher/assignment-similarity.php', ['lesson_id' => $lSim]);
$res = $r['json'];
check('the analysis runs: 4 texts compared, the picture and the short text are reported, not compared', ($res['success'] ?? false) && $res['analysis']['analysed'] === 4
    && count($res['analysis']['skipped']) === 2 && in_array('unreadable', array_column($res['analysis']['skipped'], 'reason'), true) && in_array('too_short', array_column($res['analysis']['skipped'], 'reason'), true), $r['body']);
$s1id = $subOf($lSim, $stu[1]); $s2id = $subOf($lSim, $stu[2]); $s3id = $subOf($lSim, $stu[3]); $s4id = $subOf($lSim, $stu[4]);
$pair12 = array_values(array_filter($res['pairs'], fn($p) => [$p['a'], $p['b']] === [$s1id, $s2id]));
check('the copy is found, flagged high, with names and matricules', count($pair12) === 1 && $pair12[0]['score'] >= 60 && $pair12[0]['level'] === 'high' && $pair12[0]['mat_a'] === '26A001' && $pair12[0]['name_b'] === 'Stu2');
check('the shared passage is shown', !empty($pair12[0]['excerpts']) && str_contains($pair12[0]['excerpts'][0], 'commun'));
check('the statement that everybody copied is NOT counted (boilerplate ignored)', count(array_filter($res['pairs'], fn($p) => in_array($s3id, [$p['a'], $p['b']], true) || in_array($s4id, [$p['a'], $p['b']], true))) === 0 && $res['analysis']['ignored_common'] > 10, $r['body']);
check('the worst score of each submission is given', isset($res['by_submission'][$s1id]) && $res['by_submission'][$s1id]['with'] === 'Stu2' && !isset($res['by_submission'][$s3id]));
check('students never see it (the page and the lesson do not mention it)', !str_contains($details('stu1', $lSim)['body'], 'similarity') && !str_contains(http($jar('stu1'), $base, '/student/dashboard.php')['body'], 'ressemblance'));
$late = "$tmp/zz_adv_late.txt"; file_put_contents($late, $voc('zeta', 120));
// a sixth student cannot hand in again; use an existing student's new version to make the analysis stale
Assignments::requestRevision($pdo, $s3id, $teacher, 'Reprenez tout depuis le début.');
sleep(1);   // the clock of the database counts in seconds
$r = $submit('stu3', $lSim, [], $late, 'rapport3b.txt');
check('new work after the analysis makes it "stale"', ($r = http($jar('teacher'), $base, '/teacher/assignment-similarity.php?lesson_id=' . $lSim))['json']['stale'] === true, $r['body']);
$r = http($jar('teacher'), $base, '/teacher/assignment-similarity.php', ['lesson_id' => $lSim]);
check('running it again refreshes the result', $r['json']['stale'] === false && $r['json']['analysis']['analysed'] === 4);
check('the marking window shows the worst similarity of a submission', ($r = http($jar('teacher'), $base, '/teacher/grade-assignment.php?submission_id=' . $s1id))['json']['submission']['similarity']['with'] === 'Stu2', $r['body']);

echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
