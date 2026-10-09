<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Brand.php';
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

$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$loggedIn = isLoggedIn();
$home = ['promoter' => '/promoter/dashboard.php', 'teacher' => '/teacher/dashboard.php', 'student' => '/student/dashboard.php'][$_SESSION['user_role'] ?? ''] ?? '/index.php';
?>
<!DOCTYPE html>
<html lang="fr" class="v2">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#F5F0E6">
    <title><?= $success ? 'Email confirmé' : 'Lien invalide' ?> — StudyVibe</title>
    <?= Brand::headLinks() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/sv2.css">
    <link rel="stylesheet" href="/assets/css/auth-page.css">
    <script>
      try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
    </script>
</head>
<body class="v2 vp-body">
<main class="vp-card">
    <div class="vp-logo"><a href="/index.php" aria-label="StudyVibe" style="text-decoration:none"><?= Brand::logo('md') ?></a></div>

<?php if ($success): ?>
    <div class="vp-art is-wide" role="img" aria-label="Deux diplômés qui célèbrent"><div class="vp-anim" data-lottie="/assets/anim/closing-graduates.json"></div></div>
    <h1>Email <em>confirmé</em></h1>
    <p class="vp-lead">Votre adresse est validée et votre compte est actif. Il ne reste plus qu’à entrer.</p>
    <div class="vp-actions">
        <?php if ($loggedIn): ?>
            <a class="btn btn-primary btn-lg" href="<?= $h($home) ?>">Aller à mon espace</a>
        <?php else: ?>
            <a class="btn btn-primary btn-lg" href="/index.php?auth=login">Se connecter</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="vp-art" role="img" aria-label="Une personne qui cherche une solution devant son ordinateur"><div class="vp-anim" data-lottie="/assets/anim/verify-failed.json"></div></div>
    <h1>Ce lien ne <em>fonctionne plus</em></h1>
    <p class="vp-lead"><?= $h($error !== '' ? $error : 'Le lien est invalide ou a expiré.') ?> Les liens de confirmation restent valables 48 heures. Rien de grave, on peut vous en envoyer un nouveau.</p>
    <div class="vp-actions">
        <?php if ($loggedIn): ?>
            <a class="btn btn-primary btn-lg" href="/verify-email-pending.php">Recevoir un nouveau lien</a>
        <?php else: ?>
            <a class="btn btn-primary btn-lg" href="/index.php?auth=login">Me connecter pour recevoir un nouveau lien</a>
        <?php endif; ?>
        <a class="btn btn-ghost btn-lg" href="/index.php">Retour à l’accueil</a>
    </div>
<?php endif; ?>
</main>
<script src="/assets/js/auth-page.js"></script>
</body>
</html>
