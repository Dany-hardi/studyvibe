<?php
declare(strict_types=1);

/**
 * Web push endpoint for the signed-in user.
 *   GET  ?action=state                          -> { configured, publicKey, devices }
 *   POST action=subscribe   (JSON: endpoint, keys{p256dh, auth})
 *   POST action=unsubscribe (JSON: endpoint)
 *   POST action=test                             -> queues a test notification to this user
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/PushQueue.php';
require_once __DIR__ . '/../lib/RateLimit.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'login']);
    exit;
}
$uid = (int)$_SESSION['user_id'];
$pdo = Database::getInstance();
$in = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requirePostFromSameSite();
    $in = json_decode((string)file_get_contents('php://input'), true);
    $in = is_array($in) ? $in : $_POST;
}
$action = (string)($in['action'] ?? $_GET['action'] ?? 'state');

if ($action === 'state') {
    echo json_encode(['success' => true, 'configured' => WebPush::isConfigured(), 'publicKey' => WebPush::publicKey(), 'devices' => PushQueue::deviceCount($pdo, $uid)]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'method']);
    exit;
}
if (!WebPush::isConfigured()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'not_configured']);
    exit;
}

if ($action === 'subscribe') {
    if (!RateLimit::allow($pdo, 'push_sub:' . $uid, 20, 3600)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'too_many']);
        exit;
    }
    $keys = (array)($in['keys'] ?? []);
    $ok = PushQueue::subscribe($pdo, $uid, (string)($in['endpoint'] ?? ''), (string)($keys['p256dh'] ?? ''), (string)($keys['auth'] ?? ''), (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (!$ok) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'invalid_subscription']);
        exit;
    }
    echo json_encode(['success' => true, 'devices' => PushQueue::deviceCount($pdo, $uid)]);
    exit;
}
if ($action === 'unsubscribe') {
    PushQueue::unsubscribe($pdo, $uid, (string)($in['endpoint'] ?? ''));
    echo json_encode(['success' => true, 'devices' => PushQueue::deviceCount($pdo, $uid)]);
    exit;
}
if ($action === 'test') {
    if (!RateLimit::allow($pdo, 'push_test:' . $uid, 5, 3600)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'too_many']);
        exit;
    }
    $lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
    $role = (string)($_SESSION['user_role'] ?? 'student');
    $home = ['promoter' => '/promoter/dashboard.php', 'teacher' => '/teacher/dashboard.php'][$role] ?? '/student/dashboard.php';
    $id = PushQueue::enqueue($pdo, $uid, $lang === 'en' ? 'Notifications are on' : 'Les notifications sont activées',
        $lang === 'en' ? 'You will be told here about lessons, results and reminders.' : 'Vous serez prévenu ici des leçons, des résultats et des rappels.', $home, 'test');
    echo json_encode(['success' => $id !== null, 'queued' => $id !== null]);
    exit;
}
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'unknown_action']);
