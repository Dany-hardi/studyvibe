<?php
declare(strict_types=1);

/**
 * Course material sent through the public form (contribute.php) and read on the Contributions page (needs the app running,
 * default http://127.0.0.1:8123). Throw-away rows zz.contrib.*@test.local are removed at the end.
 *
 *   php tests/integration/contributions.php [http://127.0.0.1:8123]
 */

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../lib/CourseMaterials.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');
$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok, string $detail = ''): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what . ($detail !== '' ? " ($detail)" : ''); }

$tag = 'zz.contrib';
$tmp = sys_get_temp_dir() . '/zz_contrib_' . bin2hex(random_bytes(3));
@mkdir($tmp);
$cleanup = function () use ($pdo, $tag, $tmp) {
    foreach ($pdo->query("SELECT f.file FROM material_files f JOIN material_submissions s ON s.id = f.submission_id WHERE s.email LIKE '$tag.%'")->fetchAll(PDO::FETCH_COLUMN) as $f) {
        MediaStore::delete($pdo, 'material', (string)$f);
    }
    $pdo->exec("DELETE FROM material_submissions WHERE email LIKE '$tag.%'");
    $pdo->exec("DELETE FROM filieres WHERE name LIKE 'ZZ %'");
    $pdo->exec("DELETE FROM courses WHERE title = 'ZZ contrib course'");
    $pdo->exec("DELETE FROM users WHERE email LIKE '$tag.%@test.local'");
    $pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'material:%'");
    foreach (glob("$tmp/*") ?: [] as $f) { @unlink($f); }
    @rmdir($tmp);
};
$cleanup();
@mkdir($tmp);
register_shutdown_function($cleanup);

// files
$pdf = function (string $name, int $pad = 0) use ($tmp): string {
    $p = "$tmp/$name";
    file_put_contents($p, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n" . str_repeat('x', $pad) . "\n%%EOF\n");
    return $p;
};
$a = $pdf('chapitre1.pdf');
$b = $pdf('chapitre2.pdf', 1000);
$fake = "$tmp/devoir.pdf"; file_put_contents($fake, "<?php echo 'hello'; ?>");
$big = $pdf('gros.pdf', CourseMaterials::MAX_BYTES + 1000);

// unit-level checks
check('a real PDF is recognised, a renamed script is not', CourseMaterials::isPdf($a) && !CourseMaterials::isPdf($fake));
$norm = CourseMaterials::normalizeFiles(['name' => ['a.pdf', ''], 'tmp_name' => ['/x', ''], 'error' => [0, UPLOAD_ERR_NO_FILE], 'size' => [10, 0]]);
check('an empty file slot of the multiple input is skipped', count($norm) === 1 && $norm[0]['name'] === 'a.pdf');

function http(string $jar, string $base, string $path, array|string|null $post = null, array $headers = []): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $headers]);
    if ($post !== null) {
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post]);
    }
    $raw = (string)curl_exec($ch);
    $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $head = substr($raw, 0, $hs);
    preg_match('/^Location:\s*(\S+)/mi', $head, $m);
    return ['code' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'location' => $m[1] ?? '', 'head' => $head, 'body' => substr($raw, $hs)];
}
$anon = "$tmp/jar_anon";
// the form and its signed stamp; a submission needs to wait 3 s after the form was drawn
$stamp = function () use ($anon, $base): string {
    $p = http($anon, $base, '/contribute.php');
    preg_match('/name="_t" value="([^"]+)"/', $p['body'], $m);
    return $m[1] ?? '';
};
$fields = fn(string $email, string $t) => ['email' => $email, 'name' => 'Ada Test', 'filiere' => 'ZZ Informatique', 'level' => 'L2', 'course' => 'ZZ Algorithmique', 'note' => 'chapitres 1 et 2', '_t' => $t];
$withFiles = function (array $f, array $paths): array {
    foreach (array_values($paths) as $i => $p) { $f["files[$i]"] = new CURLFile($p, 'application/pdf', basename($p)); }
    return $f;
};
$count = fn() => (int)$pdo->query("SELECT COUNT(*) FROM material_submissions WHERE email LIKE '$tag.%'")->fetchColumn();

$p = http($anon, $base, '/contribute.php');
check('the public form opens without an account', $p['code'] === 200 && str_contains($p['body'], 'name="files[]"') && str_contains($p['body'], 'name="_t"'));
$t = $stamp();

$r = http($anon, $base, '/contribute.php', $withFiles($fields("$tag.fast@test.local", $t), [$a]));
check('a form sent in under 3 seconds looks accepted but nothing is stored (bot)', str_contains($r['location'], 'sent=MAT-') && $count() === 0, $r['location']);
sleep(3);
$r = http($anon, $base, '/contribute.php', $withFiles($fields("$tag.bot@test.local", $t) + ['website' => 'http://spam'], [$a]));
check('a filled honeypot looks accepted but nothing is stored', str_contains($r['location'], 'sent=MAT-') && $count() === 0);
$r = http($anon, $base, '/contribute.php', $withFiles($fields("$tag.x@test.local", $t), [$a]), ['Origin: https://evil.example']);
check('a post from another site is refused', $r['code'] === 403 && $count() === 0, (string)$r['code']);

$r = http($anon, $base, '/contribute.php', $withFiles($fields("$tag.ada@test.local", $t), [$a, $b]));
check('two real PDFs are accepted and the sender sees a reference', $r['code'] === 303 && preg_match('/sent=(MAT-[0-9A-Z]+)/', $r['location'], $mm) === 1, $r['code'] . ' ' . $r['location'] . ' ' . substr(strip_tags($r['body']), 0, 200));
$sub = $pdo->query("SELECT * FROM material_submissions WHERE email = '$tag.ada@test.local'")->fetch(PDO::FETCH_ASSOC) ?: [];
$files = $sub ? $pdo->query("SELECT * FROM material_files WHERE submission_id = " . (int)$sub['id'] . " ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) : [];
check('the submission is stored with its fields, status new', ($sub['status'] ?? '') === 'new' && $sub['filiere'] === 'ZZ Informatique' && $sub['level'] === 'L2' && $sub['course_label'] === 'ZZ Algorithmique' && $sub['reference'] === ($mm[1] ?? ''));
check('both files are stored on disk and in the database copy', count($files) === 2 && is_file(MediaStore::path('material', $files[0]['file']))
    && (int)$pdo->query("SELECT COUNT(*) FROM media_files WHERE media_key = 'material/" . $files[1]['file'] . "'")->fetchColumn() === 1 && $files[0]['original_name'] === 'chapitre1.pdf');
check('the thank-you screen shows the reference', str_contains(http($anon, $base, '/contribute.php?sent=' . ($mm[1] ?? ''))['body'], (string)($mm[1] ?? 'none')));

$before = $count();
$r = http($anon, $base, '/contribute.php', $withFiles($fields("$tag.fake@test.local", $t), [$fake]));
check('a script renamed .pdf is refused, with the file named', $r['code'] === 422 && str_contains($r['body'], 'devoir.pdf') && $count() === $before, (string)$r['code']);
$r = http($anon, $base, '/contribute.php', $withFiles($fields("$tag.big@test.local", $t), [$big]));
check('a PDF over 10 MB is refused', $r['code'] === 422 && $count() === $before, (string)$r['code']);
$pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'material:%'");
$r = http($anon, $base, '/contribute.php', $withFiles($fields("$tag.many@test.local", $t), [$a, $a, $a, $a, $a, $a]));
check('six PDFs at once are refused', $r['code'] === 422 && $count() === $before, (string)$r['code']);
$r = http($anon, $base, '/contribute.php', $withFiles(['email' => 'not-an-email'] + $fields('', $t), [$a]));
check('a bad email is refused and the typed course is kept in the form', $r['code'] === 422 && str_contains($r['body'], 'value="ZZ Algorithmique"') && $count() === $before);
$r = http($anon, $base, '/contribute.php', $fields("$tag.nofile@test.local", $t));
check('a submission without a file is refused', $r['code'] === 422 && $count() === $before);
$pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'material:%'");
for ($i = 0; $i < 6; $i++) { http($anon, $base, '/contribute.php', $fields("$tag.rate$i@test.local", $t)); }
$r = http($anon, $base, '/contribute.php', $withFiles($fields("$tag.rate@test.local", $t), [$a]));
check('after six tries in an hour from one address, the form says to wait', $r['code'] === 429 && $count() === $before, (string)$r['code']);
$pdo->exec("DELETE FROM rate_hits WHERE bucket LIKE 'material:%'");

// ---- the Contributions page
$pw = 'Contrib-test-4Hq!';
$mk = function (string $n, string $role) use ($pdo, $pw, $tag): int {
    $pdo->prepare("INSERT INTO users (email,password,name,role,email_verified_at,is_active,is_approved,plan_id,tour_seen_at) VALUES (?,?,?,?,NOW(),1,1,NULL,NOW())")
        ->execute(["$tag.$n@test.local", password_hash($pw, PASSWORD_DEFAULT), 'ZZ ' . ucfirst($n), $role]);
    return (int)$pdo->lastInsertId();
};
$superId = $mk('boss', 'super_teacher');
$teacherId = $mk('prof', 'teacher');
$mod = (int)$pdo->query("SELECT id FROM modules LIMIT 1")->fetchColumn();
$pdo->prepare("INSERT INTO courses (module_id,teacher_id,created_by,title,description,svg_icon,is_published) VALUES (?,?,?,'ZZ contrib course','d','',1)")->execute([$mod, $teacherId, $teacherId]);
$courseId = (int)$pdo->lastInsertId();
$login = function (string $n) use ($base, $tmp, $pw, $tag): string {
    $jar = "$tmp/jar_$n";
    http($jar, $base, '/login-action.php', http_build_query(['email' => "$tag.$n@test.local", 'password' => $pw]));
    return $jar;
};
$jS = $login('boss');
$jT = $login('prof');
$sid = (int)($sub['id'] ?? 0);
$fid = (int)($files[0]['id'] ?? 0);

check('an anonymous visitor is sent away from the Contributions page', http($anon, $base, '/teacher/contributions.php')['code'] === 302);
check('an ordinary teacher is sent away too', http($jT, $base, '/teacher/contributions.php')['code'] === 302);
$pg = http($jS, $base, "/teacher/contributions.php?id=$sid");
check('a super teacher sees the submission and its files', $pg['code'] === 200 && str_contains($pg['body'], 'ZZ Algorithmique') && str_contains($pg['body'], 'chapitre1.pdf'));
$dash = http($jS, $base, '/teacher/dashboard.php');
check('the super teacher dashboard links to the Contributions page', str_contains($dash['body'], '/teacher/contributions.php'));
check('the dashboard of an ordinary teacher does not', !str_contains(http($jT, $base, '/teacher/dashboard.php')['body'], '/teacher/contributions.php'));

$dl = http($jS, $base, "/teacher/contribution-file.php?id=$fid");
check('the super teacher downloads the PDF as an attachment', $dl['code'] === 200 && str_starts_with($dl['body'], '%PDF') && stripos($dl['head'], 'attachment') !== false && stripos($dl['head'], 'application/pdf') !== false);
check('an ordinary teacher cannot download it', http($jT, $base, "/teacher/contribution-file.php?id=$fid")['code'] === 403);
check('an anonymous visitor cannot download it', http($anon, $base, "/teacher/contribution-file.php?id=$fid")['code'] === 403);
check('the file is not reachable directly in the uploads folder', http($anon, $base, '/uploads/course-material/' . ($files[0]['file'] ?? 'x'))['code'] !== 200);

// decisions
preg_match('/name="csrf_token" value="([^"]+)"/', $pg['body'], $cm);
$csrf = $cm[1] ?? '';
$decide = fn(array $p) => http($jS, $base, '/teacher/contributions.php', http_build_query($p + ['action' => 'status', 'id' => $sid, 'csrf_token' => $csrf]));
$status = fn() => (string)$pdo->query("SELECT status FROM material_submissions WHERE id = $sid")->fetchColumn();
check('a decision without the page token is refused', http($jS, $base, '/teacher/contributions.php', http_build_query(['action' => 'status', 'id' => $sid, 'status' => 'in_review']))['code'] === 403 && $status() === 'new');
$decide(['status' => 'rejected', 'reason' => '']);
check('rejecting needs a reason', $status() === 'new');
$decide(['status' => 'accepted']);
check('accepting needs a course', $status() === 'new');
$decide(['status' => 'accepted', 'course_id' => $courseId]);
check('accepting with a course links the material to it', $status() === 'accepted' && (int)$pdo->query("SELECT course_id FROM material_submissions WHERE id = $sid")->fetchColumn() === $courseId
    && (int)$pdo->query("SELECT reviewed_by FROM material_submissions WHERE id = $sid")->fetchColumn() === $superId);
$decide(['status' => 'used']);
check('marking it used keeps the course', $status() === 'used' && (int)$pdo->query("SELECT course_id FROM material_submissions WHERE id = $sid")->fetchColumn() === $courseId);
check('the decision is in the audit log', (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'material_status' AND details LIKE 'Submission #$sid:%'")->fetchColumn() >= 2);

// filières
http($jS, $base, '/teacher/contributions.php', http_build_query(['action' => 'filiere_add', 'name' => 'ZZ Génie civil', 'csrf_token' => $csrf]));
check('a field added on the Contributions page appears in the public form', str_contains(http($anon, $base, '/contribute.php')['body'], 'ZZ Génie civil'));
$fId = (int)$pdo->query("SELECT id FROM filieres WHERE name = 'ZZ Génie civil'")->fetchColumn();
http($jS, $base, '/teacher/contributions.php', http_build_query(['action' => 'filiere_toggle', 'id' => $fId, 'csrf_token' => $csrf]));
check('a hidden field disappears from the form', !str_contains(http($anon, $base, '/contribute.php')['body'], 'ZZ Génie civil'));

echo "contributions: $passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
