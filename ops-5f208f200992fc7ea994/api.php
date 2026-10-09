<?php
declare(strict_types=1);

/** JSON actions of the control center. POST with the X-Ops-Token header, except `state` which only reads. */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Mailer.php';
require_once __DIR__ . '/../lib/ControlGate.php';
require_once __DIR__ . '/../lib/ControlState.php';
require_once __DIR__ . '/../lib/ControlCommands.php';

$user = ControlGate::enter(true);
header('Content-Type: application/json');
$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'state');
$pdo = Database::getInstance();

try {
    if ($action === 'state') {
        session_write_close();
        echo json_encode(['success' => true] + ControlState::collect($pdo));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !ControlGate::checkToken()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Expired page. Reload it.']);
        exit;
    }

    if ($action === 'calibrate') {
        session_write_close();
        $r = ControlState::calibrate($pdo);
        auditLog('ops_calibrate', 'speed ' . $r['speed']);
        echo json_encode(['success' => true, 'calibration' => $r]);
        exit;
    }

    if ($action === 'command') {
        $id = (string)($_POST['id'] ?? '');
        $def = null;
        foreach (ControlCommands::catalog() as $c) { if ($c['id'] === $id) { $def = $c; } }
        if (!$def) { echo json_encode(['success' => false, 'message' => 'Unknown command.']); exit; }
        if (isset($def['phrase']) && trim((string)($_POST['phrase'] ?? '')) !== $def['phrase']) {
            echo json_encode(['success' => false, 'message' => 'Type ' . $def['phrase'] . ' to confirm.']);
            exit;
        }
        $res = ControlCommands::run($pdo, $id);
        auditLog('ops_command', $id . ': ' . ($res['message'] ?? ''));
        echo json_encode(['success' => true] + $res);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (Throwable $e) {
    logServerError($e, 'ops/api');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
