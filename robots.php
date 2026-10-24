<?php
declare(strict_types=1);

/** /robots.txt (rewritten here by .htaccess): the public pages are open, the private areas are not, and the sitemap is announced with the real address. */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Seo.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo "User-agent: *\n";
foreach (Seo::privatePaths() as $p) {
    echo 'Disallow: ' . $p . "\n";
}
echo "Allow: /\n\n";
echo 'Sitemap: ' . Seo::url('/sitemap.xml') . "\n";
