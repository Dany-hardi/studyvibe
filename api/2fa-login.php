<?php
declare(strict_types=1);

/**
 * Second step of signing in, for accounts with two-factor authentication.
 *
 *   POST code=123456            a code from the authenticator app
 *   POST code=ABCDE-FGHJK       or one of the recovery codes
 *
 * The password step (login-action.php) leaves the session "half signed in". This endpoint finishes it.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/TwoFactor.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

Security::requirePostFromSameSite();

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$pdo = Database::getInstance();

try {
    $res = TwoFactor::finishLogin($pdo, (string)($_POST['code'] ?? ''), Security::clientIp());
    if (!$res['ok']) {
        echo json_encode(['success' => false, 'error' => $res['error'], 'expired' => in_array($res['error'], ['login_expired', 'too_many'], true), 'message' => TwoFactor::message($res['error'], $lang)]);
        exit;
    }

    // The account may have been switched off while the person typed the code
    $st = $pdo->prepare("SELECT role, is_active, is_approved FROM users WHERE id = :id");
    $st->execute(['id' => $res['uid']]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u || !(int)$u['is_active'] || ($u['role'] === 'teacher' && !(int)$u['is_approved'])) {
        echo json_encode(['success' => false, 'expired' => true, 'message' => TwoFactor::message('login_expired', $lang)]);
        exit;
    }

    establishSession((int)$res['uid'], (string)$u['role']);
    auditLog('login_success_2fa', "User #{$res['uid']} ({$u['role']})");

    $map = ['promoter' => '/promoter/dashboard.php', 'teacher' => '/teacher/dashboard.php', 'student' => '/student/dashboard.php'];
    echo json_encode(['success' => true, 'redirect' => $res['redirect'] ?? ($map[$u['role']] ?? '/index.php')]);
} catch (Throwable $e) {
    logServerError($e, 'api/2fa-login');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur. Veuillez réessayer.']);
}
