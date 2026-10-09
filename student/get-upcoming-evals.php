<?php
declare(strict_types=1);

/**
 * Polled by the student dashboard: which evaluations are launched and waiting for me right now.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/StudentLiveEvals.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

try {
    $pdo  = Database::getInstance();
    $user = getCurrentUser();
    $rows = StudentLiveEvals::upcoming($pdo, (int)$user['id'], (string)$user['email']);
    echo json_encode([
        'success' => true,
        'items'   => array_map(fn($u) => [
            'id'               => (int)$u['id'],
            'is_async'         => (int)$u['is_async'],
            'is_running'       => (bool)$u['is_running'],
            'seconds_to_start' => (int)$u['seconds_to_start'],
        ], $rows),
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}
