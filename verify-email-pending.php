<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Brand.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/lib/AuthTokens.php';

if (!isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$user = getCurrentUser();
$verified = !empty($user['email_verified_at'] ?? null);

if ($verified) {
    $map = ['promoter' => '/promoter/dashboard.php', 'teacher' => '/teacher/dashboard.php', 'student' => '/student/dashboard.php'];
    header('Location: ' . ($map[$_SESSION['user_role']] ?? '/index.php'));
    exit;
}

$firstName = trim(explode(' ', trim((string)($user['name'] ?? '')))[0] ?? '');
$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr" class="v2">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#F5F0E6">
    <title>Vérifiez votre email — StudyVibe</title>
    <?= Brand::headLinks() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/sv2.css">
    <link rel="stylesheet" href="/assets/css/auth-page.css">
    <?= csrfMetaTag(); ?>
    <script>
      try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
    </script>
</head>
<body class="v2 vp-body">
<main class="vp-card">
    <div class="vp-logo"><a href="/index.php" aria-label="StudyVibe" style="text-decoration:none"><?= Brand::logo('md') ?></a></div>

    <div class="vp-art is-wide" role="img" aria-label="Une étudiante qui reçoit un email"><div class="vp-anim" data-lottie="/assets/anim/verify-pending.json"></div></div>

    <h1><?= $firstName !== '' ? 'Plus qu’une étape, <em>' . $h($firstName) . '</em>' : 'Plus qu’une <em>étape</em>' ?></h1>
    <p class="vp-lead">Nous venons d’envoyer un email de confirmation à</p>
    <span class="vp-mail"><?= $h((string)$user['email']) ?></span>

    <ol class="vp-steps">
        <li><span><strong>Ouvrez votre boîte mail</strong> et cherchez le message de StudyVibe.</span></li>
        <li><span><strong>Cliquez sur « Vérifier mon email »</strong> dans le message. Le lien reste valable 48 heures.</span></li>
        <li><span><strong>Revenez ici</strong> : vous serez dirigé vers votre espace.</span></li>
    </ol>

    <div class="vp-actions">
        <button type="button" id="continue-btn" class="btn btn-primary btn-lg">J’ai confirmé mon email</button>
        <button type="button" id="resend-btn" class="btn btn-ghost btn-lg">Renvoyer l’email</button>
    </div>
    <div id="vp-msg" class="vp-msg" role="status" aria-live="polite" hidden></div>

    <p class="vp-hint">Rien dans votre boîte ? Regardez dans les courriers indésirables. <br>Mauvaise adresse ? <a href="/logout.php">Se déconnecter</a> et recréer le compte.</p>
</main>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/auth-page.js"></script>
<script>
(function () {
    const resend = document.getElementById('resend-btn'), cont = document.getElementById('continue-btn'), box = document.getElementById('vp-msg');
    const LABEL = 'Renvoyer l’email', COOLDOWN = 60;
    let timer = null;

    function show(kind, text) { box.hidden = false; box.className = 'vp-msg is-' + kind; box.textContent = text; }

    function cooldown(sec) {
        clearInterval(timer);
        resend.disabled = true;
        const tick = () => {
            if (sec <= 0) { clearInterval(timer); resend.disabled = false; resend.textContent = LABEL; return; }
            resend.textContent = 'Renvoyer dans ' + sec-- + ' s';
        };
        tick();
        timer = setInterval(tick, 1000);
    }

    if (new URLSearchParams(location.search).has('checked')) {
        show('error', 'Votre email n’est pas encore confirmé. Ouvrez le message de StudyVibe et cliquez sur le bouton, puis revenez ici.');
    }

    resend.addEventListener('click', async () => {
        resend.disabled = true;
        resend.textContent = 'Envoi en cours…';
        show('busy', 'Nous vous renvoyons l’email…');
        try {
            const data = await svPost('/resend-verification-action.php', new FormData());
            if (data.success) {
                show('ok', 'C’est parti. Un nouvel email vient d’être envoyé à <?= $h((string)$user['email']) ?>. Pensez à regarder vos indésirables.');
                cooldown(COOLDOWN);
                return;
            }
            show('error', data.message || 'L’envoi a échoué. Réessayez dans un instant.');
        } catch (e) {
            show('error', 'Connexion impossible pour le moment. Vérifiez votre réseau et réessayez.');
        }
        resend.disabled = false;
        resend.textContent = LABEL;
    });

    // The server sends verified users straight to their dashboard, so a reload is the check
    cont.addEventListener('click', () => {
        cont.disabled = true;
        cont.textContent = 'Vérification…';
        show('busy', 'Nous vérifions votre confirmation…');
        setTimeout(() => { location.href = '/verify-email-pending.php?checked=1'; }, 400);
    });
})();
</script>
</body>
</html>
