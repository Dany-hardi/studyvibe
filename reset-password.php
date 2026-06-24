<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
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
<html lang="fr" class="sv-cream">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe — StudyVibe</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <?= csrfMetaTag(); ?>
</head>
<body class="sv-landing sv-page">
<div class="sv-container" style="max-width:400px;margin:4rem auto;padding:2rem;border:1px solid var(--sv-border-strong);background:var(--sv-surface);">
    <h1 class="sv-auth-card-title" style="margin-bottom:1rem;">Nouveau mot de passe</h1>
    <?php if (!$valid): ?>
        <p class="sv-signup-intro" style="text-align:left;color:#D32F2F;">Ce lien est invalide ou a expiré.</p>
        <a href="/index.php" class="sv-btn sv-btn-primary" style="display:inline-block;margin-top:1rem;text-decoration:none;">Retour à l'accueil</a>
    <?php else: ?>
        <form id="reset-form" class="space-y-4">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">
            <div class="sv-field">
                <label class="sv-field-label" for="new-password">Nouveau mot de passe</label>
                <input type="password" id="new-password" class="sv-field-input" minlength="6" required placeholder="6 caractères minimum">
            </div>
            <button type="submit" class="sv-btn-submit">Enregistrer</button>
        </form>
    <?php endif; ?>
</div>
<script src="/assets/js/app.js"></script>
<script>
document.getElementById('reset-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('token', document.querySelector('[name=token]').value);
    fd.append('password', document.getElementById('new-password').value);
    const data = await svPost('/reset-password-action.php', fd);
    if (data.success) {
        Toast.success(data.message);
        setTimeout(() => { window.location.href = '/index.php'; }, 1200);
    } else {
        Toast.error(data.message);
    }
});
</script>
</body>
</html>
