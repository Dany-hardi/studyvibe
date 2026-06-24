<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Mailer.php';
require_once __DIR__ . '/../Newsletter.php';

header('Content-Type: application/json');
set_time_limit(300); // 5 minutes
ini_set('max_execution_time', '300');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}


// requireCsrf();
if (!Mailer::isConfigured()) {
    echo json_encode([
        'success' => false,
        'message' => 'SMTP non configuré. Renseignez SMTP_HOST, SMTP_USER et SMTP_PASS dans .env avant d\'envoyer.',
    ]);
    exit;
}

$subject  = trim((string)($_POST['subject'] ?? ''));
$bodyHtml = trim((string)($_POST['body_html'] ?? ''));
$audience = trim((string)($_POST['audience'] ?? 'subscribers'));

if ($subject === '' || $bodyHtml === '') {
    echo json_encode(['success' => false, 'message' => 'Sujet et contenu obligatoires.']);
    exit;
}

if (!in_array($audience, ['subscribers', 'students', 'all'], true)) {
    $audience = 'subscribers';
}

try {
    $pdo = Database::getInstance();
    $recipients = Newsletter::resolveRecipients($pdo, $audience);

    if (empty($recipients)) {
        echo json_encode(['success' => false, 'message' => 'Aucun destinataire pour cette audience.']);
        exit;
    }

    $sent   = 0;
    $failed = 0;
    $appUrl = rtrim(APP_URL, '/');

    foreach ($recipients as $recipient) {
        $unsubUrl = $recipient['token']
            ? "{$appUrl}/newsletter-unsubscribe.php?token=" . urlencode($recipient['token'])
            : "{$appUrl}/newsletter-unsubscribe.php?email=" . urlencode($recipient['email']);

        $ok = Mailer::newsletter(
            $recipient['email'],
            $recipient['name'],
            $subject,
            nl2br(htmlspecialchars($bodyHtml, ENT_QUOTES, 'UTF-8')),
            $unsubUrl
        );

        if ($ok) {
            $sent++;
        } else {
            $failed++;
        }
    }

    if ($sent > 0) {
        Newsletter::logCampaign($pdo, (int)$_SESSION['user_id'], $subject, $bodyHtml, $sent);
        auditLog('newsletter_sent', "Subject: {$subject}, sent: {$sent}, failed: {$failed}");
    }

    echo json_encode([
        'success' => $sent > 0,
        'sent'    => $sent,
        'failed'  => $failed,
        'message' => $sent > 0
            ? "Newsletter envoyée à {$sent} destinataire(s)" . ($failed ? " ({$failed} échec(s))" : '') . '.'
            : 'Aucun email n\'a pu être envoyé. Vérifiez la configuration SMTP.',
    ]);

} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'send-newsletter.php');
}
