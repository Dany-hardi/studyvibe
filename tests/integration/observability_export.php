<?php
declare(strict_types=1);

/**
 * The traceability export endpoint, through a real web server: a promoter gets a PDF, an Excel workbook, a CSV and a JSON file, each
 * export is written to the audit trail, formulas in the data cannot run in a spreadsheet, and everybody else is refused.
 *
 *   php tests/integration/observability_export.php
 */
require_once __DIR__ . '/../../Database.php';

$pdo = Database::getInstance();
$root = dirname(__DIR__, 2);
$promoter = $pdo->query("SELECT id FROM users WHERE role = 'promoter' AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
$student = $pdo->query("SELECT id FROM users WHERE role = 'student' AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
if (!$promoter || !$student) { echo "needs one promoter and one student, skipped\n"; exit(0); }
$passed = 0; $failed = [];
function check(string $what, bool $ok): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what; }

// A router that signs in as the user named in the ?as= parameter, so one server can play both roles
$router = sys_get_temp_dir() . '/sv_obs_router_' . getmypid() . '.php';
file_put_contents($router, '<?php
$root = ' . var_export($root, true) . ';
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$file = $root . $path;
if (!is_file($file)) { return false; }
$_SERVER["SCRIPT_NAME"] = $path; $_SERVER["SCRIPT_FILENAME"] = $file; chdir(dirname($file));
require_once $root . "/Database.php"; require_once $root . "/auth.php";
$as = $_GET["as"] ?? "";
if ($as !== "") { $u = Database::getInstance()->prepare("SELECT id, role FROM users WHERE id = ?"); $u->execute([(int)$as]); $r = $u->fetch();
  if ($r) { $_SESSION["user_id"] = (int)$r["id"]; $_SESSION["user_role"] = $r["role"]; $_SESSION["last_regen"] = time(); } }
require $file;');
$port = random_int(30000, 39000);
$proc = proc_open(['php', '-S', "127.0.0.1:$port", $router], [['pipe', 'r'], ['file', sys_get_temp_dir() . '/sv_obs_srv.log', 'a'], ['file', sys_get_temp_dir() . '/sv_obs_srv.log', 'a']], $pipes);
usleep(900000);
$call = function (string $query) use ($port): array {
    $ctx = stream_context_create(['http' => ['timeout' => 120, 'ignore_errors' => true]]);
    $body = (string)@file_get_contents("http://127.0.0.1:$port/promoter/export-observability.php?$query", false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $h) { if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) { $code = (int)$m[1]; } }
    return [$code, $body];
};

$before = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'observability_export'")->fetchColumn();
try {
    [$c, $b] = $call("as=$promoter&format=pdf&days=7");
    check('promoter gets a PDF', $c === 200 && str_starts_with($b, '%PDF'));
    [$c, $b] = $call("as=$promoter&format=json&days=30&category=security");
    $j = json_decode($b, true);
    check('JSON is valid and carries the stamp, the summary and the log', $c === 200 && is_array($j) && str_starts_with((string)($j['report']['id'] ?? ''), 'OBS-') && isset($j['summary']['events'], $j['log']));
    check('the category filter only returns security actions', $j && count($j['log']) > 0 && count(array_filter($j['log'], fn($r) => preg_match('/login_failed|login_password_ok_2fa_pending|2fa_|password_reset_|user_deleted|user_created_by_admin|role_|verify_|ops_|account_/', $r['action']))) === count($j['log']));
    [$c, $b] = $call("as=$promoter&format=csv&days=7");
    check('CSV starts with a byte order mark and the reference line', $c === 200 && str_starts_with($b, "\xEF\xBB\xBF") && str_contains(substr($b, 0, 200), 'OBS-'));
    [$c, $b] = $call("as=$promoter&format=xlsx&days=7");
    check('Excel workbook is well-formed XML with six sheets', $c === 200 && @simplexml_load_string($b) !== false && substr_count($b, '<Worksheet ') === 6);
    [$c] = $call("as=$promoter&format=exe");
    check('an unknown format is refused', $c === 400);
    [$c] = $call("as=$student&format=pdf");
    check('a student is refused', $c === 403);
    [$c] = $call("format=json");
    check('an anonymous visitor is refused', $c === 403);
    $after = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'observability_export'")->fetchColumn();
    check('each successful export is written to the audit trail (4 expected)', $after - $before === 4);
    // formulas: a log line that starts with = must be neutralised in CSV
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, 'zz_formula_test', '=HYPERLINK(\"http://x\")', '203.0.113.9')")->execute([$promoter]);
    [$c, $b] = $call("as=$promoter&format=csv&days=1&q=zz_formula_test");
    check('a formula in the data is neutralised in the CSV', str_contains($b, "\"'=HYPERLINK") && !preg_match('/;"?=HYPERLINK/', $b));
} finally {
    proc_terminate($proc);
    @unlink($router);
    $pdo->exec("DELETE FROM audit_logs WHERE action = 'zz_formula_test'");
}
echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
