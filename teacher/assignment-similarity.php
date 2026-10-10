<?php
declare(strict_types=1);

/**
 * Similarity check between the submissions of one assignment (teacher of the course only).
 *
 *   GET  ?lesson_id=7                the stored result (pairs, passages, worst score per submission, whether it is out of date)
 *   POST lesson_id=7                 runs the analysis now and returns the result
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/AssignmentSimilarity.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/RateLimit.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'teacher') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}
$teacherId = (int)$_SESSION['user_id'];
$pdo = Database::getInstance();

try {
    $analysis = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requirePostFromSameSite();
        $lessonId = (int)($_POST['lesson_id'] ?? 0);
        if (!RateLimit::allow($pdo, 'asgsim:' . $teacherId, 30, 3600)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Trop d’analyses en peu de temps.']);
            exit;
        }
        @set_time_limit(120);
        $analysis = AssignmentSimilarity::analyse($pdo, $lessonId, $teacherId);
        if (!$analysis['ok']) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Devoir introuvable.']);
            exit;
        }
        auditLog('assignment_similarity', "Lesson #{$lessonId}");
    } else {
        $lessonId = (int)($_GET['lesson_id'] ?? 0);
    }
    $res = AssignmentSimilarity::results($pdo, $lessonId, $teacherId);
    if (!$res['ok']) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Devoir introuvable.']);
        exit;
    }
    echo json_encode(['success' => true, 'analysis' => $analysis] + $res, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    logServerError($e, 'teacher/assignment-similarity');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}
