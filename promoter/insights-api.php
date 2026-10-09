<?php
declare(strict_types=1);

/** JSON for the promoter dashboard panels. GET ?section=overview|growth|insights&days=30 (promoter only). */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Mailer.php';
require_once __DIR__ . '/../lib/PromoterInsights.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'promoter') {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}
session_write_close();   // read-only endpoint: do not keep the session locked while the queries run

$section = (string)($_GET['section'] ?? 'overview');
$days    = max(7, min(180, (int)($_GET['days'] ?? 30)));

try {
    $pdo = Database::getInstance();
    $data = PromoterInsights::cached("{$section}_{$days}", 20, function () use ($pdo, $section, $days) {
        switch ($section) {
            case 'overview':
                $o = PromoterInsights::overview($pdo, $days);
                $f = PromoterInsights::funnel($pdo, $days);
                $a = PromoterInsights::acquisition($pdo, $days);
                $e = PromoterInsights::engagement($pdo);
                $h = PromoterInsights::health($pdo);
                return ['overview' => $o, 'funnel' => $f, 'health' => $h,
                        'recommendations' => PromoterInsights::recommendations($o, $f, $h, $e, $a)];
            case 'growth':
                return ['funnel' => PromoterInsights::funnel($pdo, $days), 'acquisition' => PromoterInsights::acquisition($pdo, $days)];
            case 'insights':
                return ['engagement' => PromoterInsights::engagement($pdo), 'learning' => PromoterInsights::learning($pdo), 'live' => PromoterInsights::live($pdo)];
        }
        return [];
    });
    echo json_encode(['success' => true, 'section' => $section, 'generated' => date('c')] + $data);
} catch (Throwable $e) {
    logServerError($e, 'promoter/insights-api');
    http_response_code(500);
    echo json_encode(['success' => false]);
}
