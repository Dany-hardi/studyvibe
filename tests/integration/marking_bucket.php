<?php
declare(strict_types=1);

/**
 * The assignment pipeline end to end: a copy can be changed until the deadline and is locked after it; the teacher sends the copies to
 * the grading bucket (students then read "being marked"); the rule-based engine proposes marks; the teacher confirms them copy by copy;
 * the reliability figures; the student's "Mes notes". Needs the app running (default http://127.0.0.1:8123) and the database from .env.
 * Creates throw-away accounts and removes them.
 *
 *   php tests/integration/marking_bucket.php [http://127.0.0.1:8123]
 */

putenv('SV_NO_BACKGROUND_MAIL=1');
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/Assignments.php';
require_once __DIR__ . '/../../lib/MarkingBucket.php';
require_once __DIR__ . '/../../lib/StudentGrades.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? ' (' . mb_substr($detail, 0, 300) . ')' : ''); }

// ---------------------------------------------------------------- pure rules
$open = ['assignment_deadline' => '2999-01-01 00:00:00', 'assignment_allow_late' => 1, 'assignment_allow_resubmit' => 1];
$past = ['assignment_deadline' => '2000-01-01 00:00:00', 'assignment_allow_late' => 1, 'assignment_allow_resubmit' => 1];
$sub = ['submitted_at' => '2026-01-01 10:00:00', 'graded_at' => null, 'revision_requested_at' => null, 'released_at' => null];
check('a handed-in copy can be changed before the deadline', Assignments::state($open, $sub)['can_submit'] === true);
check('after the deadline it is locked, even when late work is accepted', Assignments::state($past, $sub)['reason'] === 'deadline');
check('late work is still accepted for a first hand-in', Assignments::state($past, null)['late'] === true);
check('a copy in the bucket cannot be changed', Assignments::state($open, ['in_marking_at' => '2026-01-02'] + $sub)['reason'] === 'in_marking');
check('pipeline: handed in, can change', Assignments::pipeline($open, $sub) === 'submitted');
check('pipeline: deadline passed, waiting for marking', Assignments::pipeline($past, $sub) === 'locked');
check('pipeline: in the bucket, proposal ready, by hand', Assignments::pipeline($past, ['in_marking_at' => 'x'] + $sub) === 'in_marking'
    && Assignments::pipeline($past, ['in_marking_at' => 'x', 'engine_at' => 'y'] + $sub) === 'proposed'
    && Assignments::pipeline($past, ['in_marking_at' => 'x', 'engine_at' => 'y', 'engine_error' => 'no_file'] + $sub) === 'by_hand');
check('pipeline: held and released', Assignments::pipeline($past, ['graded_at' => 'x'] + $sub) === 'held' && Assignments::pipeline($past, ['graded_at' => 'x', 'released_at' => 'y'] + $sub) === 'released');
check('the student reads "being marked" for a proposal or a held mark', Assignments::studentPipeline($past, ['in_marking_at' => 'x', 'engine_at' => 'y'] + $sub) === 'in_marking'
    && Assignments::studentPipeline($past, ['graded_at' => 'x'] + $sub) === 'in_marking');

// ---------------------------------------------------------------- fixtures
$pw = 'Bucket-test-9Zq!';
$pdo->exec("DELETE FROM users WHERE email LIKE 'zz.bucket.%@test.local'");
$mod = (int)$pdo->query("SELECT id FROM modules LIMIT 1")->fetchColumn();
$mk = function (string $n, string $role, ?string $mat = null) use ($pdo, $pw): int {
    $pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved,plan_id,matricule,tour_seen_at) VALUES (?,?,?,?,NOW(),1,1,NULL,?,NOW())")
        ->execute(["zz.bucket.$n@test.local", password_hash($pw, PASSWORD_DEFAULT), 'Zz ' . ucfirst($n), $role, $mat]);
    return (int)$pdo->lastInsertId();
};
$teacher = $mk('teacher', 'teacher'); $other = $mk('other', 'teacher');
$s1 = $mk('ana', 'student', '25B001'); $s2 = $mk('ben', 'student', '25B002'); $s3 = $mk('cia', 'student', '25B003');
$pdo->prepare("INSERT INTO courses (module_id,teacher_id,created_by,title,description,svg_icon,is_published) VALUES (?,?,?,'ZZ bucket course','d','',1)")->execute([$mod, $teacher, $teacher]);
$course = (int)$pdo->lastInsertId();
foreach ([$s1, $s2, $s3] as $u) { $pdo->prepare("INSERT INTO enrollments (student_id,course_id) VALUES (?,?)")->execute([$u, $course]); }
$pdo->prepare("INSERT INTO chapters (course_id,title,sort_order) VALUES (?,'Ch',1)")->execute([$course]);
$chap = (int)$pdo->lastInsertId();
$rubric = json_encode([['id' => 'c1', 'title' => 'Notions', 'max' => 8, 'levels' => []], ['id' => 'c2', 'title' => 'Explication', 'max' => 12, 'levels' => []]]);
$pdo->prepare("INSERT INTO lessons (chapter_id,title,content_type,text_content,sort_order,has_assignment,assignment_title,assignment_type,allowed_file_types,assignment_deadline,assignment_max_score,assignment_allow_late,assignment_allow_resubmit,assignment_rubric)
               VALUES (?,?,'text','x',1,1,'La photosynthèse','both','pdf,docx,txt',?,20,0,1,?)")->execute([$chap, 'Photosynthèse', date('Y-m-d H:i:s', time() + 86400), $rubric]);
$lesson = (int)$pdo->lastInsertId();

$tmp = sys_get_temp_dir();
$model = "La photosynthèse est le processus par lequel les plantes vertes utilisent l'énergie lumineuse captée par la chlorophylle pour transformer le dioxyde de carbone et l'eau en glucose. Elle libère de l'oxygène. Elle a lieu dans les chloroplastes des feuilles.";
file_put_contents("$tmp/zz_bk_good.txt", "Introduction\n" . $model . " Les chloroplastes contiennent la chlorophylle, qui absorbe la lumière. Le glucose sert ensuite de réserve d'énergie à la plante, qui le stocke parfois sous forme d'amidon.\nConclusion : c'est la base de la vie sur Terre.");
file_put_contents("$tmp/zz_bk_half.txt", "Les plantes ont de la chlorophylle dans leurs feuilles. Elles rejettent de l'oxygène pendant la journée et cela est important pour les animaux qui respirent. Le soir les plantes respirent aussi comme nous. C'est un phénomène que l'on observe dans la nature tous les jours et partout sur la planète, au printemps comme en été.");
file_put_contents("$tmp/zz_bk_first.txt", "Brouillon trop rapide : la plante mange de la terre et pousse vers le haut, voilà ce que je sais du sujet pour le moment, je compléterai plus tard avec le cours du professeur.");

$cleanup = function () use ($pdo, $course) {
    $pdo->exec("DELETE FROM courses WHERE id = $course");
    $pdo->exec("DELETE FROM users WHERE email LIKE 'zz.bucket.%@test.local'");
    $pdo->exec("DELETE FROM email_outbox WHERE to_email LIKE 'zz.bucket.%'");
    $pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'asg:%'");
    foreach (glob(sys_get_temp_dir() . '/zz_bk*') ?: [] as $f) { @unlink($f); }
};
register_shutdown_function($cleanup);

function http(string $jar, string $base, string $path, array $post = [], array $files = []): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Origin: ' . $base], CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => true]);
    if ($post !== [] || $files !== []) {   // setting the fields makes curl POST, so only for posts
        curl_setopt($ch, CURLOPT_POSTFIELDS, $files ? $post + $files : http_build_query($post));
    }
    $raw = (string)curl_exec($ch);
    $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $head = substr($raw, 0, $hs); $body = substr($raw, $hs);
    preg_match('/^Location:\s*(\S+)/mi', $head, $m);
    return ['code' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'json' => json_decode($body, true), 'body' => $body, 'location' => $m[1] ?? ''];
}
$jar = fn(string $n) => sys_get_temp_dir() . "/zz_bkjar_$n";
foreach (['teacher', 'other', 'ana', 'ben', 'cia'] as $n) {
    @unlink($jar($n));
    $r = http($jar($n), $base, '/login-action.php', ['email' => "zz.bucket.$n@test.local", 'password' => $pw]);
    check("$n can sign in", ($r['json']['success'] ?? false) === true, $r['body']);
}
$submit = fn(string $who, array $extra = [], ?string $file = null) =>
    http($jar($who), $base, '/api/submit-assignment.php', ['lesson_id' => $lesson] + $extra, $file ? ['assignment_file' => new CURLFile($file, 'text/plain', basename($file))] : []);
$details = fn(string $who) => http($jar($who), $base, '/student/get-lesson-details.php?lesson_id=' . $lesson);
$page = fn(string $who, string $qs = '') => http($jar($who), $base, '/teacher/marking.php?lesson_id=' . $lesson . $qs);
$csrf = function () use ($page): string { preg_match('/name="csrf_token" value="([^"]+)"/', $page('teacher')['body'], $m); return $m[1] ?? ''; };
$post = fn(array $data) => http($jar('teacher'), $base, '/teacher/marking.php?lesson_id=' . $lesson, $data + ['csrf_token' => $csrf(), 'lesson_id' => $lesson]);
$subOf = fn(int $st) => $pdo->query("SELECT * FROM lesson_assignment_submissions WHERE lesson_id = $lesson AND student_id = $st")->fetch(PDO::FETCH_ASSOC);

// ---------------------------------------------------------------- handing in, changing, the lock
check('a student hands in', ($r = $submit('ana', [], "$tmp/zz_bk_first.txt"))['json']['success'] === true, $r['body']);
check('and can replace the copy before the deadline', ($r = $submit('ana', [], "$tmp/zz_bk_good.txt"))['json']['success'] === true, $r['body']);
$d = $details('ana');
check('the lesson says the copy can still be changed', ($d['json']['assignment_state']['status'] ?? '') === 'submitted' && ($d['json']['assignment_state']['can_submit'] ?? false) === true, $d['body']);
$submit('ben', [], "$tmp/zz_bk_half.txt");
$submit('cia', ['assignment_link' => 'https://example.org/my-work']);
$pdo->exec("UPDATE lessons SET assignment_deadline = '2020-01-01 00:00:00' WHERE id = $lesson");
check('after the deadline the copy is locked', ($r = $submit('ben', [], "$tmp/zz_bk_good.txt"))['json']['error'] === 'closed', $r['body']);

// ---------------------------------------------------------------- who may open the bucket
check('the teacher opens the bucket', ($p = $page('teacher'))['code'] === 200 && str_contains($p['body'], 'Envoyer 3 copies en correction'), substr($p['body'], 0, 200));
check('another teacher cannot', $page('other')['code'] === 404);
check('a student is sent away', ($r = $page('ana'))['code'] === 302 && str_contains($r['location'], 'index.php'));
check('a post without the token is refused', http($jar('teacher'), $base, '/teacher/marking.php?lesson_id=' . $lesson, ['action' => 'send'])['code'] === 403);

// ---------------------------------------------------------------- rules
$r = $post(['action' => 'rules', 'r' => [
    'strictness' => 'normal', 'tone' => 'encouraging', 'model_answer' => $model, 'min_words' => '40', 'sections' => "Introduction\nConclusion",
    'penalties' => ['late' => '2', 'short' => '1', 'section' => '0.5', 'similarity' => '0', 'similarity_at' => '60'],
    'criteria' => ['c1' => ['keys' => "chlorophylle\nlumière | lumineuse\ndioxyde de carbone | CO2\noxygène", 'need' => ''], 'c2' => ['keys' => '']],
    'templates' => ['closing' => 'Bon courage pour la suite.'],
]]);
check('the rules are saved', $r['code'] === 303 && str_contains($r['location'], 'msg=rules'), $r['body']);
$rules = MarkingBucket::rules(MarkingBucket::lesson($pdo, $lesson, $teacher));
check('as the teacher wrote them', count($rules['criteria']['c1']['keys']) === 4 && $rules['criteria']['c1']['keys'][1] === ['lumière', 'lumineuse'] && $rules['sections'] === ['Introduction', 'Conclusion'] && $rules['penalties']['late'] === 2.0);

// ---------------------------------------------------------------- send to marking
$r = $post(['action' => 'send']);
check('the copies are sent to marking', $r['code'] === 303 && str_contains($r['location'], 'msg=sent&n=3'), $r['location']);
check('each copy is in the bucket', (int)$pdo->query("SELECT COUNT(*) FROM lesson_assignment_submissions WHERE lesson_id = $lesson AND in_marking_at IS NOT NULL")->fetchColumn() === 3);
$d = $details('ben');
check('the student reads "being marked" and cannot change the copy', ($d['json']['assignment_state']['status'] ?? '') === 'in_marking' && ($d['json']['assignment_state']['can_submit'] ?? true) === false, $d['body']);
check('the student was told in the app', (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $s2 AND title = 'Devoir en cours de correction'")->fetchColumn() === 1);
check('the engine proposal never reaches the student', !str_contains($d['body'], 'engine_'), $d['body']);

// ---------------------------------------------------------------- the engine
for ($i = 0; $i < 5 && MarkingBucket::pending($pdo, $lesson) > 0; $i++) { MarkingBucket::run($pdo, $lesson, 20); }
check('every copy has been read', MarkingBucket::pending($pdo, $lesson) === 0);
$a = $subOf($s1); $b = $subOf($s2); $c = $subOf($s3);
$ra = json_decode((string)$a['engine_result'], true); $rb = json_decode((string)$b['engine_result'], true);
check('the complete copy gets all the key points', (float)$ra['rows'][0]['points'] === 8.0 && $ra['rows'][0]['missing'] === [], (string)$a['engine_result']);
check('its explanation is judged on the model answer', $ra['rows'][1]['basis'] === 'model' && (float)$ra['rows'][1]['points'] >= 8, (string)$a['engine_result']);
check('the partial copy gets fewer points, and is told what is missing', (float)$b['engine_score'] < (float)$a['engine_score'] && in_array('lumière', $rb['rows'][0]['missing'], true) && str_contains($rb['feedback'], 'lumière'), (string)$b['engine_result']);
check('its missing parts are penalised as the teacher set', count(array_filter($rb['penalties'], fn($p) => $p['code'] === 'section')) === 2, (string)$b['engine_result']);
check('the feedback ends with the teacher\'s own words', str_ends_with($rb['feedback'], 'Bon courage pour la suite.'));
check('a link with no file is left to the teacher', $c['engine_error'] === 'no_file' && Assignments::pipeline(MarkingBucket::lesson($pdo, $lesson, $teacher), $c) === 'by_hand');

$p = $page('teacher', '&sub=' . $a['id']);
check('the bucket shows the proposal and highlights the key points in the copy', $p['code'] === 200 && str_contains($p['body'], 'Proposition prête') && str_contains($p['body'], '<mark>chlorophylle</mark>'), substr(strip_tags($p['body']), 0, 300));
check('copies to mark by hand come first', strpos($p['body'], 'Zz Cia') < strpos($p['body'], 'Zz Ana'));

// ---------------------------------------------------------------- the teacher confirms
$r = $post(['action' => 'confirm', 'sub' => $b['id'], 'pts' => ['c1' => (string)$rb['rows'][0]['points'], 'c2' => '5'], 'cmt' => ['c1' => '', 'c2' => 'Développez.'],
            'pen' => [0 => '1'], 'feedback' => $rb['feedback'], 'next' => '1']);
check('the teacher confirms a copy and moves to the next one', $r['code'] === 303 && str_contains($r['location'], 'msg=saved') && !str_contains($r['location'], 'sub=' . $b['id']), $r['location']);
$b2 = $subOf($s2);
$rows = json_decode((string)$b2['rubric_scores'], true);
check('the kept penalty comes off the total and is shown as a line', (float)$b2['score'] === (float)$rb['rows'][0]['points'] + 5 - 0.5 && end($rows)['penalty'] === true && (float)end($rows)['points'] === -0.5, (string)$b2['rubric_scores']);
check('the engine mark stays next to the teacher mark', (float)$b2['engine_score'] === (float)$b['engine_score']);
check('a wrong number is refused with a message', str_contains($post(['action' => 'confirm', 'sub' => $a['id'], 'pts' => ['c1' => '9', 'c2' => '1'], 'feedback' => 'x'])['location'], 'e=rubric_bad_points'));
$post(['action' => 'confirm', 'sub' => $a['id'], 'pts' => ['c1' => '8', 'c2' => (string)$ra['rows'][1]['points']], 'feedback' => $ra['feedback']]);
$post(['action' => 'confirm', 'sub' => $c['id'], 'pts' => ['c1' => '4', 'c2' => '6'], 'feedback' => 'Lien consulté.']);
$p = $page('teacher', '&f=done');
check('the reliability figures appear once copies are checked', str_contains($p['body'], 'Moteur et vous, sur 2 copie(s) vérifiée(s)'), substr(strip_tags($p['body']), 0, 400));
$d = $details('ana');
check('the student now sees the mark (immediate release)', (float)($d['json']['submission']['score'] ?? 0) === (float)$subOf($s1)['score'] && ($d['json']['assignment_state']['status'] ?? '') === 'graded', $d['body']);

// ---------------------------------------------------------------- run again, a new version joins the bucket
Assignments::requestRevision($pdo, (int)$a['id'], $teacher, 'Ajoutez un schéma, merci.');
check('a requested new version is accepted after the deadline', ($r = $submit('ana', [], "$tmp/zz_bk_good.txt"))['json']['success'] === true, $r['body']);
$a2 = $subOf($s1);
check('and goes straight into the bucket, without the old proposal', $a2['in_marking_at'] !== null && $a2['engine_at'] === null && $a2['engine_score'] === null);
$r = $post(['action' => 'rerun']);
check('the engine can be run again', $r['code'] === 303 && str_contains($r['location'], 'msg=rerun'));
for ($i = 0; $i < 5 && MarkingBucket::pending($pdo, $lesson) > 0; $i++) { MarkingBucket::run($pdo, $lesson, 20); }
check('the new version has a proposal', $subOf($s1)['engine_at'] !== null && $subOf($s1)['engine_score'] !== null);
check('marked copies keep their mark when the engine runs again', (float)$subOf($s2)['score'] === (float)$b2['score']);

// ---------------------------------------------------------------- Mes notes
$dash = http($jar('ben'), $base, '/student/dashboard.php');
check('Mes notes shows the course and its mark', $dash['code'] === 200 && str_contains($dash['body'], 'Mes notes par cours') && str_contains($dash['body'], 'ZZ bucket course'));
$g = array_values(array_filter(StudentGrades::forStudent($pdo, $s2, 'zz.bucket.ben@test.local'), fn($x) => $x['id'] === $course))[0] ?? null;
check('the course mark is the released assignment mark on /20', $g !== null && abs((float)$g['mark20'] - round((float)$b2['score'], 2)) < 0.01, json_encode($g));
$g1 = array_values(array_filter(StudentGrades::forStudent($pdo, $s1, 'zz.bucket.ana@test.local'), fn($x) => $x['id'] === $course))[0] ?? null;
check('a copy back in the bucket counts as being marked, not as a mark', $g1 !== null && $g1['mark20'] === null && $g1['pending'] === 1, json_encode($g1));

echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
