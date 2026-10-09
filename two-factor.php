<?php
declare(strict_types=1);

/**
 * Second step of signing in, as a page of its own (used when the sign-in form is not the landing dialog, for example the
 * join page of a live exam). It exists only while a password has just been accepted for an account with two-factor
 * authentication; otherwise it sends the visitor back to the home page.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Brand.php';
require_once __DIR__ . '/lib/TwoFactor.php';

if (isLoggedIn() || TwoFactor::pending() === null) {
    header('Location: /index.php');
    exit;
}

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$T = [
    'fr' => ['title' => 'Vérification en deux étapes', 'lede' => 'Ouvrez votre application d’authentification et saisissez le code à 6 chiffres. Vous pouvez aussi utiliser un code de secours.',
             'label' => 'Code', 'btn' => 'Valider', 'busy' => 'Vérification…', 'back' => 'Retour à l’accueil', 'err' => 'Vérification impossible. Réessayez.'],
    'en' => ['title' => 'Two-step verification', 'lede' => 'Open your authenticator app and enter the 6-digit code. You can also use a recovery code.',
             'label' => 'Code', 'btn' => 'Verify', 'busy' => 'Checking…', 'back' => 'Back to home', 'err' => 'Could not verify. Please try again.'],
][$lang];
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $h($T['title']) ?> — StudyVibe</title>
<?= Brand::headLinks() ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400..600&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/sv2.css">
<script>try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}</script>
<style>
  body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--paper); color: var(--ink); font: 16px/1.5 'Hanken Grotesk', system-ui, sans-serif; padding: 1rem; }
  main { width: min(26rem, 100%); background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 1.75rem 1.5rem; }
  h1 { font: 500 1.6rem/1.2 'Fraunces', serif; margin: 1rem 0 .5rem; }
  p { color: var(--ink-2); margin: 0 0 1.25rem; }
  label { display: block; font-weight: 600; margin-bottom: .35rem; }
  input { width: 100%; box-sizing: border-box; min-height: 48px; padding: .6rem .8rem; font: 600 1.3rem 'Hanken Grotesk', sans-serif; letter-spacing: .25em; text-align: center; color: var(--ink); background: var(--paper); border: 1.5px solid var(--line-2); border-radius: 12px; }
  input:focus-visible, button:focus-visible, a:focus-visible { outline: 2px solid var(--clay); outline-offset: 2px; }
  button { width: 100%; min-height: 48px; margin-top: 1rem; border: 0; border-radius: 12px; background: var(--clay); color: #fff; font: 700 1rem 'Hanken Grotesk', sans-serif; cursor: pointer; }
  button:disabled { opacity: .6; }
  .err { margin-top: .75rem; padding: .55rem .75rem; border-radius: 10px; background: color-mix(in srgb, var(--danger) 12%, transparent); color: var(--danger); font-weight: 600; font-size: .9rem; }
  .err[hidden] { display: none; }
  .back { display: block; text-align: center; margin-top: 1rem; font-size: .9rem; color: var(--ink-3); }
</style>
</head>
<body>
<main>
  <?= Brand::logo('md') ?>
  <h1><?= $h($T['title']) ?></h1>
  <p><?= $h($T['lede']) ?></p>
  <form id="f" novalidate>
    <label for="code"><?= $h($T['label']) ?></label>
    <input id="code" name="code" type="text" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" maxlength="12" required autofocus placeholder="123456">
    <div class="err" id="err" role="alert" hidden></div>
    <button id="btn" type="submit"><?= $h($T['btn']) ?></button>
  </form>
  <a class="back" href="/index.php"><?= $h($T['back']) ?></a>
</main>
<script>
  const f = document.getElementById('f'), err = document.getElementById('err'), btn = document.getElementById('btn');
  f.addEventListener('submit', async (e) => {
    e.preventDefault();
    const code = document.getElementById('code').value.trim();
    if (!code) return;
    btn.disabled = true; const label = btn.textContent; btn.textContent = <?= json_encode($T['busy']) ?>;
    try {
      const fd = new FormData(); fd.append('code', code);
      const r = await fetch('/api/2fa-login.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      if (d.success) { location.href = d.redirect; return; }
      if (d.expired) { location.href = '/index.php?auth=login'; return; }
      err.textContent = d.message || <?= json_encode($T['err']) ?>; err.hidden = false; document.getElementById('code').select();
    } catch (x) { err.textContent = <?= json_encode($T['err']) ?>; err.hidden = false; }
    btn.disabled = false; btn.textContent = label;
  });
</script>
</body>
</html>
