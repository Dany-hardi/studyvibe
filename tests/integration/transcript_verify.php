<?php
declare(strict_types=1);

/**
 * A transcript is issued, its code is checked on verify.php through a real web server, and the page must say: genuine, altered
 * (a row edited by hand), revoked, unknown. Needs the database from .env and `php -S`. Cleans up after itself.
 *
 *   php tests/integration/transcript_verify.php
 */
require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/Transcripts.php';

$pdo = Database::getInstance();
$uid = $pdo->query("SELECT id FROM users WHERE role = 'student' ORDER BY id LIMIT 1")->fetchColumn();
$passed = 0; $failed = [];
function check(string $what, bool $ok): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what; }
if (!$uid) { echo "no student in the database, skipped\n"; exit(0); }

$good = Transcripts::issue($pdo, (int)$uid, ['courses' => 3, 'lessons' => 7, 'failed' => 0, 'avg_best' => 66.5, 'avg_lessons' => 70, 'progress_avg' => 40]);
$altered = Transcripts::issue($pdo, (int)$uid, ['courses' => 1]);
$revoked = Transcripts::issue($pdo, (int)$uid, ['courses' => 2]);
$pdo->prepare("UPDATE transcript_issues SET summary = ? WHERE code = ?")->execute(['{"courses":99}', $altered['code']]);
$pdo->prepare("UPDATE transcript_issues SET revoked_at = NOW() WHERE code = ?")->execute([$revoked['code']]);

$port = random_int(20000, 29000);
$root = dirname(__DIR__, 2);
$proc = proc_open(['php', '-S', "127.0.0.1:$port", '-t', $root], [['pipe', 'r'], ['file', sys_get_temp_dir() . '/sv_verify_srv.log', 'a'], ['file', sys_get_temp_dir() . '/sv_verify_srv.log', 'a']], $pipes);
usleep(900000);
$get = function (string $code) use ($port): string {
    $ctx = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true]]);
    return (string)@file_get_contents("http://127.0.0.1:$port/verify.php?code=" . urlencode($code), false, $ctx);
};

try {
    $r = $get($good['code']);
    check('a genuine transcript is recognised', str_contains($r, 'Relevé authentique') && str_contains($r, '66,5'));
    check('the summary shows the figures, not detailed grades', str_contains($r, 'Leçons validées'));
    check('lower-case input is accepted', str_contains($get(strtolower($good['code'])), 'Relevé authentique'));
    check('an edited record is reported as altered', str_contains($get($altered['code']), 'Enregistrement altéré'));
    check('a revoked transcript is reported as revoked', str_contains($get($revoked['code']), 'Document révoqué'));
    check('an unknown code is not recognised', str_contains($get('TR-ZZZZ-ZZZZ-ZZZZ'), 'Document non reconnu'));
    check('injection-looking input is not recognised', str_contains($get("x' OR '1'='1"), 'Document non reconnu'));
    check('the page is not indexed', str_contains($r, 'noindex'));
} finally {
    proc_terminate($proc);
    foreach ([$good, $altered, $revoked] as $t) { $pdo->prepare("DELETE FROM transcript_issues WHERE code = ?")->execute([$t['code']]); }
}
echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
