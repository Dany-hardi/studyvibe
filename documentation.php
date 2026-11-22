<?php
declare(strict_types=1);

/**
 * Public documentation: getting started, guides by role, live evaluations, certificates, security and FAQ.
 * Text lives in locales/docs.php (FR + EN). Search and the side menu are in assets/js/docs.js.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Brand.php';
require_once __DIR__ . '/lib/SiteChrome.php';
require_once __DIR__ . '/lib/Analytics.php';

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
Analytics::captureSource();
Analytics::hit('view:docs');
$D = (require __DIR__ . '/locales/docs.php')[$lang];
$h = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$icons = [
    'rocket' => '<path d="M5 19c0-3 1-5 3-7l5-5c2-2 5-3 7-3 0 2-1 5-3 7l-5 5c-2 2-4 3-7 3z"/><circle cx="15" cy="9" r="1.6"/><path d="M5 19l-1 3 3-1"/>',
    'cap'    => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11.5V16c0 1.500 2.700 3 6 3s6-1.500 6-3v-4.500"/><path d="M22 9v6"/>',
    'bolt'   => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
    'book'   => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 21V5"/><path d="M9 8h6"/>',
    'seal'   => '<circle cx="12" cy="9" r="6"/><path d="m9 13-2 8 5-3 5 3-2-8"/><path d="m9.500 9 2 2 3.500-4"/>',
    'shield' => '<path d="M12 3l8 3v6c0 4.500-3.200 8-8 9-4.800-1-8-4.500-8-9V6z"/><path d="m9 12 2 2 4-4"/>',
    'lock'   => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    'help'   => '<circle cx="12" cy="12" r="9"/><path d="M9.500 9.500a2.500 2.500 0 1 1 3.500 2.300c-.700.400-1 1-1 1.700"/><circle cx="12" cy="17" r=".6" fill="currentColor"/>',
];
$svg = static fn(string $n): string => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$n] ?? $icons['help']) . '</svg>';

function dcBlock(array $b, callable $h): void
{
    if (isset($b['h'])) {
        echo '<h3>' . $h($b['h']) . '</h3>';
    } elseif (isset($b['p'])) {
        echo '<p>' . $b['p'] . '</p>';
    } elseif (isset($b['ul'])) {
        echo '<ul class="dc-ul">';
        foreach ($b['ul'] as $li) { echo '<li>' . $li . '</li>'; }
        echo '</ul>';
    } elseif (isset($b['ol'])) {
        echo '<ol class="dc-ol">';
        foreach ($b['ol'] as $li) { echo '<li><span>' . $li . '</span></li>'; }
        echo '</ol>';
    } elseif (isset($b['note'])) {
        echo '<aside class="dc-note"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v5h1"/></svg><p>' . $b['note'] . '</p></aside>';
    } elseif (isset($b['cards'])) {
        echo '<div class="dc-cards">';
        foreach ($b['cards'] as [$t, $d, $href]) {
            echo '<a class="dc-card" href="' . $h($href) . '"><strong>' . $h($t) . '</strong><span>' . $h($d) . '</span></a>';
        }
        echo '</div>';
    } elseif (isset($b['table'])) {
        echo '<div class="dc-table-wrap"><table class="dc-table"><thead><tr>';
        foreach ($b['table']['head'] as $th) { echo '<th scope="col">' . $h($th) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ($b['table']['rows'] as $row) {
            echo '<tr>';
            foreach ($row as $i => $cell) { echo $i === 0 ? '<th scope="row">' . $h($cell) . '</th>' : '<td>' . $h($cell) . '</td>'; }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } elseif (isset($b['faq'])) {
        echo '<div class="dc-faq">';
        foreach ($b['faq'] as [$q, $a]) { echo '<details><summary>' . $h($q) . '</summary><p>' . $h($a) . '</p></details>'; }
        echo '</div>';
    }
}
$mail = SiteChrome::contactEmail();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<?= SiteChrome::head(['title' => $D['title'], 'desc' => $D['desc'], 'path' => '/documentation.php', 'css' => ['/assets/css/docs.css']]) ?>
</head>
<body class="v2 dc-body" id="top">
<?= SiteChrome::header($lang, 'docs') ?>

<main id="main">
<section class="dc-hero">
  <div class="sc-wrap dc-hero-in dc-hero-plain">
    <div>
      <p class="kicker"><?= $h($D['kicker']) ?></p>
      <h1><?= $D['h1'] ?></h1>
      <p class="dc-lede"><?= $h($D['lede']) ?></p>
      <form class="dc-search" role="search" onsubmit="return false">
        <label for="dc-q" class="dc-sr"><?= $h($D['search']) ?></label>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.500-3.500"/></svg>
        <input id="dc-q" type="search" placeholder="<?= $h($D['search_ph']) ?>" autocomplete="off">
      </form>
      <p class="dc-meta"><?= $h($D['updated']) ?></p>
    </div>
  </div>
</section>

<div class="sc-wrap dc-layout">
  <nav class="dc-nav" aria-label="<?= $h($D['nav']) ?>">
    <details class="dc-nav-box" open>
      <summary><?= $h($D['nav']) ?></summary>
      <ul>
        <?php foreach ($D['sections'] as $s): ?>
          <li><a href="#<?= $h($s['id']) ?>" data-spy="<?= $h($s['id']) ?>"><?= $svg($s['icon']) ?><span><?= $h($s['h']) ?></span></a></li>
        <?php endforeach; ?>
      </ul>
    </details>
  </nav>

  <div class="dc-main">
    <p class="dc-hits" id="dc-hits" aria-live="polite" hidden data-one="<?= $h($D['hits_one']) ?>" data-other="<?= $h($D['hits_other']) ?>"></p>
    <?php foreach ($D['sections'] as $s): ?>
      <section class="dc-sec" id="<?= $h($s['id']) ?>" aria-labelledby="h-<?= $h($s['id']) ?>">
        <header class="dc-sec-head">
          <span class="dc-sec-ic"><?= $svg($s['icon']) ?></span>
          <div>
            <h2 id="h-<?= $h($s['id']) ?>"><?= $h($s['h']) ?></h2>
            <p><?= $h($s['intro']) ?></p>
          </div>
        </header>
        <div class="dc-body-text">
          <?php foreach ($s['blocks'] as $b) { dcBlock($b, $h); } ?>
        </div>
      </section>
    <?php endforeach; ?>

    <div class="dc-empty" id="dc-empty" hidden><p><?= $h($D['no_hit']) ?></p></div>

    <aside class="dc-help">
      <div>
        <h2><?= $h($D['help_h']) ?></h2>
        <p><?= $h($D['help_p']) ?></p>
      </div>
      <a class="btn btn-primary btn-lg" href="mailto:<?= $h($mail) ?>?subject=<?= rawurlencode($lang === 'en' ? 'StudyVibe support' : 'Assistance StudyVibe') ?>"><?= $h($D['help_cta']) ?></a>
    </aside>
  </div>
</div>
</main>

<?= SiteChrome::footer($lang) ?>
<?= SiteChrome::scripts() ?>
<script src="/assets/js/docs.js" defer></script>
</body>
</html>
