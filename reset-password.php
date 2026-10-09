<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Brand.php';
require_once __DIR__ . '/lib/AuthTokens.php';

$token = trim((string)($_GET['token'] ?? ''));
$valid = false;

if ($token !== '') {
    try {
        $pdo = Database::getInstance();
        $valid = AuthTokens::validatePasswordReset($pdo, $token) !== null;
    } catch (PDOException) {
        $valid = false;
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="v2">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#F5F0E6">
    <title>Nouveau mot de passe — StudyVibe</title>
    <?= Brand::headLinks() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/sv2.css">
    <link rel="stylesheet" href="/assets/css/landing.css">
    <?= csrfMetaTag(); ?>
    <script>
      try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
    </script>
    <style>
        body { min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: var(--paper); color: var(--ink); font-family: var(--font-body); }
        .rp-card { width: 100%; max-width: 440px; background: var(--card, var(--paper)); border: 1px solid var(--line, var(--paper-2)); border-radius: 14px; padding: 36px 32px 30px; box-shadow: 0 10px 40px rgba(30, 27, 22, .07); }
        .rp-logo { display: flex; justify-content: center; margin-bottom: 22px; }
        .rp-card h1 { font: 500 1.7rem/1.2 var(--font-display); letter-spacing: -.02em; margin: 0 0 .5rem; text-align: center; }
        .rp-card .sub { text-align: center; color: var(--ink-2); font-size: .95rem; line-height: 1.55; margin: 0 0 1.6rem; }
        .rp-card .btn { width: 100%; justify-content: center; }
        .rp-card .btn[disabled] { opacity: .55; cursor: not-allowed; }
        .rp-rules { list-style: none; padding: 0; margin: .5rem 0 1.2rem; display: grid; gap: .3rem; font-size: .82rem; color: var(--ink-3); }
        .rp-rules li { display: flex; align-items: center; gap: .5rem; }
        .rp-rules li::before { content: ''; width: 14px; height: 14px; border-radius: 50%; border: 1.5px solid currentColor; flex: none; }
        .rp-rules li.ok { color: var(--ok, #2F6B3F); }
        .rp-rules li.ok::before { background: currentColor; box-shadow: inset 0 0 0 3px var(--paper); }
        .rp-msg { margin-top: 14px; padding: 12px 14px; border-radius: 8px; font-size: .9rem; line-height: 1.5; border: 1px solid transparent; }
        .rp-msg.is-error { background: #FBE9E5; border-color: #E8B7AB; color: #8A2A12; }
        .rp-msg.is-busy { background: var(--paper-2); color: var(--ink-2); }
        .rp-state { text-align: center; }
        .rp-mark { width: 56px; height: 56px; border-radius: 50%; display: grid; place-items: center; margin: 0 auto 16px; font-size: 1.6rem; font-weight: 700; }
        .rp-mark.ok { background: #E7F0E9; color: #24402F; }
        .rp-mark.bad { background: #FBE9E5; color: #8A2A12; }
        .rp-card .rp-back { display: block; text-align: center; margin-top: 18px; font-size: .88rem; }
        .rp-back a { color: var(--clay); font-weight: 600; text-decoration: underline; text-underline-offset: 3px; }
        @media (max-width: 480px) { .rp-card { padding: 28px 20px 24px; } }
    </style>
</head>
<body>
<main class="rp-card">
    <div class="rp-logo"><a href="/index.php" aria-label="StudyVibe" style="text-decoration:none"><?= Brand::logo('md') ?></a></div>

<?php if (!$valid): ?>
    <div class="rp-state">
        <div class="rp-mark bad" aria-hidden="true">!</div>
        <h1>Ce lien n’est plus valable</h1>
        <p class="sub">Il a expiré ou il a déjà servi. Pas d’inquiétude, vous pouvez en demander un nouveau en quelques secondes depuis la page de connexion, avec l’option « Mot de passe oublié ».</p>
        <a href="/index.php" class="btn btn-primary btn-lg" style="text-decoration:none">Retour à l’accueil</a>
    </div>
<?php else: ?>
    <div id="rp-form-state">
        <h1>Choisissez un nouveau mot de passe</h1>
        <p class="sub">Saisissez-le deux fois pour éviter toute faute de frappe. Vous pourrez ensuite vous connecter tout de suite.</p>
        <form id="reset-form" novalidate>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES); ?>">
            <div class="field">
                <label for="new-password">Nouveau mot de passe</label>
                <div class="pw">
                    <input class="input" type="password" id="new-password" autocomplete="new-password" minlength="8" required placeholder="8 caractères minimum">
                    <button type="button" class="btn-text" data-toggle-pw="new-password">Afficher</button>
                </div>
                <div class="meter"><i id="rp-bar"></i></div>
            </div>
            <div class="field">
                <label for="confirm-password">Confirmer le mot de passe</label>
                <div class="pw">
                    <input class="input" type="password" id="confirm-password" autocomplete="new-password" required placeholder="Retapez le mot de passe">
                    <button type="button" class="btn-text" data-toggle-pw="confirm-password">Afficher</button>
                </div>
            </div>
            <ul class="rp-rules" aria-live="polite">
                <li id="rule-len">Au moins 8 caractères</li>
                <li id="rule-match">Les deux mots de passe sont identiques</li>
            </ul>
            <button type="submit" class="btn btn-primary btn-lg" id="rp-submit" disabled>Enregistrer le mot de passe</button>
            <div id="rp-msg" class="rp-msg" role="status" aria-live="polite" hidden></div>
        </form>
    </div>

    <div id="rp-done-state" class="rp-state" hidden>
        <div class="rp-mark ok" aria-hidden="true">✓</div>
        <h1>Mot de passe mis à jour</h1>
        <p class="sub">C’est fait. Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.</p>
        <a href="/index.php" class="btn btn-primary btn-lg" style="text-decoration:none">Me connecter</a>
    </div>
<?php endif; ?>
    <p class="rp-back"><a href="/index.php">← Retour à l’accueil StudyVibe</a></p>
</main>
<script src="/assets/js/app.js"></script>
<script>
(function () {
    const form = document.getElementById('reset-form');
    if (!form) return;
    const pw = document.getElementById('new-password'), cf = document.getElementById('confirm-password');
    const bar = document.getElementById('rp-bar'), btn = document.getElementById('rp-submit'), msg = document.getElementById('rp-msg');
    const ruleLen = document.getElementById('rule-len'), ruleMatch = document.getElementById('rule-match');

    document.querySelectorAll('[data-toggle-pw]').forEach(b => b.addEventListener('click', () => {
        const i = document.getElementById(b.dataset.togglePw), hidden = i.type === 'password';
        i.type = hidden ? 'text' : 'password';
        b.textContent = hidden ? 'Masquer' : 'Afficher';
    }));

    function show(kind, text) { msg.hidden = false; msg.className = 'rp-msg is-' + kind; msg.textContent = text; }

    function check() {
        const n = pw.value.length, lenOk = n >= 8, matchOk = cf.value !== '' && cf.value === pw.value;
        const score = Math.min(100, n * 12 + (/\d/.test(pw.value) ? 20 : 0) + (/[A-Z]/.test(pw.value) ? 15 : 0));
        bar.style.width = n ? score + '%' : '0';
        bar.style.background = score < 40 ? 'var(--danger)' : score < 70 ? 'var(--ochre)' : 'var(--ok)';
        ruleLen.classList.toggle('ok', lenOk);
        ruleMatch.classList.toggle('ok', matchOk);
        cf.classList.toggle('invalid', cf.value !== '' && !matchOk);
        btn.disabled = !(lenOk && matchOk);
        if (!msg.hidden && msg.classList.contains('is-error')) msg.hidden = true;
    }
    pw.addEventListener('input', check);
    cf.addEventListener('input', check);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (btn.disabled) return;
        btn.disabled = true;
        const label = btn.textContent;
        btn.textContent = 'Enregistrement…';
        show('busy', 'Nous enregistrons votre nouveau mot de passe…');
        const fd = new FormData();
        fd.append('token', form.querySelector('[name=token]').value);
        fd.append('password', pw.value);
        try {
            const data = await svPost('/reset-password-action.php', fd);
            if (data.success) {
                document.getElementById('rp-form-state').hidden = true;
                document.getElementById('rp-done-state').hidden = false;
                return;
            }
            show('error', data.message || 'Une erreur est survenue. Veuillez réessayer.');
        } catch {
            show('error', 'Connexion impossible pour le moment. Vérifiez votre réseau et réessayez.');
        }
        btn.textContent = label;
        check();
    });
})();
</script>
</body>
</html>
