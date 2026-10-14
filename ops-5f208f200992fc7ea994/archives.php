<?php
declare(strict_types=1);

/** Log archives of the control center: the audit log and the server errors moved out of the live tables by month, and the most recent errors. */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/ControlGate.php';
require_once __DIR__ . '/../lib/Archive.php';

$user = ControlGate::enter();
$pdo = Database::getInstance();
$h = fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rotate' && hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''))) {
    $flash = Archive::rotateLogs($pdo) . ' rows archived';
    auditLog('logs_rotated', $flash);
}
$id = (int)($_GET['id'] ?? 0);
if ($id > 0 && isset($_GET['download'])) {
    $a = Archive::open($pdo, $id);
    if (!$a || $a['meta']['kind'] !== 'logs') { http_response_code(404); exit('Not found'); }
    auditLog('archive_download', 'logs archive ' . $id);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="logs-' . $id . '.json"');
    echo json_encode($a['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
$archives = $pdo->query("SELECT id, title, items, bytes, created_at FROM archives WHERE kind = 'logs' ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
$errors = $pdo->query("SELECT context, message, where_at, hits, first_at, last_at FROM server_errors ORDER BY last_at DESC LIMIT 60")->fetchAll(PDO::FETCH_ASSOC);
$counts = $pdo->query("SELECT (SELECT COUNT(*) FROM audit_logs) a, (SELECT COUNT(*) FROM server_errors) e, (SELECT COUNT(*) FROM archives) r")->fetch(PDO::FETCH_ASSOC);
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow, noarchive">
<title>Archives — Control center</title>
<?= Brand::headLinks() ?>
<link rel="stylesheet" href="ops.css">
<style>main{max-width:70rem;margin:0 auto;padding:1.5rem 1rem 4rem} table{width:100%;border-collapse:collapse} th,td{text-align:left;padding:.45rem .6rem;border-bottom:1px solid rgba(255,255,255,.12);font-size:.9rem;vertical-align:top} .n{font-variant-numeric:tabular-nums} button,.b{padding:.5rem .9rem;border-radius:8px;border:1px solid rgba(255,255,255,.3);background:transparent;color:inherit;cursor:pointer;text-decoration:none;display:inline-block}</style>
</head>
<body class="ops"><main>
  <p><a href="./">← Control center</a></p>
  <h1>Archives</h1>
  <p>Live tables: <b class="n"><?= (int)$counts['a'] ?></b> audit rows (kept <?= Archive::LOG_RETENTION_DAYS ?> days), <b class="n"><?= (int)$counts['e'] ?></b> error kinds (kept <?= Archive::ERROR_RETENTION_DAYS ?> days) · <b class="n"><?= (int)$counts['r'] ?></b> archives in total (sessions, exclusions, logs).</p>
  <?php if ($flash): ?><p role="status"><?= $h($flash) ?></p><?php endif; ?>
  <form method="post"><input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>"><input type="hidden" name="action" value="rotate"><button type="submit">Archive old logs now</button></form>
  <h2>Log archives</h2>
  <table><thead><tr><th>Date</th><th>Content</th><th>Rows</th><th>Size</th><th></th></tr></thead><tbody>
  <?php foreach ($archives as $a): ?><tr><td class="n"><?= $h($a['created_at']) ?></td><td><?= $h($a['title']) ?></td><td class="n"><?= (int)$a['items'] ?></td><td class="n"><?= $h(number_format((int)$a['bytes'] / 1024, 1)) ?> KB</td><td><a href="?id=<?= (int)$a['id'] ?>&download=1">JSON</a></td></tr>
  <?php endforeach; if (!$archives): ?><tr><td colspan="5">Nothing archived yet.</td></tr><?php endif; ?></tbody></table>
  <h2>Latest server errors</h2>
  <table><thead><tr><th>Last</th><th>Hits</th><th>Context</th><th>Message</th><th>Where</th></tr></thead><tbody>
  <?php foreach ($errors as $e): ?><tr><td class="n"><?= $h($e['last_at']) ?></td><td class="n"><?= (int)$e['hits'] ?></td><td><?= $h($e['context']) ?></td><td><?= $h($e['message']) ?></td><td><?= $h($e['where_at']) ?></td></tr>
  <?php endforeach; if (!$errors) : ?><tr><td colspan="5">No error recorded.</td></tr><?php endif; ?></tbody></table>
</main></body></html>
