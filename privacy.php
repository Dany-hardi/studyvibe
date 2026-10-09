<?php
declare(strict_types=1);

/**
 * Privacy policy. Text lives in locales/privacy.php (FR + EN), design in assets/css/privacy.css.
 * The language follows the site-wide choice (cookie / profile) and can be switched from the header.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Brand.php';

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';

require_once __DIR__ . '/lib/Analytics.php';
Analytics::hit('view:privacy');
$all  = require __DIR__ . '/locales/privacy.php';
$P    = $all[$lang];

$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

/** Renders one content block. Text comes from our own locale file, so inline tags are allowed. */
function pvBlock(array $b): void
{
    if (isset($b['p'])) {
        echo '<p>' . $b['p'] . '</p>';
    } elseif (isset($b['ul'])) {
        echo '<ul class="pv-list">';
        foreach ($b['ul'] as $li) {
            echo '<li>' . $li . '</li>';
        }
        echo '</ul>';
    } elseif (isset($b['steps'])) {
        echo '<ol class="pv-steps">';
        foreach ($b['steps'] as $li) {
            echo '<li><span>' . $li . '</span></li>';
        }
        echo '</ol>';
    } elseif (isset($b['note'])) {
        echo '<aside class="pv-note"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6l7-3z"/><path d="m9 12 2 2 4-4"/></svg><p>' . $b['note'] . '</p></aside>';
    } elseif (isset($b['table'])) {
        $head = $b['table']['head'];
        echo '<div class="pv-table-wrap"><table class="pv-table"><thead><tr>';
        foreach ($head as $th) {
            echo '<th scope="col">' . htmlspecialchars($th, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($b['table']['rows'] as $row) {
            echo '<tr>';
            foreach ($row as $i => $cell) {
                echo '<td data-label="' . htmlspecialchars($head[$i] ?? '', ENT_QUOTES, 'UTF-8') . '">' . $cell . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" class="v2">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($P['title']) ?> — StudyVibe</title>
<meta name="description" content="<?= $h($P['desc']) ?>">
<meta name="theme-color" content="#F5F0E6">
<?= Brand::headLinks() ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/sv2.css">
<link rel="stylesheet" href="/assets/css/privacy.css">
<script>
  document.documentElement.classList.add('js');
  try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
</script>
</head>
<body class="v2 pv-body">

<header class="pv-top" id="pv-top">
  <div class="pv-top-in">
    <a class="pv-back" href="/index.php">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="m11 6-6 6 6 6"/></svg>
      <span><?= $h($P['back']) ?></span>
    </a>
    <a class="pv-logo" href="/index.php" aria-label="StudyVibe"><?= Brand::logo('md') ?></a>
    <div class="pv-tools">
      <div class="seg" role="group" aria-label="Language">
        <a href="#" data-lang="fr" <?= $lang === 'fr' ? 'aria-current="true"' : '' ?>>FR</a>
        <a href="#" data-lang="en" <?= $lang === 'en' ? 'aria-current="true"' : '' ?>>EN</a>
      </div>
      <button class="chip" type="button" id="pv-theme" aria-label="<?= $lang === 'en' ? 'Switch theme' : 'Changer de thème' ?>"><svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 2a6 6 0 0 1 0 12z" fill="currentColor"/></svg></button>
    </div>
  </div>
  <div class="pv-progress" aria-hidden="true"><i id="pv-bar"></i></div>
</header>

<main>
<section class="pv-hero">
  <div class="pv-wrap pv-hero-grid">
    <div class="pv-hero-text">
      <p class="pv-kicker"><?= $h($P['kicker']) ?></p>
      <h1><?= $P['h1'] ?></h1>
      <p class="pv-lede"><?= $h($P['lede']) ?></p>
      <p class="pv-meta"><span><?= $h($P['updated']) ?></span><span aria-hidden="true">·</span><span><?= $h($P['version']) ?></span></p>
    </div>
    <figure class="pv-art" role="img" aria-label="<?= $h($P['art_alt']) ?>">
      <div class="pv-blob" aria-hidden="true"></div>
      <div class="pv-anim" data-lottie="/assets/anim/privacy-hero.json"></div>
    </figure>
  </div>
</section>

<section class="pv-short" aria-labelledby="pv-short-h">
  <div class="pv-wrap">
    <h2 id="pv-short-h" class="pv-short-h"><?= $h($P['short_h']) ?></h2>
    <div class="pv-short-grid">
      <?php foreach ($P['short'] as $i => [$t, $d]): ?>
        <article class="pv-card" style="--i:<?= $i ?>">
          <span class="pv-card-n num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <h3><?= $h($t) ?></h3>
          <p><?= $h($d) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="pv-wrap pv-layout">
  <nav class="pv-toc" aria-label="<?= $h($P['toc']) ?>">
    <details class="pv-toc-box" id="pv-toc-box" open>
      <summary><?= $h($P['toc']) ?></summary>
      <ol>
        <?php foreach ($P['sections'] as $i => $s): ?>
          <li><a href="#<?= $h($s['id']) ?>" data-spy="<?= $h($s['id']) ?>"><span class="num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span><span class="pv-toc-t"><?= $h($s['h']) ?></span><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></a></li>
        <?php endforeach; ?>
      </ol>
    </details>
  </nav>

  <article class="pv-doc">
    <?php foreach ($P['sections'] as $i => $s): ?>
      <section class="pv-sec" id="<?= $h($s['id']) ?>" aria-labelledby="h-<?= $h($s['id']) ?>">
        <header class="pv-sec-head">
          <span class="pv-sec-n num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <h2 id="h-<?= $h($s['id']) ?>"><?= $h($s['h']) ?></h2>
        </header>
        <?php foreach ($s['blocks'] as $b) { pvBlock($b); } ?>
        <?php if ($s['id'] === 'contact'): ?>
          <div class="pv-contact">
            <div class="pv-contact-art" role="img" aria-label="<?= $h($P['art2_alt']) ?>"><div class="pv-anim" data-lottie="/assets/anim/privacy-rights.json"></div></div>
            <div>
              <h3><?= $h($P['contact_h']) ?></h3>
              <p><?= $h($P['contact_p']) ?></p>
              <a class="btn btn-primary btn-lg" href="mailto:danielwilfriedtakou@gmail.com?subject=<?= rawurlencode($lang === 'en' ? 'My data' : 'Mes données') ?>">
                <?= $h($P['contact_cta']) ?>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
              </a>
            </div>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </article>
</div>
</main>

<footer class="pv-foot"><div class="pv-wrap pv-foot-in">
  <a href="/index.php" class="brand" aria-label="StudyVibe"><?= Brand::logo('sm') ?></a>
  <span>© <?= date('Y') ?> StudyVibe · <?= $h($P['version']) ?> · <?= $h($P['updated']) ?></span>
</div></footer>

<button type="button" class="pv-up" id="pv-up" aria-label="<?= $h($P['top']) ?>" title="<?= $h($P['top']) ?>">
  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m6 11 6-6 6 6"/></svg>
</button>

<script>
(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* Language: same endpoint as the rest of the site, then reload in the new language */
  $$('[data-lang]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      var lang = a.getAttribute('data-lang');
      if (document.documentElement.lang === lang) return;
      fetch('/api/set-language.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ lang: lang }) })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d && d.success) location.reload(); })
        .catch(function () { location.search = '?lang=' + lang; });
    });
  });

  /* Theme */
  $('#pv-theme').addEventListener('click', function () {
    var dark = document.documentElement.classList.toggle('dark');
    try { localStorage.setItem('sv_dark', dark ? '1' : '0'); } catch (e) {}
  });

  /* Reading progress, back-to-top, table of contents follows the section on screen */
  var bar = $('#pv-bar'), up = $('#pv-up'), top = $('#pv-top');
  function onScroll() {
    var max = document.documentElement.scrollHeight - innerHeight;
    bar.style.width = (max > 0 ? Math.min(100, scrollY / max * 100) : 0) + '%';
    up.classList.toggle('is-on', scrollY > 700);
    top.classList.toggle('is-stuck', scrollY > 8);
  }
  addEventListener('scroll', onScroll, { passive: true }); onScroll();
  up.addEventListener('click', function () { scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); });

  var links = {}; $$('[data-spy]').forEach(function (a) { links[a.getAttribute('data-spy')] = a; });
  if ('IntersectionObserver' in window) {
    var visible = {};
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { visible[e.target.id] = e.isIntersecting; });
      var first = $$('.pv-sec').find(function (s) { return visible[s.id]; });
      if (first) { Object.keys(links).forEach(function (k) { links[k].classList.toggle('is-on', k === first.id); }); }
    }, { rootMargin: '-90px 0px -60% 0px' });
    $$('.pv-sec').forEach(function (s) { io.observe(s); });
  }

  /* On phones the contents list starts folded */
  var box = $('#pv-toc-box');
  if (box && matchMedia('(max-width: 980px)').matches) box.removeAttribute('open');
  $$('.pv-toc a').forEach(function (a) { a.addEventListener('click', function () { if (matchMedia('(max-width: 980px)').matches) box.removeAttribute('open'); }); });

  /* Sections fade in as they arrive */
  if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var rv = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); rv.unobserve(e.target); } }); }, { threshold: .08 });
    $$('.pv-sec, .pv-card').forEach(function (el) { el.classList.add('rv'); rv.observe(el); });
  }
})();
</script>
<script src="/assets/js/auth-page.js"></script>
</body>
</html>
