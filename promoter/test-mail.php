<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Mailer.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$user = getCurrentUser();
$ok   = Mailer::send(
    $user['email'],
    'Test SMTP StudyVibe',
    '<p>Si vous recevez cet email, la configuration SMTP fonctionne correctement.</p>'
);

echo json_encode([
    'success'    => $ok,
    'configured' => Mailer::isConfigured(),
    'message'    => $ok
        ? 'Email de test envoyé à ' . $user['email']
        : (Mailer::getLastError() ?? 'Échec d\'envoi'),
]);
