<?php
declare(strict_types=1);

/**
 * Door of the control center. The only visible effect for anybody who is not the owner is a plain 404, exactly like any
 * address that does not exist: no redirect to a login page, no hint that something is here.
 *
 * Who gets in: a signed-in promoter. If CONTROL_OWNER_EMAIL is set in .env (one address or a comma-separated list),
 * only those promoters.
 */
final class ControlGate
{
    public static function enter(bool $json = false): array
    {
        if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'promoter') {
            self::deny($json);
        }
        $user = getCurrentUser();
        if (!$user) {
            self::deny($json);
        }
        if (defined('CONTROL_OWNER_EMAIL') && trim((string)CONTROL_OWNER_EMAIL) !== '') {
            $allowed = array_map('strtolower', array_map('trim', explode(',', (string)CONTROL_OWNER_EMAIL)));
            if (!in_array(strtolower((string)$user['email']), $allowed, true)) {
                self::deny($json);
            }
        }
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Cache-Control: no-store');
        return $user;
    }

    public static function token(): string
    {
        if (empty($_SESSION['ops_csrf'])) {
            $_SESSION['ops_csrf'] = bin2hex(random_bytes(24));
        }
        return (string)$_SESSION['ops_csrf'];
    }

    public static function checkToken(): bool
    {
        $sent = (string)($_SERVER['HTTP_X_OPS_TOKEN'] ?? $_POST['token'] ?? '');
        return $sent !== '' && hash_equals(self::token(), $sent);
    }

    private static function deny(bool $json): never
    {
        http_response_code(404);
        if ($json) {
            header('Content-Type: application/json');
            echo '{"success":false}';
            exit;
        }
        $root = dirname(__DIR__);
        if (is_file($root . '/404.php')) {
            require $root . '/404.php';
        }
        exit;
    }
}
