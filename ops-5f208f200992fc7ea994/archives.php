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
$verify = null;
$csrfOk = $_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''));
if ($csrfOk) {
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'snapshot_all') {
        @unlink(dirname(__DIR__) . '/uploads/live_cache/archive_sweep.flag');
        $flash = 'Sweep: ' . json_encode(Archive::sweep($pdo));
        auditLog('archive_sweep', 'manual');
    } elseif ($act === 'verify') {
        $verify = Archive::verifyLedger($pdo);
        auditLog('archive_verify', $verify['ok'] ? 'ok' : 'PROBLEM');
    } elseif ($act === 'erase_person') {
        $mail = trim((string)($_POST['email'] ?? ''));
        $q = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $q->execute([strtolower($mail)]);
        $uid = (int)$q->fetchColumn();
        if ($mail === '' || trim((string)($_POST['reason'] ?? '')) === '') {
            $flash = 'An email and a reason are required.';
        } else {
            $r = Archive::redactPerson($pdo, $mail, $uid ?: null, 'owner #' . (int)$user['id'], (string)$_POST['reason']);
            $flash = "Erased from {$r['archives']} archive(s) ({$r['rows']} rows). Figures and marks are kept.";
            auditLog('archive_person_erased', $r['archives'] . ' archives');
        }
    } elseif ($act === 'purge') {
        if (trim((string)($_POST['confirm'] ?? '')) !== 'PURGE ' . (int)($_POST['archive_id'] ?? 0)) {
            $flash = 'Type PURGE followed by the archive number to confirm.';
        } else {
            $r = Archive::purge($pdo, (int)$_POST['archive_id'], (string)($_POST['reason'] ?? ''), 'owner #' . (int)$user['id']);
            $flash = $r['message'];
            auditLog('archive_purged', 'archive ' . (int)$_POST['archive_id']);
        }
    }
}
$id = (int)($_GET['id'] ?? 0);
if ($id > 0 && isset($_GET['download'])) {
    $a = Archive::open($pdo, $id);
    if (!$a) { http_response_code(404); exit('Not found or no longer matches its seal'); }
    auditLog('archive_download', $a['meta']['kind'] . ' archive ' . $id);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $a['meta']['kind'] . '-' . $id . '.json"');
    echo json_encode($a['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
$kind = in_array($_GET['kind'] ?? 'logs', ['logs', 'session', 'registration', 'user', 'course', 'stats', 'attestation'], true) ? (string)($_GET['kind'] ?? 'logs') : 'logs';
$archives = $pdo->prepare("SELECT id, kind, title, reason, items, bytes, redacted_at, created_at FROM archives WHERE kind = ? ORDER BY id DESC LIMIT 200");
$archives->execute([$kind]);
$archives = $archives->fetchAll(PDO::FETCH_ASSOC);
$byKind = $pdo->query("SELECT kind, COUNT(*) n, COALESCE(SUM(bytes),0) b FROM archives GROUP BY kind ORDER BY kind")->fetchAll(PDO::FETCH_ASSOC);
$ledgerTail = $pdo->query("SELECT id, archive_id, event, note, actor, `at` FROM archive_ledger ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
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
  <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>"><input type="hidden" name="action" value="snapshot_all"><button type="submit">Archive everything now</button></form>
  <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>"><input type="hidden" name="action" value="verify"><button type="submit">Verify integrity</button></form>
  <?php if ($verify): ?><p role="status"><?= $verify['ok'] ? '✔ Intact' : '✖ PROBLEM' ?> — <?= (int)$verify['entries'] ?> ledger entries, <?= (int)$verify['archives'] ?> archives. Ledger head <code><?= $h(substr($verify['head'], 0, 16)) ?></code>.
    <?php if ($verify['broken_at']): ?> Chain broken at entry #<?= (int)$verify['broken_at'] ?>.<?php endif; ?>
    <?php if ($verify['mismatches']): ?> Content differs from the ledger: #<?= $h(implode(', #', $verify['mismatches'])) ?>.<?php endif; ?>
    <?php if ($verify['missing']): ?> Missing without a purge: #<?= $h(implode(', #', $verify['missing'])) ?>.<?php endif; ?></p><?php endif; ?>
  <h2>Archives by kind</h2>
  <p><?php foreach ($byKind as $k): ?><a class="b" href="?kind=<?= $h($k['kind']) ?>"><?= $h($k['kind']) ?> · <span class="n"><?= (int)$k['n'] ?></span> · <?= $h(number_format((int)$k['b'] / 1024, 0)) ?> KB</a> <?php endforeach; ?></p>
  <h2><?= $h(ucfirst($kind)) ?> archives</h2>
  <table><thead><tr><th>#</th><th>Date</th><th>Content</th><th>Why</th><th>Rows</th><th>Size</th><th></th></tr></thead><tbody>
  <?php foreach ($archives as $a): ?><tr><td class="n"><?= (int)$a['id'] ?></td><td class="n"><?= $h($a['created_at']) ?></td><td><?= $h($a['title']) ?><?= $a['redacted_at'] ? ' <small>(identities erased ' . $h($a['redacted_at']) . ')</small>' : '' ?></td><td><?= $h($a['reason']) ?></td><td class="n"><?= (int)$a['items'] ?></td><td class="n"><?= $h(number_format((int)$a['bytes'] / 1024, 1)) ?> KB</td><td><a href="?id=<?= (int)$a['id'] ?>&download=1">JSON</a></td></tr>
  <?php endforeach; if (!$archives): ?><tr><td colspan="7">Nothing archived yet.</td></tr><?php endif; ?></tbody></table>
  <h2>Ledger (latest entries)</h2>
  <table><thead><tr><th>#</th><th>Archive</th><th>Event</th><th>Note</th><th>By</th><th>At (UTC)</th></tr></thead><tbody>
  <?php foreach ($ledgerTail as $l): ?><tr><td class="n"><?= (int)$l['id'] ?></td><td class="n"><?= (int)$l['archive_id'] ?></td><td><?= $h($l['event']) ?></td><td><?= $h($l['note']) ?></td><td><?= $h($l['actor']) ?></td><td class="n"><?= $h($l['at']) ?></td></tr>
  <?php endforeach; if (!$ledgerTail): ?><tr><td colspan="6">Empty.</td></tr><?php endif; ?></tbody></table>
  <h2>Erase a person from the archives</h2>
  <p>On an erasure request: their name, email and account link are removed from every archive (rewritten, re-sealed, written to the ledger). Marks and figures stay, anonymous.</p>
  <form method="post"><input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>"><input type="hidden" name="action" value="erase_person">
    <input name="email" type="email" placeholder="email of the person" required> <input name="reason" placeholder="reference of the request" required size="30"> <button type="submit">Erase identity</button></form>
  <h2>Purge an archive (exceptional)</h2>
  <p>Removes the whole archive. Only for a legal order or a proven error. The reason and the archive's SHA-256 stay in the ledger forever.</p>
  <form method="post"><input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>"><input type="hidden" name="action" value="purge">
    <input name="archive_id" type="number" min="1" placeholder="archive #" required> <input name="reason" placeholder="written reason (15+ characters)" required size="36" minlength="15"> <input name="confirm" placeholder="type PURGE <number>" required> <button type="submit">Purge</button></form>
  <h2>Latest server errors</h2>
  <table><thead><tr><th>Last</th><th>Hits</th><th>Context</th><th>Message</th><th>Where</th></tr></thead><tbody>
  <?php foreach ($errors as $e): ?><tr><td class="n"><?= $h($e['last_at']) ?></td><td class="n"><?= (int)$e['hits'] ?></td><td><?= $h($e['context']) ?></td><td><?= $h($e['message']) ?></td><td><?= $h($e['where_at']) ?></td></tr>
  <?php endforeach; if (!$errors) : ?><tr><td colspan="5">No error recorded.</td></tr><?php endif; ?></tbody></table>
</main></body></html>
