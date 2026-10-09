<?php
declare(strict_types=1);

/**
 * Background check used by the live-evaluation entry form: is this email acceptable, and does it
 * belong to a StudyVibe account? Throttled per browser session to make bulk probing impractical.
 */
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../lib/LiveGuest.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$window = 60;
$max    = 30;
$now    = time();
$hits   = array_filter($_SESSION['live_email_checks'] ?? [], fn($t) => $t > $now - $window);
if (count($hits) >= $max) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Trop de vérifications. Patientez une minute.']);
    exit;
}
$hits[] = $now;
$_SESSION['live_email_checks'] = array_values($hits);

$email = LiveGuest::normalize((string)($_GET['email'] ?? ''));
$error = LiveGuest::check($email);
if ($error !== null) {
    echo json_encode(['success' => true, 'allowed' => false, 'registered' => false, 'message' => $error]);
    exit;
}

try {
    $user = LiveGuest::findUser(Database::getInstance(), $email);
    echo json_encode([
        'success'    => true,
        'allowed'    => true,
        'registered' => $user !== null,
        'name'       => $user['name'] ?? '',
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Vérification indisponible.']);
}
