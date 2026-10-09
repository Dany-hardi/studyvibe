<?php
declare(strict_types=1);

/**
 * Onboarding walkthrough state. POST action=complete marks the tour as seen for the signed-in user,
 * POST action=reset makes it start again on the next dashboard load.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

$action = (string)($_POST['action'] ?? '');
if (!in_array($action, ['complete', 'reset'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare('UPDATE users SET tour_seen_at = ' . ($action === 'complete' ? 'NOW()' : 'NULL') . ' WHERE id = :id');
    $stmt->execute(['id' => (int)$_SESSION['user_id']]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'tour');
}
