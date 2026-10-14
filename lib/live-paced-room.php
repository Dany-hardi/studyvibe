<?php
declare(strict_types=1);

/**
 * The exam room of a free-pace live evaluation (common start, each student goes at their own speed and hands in when ready).
 * Included by live-session.php once the participant is registered. Everything dynamic is in assets/js/live-paced.js.
 *
 * Expects: $session (row), $registration (row), $code, $regId, $resultsUrl, $isGuestParticipant.
 */
require_once __DIR__ . '/Brand.php';
require_once __DIR__ . '/LiveCodeReview.php';

$prLang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$prCfg = [
    'code' => $code, 'lang' => $prLang, 'title' => (string)$session['title'], 'course' => (string)($session['course_title'] ?? ''),
    'results_url' => $resultsUrl, 'guest' => (bool)$isGuestParticipant,
    'leave_url' => isLoggedIn() ? '/student/dashboard.php' : '/evaluations.php',
    'max_code' => LiveCodeReview::MAX_CODE,
];
?>
<!DOCTYPE html>
<html lang="<?= $prLang ?>" class="v2">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars((string)$session['title']) ?> — StudyVibe</title>
<meta name="robots" content="noindex, nofollow">
<?= Brand::headLinks() ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400..600&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/sv2.css?v=<?= (int)@filemtime(__DIR__ . '/../assets/css/sv2.css') ?>">
<link rel="stylesheet" href="/assets/css/live-paced.css?v=<?= (int)@filemtime(__DIR__ . '/../assets/css/live-paced.css') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js"></script>
<script>
  document.documentElement.classList.add('js');
  try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
  window.PR = <?= json_encode($prCfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
</head>
<body class="v2 pr-body">
<a class="pr-skip" href="#pr-card"><?= $prLang === 'en' ? 'Skip to the question' : 'Aller à la question' ?></a>

<header class="pr-top" id="pr-top" hidden>
  <div class="pr-top-in">
    <div class="pr-brand"><?= Brand::logo('sm') ?></div>
    <div class="pr-title"><strong id="pr-title"></strong><span id="pr-course"></span></div>
    <div class="pr-meta" aria-live="polite">
      <span class="pr-chip" id="pr-count"></span>
      <span class="pr-chip pr-chip-clock" id="pr-end" title=""></span>
      <span class="pr-save" id="pr-save"></span>
    </div>
    <button type="button" class="pr-btn pr-btn-main" id="pr-submit-all"></button>
  </div>
</header>

<main class="pr-main" id="pr-main">

  <section id="pr-loading" class="pr-center"><div class="pr-spinner" aria-hidden="true"></div><p id="pr-loading-t"></p></section>

  <section id="pr-lobby" class="pr-center pr-lobby" hidden>
    <div class="pr-lobby-card">
      <p class="pr-kicker" id="pr-lobby-kicker"></p>
      <h1 id="pr-lobby-title"></h1>
      <p class="pr-lede" id="pr-lobby-lede"></p>
      <div class="pr-count" id="pr-lobby-count" aria-live="polite">--:--</div>
      <ul class="pr-rules" id="pr-rules"></ul>
    </div>
  </section>

  <section id="pr-exam" class="pr-exam" hidden>
    <nav class="pr-nav" id="pr-nav" aria-label=""></nav>
    <article class="pr-card" id="pr-card" tabindex="-1"></article>
  </section>

  <section id="pr-review" class="pr-center" hidden>
    <div class="pr-review-card" role="dialog" aria-modal="true" aria-labelledby="pr-review-title">
      <h2 id="pr-review-title"></h2>
      <p id="pr-review-lede" class="pr-lede"></p>
      <ul class="pr-unanswered" id="pr-unanswered"></ul>
      <div class="pr-review-actions">
        <button type="button" class="pr-btn pr-btn-ghost" id="pr-review-back"></button>
        <button type="button" class="pr-btn pr-btn-danger" id="pr-review-force"></button>
      </div>
    </div>
  </section>

  <section id="pr-done" class="pr-center" hidden>
    <div class="pr-done-card">
      <div class="pr-done-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.2 4.2L19 7"/></svg></div>
      <h1 id="pr-done-title"></h1>
      <p class="pr-lede" id="pr-done-lede"></p>
      <p class="pr-note" id="pr-done-ai"></p>
      <div class="pr-done-actions">
        <a class="pr-btn pr-btn-main" id="pr-done-results" href="#" hidden></a>
        <a class="pr-btn pr-btn-ghost" id="pr-done-leave" href="#"></a>
      </div>
    </div>
  </section>
</main>

<!-- confirmation before the final submission -->
<div class="pr-overlay" id="pr-confirm" hidden role="dialog" aria-modal="true" aria-labelledby="pr-confirm-title">
  <div class="pr-dialog">
    <h2 id="pr-confirm-title"></h2>
    <p id="pr-confirm-text" class="pr-lede"></p>
    <div class="pr-review-actions">
      <button type="button" class="pr-btn pr-btn-ghost" id="pr-confirm-no"></button>
      <button type="button" class="pr-btn pr-btn-main" id="pr-confirm-yes"></button>
    </div>
  </div>
</div>

<!-- figure lightbox -->
<div class="pr-overlay pr-zoom" id="pr-zoom" hidden role="dialog" aria-modal="true"><button type="button" class="pr-zoom-close" id="pr-zoom-close" aria-label="Fermer">×</button><img alt="" id="pr-zoom-img"></div>

<!-- code editor: opens only when the student asks for it -->
<div class="pr-overlay pr-code" id="pr-code" hidden role="dialog" aria-modal="true" aria-labelledby="pr-code-title">
  <div class="prc">
    <header class="prc-head">
      <div class="prc-head-l">
        <span class="prc-kicker" id="pr-code-title"></span>
        <span class="pr-chip" id="pr-code-lang"></span>
        <span class="pr-chip prc-nolimit" id="pr-code-nolimit"></span>
      </div>
      <div class="prc-head-r">
        <span class="prc-lintsum" id="pr-code-lint" role="status"></span>
        <span class="pr-save" id="pr-code-save"></span>
        <button type="button" class="pr-btn pr-btn-ghost" id="pr-code-format"></button>
        <button type="button" class="pr-btn pr-btn-main" id="pr-code-done"></button>
      </div>
    </header>
    <div class="prc-body">
      <aside class="prc-ref" id="pr-code-ref">
        <details open>
          <summary id="pr-code-ref-sum"></summary>
          <div class="prc-ref-text" id="pr-code-ref-text"></div>
          <figure class="prc-ref-fig" id="pr-code-ref-fig" hidden><img alt="" id="pr-code-ref-img"><figcaption><button type="button" class="pr-link" id="pr-code-ref-zoom"></button></figcaption></figure>
        </details>
      </aside>
      <div class="prc-editor">
        <div class="prc-gutter" id="pr-code-gutter" aria-hidden="true"></div>
        <textarea id="pr-code-input" spellcheck="false" autocomplete="off" autocapitalize="off" autocorrect="off" wrap="off"></textarea>
      </div>
    </div>
    <div class="prc-problems" id="pr-code-problems" aria-live="polite"></div>
    <footer class="prc-foot"><span id="pr-code-count"></span><span id="pr-code-hint"></span></footer>
  </div>
</div>

<script src="/assets/js/code-tools.js?v=<?= (int)@filemtime(__DIR__ . '/../assets/js/code-tools.js') ?>"></script>
<script src="/assets/js/live-paced.js?v=<?= (int)@filemtime(__DIR__ . '/../assets/js/live-paced.js') ?>"></script>
</body>
</html>
