<?php
declare(strict_types=1);

/** /sitemap.xml (rewritten here by .htaccess): the public pages plus every open job posting. Nothing private is ever listed. */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Seo.php';

$urls = [];
foreach (Seo::publicPages() as $path => [$prio, $freq]) {
    $file = __DIR__ . ($path === '/' ? '/index.php' : $path);
    $urls[] = [Seo::url($path === '/' ? '/' : $path), is_file($file) ? gmdate('Y-m-d', (int)filemtime($file)) : null, $freq, $prio];
}
try {
    require_once __DIR__ . '/Database.php';
    require_once __DIR__ . '/lib/Careers.php';
    foreach (Careers::publicJobs(Database::getInstance()) as $job) {
        if (!empty($job['public_id'])) {
            $when = (string)($job['published_at'] ?? '');
            $urls[] = [Seo::url('/jobs/' . $job['public_id']), $when !== '' ? gmdate('Y-m-d', (int)strtotime($when)) : null, 'weekly', '0.5'];
        }
    }
} catch (Throwable $e) {
    error_log('sitemap jobs: ' . $e->getMessage());   // the static pages are still served
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $freq, $prio]) {
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . ($mod ? '<lastmod>' . $mod . '</lastmod>' : '')
        . '<changefreq>' . $freq . '</changefreq><priority>' . $prio . "</priority></url>\n";
}
echo "</urlset>\n";
