<?php
declare(strict_types=1);

/**
 * Traceability and observability export for the administrator (promoter): what happened on the platform in a period.
 *
 *   /promoter/export-observability.php?format=pdf|csv|json|xlsx&days=30            last N days (1-365)
 *                                      &from=2026-09-01&to=2026-09-30              or an explicit period
 *                                      &category=security|access|academic|exports|verification   optional
 *                                      &q=text                                       optional: action, details, name, e-mail, IP
 *
 * Every export is itself recorded in the audit trail (who, format, period, filter), so the exports are traceable too.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/ObservabilityDocs.php';
require_once __DIR__ . '/../lib/SpreadsheetExporter.php';

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'promoter') {
    http_response_code(403);
    exit('Accès refusé.');
}
$me = getCurrentUser();
if (!$me) {
    http_response_code(403);
    exit('Accès refusé.');
}

$format = strtolower(trim((string)($_GET['format'] ?? 'pdf')));
if (!in_array($format, ['pdf', 'csv', 'json', 'xlsx'], true)) {
    http_response_code(400);
    exit('Format non valide.');
}
$category = isset($_GET['category']) && in_array($_GET['category'], Observability::categories(), true) ? (string)$_GET['category'] : null;
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80);
$per = Observability::period($_GET['from'] ?? null, $_GET['to'] ?? null, (int)($_GET['days'] ?? 30));
$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';

try {
    $pdo = Database::getInstance();
    $data = Observability::collect($pdo, $per['from'], $per['to'], $category, $q, $format === 'pdf' ? Observability::PDF_ROWS : Observability::DATA_ROWS);
    $stamp = Observability::stamp($data, (string)$me['name']);
    $file = 'studyvibe_tracabilite_' . $per['from'] . '_' . $per['to'] . ($category ? '_' . $category : '');

    auditLog('observability_export', "{$format} · {$per['from']} → {$per['to']}" . ($category ? " · {$category}" : '') . ($q !== '' ? " · q={$q}" : '') . " · {$stamp['id']}");

    if ($format === 'pdf') {
        $doc = ObservabilityDocs::report($data, $stamp, $lang);
        $pdf = LatexCompiler::compile($doc['tex'], $doc['assets']);
        if (!$pdf) {
            http_response_code(503);
            exit($lang === 'en' ? 'The PDF could not be produced right now. The CSV, JSON and Excel exports are still available.' : 'Le PDF n’a pas pu être produit pour le moment. Les exports CSV, JSON et Excel restent disponibles.');
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $file . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: no-store');
        echo $pdf;
        exit;
    }

    if ($format === 'json') {
        $out = [
            'report' => $stamp + ['period' => $data['period'], 'filters' => $data['filters'], 'retention' => $data['retention']],
            'summary' => $data['summary'], 'timeline' => $data['timeline'], 'top_actions' => $data['top_actions'], 'top_actors' => $data['top_actors'],
            'failed_ips' => $data['failed_ips'], 'delivery' => $data['delivery'], 'usage' => $data['usage'],
            'rows_total' => $data['rows_total'], 'rows_truncated' => $data['rows_truncated'], 'log' => $data['rows'],
        ];
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '.json"');
        header('Cache-Control: no-store');
        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // A cell that starts with = + - @ would be run as a formula by a spreadsheet: neutralise it
    $safe = fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v;
    $head = ['ID', 'Date', $lang === 'en' ? 'User ID' : 'ID utilisateur', $lang === 'en' ? 'Name' : 'Nom', $lang === 'en' ? 'Role' : 'Rôle', 'Action', $lang === 'en' ? 'Details' : 'Détails', 'IP'];
    $lines = array_map(fn($r) => [(int)$r['id'], $r['created_at'], $r['user_id'] ?? '', $safe($r['name']), $r['role'], $r['action'], $safe($r['details']), $r['ip']], $data['rows']);

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '.csv"');
        header('Cache-Control: no-store');
        $fh = fopen('php://output', 'w');
        fwrite($fh, "\xEF\xBB\xBF");   // UTF-8 byte order mark: Excel then reads the accents correctly
        $sep = $lang === 'en' ? ',' : ';';
        fputcsv($fh, ['# ' . $stamp['id'], $per['from'] . ' → ' . $per['to'], $stamp['generated_by'], $stamp['generated_at'], 'sha256:' . $stamp['fingerprint']], $sep, '"', '\\');
        fputcsv($fh, $head, $sep, '"', '\\');
        foreach ($lines as $l) {
            fputcsv($fh, $l, $sep, '"', '\\');
        }
        fclose($fh);
        exit;
    }

    // xlsx (SpreadsheetML workbook, as the other promoter exports)
    $S = $data['summary'];
    $sheets = [
        ['name' => $lang === 'en' ? 'Summary' : 'Synthèse', 'headers' => [$lang === 'en' ? 'Measure' : 'Mesure', $lang === 'en' ? 'Value' : 'Valeur'], 'rows' => [
            [$lang === 'en' ? 'Reference' : 'Référence', $stamp['id']], [$lang === 'en' ? 'Period' : 'Période', $per['from'] . ' → ' . $per['to']],
            [$lang === 'en' ? 'Filter' : 'Filtre', ($category ?? '—') . ($q !== '' ? " · {$q}" : '')], [$lang === 'en' ? 'Generated by' : 'Généré par', $stamp['generated_by']],
            [$lang === 'en' ? 'Events' : 'Événements', $S['events']], [$lang === 'en' ? 'Distinct actors' : 'Acteurs distincts', $S['actors']], [$lang === 'en' ? 'Distinct IPs' : 'Adresses IP distinctes', $S['ips']],
            [$lang === 'en' ? 'Successful sign-ins' : 'Connexions réussies', $S['logins_ok']], [$lang === 'en' ? 'Failed sign-ins' : 'Échecs de connexion', $S['logins_failed']],
            [$lang === 'en' ? 'Failure rate (%)' : 'Taux d’échec (%)', $S['failure_rate']], [$lang === 'en' ? 'Security events' : 'Événements de sécurité', $S['security']],
            [$lang === 'en' ? 'Exports' : 'Exports', $S['exports']], [$lang === 'en' ? 'Accounts deleted' : 'Comptes supprimés', $S['deletions']],
            [$lang === 'en' ? 'Fingerprint (HMAC-SHA256)' : 'Empreinte (HMAC-SHA256)', $stamp['fingerprint']],
        ]],
        ['name' => $lang === 'en' ? 'Per day' : 'Par jour', 'headers' => ['Date', $lang === 'en' ? 'Events' : 'Événements', $lang === 'en' ? 'Failed sign-ins' : 'Échecs de connexion'],
            'rows' => array_map(fn($r) => [$r['day'], $r['events'], $r['failed']], $data['timeline'])],
        ['name' => $lang === 'en' ? 'Top actions' : 'Actions', 'headers' => ['Action', $lang === 'en' ? 'Count' : 'Nombre'], 'rows' => array_map(fn($a, $c) => [$a, $c], array_keys($data['top_actions']), array_values($data['top_actions']))],
        ['name' => $lang === 'en' ? 'Top actors' : 'Acteurs', 'headers' => [$lang === 'en' ? 'Name' : 'Nom', $lang === 'en' ? 'Role' : 'Rôle', $lang === 'en' ? 'Events' : 'Événements', $lang === 'en' ? 'Last activity' : 'Dernière activité'],
            'rows' => array_map(fn($r) => [$safe($r['name']), $r['role'], (int)$r['c'], $r['last_seen']], $data['top_actors'])],
        ['name' => $lang === 'en' ? 'Channels' : 'Canaux', 'headers' => [$lang === 'en' ? 'Channel' : 'Canal', 'Total', $lang === 'en' ? 'Sent' : 'Envoyés', $lang === 'en' ? 'Failed' : 'Échecs', $lang === 'en' ? 'Pending' : 'En attente', $lang === 'en' ? 'Success (%)' : 'Réussite (%)'],
            'rows' => array_map(fn($k, $r) => [$k, $r['total'], $r['sent'], $r['failed'], $r['pending'], $r['success_rate'] ?? ''], array_keys($data['delivery']), array_values($data['delivery']))],
        ['name' => $lang === 'en' ? 'Log' : 'Journal', 'headers' => $head, 'rows' => $lines],
    ];
    SpreadsheetExporter::sendDownload($file, $sheets);
} catch (Throwable $e) {
    logServerError($e, 'promoter/export-observability');
    http_response_code(500);
    exit('Erreur lors de la génération de l’export.');
}
