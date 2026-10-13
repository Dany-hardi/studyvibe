<?php
declare(strict_types=1);

/**
 * Door of the careers workspace. Every file in this folder starts with it: only a signed-in administrator (promoter, e-mail verified,
 * two-factor if the institution requires it) gets past. Nothing in here is reachable by teachers, students or visitors.
 */

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../lib/Security.php';
require_once __DIR__ . '/../../lib/Careers.php';

/**
 * Returns the administrator's id, or answers 401/403 and stops. $json: JSON body (the API) or a plain message (downloads).
 * Conditions: signed in, role promoter, e-mail verified, and the authenticator app when the institution requires it for administrators
 * (the same as requireRole(), which redirects instead of answering and so cannot serve an API or a download).
 */
function careersAdminOrFail(bool $json = true): int
{
    $fail = static function (int $code, string $msg) use ($json): never {
        http_response_code($code);
        if ($json) {
            echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            echo $msg;
        }
        exit;
    };
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    if (!isLoggedIn()) {
        $fail(401, 'Session expirée. Reconnectez-vous.');
    }
    if (($_SESSION['user_role'] ?? '') !== 'promoter') {
        $fail(403, 'Accès non autorisé.');
    }
    $me = getCurrentUser();
    $needs2fa = REQUIRE_2FA_ROLES !== '' && in_array('promoter', array_filter(array_map('trim', explode(',', REQUIRE_2FA_ROLES))), true);
    if (!$me || empty($me['email_verified_at']) || ($needs2fa && empty($me['totp_enabled_at']))) {
        $fail(403, 'Accès non autorisé.');
    }
    return (int)$_SESSION['user_id'];
}

/** A real CSRF check for this workspace (the app-wide helper is a placeholder): the page puts the token in a meta tag and sends it back in a header. */
function careersCheckCsrf(): void
{
    $sent = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $have = (string)($_SESSION['csrf_token'] ?? '');
    if ($have === '' || !hash_equals($have, $sent)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Requête refusée (jeton expiré). Rechargez la page.']);
        exit;
    }
}
