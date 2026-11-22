<?php
declare(strict_types=1);

/**
 * Contributions: the course material people sent through contribute.php. Super teachers and promoters only.
 *
 *   GET  ?status=&filiere=&q=   the list          ?id=N   one submission, with its files
 *   POST action=status   id, status (in_review | accepted | rejected | used | new), course_id, reason
 *        action=filiere_add name          action=filiere_toggle id
 *
 * Accepting links the material to a course; "used" means a quiz was built from it, and the sender is told by email
 * (also when it is rejected, with the reason).
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/CourseMaterials.php';

if (!isLoggedIn() || !(isSuperTeacher($_SESSION['user_role'] ?? null) || ($_SESSION['user_role'] ?? '') === 'promoter')) {
    header('Location: /index.php');
    exit;
}
$user = getCurrentUser();
$pdo = Database::getInstance();
$en = (TranslationService::getLang() ?? 'fr') === 'en';
$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$T = $en ? [
    'title' => 'Contributions', 'back' => 'Back to the dashboard',
    'lede' => 'Course material sent through the public form. Read it, link it to a course, and use it to build quizzes from the real course.',
    'link' => 'Public form to share:', 'all' => 'All', 'search' => 'Search (course, email, reference)', 'filter' => 'Filter', 'any_filiere' => 'Every field',
    'when' => 'Received', 'course' => 'Course', 'filiere' => 'Field', 'from' => 'From', 'files' => 'Files', 'status' => 'Status', 'none' => 'Nothing here yet.',
    'open' => 'Open', 'download' => 'Download', 'dup' => 'already sent before', 'note' => 'Note', 'linked' => 'Linked course', 'nolink' => 'not linked yet',
    'decide' => 'Decision', 'to_course' => 'Course', 'choose' => 'Choose a course', 'reason' => 'Reason (needed to reject; sent to the sender)',
    'save' => 'Save', 'reviewed' => 'Last decision', 'filieres' => 'Fields offered in the form', 'add' => 'Add', 'on' => 'shown', 'off' => 'hidden', 'toggle' => 'Show / hide',
    'saved' => 'Saved.', 'err_course' => 'Choose the course first.', 'err_reason' => 'Write a reason to reject.', 'err' => 'Not saved.',
    's' => ['new' => 'New', 'in_review' => 'In review', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'used' => 'Used for a quiz'],
] : [
    'title' => 'Contributions', 'back' => 'Retour au tableau de bord',
    'lede' => 'Les supports de cours envoyés par le formulaire public. Lisez-les, reliez-les à un cours et servez-vous-en pour construire des quiz fidèles au vrai cours.',
    'link' => 'Formulaire public à partager :', 'all' => 'Tous', 'search' => 'Rechercher (cours, e-mail, référence)', 'filter' => 'Filtrer', 'any_filiere' => 'Toutes les filières',
    'when' => 'Reçu', 'course' => 'Cours', 'filiere' => 'Filière', 'from' => 'Envoyé par', 'files' => 'Fichiers', 'status' => 'Statut', 'none' => 'Rien pour le moment.',
    'open' => 'Ouvrir', 'download' => 'Télécharger', 'dup' => 'déjà envoyé auparavant', 'note' => 'Précision', 'linked' => 'Cours relié', 'nolink' => 'pas encore relié',
    'decide' => 'Décision', 'to_course' => 'Cours', 'choose' => 'Choisir un cours', 'reason' => 'Motif (obligatoire pour refuser ; envoyé à l’expéditeur)',
    'save' => 'Enregistrer', 'reviewed' => 'Dernière décision', 'filieres' => 'Filières proposées dans le formulaire', 'add' => 'Ajouter', 'on' => 'affichée', 'off' => 'masquée', 'toggle' => 'Afficher / masquer',
    'saved' => 'Enregistré.', 'err_course' => 'Choisissez d’abord le cours.', 'err_reason' => 'Écrivez un motif pour refuser.', 'err' => 'Non enregistré.',
    's' => ['new' => 'Nouveau', 'in_review' => 'En lecture', 'accepted' => 'Accepté', 'rejected' => 'Refusé', 'used' => 'Utilisé pour un quiz'],
];

$flash = '';
$flashBad = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrfToken(), (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('CSRF');
    }
    $act = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($act === 'status') {
        $status = (string)($_POST['status'] ?? '');
        $res = CourseMaterials::setStatus($pdo, $id, $status, (int)$user['id'], (int)($_POST['course_id'] ?? 0) ?: null, (string)($_POST['reason'] ?? ''));
        if ($res['ok']) {
            auditLog('material_status', "Submission #{$id}: {$res['previous']} -> {$status}");
            if (in_array($status, ['used', 'rejected'], true) && $res['previous'] !== $status) {
                register_shutdown_function(static function () use ($id, $status): void {
                    try {
                        require_once __DIR__ . '/../Mailer.php';
                        $s = CourseMaterials::find(Database::getInstance(), $id);
                        if ($s) {
                            Mailer::materialDecision($s, $status === 'used');
                        }
                    } catch (Throwable $e) {
                        error_log('[materials] decision mail ' . $id . ': ' . $e->getMessage());
                    }
                });
            }
            header('Location: /teacher/contributions.php?id=' . $id . '&ok=1', true, 303);
            exit;
        }
        $flash = $T['err_' . ($res['error'] ?? '')] ?? $T['err'];
        $flashBad = true;
        $_GET['id'] = (string)$id;
    } elseif ($act === 'filiere_add') {
        CourseMaterials::addFiliere($pdo, (string)($_POST['name'] ?? ''));
        header('Location: /teacher/contributions.php?ok=1#filieres', true, 303);
        exit;
    } elseif ($act === 'filiere_toggle') {
        CourseMaterials::toggleFiliere($pdo, $id);
        header('Location: /teacher/contributions.php?ok=1#filieres', true, 303);
        exit;
    }
}
if (!empty($_GET['ok']) && $flash === '') {
    $flash = $T['saved'];
}

$filters = ['status' => (string)($_GET['status'] ?? ''), 'filiere' => (string)($_GET['filiere'] ?? ''), 'q' => trim((string)($_GET['q'] ?? ''))];
$rows = CourseMaterials::search($pdo, $filters);
$counts = CourseMaterials::counts($pdo);
$view = !empty($_GET['id']) ? CourseMaterials::find($pdo, (int)$_GET['id']) : null;
$allFilieres = CourseMaterials::filieres($pdo, true);
$usedFilieres = $pdo->query("SELECT DISTINCT filiere FROM material_submissions ORDER BY filiere")->fetchAll(PDO::FETCH_COLUMN);
$courses = $view ? $pdo->query("SELECT c.id, c.title, u.name AS teacher FROM courses c LEFT JOIN users u ON u.id = c.teacher_id ORDER BY c.title")->fetchAll(PDO::FETCH_ASSOC) : [];
$publicUrl = rtrim((string)APP_URL, '/') . '/contribute.php';
$mb = static fn($b) => number_format(((int)$b) / 1048576, 1, $en ? '.' : ',', ' ') . ($en ? ' MB' : ' Mo');
$qs = static fn(array $over) => '?' . http_build_query(array_filter($over + $filters, fn($v) => $v !== '' && $v !== null));
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
  main { max-width: 72rem; margin: 0 auto; padding: 1.5rem 1rem 4rem; }
  a { color: var(--clay); }
  h1 { font: 500 clamp(1.6rem, 4vw, 2.2rem)/1.15 'Fraunces', serif; margin: .5rem 0 .25rem; }
  h2 { font: 500 1.25rem/1.25 'Fraunces', serif; margin: 2rem 0 .75rem; }
  .sub { color: var(--ink-2); margin: 0 0 1rem; max-width: 50rem; }
  .card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 1rem 1.25rem; }
  table { width: 100%; border-collapse: collapse; background: var(--card); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
  th, td { text-align: left; padding: .6rem .8rem; border-bottom: 1px solid var(--line); font-size: .92rem; vertical-align: top; }
  th { color: var(--ink-2); font-size: .78rem; letter-spacing: .05em; text-transform: uppercase; }
  tr.is-sel td { background: color-mix(in srgb, var(--clay) 8%, var(--card)); }
  .btn { display: inline-flex; align-items: center; padding: .55rem 1rem; min-height: 44px; box-sizing: border-box; border: 1px solid var(--line-2); border-radius: 10px; background: var(--card); color: var(--ink); text-decoration: none; cursor: pointer; font: inherit; font-weight: 600; }
  .btn-main { background: var(--clay); border-color: var(--clay); color: #fff; }
  .btn:focus-visible, a:focus-visible, .input:focus-visible { outline: 2px solid var(--clay); outline-offset: 2px; }
  .flash { background: #E8F2EA; color: #24402F; border-radius: 10px; padding: .7rem 1rem; margin: 0 0 1rem; }
  .flash.bad { background: #FBE9E7; color: #7A2412; }
  .overflow { overflow-x: auto; }
  .muted { color: var(--ink-2); }
  .tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin: 0 0 .9rem; }
  .tabs a { padding: .35rem .8rem; border: 1px solid var(--line); border-radius: 999px; text-decoration: none; color: var(--ink); font-size: .88rem; }
  .tabs a.on { background: var(--ink); color: var(--paper); border-color: var(--ink); }
  .chip { display: inline-block; padding: .1rem .55rem; border-radius: 999px; font-size: .78rem; font-weight: 600; background: var(--paper); border: 1px solid var(--line); white-space: nowrap; }
  .chip.new { background: color-mix(in srgb, var(--clay) 14%, var(--card)); border-color: var(--clay); }
  .chip.accepted, .chip.used { background: #E8F2EA; border-color: #9CC3A6; color: #24402F; }
  .chip.rejected { background: #FBE9E7; border-color: #E6A99B; color: #7A2412; }
  .grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(18rem, 24rem); gap: 1.25rem; align-items: start; }
  @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
  .filters { display: flex; flex-wrap: wrap; gap: .5rem; margin: 0 0 .9rem; }
  .filters .input { width: auto; flex: 1 1 12rem; min-height: 44px; }
  .field { margin: 0 0 .8rem; }
  .files { list-style: none; padding: 0; margin: .5rem 0 0; }
  .files li { display: flex; justify-content: space-between; gap: .75rem; align-items: center; padding: .45rem 0; border-bottom: 1px solid var(--line); font-size: .9rem; word-break: break-word; }
  dl { display: grid; grid-template-columns: auto 1fr; gap: .25rem .9rem; margin: .5rem 0 1rem; font-size: .92rem; }
  dt { color: var(--ink-2); }
  dd { margin: 0; word-break: break-word; }
  code { font-size: .85rem; word-break: break-all; }
</style>
</head>
<body>
<main>
  <a href="<?= ($_SESSION['user_role'] ?? '') === 'promoter' ? '/promoter/dashboard.php' : '/teacher/dashboard.php' ?>"><?= $h($T['back']) ?></a>
  <h1><?= $h($T['title']) ?></h1>
  <p class="sub"><?= $h($T['lede']) ?></p>
  <p class="sub"><?= $h($T['link']) ?> <a href="/contribute.php" target="_blank" rel="noopener"><code><?= $h($publicUrl) ?></code></a></p>
  <?php if ($flash !== ''): ?><p class="flash<?= $flashBad ? ' bad' : '' ?>" role="status"><?= $h($flash) ?></p><?php endif; ?>

  <nav class="tabs" aria-label="<?= $h($T['status']) ?>">
    <a href="<?= $h($qs(['status' => '', 'id' => null])) ?>" class="<?= $filters['status'] === '' ? 'on' : '' ?>"><?= $h($T['all']) ?> (<?= array_sum($counts) ?>)</a>
    <?php foreach (CourseMaterials::STATUSES as $s): ?>
      <a href="<?= $h($qs(['status' => $s, 'id' => null])) ?>" class="<?= $filters['status'] === $s ? 'on' : '' ?>"><?= $h($T['s'][$s]) ?> (<?= (int)$counts[$s] ?>)</a>
    <?php endforeach; ?>
  </nav>
  <form class="filters" method="get">
    <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= $h($filters['status']) ?>"><?php endif; ?>
    <input class="input" type="search" name="q" value="<?= $h($filters['q']) ?>" placeholder="<?= $h($T['search']) ?>" aria-label="<?= $h($T['search']) ?>">
    <select class="input" name="filiere" aria-label="<?= $h($T['filiere']) ?>">
      <option value=""><?= $h($T['any_filiere']) ?></option>
      <?php foreach ($usedFilieres as $f): ?><option value="<?= $h($f) ?>" <?= $filters['filiere'] === $f ? 'selected' : '' ?>><?= $h($f) ?></option><?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= $h($T['filter']) ?></button>
  </form>

  <div class="grid">
    <div class="overflow">
      <table>
        <thead><tr><th><?= $h($T['when']) ?></th><th><?= $h($T['course']) ?></th><th><?= $h($T['filiere']) ?></th><th><?= $h($T['files']) ?></th><th><?= $h($T['status']) ?></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="5" class="muted"><?= $h($T['none']) ?></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr class="<?= $view && (int)$view['id'] === (int)$r['id'] ? 'is-sel' : '' ?>">
            <td class="num"><?= $h(date('d/m/Y H:i', strtotime((string)$r['created_at']))) ?><br><span class="muted" style="font-size:.8rem"><?= $h($r['reference']) ?></span></td>
            <td><a href="<?= $h($qs(['id' => (int)$r['id']])) ?>"><?= $h($r['course_label']) ?></a><?php if ($r['course_title']): ?><br><span class="muted" style="font-size:.82rem">→ <?= $h($r['course_title']) ?></span><?php endif; ?></td>
            <td><?= $h($r['filiere']) ?><?= $r['level'] ? ' · ' . $h($r['level']) : '' ?></td>
            <td class="num"><?= (int)$r['file_count'] ?> · <?= $h($mb($r['total_size'])) ?></td>
            <td><span class="chip <?= $h($r['status']) ?>"><?= $h($T['s'][$r['status']] ?? $r['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <aside>
    <?php if ($view): ?>
      <div class="card">
        <span class="chip <?= $h($view['status']) ?>"><?= $h($T['s'][$view['status']] ?? $view['status']) ?></span>
        <h2 style="margin-top:.6rem"><?= $h($view['course_label']) ?></h2>
        <dl>
          <dt><?= $h($T['filiere']) ?></dt><dd><?= $h($view['filiere']) ?><?= $view['level'] ? ' · ' . $h($view['level']) : '' ?></dd>
          <dt><?= $h($T['from']) ?></dt><dd><?= $h(trim(($view['name'] ?? '') . ' ' . ($view['name'] ? '· ' : '') . $view['email'])) ?></dd>
          <dt><?= $h($T['when']) ?></dt><dd><?= $h(date('d/m/Y H:i', strtotime((string)$view['created_at']))) ?> · <?= $h($view['reference']) ?></dd>
          <dt><?= $h($T['linked']) ?></dt><dd><?= $view['course_title'] ? $h($view['course_title']) : '<span class="muted">' . $h($T['nolink']) . '</span>' ?></dd>
          <?php if ($view['note']): ?><dt><?= $h($T['note']) ?></dt><dd><?= nl2br($h($view['note'])) ?></dd><?php endif; ?>
          <?php if ($view['reason']): ?><dt><?= $h($T['reason']) ?></dt><dd><?= $h($view['reason']) ?></dd><?php endif; ?>
        </dl>
        <strong><?= $h($T['files']) ?></strong>
        <ul class="files">
          <?php foreach ($view['files'] as $f): ?>
            <li><span><?= $h($f['original_name']) ?> <span class="muted">· <?= $h($mb($f['size'])) ?><?= (int)$f['dup_count'] > 0 ? ' · ' . $h($T['dup']) : '' ?></span></span>
              <a class="btn" href="/teacher/contribution-file.php?id=<?= (int)$f['id'] ?>"><?= $h($T['download']) ?></a></li>
          <?php endforeach; ?>
        </ul>

        <form method="post" style="margin-top:1.25rem">
          <input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>">
          <input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$view['id'] ?>">
          <div class="field"><label for="c-status"><strong><?= $h($T['decide']) ?></strong></label>
            <select class="input" id="c-status" name="status">
              <?php foreach (CourseMaterials::STATUSES as $s): ?><option value="<?= $h($s) ?>" <?= $view['status'] === $s ? 'selected' : '' ?>><?= $h($T['s'][$s]) ?></option><?php endforeach; ?>
            </select></div>
          <div class="field"><label for="c-course"><?= $h($T['to_course']) ?></label>
            <select class="input" id="c-course" name="course_id">
              <option value=""><?= $h($T['choose']) ?></option>
              <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$view['course_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= $h($c['title']) ?><?= $c['teacher'] ? ' · ' . $h($c['teacher']) : '' ?></option><?php endforeach; ?>
            </select></div>
          <div class="field"><label for="c-reason"><?= $h($T['reason']) ?></label>
            <textarea class="input" id="c-reason" name="reason" rows="2" maxlength="500"><?= $h($view['reason'] ?? '') ?></textarea></div>
          <button class="btn btn-main" type="submit"><?= $h($T['save']) ?></button>
        </form>
      </div>
    <?php endif; ?>

      <div class="card" id="filieres" style="margin-top:1.25rem">
        <strong><?= $h($T['filieres']) ?></strong>
        <ul class="files">
          <?php foreach ($allFilieres as $f): ?>
            <li><span><?= $h($f['name']) ?> <span class="muted">· <?= $h((int)$f['is_active'] ? $T['on'] : $T['off']) ?></span></span>
              <form method="post"><input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>"><input type="hidden" name="action" value="filiere_toggle"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                <button class="btn" type="submit"><?= $h($T['toggle']) ?></button></form></li>
          <?php endforeach; ?>
        </ul>
        <form method="post" style="display:flex;gap:.5rem;margin-top:.75rem">
          <input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>"><input type="hidden" name="action" value="filiere_add">
          <input class="input" name="name" maxlength="120" required aria-label="<?= $h($T['filiere']) ?>" placeholder="<?= $h($T['filiere']) ?>">
          <button class="btn" type="submit"><?= $h($T['add']) ?></button>
        </form>
      </div>
    </aside>
  </div>
</main>
</body>
</html>
