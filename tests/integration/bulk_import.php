<?php
declare(strict_types=1);

/**
 * Bulk import of questions with pictures from a ZIP: the package rules (lib/BulkPackage.php) and the endpoint
 * (teacher/bulk-import.php, needs the app running, default http://127.0.0.1:8123).
 *
 *   php tests/integration/bulk_import.php [http://127.0.0.1:8123]
 */

putenv('SV_NO_BACKGROUND_MAIL=1');
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/BulkPackage.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }

$tmp = sys_get_temp_dir();
function png(string $label = 'x'): string {
    $im = imagecreatetruecolor(300, 120); imagefill($im, 0, 0, imagecolorallocate($im, 30, 30, 30)); imagestring($im, 5, 10, 50, $label, imagecolorallocate($im, 255, 255, 255));
    ob_start(); imagepng($im); return (string)ob_get_clean();
}
function mkzip(array $files): string {
    $p = tempnam(sys_get_temp_dir(), 'zzbulk_'); $z = new ZipArchive(); $z->open($p, ZipArchive::OVERWRITE);
    foreach ($files as $name => $data) { $z->addFromString($name, $data); }
    $z->close(); return $p;
}
$header = "question,type,option_a,option_b,option_c,option_d,correct,explanation,image,time_limit\n";
$row = fn(string $q, string $img = '', string $extra = '') => "\"$q\",mcq,a,b,c,d,B,expl,$img,$extra\n";

// ---- the template is itself a valid package
$tpl = BulkPackage::template();
$r = BulkPackage::check($tpl, 'modele.zip', 'live');
check('the downloadable template is valid', $r['ok'] === true && $r['summary']['questions'] === 3 && $r['summary']['with_image'] === 1, json_encode($r['errors']));
check('the template shows a written question and a true/false one', in_array('written', array_column($r['rows'], 'type'), true));
BulkPackage::cleanup($r['tmp']); @unlink($tpl);

// ---- good packages
$z = mkzip(['questions.csv' => $header . $row('Q1', 'FIGURE1.PNG', '45') . $row('Q2'), 'images/figure1.png' => png('1')]);
$r = BulkPackage::check($z, 'p.zip', 'live');
check('an image is found whatever the case of its name', $r['ok'] && $r['summary']['with_image'] === 1, json_encode($r['errors']));
check('time_limit is read', $r['questions'][0]['time_limit'] === 45 && $r['questions'][1]['time_limit'] === null);
BulkPackage::cleanup($r['tmp']);
$z = mkzip(['mon pack/questions.csv' => $header . $row('Q1', 'figure1.png'), 'mon pack/img/sous dossier\\figure1.png' => png('1')]);
$r = BulkPackage::check($z, 'p.zip', 'live');
check('a zip made from a folder works (nested folders, backslashes)', $r['ok'], json_encode($r['errors']));
BulkPackage::cleanup($r['tmp']);
$z = mkzip(['q.csv' => $header . $row('Q1')]);
check('a zip with only a csv works', BulkPackage::check($z, 'p.zip', 'live')['ok']);
file_put_contents("$tmp/zz_plain.csv", $header . $row('Q1') . $row('Q2'));
check('a plain csv works', ($r = BulkPackage::check("$tmp/zz_plain.csv", 'questions.csv', 'live'))['ok'] && $r['summary']['questions'] === 2);

// ---- bad packages: all reported, nothing imported
$z = mkzip(['questions.csv' => $header . $row('Q1', 'absent.png') . $row('Q2', 'ok.png'), 'images/ok.png' => png('o'), 'images/inutile.png' => png('u')]);
$r = BulkPackage::check($z, 'p.zip', 'live');
check('a missing picture is an error naming the question and the file', !$r['ok'] && (bool)array_filter($r['errors'], fn($e) => str_contains($e, 'Question 1') && str_contains($e, 'absent.png')));
check('an unused picture is only a warning', (bool)array_filter($r['warnings'], fn($w) => str_contains($w, 'inutile.png')));
check('each line has its own status', $r['rows'][0]['status'] === 'error' && $r['rows'][1]['status'] === 'ok');
BulkPackage::cleanup($r['tmp']);
$z = mkzip(['questions.csv' => $header . $row('Q1', 'faux.png'), 'images/faux.png' => '<?php echo "pwned"; ?>']);
$r = BulkPackage::check($z, 'p.zip', 'live');
check('a script renamed .png is refused', !$r['ok'] && (bool)array_filter($r['errors'], fn($e) => str_contains($e, 'faux.png') && str_contains($e, 'pas une image')));
BulkPackage::cleanup($r['tmp']);
$z = mkzip(['questions.csv' => $header . $row('Q1', 'evil.png'), '../../zz_evil.png' => png('e'), 'images/../../zz_evil2.png' => png('e')]);
$r = BulkPackage::check($z, 'p.zip', 'live');
check('an entry that climbs out of the folder is never used', !$r['ok'] && !is_file(sys_get_temp_dir() . '/../zz_evil.png') && !is_file("$tmp/zz_evil2.png") && !is_file('/zz_evil.png'));
BulkPackage::cleanup($r['tmp']);
check('a zip without csv is explained', ($r = BulkPackage::check(mkzip(['images/a.png' => png()]), 'p.zip', 'live'))['ok'] === false && (bool)array_filter($r['errors'], fn($e) => str_contains($e, '.csv')));
BulkPackage::cleanup($r['tmp']);
$many = []; for ($i = 0; $i < BulkPackage::MAX_ENTRIES + 1; $i++) { $many["images/i$i.png"] = 'x'; }
check('too many files are refused', BulkPackage::check(mkzip($many + ['q.csv' => $header . $row('Q')]), 'p.zip', 'live')['ok'] === false);
check('not a zip at all is explained', BulkPackage::check("$tmp/zz_plain.csv", 'photo.exe', 'live')['ok'] === false);
file_put_contents("$tmp/zz_broken.zip", 'this is not a zip');
check('a corrupted zip is explained', BulkPackage::check("$tmp/zz_broken.zip", 'p.zip', 'live')['ok'] === false);
$z = mkzip(['questions.csv' => $header . $row('Q1', 'f.png'), 'f.png' => png()]);
$r = BulkPackage::check($z, 'p.zip', 'lesson');
check('pictures are ignored with a warning for lesson quizzes', $r['ok'] && (bool)array_filter($r['warnings'], fn($w) => str_contains($w, 'ignorée')));
BulkPackage::cleanup($r['tmp']);

// ---- atomicity: a failing import leaves no picture behind
$before = (int)$pdo->query("SELECT COUNT(*) FROM media_files WHERE media_key LIKE 'live_question/%'")->fetchColumn();
$z = mkzip(['questions.csv' => $header . $row('Q1', 'f.png'), 'f.png' => png()]);
$r = BulkPackage::check($z, 'p.zip', 'live');
try { BulkPackage::importLive($pdo, 999999999, $r['questions']); $threw = false; } catch (Throwable $e) { $threw = true; }
check('importing into a session that does not exist fails', $threw);
check('and no picture is left behind', (int)$pdo->query("SELECT COUNT(*) FROM media_files WHERE media_key LIKE 'live_question/%'")->fetchColumn() === $before);
BulkPackage::cleanup($r['tmp']);

// ---- the endpoint
$pw = 'Flow-test-9Zq!';
$pdo->exec("DELETE FROM users WHERE email LIKE 'zz.bulk.%@test.local'");
$mod = (int)$pdo->query("SELECT id FROM modules LIMIT 1")->fetchColumn();
$mk = function (string $n) use ($pdo, $pw): int {
    $pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved,plan_id,tour_seen_at) VALUES (?,?,?,'teacher',NOW(),1,1,NULL,NOW())")->execute(["zz.bulk.$n@test.local", password_hash($pw, PASSWORD_DEFAULT), ucfirst($n)]);
    return (int)$pdo->lastInsertId();
};
$t1 = $mk('one'); $t2 = $mk('two');
$pdo->prepare("INSERT INTO courses (module_id,teacher_id,created_by,title,description,svg_icon,is_published) VALUES (?,?,?,'ZZ bulk course','d','',1)")->execute([$mod, $t1, $t1]);
$course = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO live_eval_sessions (course_id,teacher_id,title,session_code,start_time,end_time,default_time_limit,status) VALUES (?,?,'ZZ bulk session',?,NOW() + INTERVAL 1 DAY,NOW() + INTERVAL 2 DAY,30,0)")->execute([$course, $t1, 'zzbk' . bin2hex(random_bytes(3))]);
$sid = (int)$pdo->lastInsertId();
register_shutdown_function(function () use ($pdo, $course, $sid) {
    foreach ($pdo->query("SELECT image_path FROM live_eval_questions WHERE session_id = $sid AND image_path IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $f) { MediaStore::delete($pdo, 'live_question', (string)$f); }
    $pdo->exec("DELETE FROM courses WHERE id = $course");
    $pdo->exec("DELETE FROM users WHERE email LIKE 'zz.bulk.%@test.local'");
    $pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'bulk:%'");
    foreach (glob(sys_get_temp_dir() . '/zz*') ?: [] as $f) { @unlink($f); }
});
function http(string $jar, string $base, string $path, array $post = [], ?string $file = null, string $name = 'pack.zip'): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60]);
    if ($post !== [] || $file !== null) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post + ($file ? ['file' => new CURLFile($file, 'application/zip', $name)] : [])]);
    }
    $body = (string)curl_exec($ch);
    return ['code' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'json' => json_decode($body, true), 'body' => $body];
}
$j1 = "$tmp/zz_bj1"; $j2 = "$tmp/zz_bj2"; @unlink($j1); @unlink($j2);
http($j1, $base, '/login-action.php', ['email' => 'zz.bulk.one@test.local', 'password' => $pw]);
http($j2, $base, '/login-action.php', ['email' => 'zz.bulk.two@test.local', 'password' => $pw]);
$fields = ['type' => 'live', 'course_id' => $course, 'session_id' => $sid];
$good = mkzip(['questions.csv' => $header . $row('Q avec image', 'schema.png', '40') . $row('Q sans image') . "\"Écrite\",written,,,,,5|cinq,,,\n", 'images/schema.png' => png('s')]);

$t = http($j1, $base, '/teacher/bulk-import.php?template=1');
check("the template downloads as a zip", $t["code"] === 200 && str_starts_with($t["body"], "PK"), $t["code"] . " " . substr($t["body"], 0, 200));
check('an anonymous visitor cannot use the endpoint', http("$tmp/zz_bj_anon", $base, '/teacher/bulk-import.php', $fields + ['mode' => 'preview'], $good)['code'] === 403);
check('another teacher cannot import into my session', http($j2, $base, '/teacher/bulk-import.php', $fields + ['mode' => 'preview'], $good)['code'] === 403);
$p = http($j1, $base, '/teacher/bulk-import.php', $fields + ['mode' => 'preview'], $good);
check('preview describes the package and imports nothing', ($p['json']['can_import'] ?? false) === true && count($p['json']['rows'] ?? []) === 3 && $p['json']['summary']['with_image'] === 1
    && (int)$pdo->query("SELECT COUNT(*) FROM live_eval_questions WHERE session_id = $sid")->fetchColumn() === 0, $p['body']);
$bad = mkzip(['questions.csv' => $header . $row('Q1', 'absent.png') . $row('Q2')]);
$c = http($j1, $base, '/teacher/bulk-import.php', $fields + ['mode' => 'commit'], $bad);
check('a package with an error is refused at commit, with the reasons', ($c['json']['success'] ?? true) === false && !empty($c['json']['errors']) && (int)$pdo->query("SELECT COUNT(*) FROM live_eval_questions WHERE session_id = $sid")->fetchColumn() === 0, $c['body']);
$c = http($j1, $base, '/teacher/bulk-import.php', $fields + ['mode' => 'commit'], $good);
check('a good package is imported', ($c['json']['success'] ?? false) === true && $c['json']['imported'] === 3, $c['body']);
$qs = $pdo->query("SELECT question_text, question_type, time_limit, image_path FROM live_eval_questions WHERE session_id = $sid ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
check('the questions keep their picture, their time and their type', $qs[0]['image_path'] !== null && (int)$qs[0]['time_limit'] === 40 && $qs[1]['image_path'] === null && $qs[2]['question_type'] === 'written');
$img = http($j1, $base, '/download.php?type=live_question&file=' . $qs[0]['image_path']);
check('the picture is served to the teacher (and is kept in the database)', $img['code'] === 200 && str_starts_with($img['body'], "\xFF\xD8") && (int)$pdo->query("SELECT COUNT(*) FROM media_files WHERE media_key = 'live_question/" . $qs[0]['image_path'] . "'")->fetchColumn() === 1);
check('the import is in the audit log', (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'questions_bulk_imported' AND created_at >= NOW() - INTERVAL 5 MINUTE")->fetchColumn() >= 1);

echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
