<?php
declare(strict_types=1);

/**
 * Phone number of the signed-in account (any role).
 *
 *   POST action=start  phone=...   sends a 6-digit code by SMS to that number
 *   POST action=verify code=...    checks the code and saves the number on the account
 *   POST action=optin  value=0|1   allows or stops reminder SMS (verification codes are always sent)
 *   POST action=status             what the page needs to know about the account's phone
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Otp.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

Security::requirePostFromSameSite();

if (!SmsGateway::enabled()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Fonction désactivée.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Connexion requise.']);
    exit;
}

$lang   = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$userId = (int)$_SESSION['user_id'];
$action = (string)($_POST['action'] ?? '');
$pdo    = Database::getInstance();

$reply = static function (array $res) use ($lang): never {
    if (!empty($res['error'])) {
        $res['message'] = Otp::message((string)$res['error'], $lang);
        $res['success'] = false;
    } else {
        $res['success'] = true;
    }
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
};

try {
    $row = $pdo->prepare("SELECT phone_e164, phone_pending, phone_verified_at, sms_opt_in FROM users WHERE id = :id AND is_active = 1");
    $row->execute(['id' => $userId]);
    $me = $row->fetch(PDO::FETCH_ASSOC);
    if (!$me) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Connexion requise.']);
        exit;
    }

    switch ($action) {
        case 'status':
            $reply([
                'verified'      => !empty($me['phone_e164']),
                'phone_masked'  => !empty($me['phone_e164']) ? Phone::mask((string)$me['phone_e164']) : null,
                'pending'       => $me['phone_pending'] ?: null,
                'sms_opt_in'    => (int)$me['sms_opt_in'] === 1,
                'sms_available' => SmsGateway::isConfigured(),
            ]);

        case 'start':
            $reply(Otp::start($pdo, $userId, (string)($_POST['phone'] ?? ''), Security::clientIp(), $lang));

        case 'verify':
            $res = Otp::verify($pdo, $userId, (string)($_POST['code'] ?? ''));
            if ($res['ok']) {
                auditLog('phone_verified', "User #{$userId}");
            }
            $reply($res);

        case 'optin':
            $pdo->prepare("UPDATE users SET sms_opt_in = :v WHERE id = :id")
                ->execute(['v' => !empty($_POST['value']) && $_POST['value'] !== '0' ? 1 : 0, 'id' => $userId]);
            $reply(['sms_opt_in' => !empty($_POST['value']) && $_POST['value'] !== '0']);

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
    }
} catch (Throwable $e) {
    logServerError($e, 'api/phone');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur. Veuillez réessayer.']);
}
