<?php
declare(strict_types=1);

/**
 * Anonymous traffic counters for the promoter dashboard.
 *
 * What is stored: a day, an event name, an optional channel tag ("src") and a counter. Nothing else: no IP address, no user id,
 * no cookie. "visitor" is counted once per browser session per day. Failures never reach the visitor.
 */
final class Analytics
{
    /** Events the site is allowed to count. Anything else is ignored. */
    public const EVENTS = [
        'view:landing', 'view:privacy', 'view:join', 'view:verify', 'view:evaluations', 'view:live_invite', 'view:verify_email',
        'visitor', 'signup_open', 'signup_step2', 'signup_submit', 'login_open', 'login_submit', 'login_fail',
    ];

    public static function cleanSrc(?string $raw): string
    {
        $raw = strtolower(trim((string)$raw));
        return preg_match('/^[a-z0-9][a-z0-9_-]{0,38}$/', $raw) ? $raw : '';
    }

    /** Remembers the channel (?src=...) for the rest of the session so a later sign-up can be attributed to it. */
    public static function captureSource(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        $src = self::cleanSrc($_GET['src'] ?? '');
        if ($src !== '' && empty($_SESSION['sv_src'])) {
            $_SESSION['sv_src'] = $src;      // first touch wins
        }
        return (string)($_SESSION['sv_src'] ?? '');
    }

    public static function hit(string $event, ?string $src = null): void
    {
        if (!in_array($event, self::EVENTS, true) || self::looksLikeBot()) {
            return;
        }
        try {
            $src = $src !== null ? self::cleanSrc($src) : (string)($_SESSION['sv_src'] ?? '');
            $pdo = Database::getInstance();
            $pdo->prepare("INSERT INTO site_events (day, event, src, n) VALUES (CURDATE(), :e, :s, 1) ON DUPLICATE KEY UPDATE n = n + 1")
                ->execute(['e' => $event, 's' => $src]);
            // One visitor per session per day
            if (session_status() === PHP_SESSION_ACTIVE && str_starts_with($event, 'view:') && ($_SESSION['sv_visitor_day'] ?? '') !== date('Y-m-d')) {
                $_SESSION['sv_visitor_day'] = date('Y-m-d');
                $pdo->prepare("INSERT INTO site_events (day, event, src, n) VALUES (CURDATE(), 'visitor', :s, 1) ON DUPLICATE KEY UPDATE n = n + 1")
                    ->execute(['s' => $src]);
            }
        } catch (Throwable) { /* analytics must never break a page */ }
    }

    private static function looksLikeBot(): bool
    {
        $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        return $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|preview|monitor|curl|wget|python-requests|headless/', $ua);
    }
}
