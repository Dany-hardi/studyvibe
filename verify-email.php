<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/AuthTokens.php';

$token   = trim((string)($_GET['token'] ?? ''));
$success = false;
$error   = '';

if ($token !== '') {
    try {
        $pdo     = Database::getInstance();
        $success = AuthTokens::verifyEmail($pdo, $token);
        if (!$success) {
            $error = 'Lien invalide ou expiré.';
        } else {
            auditLog('email_verified', 'token');
        }
    } catch (PDOException $e) {
        logServerError($e, 'verify-email');
        $error = 'Erreur serveur.';
    }
} else {
    $error = 'Jeton de vérification manquant.';
}
?>
<!DOCTYPE html>
<html lang="fr" class="sv-cream">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification email — StudyVibe</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        .status-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            border-radius: 50%;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body class="sv-landing sv-page flex items-center justify-center min-h-screen">
<div class="sv-container" style="max-width:480px;margin:2rem auto;padding:3rem 2rem;border:1px solid var(--sv-border-strong);background:var(--sv-surface);text-align:center;border-radius:12px;box-shadow: 0 8px 30px rgba(0, 0, 0, 0.03);">
    <?php if ($success): ?>
        <div class="status-container bg-[#E8F5E9] text-[#004B23]">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </div>
        <h1 class="font-serif text-3xl font-light text-[#111111] mb-3">Email vérifié</h1>
        <p class="text-sm text-[#555555] font-light leading-relaxed mb-6">
            Félicitations ! Votre adresse email a été validée avec succès et votre compte est désormais actif.
        </p>
        <a href="/index.php" class="sv-btn-submit block text-center" style="max-width:280px;margin:0 auto;text-decoration:none;line-height:40px;height:40px;padding:0;">Se connecter</a>
    <?php else: ?>
        <div class="status-container bg-[#FFEBEE] text-[#D32F2F]">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
        </div>
        <h1 class="font-serif text-3xl font-light text-[#D32F2F] mb-3">Échec de validation</h1>
        <p class="text-sm text-[#555555] font-light leading-relaxed mb-6">
            Le jeton de validation est invalide, expiré ou corrompu.
        </p>
        <p class="text-xs text-[#888888] font-light mb-6">
            Veuillez demander un nouvel email de validation en vous connectant à votre espace.
        </p>
        <a href="/verify-email-pending.php" class="sv-btn-submit block text-center" style="max-width:280px;margin:0 auto;text-decoration:none;line-height:40px;height:40px;padding:0;background:#F5F5F7;color:#333;">Renvoyer un email</a>
    <?php endif; ?>
</div>
</body>
</html>
