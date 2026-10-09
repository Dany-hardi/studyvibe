<?php
declare(strict_types=1);

/**
 * PDF of one of the promoter's sheets (the same data as the Excel export), typeset with LaTeX in landscape.
 *
 *   /promoter/export-pdf.php?type=students      students | teachers | modules | courses | enrollments |
 *                                                certifications | certification_attempts | audit_logs | metrics
 *   (the old values "logs" and "students" still work)
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/ExportDocs.php';
require_once __DIR__ . '/../lib/PromoterExportService.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    header('Location: /index.php');
    exit;
}

$type = strtolower(trim((string)($_GET['type'] ?? 'audit_logs')));
$type = ['logs' => 'audit_logs'][$type] ?? $type;
$allowed = ['metrics', 'students', 'teachers', 'modules', 'courses', 'enrollments', 'certifications', 'certification_attempts', 'audit_logs'];
if (!in_array($type, $allowed, true)) {
    http_response_code(400);
    exit('Type d\'export non valide.');
}

try {
    $sheet = (new PromoterExportService(Database::getInstance()))->buildSheetByType($type);
    if ($sheet === null) {
        http_response_code(400);
        exit('Feuille introuvable.');
    }
    $lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
    $doc  = ExportDocs::sheetReport($sheet, $lang);
    $pdf  = LatexCompiler::compile($doc['tex']);
    if (!$pdf) {
        // No LaTeX on this server: the Excel version carries the same data
        header('Location: /promoter/export-excel.php?type=' . urlencode($type));
        exit;
    }
    auditLog('export_pdf', $type);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="studyvibe_' . $type . '_' . date('Y-m-d') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
} catch (Throwable $e) {
    logServerError($e, 'promoter/export-pdf');
    http_response_code(500);
    exit('Erreur lors de la génération du PDF.');
}
