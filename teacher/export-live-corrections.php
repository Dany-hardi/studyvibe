<?php
declare(strict_types=1);

/**
 * A ZIP with the correction script of every student for the code questions of a session (see lib/LiveCodeExport.php).
 * Teacher only, only for a session of that teacher.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/LiveCodeExport.php';

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'teacher') {
    http_response_code(403);
    exit('Accès non autorisé.');
}
$pdo = Database::getInstance();
$en = TranslationService::getLang() === 'en';
$res = LiveCodeExport::build($pdo, (int)($_GET['session_id'] ?? 0), (int)$_SESSION['user_id'], $en);
if (!$res['ok']) {
    http_response_code($res['error'] === 'not_found' ? 404 : 409);
    exit(match ($res['error']) {
        'no_code_question' => $en ? 'This session has no code question.' : 'Cette séance n’a pas de question de code.',
        'no_answers' => $en ? 'No student has submitted code yet.' : 'Aucun étudiant n’a encore soumis de code.',
        default => $en ? 'Session not found.' : 'Séance introuvable.',
    });
}
auditLog('live_code_corrections_zip', 'session ' . (int)$_GET['session_id'] . ', ' . $res['count'] . ' files');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="corrections.zip"; filename*=UTF-8\'\'' . rawurlencode((string)$res['name']));
header('Content-Length: ' . (string)filesize($res['path']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
readfile($res['path']);
@unlink($res['path']);
