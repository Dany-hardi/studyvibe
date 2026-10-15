<?php
declare(strict_types=1);

/**
 * Archives of the teacher's live evaluations: every snapshot (end of the session, before a reset, a deletion or an exclusion, on request),
 * readable here and downloadable as JSON or as a CSV of the marks. Deleting a session never deletes its archives.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/Archive.php';
require_once __DIR__ . '/../lib/LiveEvents.php';

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'teacher') {
    http_response_code(403);
    exit('Accès non autorisé.');
}
$pdo = Database::getInstance();
$teacherId = (int)$_SESSION['user_id'];
$en = TranslationService::getLang() === 'en';
$h = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$sessionFilter = (int)($_GET['session_id'] ?? $_POST['session_id'] ?? 0);
$flash = '';

// archive a session now
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'snapshot') {
    if (!hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('CSRF');
    }
    $own = $pdo->prepare("SELECT 1 FROM live_eval_sessions WHERE id = :s AND teacher_id = :t");
    $own->execute(['s' => $sessionFilter, 't' => $teacherId]);
    if ($own->fetchColumn()) {
        $before = (int)$pdo->query("SELECT COUNT(*) FROM archives WHERE kind = 'session' AND ref_id = " . $sessionFilter)->fetchColumn();
        Archive::snapshotSession($pdo, $sessionFilter, 'manual', $teacherId);
        $after = (int)$pdo->query("SELECT COUNT(*) FROM archives WHERE kind = 'session' AND ref_id = " . $sessionFilter)->fetchColumn();
        $flash = $after > $before ? ($en ? 'Archive created.' : 'Archive créée.') : ($en ? 'Nothing changed since the last archive: it is already up to date.' : 'Rien n’a changé depuis la dernière archive : elle est à jour.');
    }
}

// downloads
$openId = (int)($_GET['id'] ?? 0);
if ($openId > 0 && isset($_GET['download'])) {
    $a = Archive::open($pdo, $openId, $teacherId);
    if (!$a) { http_response_code(404); exit('Archive introuvable ou altérée.'); }
    auditLog('archive_download', 'archive ' . $openId);
    if ($_GET['download'] === 'csv' && isset($a['data']['registrations'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="notes-archive-' . $openId . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $total = count($a['data']['questions'] ?? []);
        fputcsv($out, ['nom', 'email', 'inscrit_le', 'premiere_connexion', 'rendu_le', 'mode_de_remise', 'note', 'sur', 'pourcentage', 'annule'], ';', '"', '');
        foreach ($a['data']['registrations'] as $r) {
            $n = (int)($r['question_count'] ?? 0) ?: $total;
            $mark = $r['score'] === null ? '' : ($r['correct_count'] !== null ? (int)$r['correct_count'] : (int)round((float)$r['score'] / 100 * $n));
            fputcsv($out, [$r['name'], $r['email'], $r['registered_at'], $r['first_seen'] ?? '', $r['submitted_at'] ?? '', $r['submit_mode'] ?? '', $mark, $n, $r['score'] ?? '', $r['cancelled_at'] ? 'oui' : ''], ';', '"', '');
        }
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="archive-' . $openId . '.json"');
    echo json_encode($a['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$view = $openId > 0 ? Archive::open($pdo, $openId, $teacherId) : null;
$list = Archive::listFor($pdo, $teacherId, $sessionFilter ?: null);
$reasons = $en
    ? ['final' => 'End of the session', 'manual' => 'On request', 'before_delete' => 'Before deletion', 'before_reset' => 'Before a reset', 'excluded' => 'Participant excluded']
    : ['final' => 'Fin de la séance', 'manual' => 'Sur demande', 'before_delete' => 'Avant suppression', 'before_reset' => 'Avant réinitialisation', 'excluded' => 'Participant exclu'];
$T = $en
    ? ['title' => 'Archives', 'back' => 'Back to the dashboard', 'lede' => 'Every live evaluation is archived when it ends, before a reset, a deletion or an exclusion, and on request. Deleting a session never deletes its archives.',
       'now' => 'Archive this session now', 'when' => 'Date', 'what' => 'Archive', 'why' => 'Reason', 'size' => 'Size', 'part' => 'Participants', 'none' => 'No archive yet.', 'open' => 'Read', 'json' => 'JSON', 'csv' => 'Marks (CSV)',
       'name' => 'Name', 'reg' => 'Registered', 'sub' => 'Handed in', 'mark' => 'Mark', 'timeline' => 'Timeline', 'ev' => 'Event', 'all' => 'All archives', 'q' => 'questions', 'sess' => 'Session']
    : ['title' => 'Archives', 'back' => 'Retour au tableau de bord', 'lede' => 'Chaque évaluation en direct est archivée à sa fin, avant une réinitialisation, une suppression ou une exclusion, et sur demande. Supprimer une séance ne supprime jamais ses archives.',
       'now' => 'Archiver cette séance maintenant', 'when' => 'Date', 'what' => 'Archive', 'why' => 'Motif', 'size' => 'Taille', 'part' => 'Participants', 'none' => 'Aucune archive pour le moment.', 'open' => 'Consulter', 'json' => 'JSON', 'csv' => 'Notes (CSV)',
       'name' => 'Nom', 'reg' => 'Inscrit', 'sub' => 'Rendu', 'mark' => 'Note', 'timeline' => 'Chronologie', 'ev' => 'Événement', 'all' => 'Toutes les archives', 'q' => 'questions', 'sess' => 'Séance'];
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="<?= $en ? 'en' : 'fr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($T['title']) ?> — StudyVibe</title>
<?= Brand::headLinks() ?>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400..600&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/sv2.css">
<script>try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}</script>
<style>
  body { margin: 0; background: var(--paper); color: var(--ink); font: 16px/1.5 'Hanken Grotesk', system-ui, sans-serif; }
  main { max-width: 66rem; margin: 0 auto; padding: 1.5rem 1rem 4rem; }
  a { color: var(--clay); }
  h1 { font: 500 clamp(1.6rem, 4vw, 2.2rem)/1.15 'Fraunces', serif; margin: .5rem 0 .25rem; }
  h2 { font: 500 1.25rem/1.25 'Fraunces', serif; margin: 2rem 0 .75rem; }
  .sub { color: var(--ink-2); margin: 0 0 1.25rem; max-width: 46rem; }
  table { width: 100%; border-collapse: collapse; background: var(--card); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
  th, td { text-align: left; padding: .6rem .8rem; border-bottom: 1px solid var(--line); font-size: .92rem; vertical-align: top; }
  th { color: var(--ink-2); font-size: .78rem; letter-spacing: .05em; text-transform: uppercase; }
  .num { font-variant-numeric: tabular-nums; }
  .btn { display: inline-block; padding: .55rem 1rem; min-height: 44px; box-sizing: border-box; border: 1px solid var(--line-2); border-radius: 10px; background: var(--card); color: var(--ink); text-decoration: none; cursor: pointer; font: inherit; font-weight: 600; }
  .btn-main { background: var(--clay); border-color: var(--clay); color: #fff; }
  .btn:focus-visible, a:focus-visible { outline: 2px solid var(--clay); outline-offset: 2px; }
  .flash { background: #E8F2EA; color: #24402F; border-radius: 10px; padding: .7rem 1rem; margin: 0 0 1rem; }
  .overflow { overflow-x: auto; }
  .muted { color: var(--ink-2); }
</style>
</head>
<body>
<main>
  <a href="/teacher/dashboard.php#tab-live-eval"><?= $h($T['back']) ?></a>
  <h1><?= $h($T['title']) ?></h1>
  <p class="sub"><?= $h($T['lede']) ?></p>
  <p><a href="/teacher/attestation.php"><?= $en ? 'Certificate of activity: prove the evaluations you ran' : 'Attestation d’activité : prouvez les évaluations que vous avez menées' ?></a></p>
  <?php if ($flash !== ''): ?><p class="flash" role="status"><?= $h($flash) ?></p><?php endif; ?>

  <?php if ($sessionFilter): ?>
    <form method="post" style="margin:0 0 1rem; display:flex; gap:.6rem; flex-wrap:wrap; align-items:center">
      <input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>"><input type="hidden" name="action" value="snapshot"><input type="hidden" name="session_id" value="<?= $sessionFilter ?>">
      <button class="btn btn-main" type="submit"><?= $h($T['now']) ?></button>
      <a class="btn" href="/teacher/archives.php"><?= $h($T['all']) ?></a>
    </form>
  <?php endif; ?>

  <?php if ($view): $d = $view['data']; $m = $view['meta']; ?>
    <h2><?= $h($m['title']) ?> <span class="muted" style="font-size:.9rem">· <?= $h($reasons[$m['reason']] ?? $m['reason']) ?> · <?= $h($m['created_at']) ?></span></h2>
    <p><a class="btn" href="?id=<?= (int)$m['id'] ?>&download=json"><?= $h($T['json']) ?></a>
       <?php if (isset($d['registrations'])): ?><a class="btn" href="?id=<?= (int)$m['id'] ?>&download=csv"><?= $h($T['csv']) ?></a><?php endif; ?></p>
    <?php if (isset($d['registrations'])): $totalQ = count($d['questions'] ?? []); ?>
      <p class="muted"><?= count($d['registrations']) ?> <?= $h($T['part']) ?> · <?= $totalQ ?> <?= $h($T['q']) ?> · <?= count($d['answers'] ?? []) ?> answers · <?= count($d['code_reviews'] ?? []) ?> code reviews</p>
      <div class="overflow"><table>
        <thead><tr><th><?= $h($T['name']) ?></th><th>E-mail</th><th><?= $h($T['reg']) ?></th><th><?= $h($T['sub']) ?></th><th><?= $h($T['mark']) ?></th></tr></thead>
        <tbody>
        <?php foreach ($d['registrations'] as $r): $n = (int)($r['question_count'] ?? 0) ?: $totalQ; $mark = $r['score'] === null ? null : ($r['correct_count'] !== null ? (int)$r['correct_count'] : (int)round((float)$r['score'] / 100 * $n)); ?>
          <tr><td><?= $h($r['name']) ?></td><td><?= $h($r['email']) ?></td><td class="num"><?= $h($r['registered_at']) ?></td><td class="num"><?= $h($r['submitted_at'] ?? '—') ?><?= !empty($r['submit_mode']) ? ' · ' . $h($r['submit_mode']) : '' ?></td>
              <td class="num"><?= $mark === null ? '—' : '<b>' . $mark . '</b> / ' . $n . ' (' . $h(str_replace('.', ',', (string)round((float)$r['score'], 1))) . ' %)' ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php if (!empty($d['events'])): ?>
        <h2><?= $h($T['timeline']) ?></h2>
        <div class="overflow"><table><thead><tr><th><?= $h($T['when']) ?></th><th><?= $h($T['ev']) ?></th><th>#</th><th></th></tr></thead><tbody>
          <?php foreach (array_slice($d['events'], -200) as $e): ?>
            <tr><td class="num"><?= $h($e['created_at']) ?></td><td><?= $h($e['event']) ?></td><td class="num"><?= $h($e['registration_id'] ?? '') ?></td><td class="muted"><?= $h($e['detail'] ?? '') ?></td></tr>
          <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
    <?php else: ?>
      <pre style="white-space:pre-wrap"><?= $h(json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE)) ?></pre>
    <?php endif; ?>
  <?php endif; ?>

  <h2><?= $h($T['all']) ?></h2>
  <?php if (!$list): ?><p class="muted"><?= $h($T['none']) ?></p><?php else: ?>
  <div class="overflow"><table>
    <thead><tr><th><?= $h($T['when']) ?></th><th><?= $h($T['what']) ?></th><th><?= $h($T['why']) ?></th><th><?= $h($T['part']) ?></th><th><?= $h($T['size']) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $a): ?>
      <tr><td class="num"><?= $h($a['created_at']) ?></td><td><?= $h($a['title']) ?> <span class="muted">· <?= $h($a['kind']) ?></span></td><td><?= $h($reasons[$a['reason']] ?? $a['reason']) ?></td>
          <td class="num"><?= (int)$a['items'] ?></td><td class="num"><?= $h(number_format((int)$a['bytes'] / 1024, 1)) ?> KB</td>
          <td><a href="?id=<?= (int)$a['id'] ?><?= $sessionFilter ? '&session_id=' . $sessionFilter : '' ?>"><?= $h($T['open']) ?></a> · <a href="?id=<?= (int)$a['id'] ?>&download=json"><?= $h($T['json']) ?></a><?= $a['kind'] === 'session' ? ' · <a href="?id=' . (int)$a['id'] . '&download=csv">' . $h($T['csv']) . '</a>' : '' ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</main>
</body>
</html>
