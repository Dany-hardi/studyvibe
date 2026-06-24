<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
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
?>
<!DOCTYPE html>
<html lang="fr" class="sv-cream">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérifiez votre email — StudyVibe</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <?= csrfMetaTag(); ?>
    <style>
        .pulse-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            background-color: rgba(0, 75, 35, 0.08);
            color: #004B23;
            border-radius: 50%;
            margin-bottom: 1.5rem;
            animation: pulse-glow 2s infinite ease-in-out;
        }
        @keyframes pulse-glow {
            0% { transform: scale(0.96); box-shadow: 0 0 0 0 rgba(0, 75, 35, 0.2); }
            50% { transform: scale(1.04); box-shadow: 0 0 0 10px rgba(0, 75, 35, 0); }
            100% { transform: scale(0.96); box-shadow: 0 0 0 0 rgba(0, 75, 35, 0); }
        }
    </style>
</head>
<body class="sv-landing sv-page flex items-center justify-center min-h-screen">
<div class="sv-container" style="max-width:480px;margin:2rem auto;padding:3rem 2rem;border:1px solid var(--sv-border-strong);background:var(--sv-surface);text-align:center;border-radius:12px;box-shadow: 0 8px 30px rgba(0, 0, 0, 0.03);">
    <div class="pulse-container">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
        </svg>
    </div>
    
    <h1 class="font-serif text-3xl font-light text-[#111111] mb-3">Vérifiez votre email</h1>
    
    <p class="text-sm text-[#555555] font-light leading-relaxed mb-6">
        Un email de confirmation a été envoyé à :<br>
        <span class="font-semibold text-[#004B23] text-base block mt-2"><?= htmlspecialchars($user['email']); ?></span>
    </p>
    
    <p class="text-xs text-[#888888] font-light mb-8 leading-relaxed">
        Cliquez sur le lien d'activation contenu dans l'email pour valider votre compte. Si vous n'avez rien reçu, vérifiez vos spams ou demandez un renvoi.
    </p>
    
    <button type="button" id="resend-btn" class="sv-btn-submit w-full" style="max-width:280px;margin:0 auto 1.5rem auto;">Renvoyer l'email</button>
    
    <p class="text-xs pt-2"><a href="/logout.php" class="text-[#D32F2F] hover:underline font-semibold">Se déconnecter</a></p>
</div>
<script src="/assets/js/app.js"></script>
<script>
document.getElementById('resend-btn').addEventListener('click', async () => {
    const btn = document.getElementById('resend-btn');
    btn.disabled = true;
    btn.textContent = 'Envoi en cours…';
    const data = await svPost('/resend-verification-action.php', new FormData());
    if (typeof Toast !== 'undefined') {
        Toast[data.success ? 'success' : 'error'](data.message);
    }
    btn.disabled = false;
    btn.textContent = "Renvoyer l'email";
});
</script>
</body>
</html>
