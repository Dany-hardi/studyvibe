<?php
declare(strict_types=1);

/**
 * Careers: the public list of open positions, with what the team cares about, how hiring works, and an open application.
 * Postings are written by the administrator in promoter/careers/. Text of this page: locales/careers.php.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Brand.php';
require_once __DIR__ . '/lib/SiteChrome.php';
require_once __DIR__ . '/lib/Careers.php';
require_once __DIR__ . '/lib/CareersArt.php';
require_once __DIR__ . '/lib/CareersWorker.php';
require_once __DIR__ . '/lib/Analytics.php';

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
Analytics::captureSource();
Analytics::hit('view:careers');

$L = (require __DIR__ . '/locales/careers.php')[$lang];
$h = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$plural = static fn(int $n, string $key) => str_replace('{n}', (string)$n, $L[$key . ($n === 1 ? '_one' : '_other')]);
$months = $lang === 'en'
    ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    : ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$date = static function (?string $d) use ($months): string {
    $t = $d ? strtotime($d) : false;
    return $t ? (int)date('j', $t) . ' ' . $months[(int)date('n', $t) - 1] . ' ' . date('Y', $t) : '';
};

$jobs = [];
try {
    $jobs = Careers::publicJobs(Database::getInstance());
    CareersWorker::tick(Database::getInstance());
} catch (Throwable $e) {
    logServerError($e, 'careers.list');
}

$depts = [];
$remote = 0;
foreach ($jobs as $j) {
    if ($j['department'] !== '') {
        $depts[$j['department']] = true;
    }
    if ($j['work_mode'] === 'remote' || $j['work_mode'] === 'hybrid') {
        $remote++;
    }
}
ksort($depts);
$types = array_values(array_unique(array_column($jobs, 'employment_type')));
$modes = array_values(array_unique(array_column($jobs, 'work_mode')));
$isNew = static fn(array $j): bool => !empty($j['published_at']) && strtotime((string)$j['published_at']) >= strtotime('-7 days');

$ico = [
    'pin'   => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.800 12 21 12 21z"/><circle cx="12" cy="9.500" r="2.500"/></svg>',
    'clock' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'home'  => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<?= SiteChrome::head(['title' => $L['title'], 'desc' => $L['desc'], 'css' => ['/assets/css/careers.css']]) ?>
</head>
<body class="v2 ca-body" id="top">
<?= SiteChrome::header($lang, 'careers') ?>

<main id="main">
<section class="ca-hero">
  <div class="ca-wrap ca-hero-grid">
    <div>
      <p class="kicker"><?= $h($L['kicker']) ?></p>
      <h1><?= $L['h1'] ?></h1>
      <p class="ca-lede"><?= $h($L['lede']) ?></p>
      <div class="ca-cta">
        <a class="btn btn-primary btn-lg" href="#roles"><?= $h($L['cta_roles']) ?>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="m6 13 6 6 6-6"/></svg></a>
        <a class="btn btn-ghost btn-lg" href="/career.php?slug=<?= Careers::SPONTANEOUS ?>"><?= $h($L['cta_open']) ?></a>
      </div>
      <?php if ($jobs): ?>
        <ul class="ca-stats">
          <li><span class="num"><?= count($jobs) ?></span> <?= $h(trim(str_replace('{n}', '', $L['stat_roles_' . (count($jobs) === 1 ? 'one' : 'other')]))) ?></li>
          <?php if (count($depts) > 0): ?><li><span class="num"><?= count($depts) ?></span> <?= $h(trim(str_replace('{n}', '', $L['stat_teams_' . (count($depts) === 1 ? 'one' : 'other')]))) ?></li><?php endif; ?>
          <?php if ($remote > 0): ?><li><?= $ico['home'] ?> <?= $h($L['stat_remote']) ?></li><?php endif; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="ca-hero-art"><?= CareersArt::hero($L['art_alt']) ?></div>
  </div>
</section>

<section class="ca-sec ca-sec-alt" aria-labelledby="ca-values-h">
  <div class="ca-wrap">
    <div class="ca-head ca-rv">
      <p class="kicker"><?= $h($L['values_k']) ?></p>
      <h2 id="ca-values-h"><?= $h($L['values_h']) ?></h2>
      <p><?= $h($L['values_p']) ?></p>
    </div>
    <div class="ca-values">
      <?php foreach ($L['values'] as $i => [$icon, $title, $desc]): ?>
        <article class="ca-value ca-rv" style="--d:<?= $i * .08 ?>s">
          <h3><?= $h($title) ?></h3>
          <p><?= $h($desc) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="ca-sec" id="roles" aria-labelledby="ca-roles-h" style="scroll-margin-top:72px">
  <div class="ca-wrap">
    <div class="ca-head ca-rv">
      <p class="kicker"><?= $h($L['roles_k']) ?></p>
      <h2 id="ca-roles-h"><?= $h($L['roles_h']) ?></h2>
      <p><?= $h($L['roles_p']) ?></p>
    </div>

    <?php if ($jobs): ?>
      <form class="ca-filters ca-rv" id="ca-filters" role="search" onsubmit="return false">
        <div class="field">
          <label for="ca-q"><?= $h($L['f_search']) ?></label>
          <input class="input" type="search" id="ca-q" placeholder="<?= $h($L['f_search_ph']) ?>" autocomplete="off">
        </div>
        <div class="field">
          <label for="ca-dept"><?= $h($L['f_dept']) ?></label>
          <select class="input ca-sel" id="ca-dept">
            <option value=""><?= $h($L['f_all_dept']) ?></option>
            <?php foreach (array_keys($depts) as $d): ?><option value="<?= $h($d) ?>"><?= $h($d) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="ca-type"><?= $h($L['f_type']) ?></label>
          <select class="input ca-sel" id="ca-type">
            <option value=""><?= $h($L['f_all_type']) ?></option>
            <?php foreach ($types as $t): ?><option value="<?= $h($t) ?>"><?= $h($L['types'][$t] ?? $t) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="ca-mode"><?= $h($L['f_mode']) ?></label>
          <select class="input ca-sel" id="ca-mode">
            <option value=""><?= $h($L['f_all_mode']) ?></option>
            <?php foreach ($modes as $m): ?><option value="<?= $h($m) ?>"><?= $h($L['modes'][$m] ?? $m) ?></option><?php endforeach; ?>
          </select>
        </div>
      </form>

      <div class="ca-count" aria-live="polite">
        <span id="ca-count" data-one="<?= $h($L['result_one']) ?>" data-other="<?= $h($L['result_other']) ?>"><?= $h($plural(count($jobs), 'result')) ?></span>
        <button type="button" class="ca-reset" id="ca-reset" hidden><?= $h($L['f_reset']) ?></button>
      </div>

      <ul class="ca-jobs" id="ca-jobs">
        <?php foreach ($jobs as $j):
            $hay = mb_strtolower($j['title'] . ' ' . $j['department'] . ' ' . $j['location'] . ' ' . $j['summary'] . ' ' . ($L['types'][$j['employment_type']] ?? '') . ' ' . ($L['modes'][$j['work_mode']] ?? '')); ?>
          <li class="ca-job" data-dept="<?= $h($j['department']) ?>" data-type="<?= $h($j['employment_type']) ?>" data-mode="<?= $h($j['work_mode']) ?>" data-hay="<?= $h($hay) ?>">
            <a href="/career.php?slug=<?= $h($j['slug']) ?>">
              <div>
                <div class="ca-job-top">
                  <h3><?= $h($j['title']) ?></h3>
                  <?php if ($isNew($j)): ?><span class="ca-badge-new"><?= $h($L['new']) ?></span><?php endif; ?>
                </div>
                <?php if ($j['summary'] !== ''): ?><p><?= $h($j['summary']) ?></p><?php endif; ?>
                <ul class="ca-tags">
                  <?php if ($j['department'] !== ''): ?><li class="ca-tag ca-tag-dept"><?= $h($j['department']) ?></li><?php endif; ?>
                  <?php if ($j['location'] !== ''): ?><li class="ca-tag"><?= $ico['pin'] ?><?= $h($j['location']) ?></li><?php endif; ?>
                  <li class="ca-tag"><?= $ico['home'] ?><?= $h($L['modes'][$j['work_mode']] ?? '') ?></li>
                  <li class="ca-tag"><?= $ico['clock'] ?><?= $h($L['types'][$j['employment_type']] ?? '') ?></li>
                </ul>
                <div class="ca-job-meta">
                  <?php if ($j['published_at']): ?><span><?= $h(str_replace('{date}', $date($j['published_at']), $L['posted'])) ?></span><?php endif; ?>
                  <?php if ($j['closes_at']): ?><span><?= $h(str_replace('{date}', $date($j['closes_at']), $L['closes'])) ?></span><?php endif; ?>
                  <?php if ((int)$j['openings'] > 1): ?><span><?= $h($plural((int)$j['openings'], 'openings')) ?></span><?php endif; ?>
                </div>
              </div>
              <span class="ca-go" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="ca-empty ca-empty-plain" id="ca-nomatch" hidden>
        <div>
          <h3><?= $h($L['no_match_h']) ?></h3>
          <p><?= $h($L['no_match_p']) ?></p>
          <a class="btn btn-ghost" href="/career.php?slug=<?= Careers::SPONTANEOUS ?>"><?= $h($L['cta_open']) ?></a>
        </div>
      </div>
    <?php else: ?>
      <div class="ca-empty">
        <?= CareersArt::empty($L['empty_art']) ?>
        <div>
          <h3><?= $h($L['empty_h']) ?></h3>
          <p><?= $h($L['empty_p']) ?></p>
          <a class="btn btn-primary" href="/career.php?slug=<?= Careers::SPONTANEOUS ?>"><?= $h($L['cta_open']) ?></a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="ca-sec ca-sec-alt" aria-labelledby="ca-process-h">
  <div class="ca-wrap">
    <div class="ca-head ca-head-c ca-rv">
      <p class="kicker"><?= $h($L['process_k']) ?></p>
      <h2 id="ca-process-h"><?= $h($L['process_h']) ?></h2>
      <p><?= $h($L['process_p']) ?></p>
    </div>
    <ol class="ca-steps">
      <?php foreach ($L['steps'] as $i => [$title, $desc]): ?>
        <li class="ca-step ca-rv" style="--d:<?= $i * .08 ?>s">
          <span class="ca-step-n num"><?= $i + 1 ?></span>
          <h3><?= $h($title) ?></h3>
          <p><?= $h($desc) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<?php if ($jobs): ?>
<section class="ca-sec" aria-labelledby="ca-open-h">
  <div class="ca-wrap">
    <div class="ca-band">
      <div>
        <h2 id="ca-open-h"><?= $h($L['open_h']) ?></h2>
        <p><?= $h($L['open_p']) ?></p>
        <a class="btn btn-lg" href="/career.php?slug=<?= Careers::SPONTANEOUS ?>"><?= $h($L['open_cta']) ?></a>
      </div>
      <div class="ca-band-art"><?= CareersArt::envelope($L['open_art']) ?></div>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="ca-sec <?= $jobs ? 'ca-sec-alt' : '' ?>" aria-labelledby="ca-faq-h">
  <div class="ca-wrap">
    <div class="ca-head ca-head-c ca-rv">
      <p class="kicker"><?= $h($L['faq_k']) ?></p>
      <h2 id="ca-faq-h"><?= $h($L['faq_h']) ?></h2>
    </div>
    <div class="ca-faq">
      <?php foreach ($L['faq'] as [$q, $a]): ?>
        <details class="ca-rv">
          <summary><?= $h($q) ?></summary>
          <div class="ca-a"><?= $a /* our own text, may contain a link */ ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
</main>

<?= SiteChrome::footer($lang) ?>
<?= SiteChrome::scripts() ?>
<script src="/assets/js/careers.js" defer></script>
</body>
</html>
