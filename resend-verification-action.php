<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/lib/AuthTokens.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non connecté.']);
    exit;
}

// requireCsrf();

try {
    $pdo  = Database::getInstance();
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable.']);
        exit;
    }

    if (!empty($user['email_verified_at'])) {
        echo json_encode(['success' => true, 'message' => 'Votre email est déjà vérifié.']);
        exit;
    }

    // One email per minute and per session is plenty; it also protects the mailbox from repeated clicks
    $last = (int)($_SESSION['verify_resend_at'] ?? 0);
    if (time() - $last < 60) {
        $wait = 60 - (time() - $last);
        echo json_encode(['success' => false, 'message' => "Un email vient déjà d'être envoyé. Patientez encore {$wait} s avant de réessayer."]);
        exit;
    }
    $_SESSION['verify_resend_at'] = time();

    $token = AuthTokens::createEmailVerification($pdo, (int)$user['id']);
    $sent  = Mailer::emailVerification($user['email'], $user['name'], $token);

    echo json_encode([
        'success' => $sent,
        'message' => $sent ? 'Email de vérification renvoyé.' : 'Échec d\'envoi. Vérifiez la configuration SMTP.',
    ]);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'resend-verification');
}
