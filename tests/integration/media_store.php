<?php
declare(strict_types=1);

/**
 * Uploads that survive a redeploy (needs the database from .env). Creates temporary files and removes them at the end.
 *
 *   php tests/integration/media_store.php
 */

require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../../lib/MediaStore.php';

$pdo = Database::getInstance();
$passed = 0; $failed = [];
function check(string $what, bool $ok): void { global $passed, $failed; $ok ? $passed++ : $failed[] = $what; }

$tmp = sys_get_temp_dir();
$cleanup = [];

// A large noisy picture becomes a small, upright JPEG
$im = imagecreatetruecolor(3000, 2000);
for ($i = 0; $i < 3000; $i++) {
    imagefilledellipse($im, random_int(0, 3000), random_int(0, 2000), random_int(10, 200), random_int(10, 200), imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
}
$src = "$tmp/zz_media_src.png";
imagepng($im, $src, 0);
$r = MediaStore::saveImageFile($pdo, $src, 'avatar', 512);
check('picture accepted', $r['ok'] === true);
$path = MediaStore::path('avatar', $r['file'] ?? 'missing.jpg');
$size = @getimagesize($path);
check('picture scaled down to 512 px', $size !== false && max($size[0], $size[1]) === 512 && $size['mime'] === 'image/jpeg');
check('picture is far smaller than the source', filesize($path) < filesize($src) / 20);

// The web server loses its disk: the file comes back from the database, byte for byte
$sha = hash_file('sha256', $path);
unlink($path);
check('file really gone', !is_file($path));
check('restore from the database works', MediaStore::restore($pdo, 'avatar/' . $r['file'], $path));
check('restored file is identical', hash_file('sha256', $path) === $sha);

// A damaged copy is never served
unlink($path);
$pdo->prepare("UPDATE media_chunks SET data = :d WHERE media_key = :k AND seq = 0")->execute(['d' => 'corrupted', 'k' => 'avatar/' . $r['file']]);
check('a damaged database copy is refused', MediaStore::restore($pdo, 'avatar/' . $r['file'], $path) === false && !is_file($path));
MediaStore::delete($pdo, 'avatar', $r['file']);

// Things that are not pictures
file_put_contents($src, "<?php echo 'x'; ?>");
check('a script renamed as a picture is refused', (MediaStore::saveImageFile($pdo, $src, 'avatar', 512)['error'] ?? '') === 'not_image');
file_put_contents($src, "GIF89a" . str_repeat("A", 100));
check('a fake GIF header is refused', (MediaStore::saveImageFile($pdo, $src, 'avatar', 512)['ok'] ?? true) === false);

// A 3 MB document goes through MySQL's 1 MB packet limit in slices
$doc = "$tmp/zz_media_doc.bin";
file_put_contents($doc, random_bytes(3_000_000));
check('large document stored in slices', MediaStore::persist($pdo, 'library/zz_test_doc.bin', $doc));
$out = "$tmp/zz_media_out.bin";
@unlink($out);
check('large document restored', MediaStore::restore($pdo, 'library/zz_test_doc.bin', $out) && hash_file('sha256', $out) === hash_file('sha256', $doc));
$pdo->exec("DELETE FROM media_chunks WHERE media_key = 'library/zz_test_doc.bin'");
$pdo->exec("DELETE FROM media_files WHERE media_key = 'library/zz_test_doc.bin'");

foreach ([$src, $doc, $out] as $f) { @unlink($f); }
echo "$passed passed, " . count($failed) . " failed\n";
foreach ($failed as $f) { echo "  FAIL  $f\n"; }
exit($failed ? 1 : 0);
