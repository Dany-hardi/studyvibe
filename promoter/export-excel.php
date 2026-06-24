<?php
declare(strict_types=1);

/**
 * Export Excel depuis le tableau de bord promoteur.
 *
 * Paramètre GET « type » :
 *   metrics | students | teachers | modules | courses | enrollments |
 *   certifications | certification_attempts | audit_logs | all
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/SpreadsheetExporter.php';
require_once __DIR__ . '/../lib/PromoterExportService.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    header('Location: /index.php');
    exit;
}

$type = strtolower(trim((string)($_GET['type'] ?? 'all')));
$allowed = [
    'metrics', 'students', 'teachers', 'modules', 'courses', 'enrollments',
    'certifications', 'certification_attempts', 'audit_logs', 'all',
];

if (!in_array($type, $allowed, true)) {
    http_response_code(400);
    exit('Type d\'export non valide.');
}

try {
    $service = new PromoterExportService(Database::getInstance());

    if ($type === 'all') {
        $sheets   = $service->buildAllSheets();
        $filename = 'studyvibe_export_complet_' . date('Y-m-d');
        auditLog('export_excel_all', count($sheets) . ' feuilles');
    } else {
        $sheet = $service->buildSheetByType($type);
        if ($sheet === null) {
            http_response_code(400);
            exit('Feuille introuvable.');
        }
        $sheets   = [$sheet];
        $filename = 'studyvibe_' . $type . '_' . date('Y-m-d');
        auditLog('export_excel', $type);
    }

    SpreadsheetExporter::sendDownload($filename, $sheets);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'export-excel.php');
}
