<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    header('Location: /index.php');
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="certifies_studyvibe_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
fputcsv($out, ['Étudiant', 'Email', 'Module', 'Code Certificat', 'Date', 'Manuel'], ';');

try {
    $pdo  = Database::getInstance();
    $rows = $pdo->query("
        SELECT u.name, u.email, m.title AS module_title, cert.certificate_code,
               cert.issued_at, cert.manual_issue
        FROM certificates cert
        JOIN users u ON u.id = cert.student_id
        JOIN modules m ON m.id = cert.module_id
        ORDER BY cert.issued_at DESC
    ")->fetchAll();

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['name'],
            $r['email'],
            $r['module_title'],
            $r['certificate_code'],
            date('d/m/Y H:i', strtotime($r['issued_at'])),
            $r['manual_issue'] ? 'Oui' : 'Non',
        ], ';');
    }
    auditLog('export_certificates', count($rows) . ' lignes');
} catch (PDOException) {
    fputcsv($out, ['Erreur export'], ';');
}

fclose($out);
