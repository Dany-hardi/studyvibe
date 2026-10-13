<?php
declare(strict_types=1);

/**
 * CSV of the applications (same filters as the list: job_id, status, q, starred). UTF-8 with a BOM so Excel opens accents correctly.
 * Cells that start with = + - @ are prefixed so a spreadsheet never runs them as a formula.
 */

require_once __DIR__ . '/_guard.php';

careersAdminOrFail(false);
try {
    $pdo = Database::getInstance();
    $rows = Careers::applications($pdo, [
        'job_id' => $_GET['job_id'] ?? null, 'status' => $_GET['status'] ?? null, 'q' => $_GET['q'] ?? '', 'starred' => !empty($_GET['starred']),
    ], 2000);
    auditLog('career_export', count($rows) . ' applications');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="candidatures-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: private, no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $safe = static fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v;
    fputcsv($out, ['Référence', 'Date', 'Poste', 'Nom', 'E-mail', 'Téléphone', 'Ville', 'Étape', 'Note', 'Favori'], ',', '"', '');
    foreach ($rows as $r) {
        fputcsv($out, array_map($safe, [$r['reference'], $r['created_at'], $r['job_title'] ?: 'Candidature spontanée', $r['full_name'], $r['email'], $r['phone'], $r['location'], $r['status'], $r['rating'] ?? '', $r['is_starred'] ? 'oui' : '']), ',', '"', '');
    }
    fclose($out);
} catch (Throwable $e) {
    logServerError($e, 'promoter/careers/export');
    http_response_code(500);
    exit('Erreur serveur.');
}
