<?php
declare(strict_types=1);

/**
 * Hands out an applicant's résumé (?id=APPLICATION_ID). Administrators only; every download is written to the audit log.
 * The file is read from the "career_resume" storage (restored from the database copy if the server's disk was wiped) and always sent as
 * an attachment, never displayed in the browser.
 */

require_once __DIR__ . '/_guard.php';

careersAdminOrFail(false);
$id = (int)($_GET['id'] ?? 0);

try {
    $pdo = Database::getInstance();
    $st = $pdo->prepare('SELECT reference, full_name, resume_file, resume_name FROM career_applications WHERE id = :i');
    $st->execute(['i' => $id]);
    $a = $st->fetch(PDO::FETCH_ASSOC);
    if (!$a || empty($a['resume_file']) || !preg_match('/^[a-zA-Z0-9._-]+$/', (string)$a['resume_file'])) {
        http_response_code(404);
        exit('Fichier introuvable.');
    }
    $path = MediaStore::path('career_resume', (string)$a['resume_file']);
    if (!is_file($path)) {
        MediaStore::restore($pdo, 'career_resume/' . $a['resume_file'], $path);
    }
    if (!is_file($path)) {
        http_response_code(404);
        exit('Fichier introuvable.');
    }
    auditLog('career_resume_download', 'Application #' . $id . ' ' . $a['reference']);

    $ext = strtolower(pathinfo((string)$a['resume_file'], PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc' => 'application/msword',
        default => 'application/octet-stream',
    };
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)($a['resume_name'] ?: ('CV_' . $a['reference'] . '.' . $ext))) ?: ('CV.' . $ext);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($path));
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($path);
} catch (Throwable $e) {
    logServerError($e, 'promoter/careers/resume');
    http_response_code(500);
    exit('Erreur serveur.');
}
