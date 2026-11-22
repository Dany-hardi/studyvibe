<?php
declare(strict_types=1);

/**
 * One PDF sent through contribute.php. Super teachers and promoters only. Always sent as an attachment (never shown inline),
 * read from the "material" storage, restored from the database copy if the server's disk was wiped.
 *
 *   GET ?id=<material_files.id>
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/CourseMaterials.php';

if (!isLoggedIn() || !(isSuperTeacher($_SESSION['user_role'] ?? null) || ($_SESSION['user_role'] ?? '') === 'promoter')) {
    http_response_code(403);
    exit('Accès refusé.');
}
$pdo = Database::getInstance();
$f = CourseMaterials::file($pdo, (int)($_GET['id'] ?? 0));
if (!$f || !preg_match('/^[a-f0-9]{32}\.pdf$/', (string)$f['file'])) {
    http_response_code(404);
    exit('Fichier introuvable.');
}
$path = MediaStore::path('material', (string)$f['file']);
if (!is_file($path)) {
    MediaStore::restore($pdo, 'material/' . $f['file'], $path);
}
if (!is_file($path)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}
$name = preg_replace('/[^\w.\- ()]+/u', '_', (string)$f['original_name']) ?: 'support.pdf';
if (!str_ends_with(strtolower($name), '.pdf')) {
    $name .= '.pdf';
}
header('Content-Type: application/pdf');
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: attachment; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode((string)$f['original_name']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
