<?php
declare(strict_types=1);

/**
 * Link-preview tags (Open Graph + Twitter card) so a shared StudyVibe link shows a title, a sentence and a picture
 * in WhatsApp, Facebook, LinkedIn, Telegram, Slack and iMessage. Put <?= SocialMeta::tags(...) ?> in <head>.
 *
 * Images are 1200x630 cards in assets/img/og-*.png: default (site), evaluation (live), evaluation-async (free assignment), course, certificate.
 * Never pass private data (names, scores, certificate holders): these tags are read by anybody holding the link.
 */
final class SocialMeta
{
    public static function tags(string $title, string $description, string $path = '/', string $image = 'og-default.png'): string
    {
        $base = defined('APP_URL') ? rtrim((string)APP_URL, '/') : '';
        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $url = $base . $path;
        $img = $base . '/assets/img/' . ltrim($image, '/');

        return implode("\n", [
            '<meta property="og:type" content="website">',
            '<meta property="og:site_name" content="StudyVibe">',
            '<meta property="og:title" content="' . $h($title) . '">',
            '<meta property="og:description" content="' . $h($description) . '">',
            '<meta property="og:url" content="' . $h($url) . '">',
            '<meta property="og:image" content="' . $h($img) . '">',
            '<meta property="og:image:secure_url" content="' . $h($img) . '">',
            '<meta property="og:image:type" content="image/png">',
            '<meta property="og:image:width" content="1200">',
            '<meta property="og:image:height" content="630">',
            '<meta property="og:image:alt" content="' . $h($title) . '">',
            '<meta property="og:locale" content="' . (TranslationService::getLang() === 'en' ? 'en_US' : 'fr_FR') . '">',
            '<meta name="twitter:card" content="summary_large_image">',
            '<meta name="twitter:title" content="' . $h($title) . '">',
            '<meta name="twitter:description" content="' . $h($description) . '">',
            '<meta name="twitter:image" content="' . $h($img) . '">',
            '<meta name="twitter:image:alt" content="' . $h($title) . '">',
            '<link rel="image_src" href="' . $h($img) . '">',
        ]) . "\n";
    }
}
