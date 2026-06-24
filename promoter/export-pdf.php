<?php
declare(strict_types=1);

/**
 * Redirection legacy PDF → export Excel équivalent.
 */

require_once __DIR__ . '/../auth.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    header('Location: /index.php');
    exit;
}

$type = strtolower(trim((string)($_GET['type'] ?? 'logs')));
$map  = [
    'logs'     => 'audit_logs',
    'students' => 'students',
];

$excelType = $map[$type] ?? 'audit_logs';
header('Location: /promoter/export-excel.php?type=' . urlencode($excelType));
exit;
