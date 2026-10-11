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
    /** The folder of the control center, as a URL path. */
    public const PATH = '/ops-5f208f200992fc7ea994/';

    /**
     * Who may even see the door: a promoter, and, if CONTROL_OWNER_EMAIL is set, one of the listed addresses. The administrator dashboard
     * uses this to decide whether to show the link; it opens nothing by itself.
     */
    public static function isOwner(?array $user): bool
    {
        if (!$user || ($user['role'] ?? '') !== 'promoter' || ($_SESSION['user_role'] ?? '') !== 'promoter') {
            return false;
        }
        if (defined('CONTROL_OWNER_EMAIL') && trim((string)CONTROL_OWNER_EMAIL) !== '') {
            $allowed = array_map('strtolower', array_map('trim', explode(',', (string)CONTROL_OWNER_EMAIL)));
            return in_array(strtolower((string)$user['email']), $allowed, true);
        }
        return true;
    }

    /**
     * @param bool $requireUnlock false only for the page that does the unlocking itself. Everything else needs the second lock open
     *                            (see ControlUnlock): pages send the owner to the unlock page, JSON answers 401 {locked:true}.
     */
    public static function enter(bool $json = false, bool $requireUnlock = true): array
    {
        if (!isLoggedIn()) {
            self::deny($json);
        }
        $user = getCurrentUser();
        if (!self::isOwner($user)) {
            self::deny($json);
        }
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Cache-Control: no-store');
        if ($requireUnlock) {
            require_once __DIR__ . '/ControlUnlock.php';
            if (!ControlUnlock::isUnlocked((int)$user['id'])) {
                if ($json) {
                    http_response_code(401);
                    header('Content-Type: application/json');
                    echo '{"success":false,"locked":true,"message":"Locked. Verify again."}';
                    exit;
                }
                header('Location: ' . self::PATH . 'unlock.php');
                exit;
            }
        }
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
