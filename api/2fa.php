<?php
declare(strict_types=1);

/**
 * Two-factor authentication settings of the signed-in account (any role).
 *
 *   POST action=status
 *   POST action=begin                    makes a secret (kept in the session) and returns it with the otpauth address
 *   POST action=confirm   code=...       checks a code from the app, turns 2FA on, returns the 10 recovery codes (once)
 *   POST action=recovery  code=...       new recovery codes (needs a current code from the app)
 *   POST action=disable   password=... code=...
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/TwoFactor.php';
require_once __DIR__ . '/../Mailer.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

Security::requirePostFromSameSite();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Connexion requise.']);
    exit;
}
if (!Security::secretIsStrong() && APP_DEBUG !== 'true') {
    // The secret of every account is encrypted with APP_SECRET: refuse to protect anything with the placeholder value
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'APP_SECRET doit être défini dans .env (32 caractères minimum) avant d’activer la double authentification.']);
    exit;
}

$lang   = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$userId = (int)$_SESSION['user_id'];
$pdo    = Database::getInstance();
$ip     = Security::clientIp();
$user   = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Connexion requise.']);
    exit;
}

$fail = static function (string $code) use ($lang): never {
    echo json_encode(['success' => false, 'error' => $code, 'message' => TwoFactor::message($code, $lang)], JSON_UNESCAPED_UNICODE);
    exit;
};
$notify = static function (string $fr, string $en) use ($user, $lang): void {
    try {
        Mailer::securityNotice((string)$user['email'], (string)$user['name'], $lang === 'en' ? $en : $fr, $lang);
    } catch (Throwable $e) {
        logServerError($e, 'security notice mail');
    }
};

try {
    switch ((string)($_POST['action'] ?? '')) {
        case 'status':
            echo json_encode([
                'success'  => true,
                'enabled'  => TwoFactor::isEnabled($pdo, $userId),
                'recovery' => TwoFactor::remainingRecovery($pdo, $userId),
            ]);
            break;

        case 'begin':
            if (TwoFactor::isEnabled($pdo, $userId)) {
                echo json_encode(['success' => false, 'message' => $lang === 'en' ? 'Two-factor authentication is already on.' : 'La double authentification est déjà active.']);
                break;
            }
            echo json_encode(['success' => true] + TwoFactor::begin($user));
            break;

        case 'confirm':
            $res = TwoFactor::confirm($pdo, $userId, (string)($_POST['code'] ?? ''), $ip);
            if (!$res['ok']) {
                $fail($res['error']);
            }
            auditLog('2fa_enabled', "User #{$userId}");
            $notify('La double authentification vient d’être activée sur votre compte.', 'Two-factor authentication was just turned on for your account.');
            echo json_encode(['success' => true, 'recovery_codes' => $res['recovery_codes']]);
            break;

        case 'recovery':
            $res = TwoFactor::regenerateRecovery($pdo, $userId, (string)($_POST['code'] ?? ''), $ip);
            if (!$res['ok']) {
                $fail($res['error']);
            }
            auditLog('2fa_recovery_regenerated', "User #{$userId}");
            $notify('De nouveaux codes de secours ont été générés pour votre compte. Les anciens ne fonctionnent plus.', 'New recovery codes were generated for your account. The old ones no longer work.');
            echo json_encode(['success' => true, 'recovery_codes' => $res['recovery_codes']]);
            break;

        case 'disable':
            $res = TwoFactor::disable($pdo, $userId, (string)($_POST['password'] ?? ''), (string)($_POST['code'] ?? ''), $ip);
            if (!$res['ok']) {
                $fail($res['error']);
            }
            auditLog('2fa_disabled', "User #{$userId}");
            $notify('La double authentification vient d’être désactivée sur votre compte.', 'Two-factor authentication was just turned off for your account.');
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
    }
} catch (Throwable $e) {
    logServerError($e, 'api/2fa');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur. Veuillez réessayer.']);
}
