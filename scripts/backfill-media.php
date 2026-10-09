<?php
declare(strict_types=1);

/**
 * One-time: copies the uploaded files that are already on the disk into the database (see lib/MediaStore.php), so they
 * survive a redeploy too. Files already copied are skipped. Safe to run again.
 *
 *   php scripts/backfill-media.php
 */

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../lib/MediaStore.php';

$pdo = Database::getInstance();
$done = $skipped = $failed = 0;

foreach (array_keys(MediaStore::DIRS) as $kind) {
    $dir = MediaStore::dir($kind);
    foreach (scandir($dir) ?: [] as $name) {
        $path = $dir . $name;
        if ($name[0] === '.' || !is_file($path)) {
            continue;
        }
        $key = $kind . '/' . $name;
        $has = $pdo->prepare('SELECT sha256 FROM media_files WHERE media_key = :k');
        $has->execute(['k' => $key]);
        if ($has->fetchColumn() === hash_file('sha256', $path)) {
            $skipped++;
            continue;
        }
        MediaStore::persist($pdo, $key, $path) ? $done++ : $failed++;
    }
}
echo "Copied: $done, already there: $skipped, failed: $failed\n";
exit($failed > 0 ? 1 : 0);
