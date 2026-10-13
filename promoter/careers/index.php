<?php
declare(strict_types=1);

/**
 * Careers workspace for administrators: overview, job postings, applications.
 * Isolated from the rest of the dashboard: its own page, API (api.php), downloads (resume.php, export.php), styles and script.
 * The first data is printed into the page so nothing waits for a request.
 */

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../lib/Brand.php';
require_once __DIR__ . '/../../lib/CareersInterviews.php';
require_once __DIR__ . '/../../lib/CareersWorker.php';
requireRole('promoter');

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$t = (require __DIR__ . '/../../locales/careers-admin.php')[$lang];
$h = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
header('Cache-Control: no-store');

$boot = ['stats' => null, 'jobs' => [], 'apps' => [], 'interviews' => []];
try {
    $pdo = Database::getInstance();
    CareersWorker::tick($pdo);
    $boot = ['stats' => Careers::stats($pdo), 'jobs' => Careers::adminJobs($pdo), 'apps' => Careers::applications($pdo, ['bucket' => 'any'], 1500), 'interviews' => CareersInterviews::list($pdo)];
} catch (Throwable $e) {
    logServerError($e, 'promoter/careers/index');
}
$user = getCurrentUser();
$cfg = [
    'lang' => $lang, 't' => $t, 'boot' => $boot, 'spontaneous' => Careers::SPONTANEOUS,
    'teams' => Careers::teamsFor($lang), 'types' => Careers::TYPES, 'modes' => Careers::MODES, 'jobStatuses' => Careers::JOB_STATUSES, 'appStatuses' => Careers::APP_STATUSES,
];
$tabs = [['overview', $t['tab_overview']], ['jobs', $t['tab_jobs']], ['apps', $t['tab_apps']], ['screening', $t['tab_screening']], ['interviews', $t['tab_interviews']]];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $h($t['page_title']) ?></title>
<?= csrfMetaTag() ?>
<?= Brand::headLinks() ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/sv2.css">
<link rel="stylesheet" href="/assets/css/careers-admin.css">
<script>
  document.documentElement.classList.add('js');
  try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
</script>
</head>
<body class="v2 cx">
<header class="cx-top">
  <div class="cx-top-in">
    <a class="cx-back" href="/promoter/dashboard.php" aria-label="<?= $h($t['back']) ?>">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="m11 6-6 6 6 6"/></svg>
      <span><?= $h($t['back']) ?></span>
    </a>
    <div class="cx-brand"><strong><?= $h($t['title']) ?></strong><span><?= $h($t['sub']) ?></span></div>
    <div class="cx-tools">
      <a class="cx-btn cx-btn-ghost cx-hide-sm" href="/careers.php" target="_blank" rel="noopener"><?= $h($t['view_public']) ?>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7"/><path d="M8 7h9v9"/></svg></a>
      <button class="cx-icon" type="button" id="cx-theme" aria-label="<?= $h($t['theme']) ?>"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 2a6 6 0 0 1 0 12z" fill="currentColor"/></svg></button>
    </div>
  </div>
  <nav class="cx-tabs" role="tablist" aria-label="<?= $h($t['title']) ?>">
    <?php foreach ($tabs as [$key, $label]): ?>
      <button type="button" role="tab" id="tab-<?= $key ?>" aria-controls="panel-<?= $key ?>" data-tab="<?= $key ?>" aria-selected="false"><span><?= $h($label) ?></span><b class="cx-count" data-count="<?= $key ?>" hidden></b></button>
    <?php endforeach; ?>
  </nav>
</header>

<main class="cx-main" id="cx-main">
  <section class="cx-panel" id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" hidden></section>
  <section class="cx-panel" id="panel-jobs" role="tabpanel" aria-labelledby="tab-jobs" hidden></section>
  <section class="cx-panel" id="panel-apps" role="tabpanel" aria-labelledby="tab-apps" hidden></section>
  <section class="cx-panel" id="panel-screening" role="tabpanel" aria-labelledby="tab-screening" hidden></section>
  <section class="cx-panel" id="panel-interviews" role="tabpanel" aria-labelledby="tab-interviews" hidden></section>
</main>

<!-- Application drawer -->
<div class="cx-scrim" id="cx-scrim" hidden></div>
<aside class="cx-drawer" id="cx-drawer" role="dialog" aria-modal="true" aria-labelledby="cx-drawer-title" hidden tabindex="-1"></aside>

<!-- Markdown import -->
<dialog class="cx-dialog cx-dialog-wide" id="cx-md-dlg" aria-labelledby="cx-md-title"></dialog>
<!-- Interview scheduler -->
<dialog class="cx-dialog" id="cx-iv-dlg" aria-labelledby="cx-iv-title"></dialog>
<!-- Job editor -->
<dialog class="cx-dialog" id="cx-job-dlg" aria-labelledby="cx-job-title"></dialog>
<!-- Confirmation -->
<dialog class="cx-dialog cx-confirm" id="cx-confirm" aria-labelledby="cx-confirm-t">
  <form method="dialog">
    <h2 id="cx-confirm-t"></h2>
    <p id="cx-confirm-p"></p>
    <div class="cx-actions"><button class="cx-btn cx-btn-ghost" value="cancel"><?= $h($t['cancel']) ?></button><button class="cx-btn cx-btn-danger" value="ok" id="cx-confirm-ok"><?= $h($t['yes_delete']) ?></button></div>
  </form>
</dialog>
<div class="cx-toasts" id="cx-toasts" aria-live="polite"></div>

<script>window.CAA = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="/assets/js/careers-admin.js" defer></script>
</body>
</html>
