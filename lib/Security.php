<?php
declare(strict_types=1);

/**
 * Small checks shared by the endpoints that change an account (phone, two-factor, profile).
 */
final class Security
{
    /**
     * Refuses a state-changing request that was started by another website. Session cookies are already SameSite=Strict;
     * this is a second lock: the browser tells us where the request came from (Origin, Sec-Fetch-Site) and it must be us.
     */
    public static function requirePostFromSameSite(): void
    {
        $fail = static function (int $code, string $msg): never {
            http_response_code($code);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        };
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $fail(405, 'Méthode non autorisée.');
        }
        $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
        if ($site !== '' && !in_array($site, ['same-origin', 'none'], true)) {
            $fail(403, 'Requête refusée.');
        }
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '') {
            $oh = parse_url($origin, PHP_URL_HOST);
            $host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
            if (!$oh || strcasecmp((string)$oh, (string)$host) !== 0) {
                $fail(403, 'Requête refusée.');
            }
        }
    }

    /** The visitor's address. Behind a proxy (Railway, Render, Cloudflare) set TRUST_PROXY=true so the real address is read. */
    public static function clientIp(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (defined('TRUST_PROXY') && TRUST_PROXY === 'true' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $first = trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                $ip = $first;
            }
        }
        return substr($ip, 0, 45);
    }

    /** True when APP_SECRET is a real secret (long, and not the placeholder). Anything that encrypts or signs depends on it. */
    public static function secretIsStrong(): bool
    {
        return APP_SECRET !== 'fallback_secret_key' && strlen(APP_SECRET) >= 32;
    }
}
