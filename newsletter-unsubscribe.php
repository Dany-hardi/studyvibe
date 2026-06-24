<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Newsletter.php';

$token = trim((string)($_GET['token'] ?? ''));
$email = trim((string)($_GET['email'] ?? ''));
$done  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $token !== '' || $email !== '') {
    try {
        $pdo = Database::getInstance();
        if ($token !== '') {
            $done = Newsletter::unsubscribe($pdo, $token);
        } elseif ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("UPDATE newsletter_subscribers SET is_active = 0 WHERE email = :email");
            $stmt->execute(['email' => strtolower($email)]);
            $done = $stmt->rowCount() > 0;
        }
        if (!$done && $error === '') {
            $error = 'Abonnement introuvable ou déjà désactivé.';
        }
    } catch (PDOException $e) {
        $error = 'Erreur technique. Réessayez plus tard.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="sv-cream">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Désabonnement — StudyVibe</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="sv-page sv-landing" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;">
    <div class="sv-auth-card" style="position:static;max-width:420px;width:100%;">
        <?php if ($done): ?>
            <h1 class="sv-auth-card-title">Désabonnement confirmé</h1>
            <p class="sv-auth-card-sub" style="margin-top:1rem;">Vous ne recevrez plus la newsletter StudyVibe.</p>
        <?php elseif ($error): ?>
            <h1 class="sv-auth-card-title">Désabonnement</h1>
            <p class="sv-auth-card-sub" style="margin-top:1rem;color:var(--sv-danger);"><?= htmlspecialchars($error); ?></p>
        <?php else: ?>
            <h1 class="sv-auth-card-title">Se désabonner</h1>
            <p class="sv-auth-card-sub" style="margin-top:1rem;">Entrez votre adresse email pour vous désabonner de la newsletter.</p>
            <form method="POST" style="margin-top:1.5rem;">
                <div class="sv-field">
                    <label class="sv-field-label" for="email">Adresse électronique</label>
                    <input type="email" id="email" name="email" required class="sv-field-input" placeholder="vous@exemple.com">
                </div>
                <button type="submit" class="sv-btn-submit">Confirmer le désabonnement</button>
            </form>
        <?php endif; ?>
        <p style="margin-top:1.5rem;text-align:center;"><a href="/" style="font-size:0.875rem;color:var(--sv-accent);">Retour à l'accueil</a></p>
    </div>
</body>
</html>
