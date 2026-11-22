<?php
declare(strict_types=1);

/**
 * Search-engine tags for the public pages: canonical address, robots rules, author/application names and structured data (JSON-LD).
 * Pair it with SocialMeta::tags() (Open Graph + Twitter) and the page's own <title> and <meta name="description">.
 *
 *   <?= Seo::head('/privacy.php') ?>                       indexable page
 *   <?= Seo::head('/x.php', ['robots' => 'noindex']) ?>    kept out of the results
 *
 * The canonical address ignores the query string (?src=, ?auth=, ?lang=) so campaign and dialog links never split a page's ranking.
 * Private areas (accounts, dashboards, API, live rooms) are kept out through robots.txt and the X-Robots-Tag header (see .htaccess).
 */
final class Seo
{
    public const NAME = 'StudyVibe';

    public static function base(): string
    {
        return defined('APP_URL') ? rtrim((string)APP_URL, '/') : '';
    }

    public static function url(string $path): string
    {
        return self::base() . '/' . ltrim($path, '/');
    }

    private static function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param array{robots?:string,jsonld?:array<int,array<string,mixed>>} $o
     */
    public static function head(string $path = '/', array $o = []): string
    {
        $robots = $o['robots'] ?? 'index, follow, max-image-preview:large, max-snippet:-1';
        $out = [
            '<link rel="canonical" href="' . self::h($path === '/' ? self::base() . '/' : self::url($path)) . '">',
            '<meta name="robots" content="' . self::h($robots) . '">',
            '<meta name="application-name" content="' . self::NAME . '">',
            '<meta name="apple-mobile-web-app-title" content="' . self::NAME . '">',
            '<meta name="format-detection" content="telephone=no">',
        ];
        if (stripos($robots, 'noindex') === false) {
            $out[] = '<meta name="author" content="' . self::NAME . '">';
        }
        foreach ($o['jsonld'] ?? [] as $block) {
            $out[] = self::jsonLd($block);
        }
        return implode("\n", $out) . "\n";
    }

    /** One <script type="application/ld+json"> block. "</" is escaped so the data can never close the tag. */
    public static function jsonLd(array $data): string
    {
        $json = json_encode(['@context' => 'https://schema.org'] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    public static function organization(string $description): array
    {
        return [
            '@type' => 'Organization', '@id' => self::url('/#organization'), 'name' => self::NAME,
            'url' => self::url('/'), 'logo' => self::url('/assets/img/apple-touch-icon.png'), 'description' => $description,
        ];
    }

    public static function website(string $description, string $lang): array
    {
        return [
            '@type' => 'WebSite', '@id' => self::url('/#website'), 'name' => self::NAME, 'url' => self::url('/'),
            'description' => $description, 'inLanguage' => $lang === 'en' ? 'en' : 'fr', 'publisher' => ['@id' => self::url('/#organization')],
        ];
    }

    /** The product itself, for the landing page. No price is claimed: the offer says it is free to start. */
    public static function application(string $description, string $lang): array
    {
        return [
            '@type' => 'SoftwareApplication', 'name' => self::NAME, 'applicationCategory' => 'EducationalApplication',
            'operatingSystem' => 'Web, Android, iOS (installable web app)', 'url' => self::url('/'),
            'description' => $description, 'inLanguage' => $lang === 'en' ? 'en' : 'fr',
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'XAF'],
            'publisher' => ['@id' => self::url('/#organization')],
        ];
    }

    /** Public pages that belong in the sitemap: path => [priority, change frequency]. */
    public static function publicPages(): array
    {
        return [
            '/'                 => ['1.0', 'weekly'],
            '/evaluations.php'  => ['0.8', 'daily'],
            '/careers.php'      => ['0.6', 'weekly'],
            '/documentation.php' => ['0.6', 'monthly'],
            '/verify.php'       => ['0.4', 'yearly'],
            '/privacy.php'      => ['0.3', 'yearly'],
            '/terms.php'        => ['0.3', 'yearly'],
        ];
    }

    /** Paths and path prefixes no crawler should visit. */
    public static function privatePaths(): array
    {
        return ['/api/', '/account/', '/student/', '/teacher/', '/promoter/', '/ops-5f208f200992fc7ea994/', '/uploads/', '/live-session.php', '/join.php',
                '/track.php', '/login-action.php', '/signup-action.php', '/logout.php', '/two-factor.php', '/reset-password', '/forgot-password-action.php',
                '/verify-email', '/resend-verification-action.php', '/download.php', '/certificate.php', '/newsletter-unsubscribe.php'];
    }
}
