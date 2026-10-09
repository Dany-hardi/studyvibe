<?php
declare(strict_types=1);

/**
 * Marking one assignment submission, for the teacher of the course.
 *
 *   GET  ?submission_id=5                                   everything the marking window shows (student, file, link, comment, history)
 *   POST action=grade     submission_id, score, feedback    saves the mark and the feedback; the student is told
 *   POST action=revision  submission_id, note               asks for a new version (allowed even after the deadline); the student is told
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Assignments.php';
require_once __DIR__ . '/../lib/Security.php';

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
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Security::requirePostFromSameSite();
        $id = (int)($_POST['submission_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'grade') {
            $res = Assignments::grade($pdo, $id, $teacherId, (string)($_POST['score'] ?? ''), (string)($_POST['feedback'] ?? ''));
        } elseif ($action === 'revision') {
            $res = Assignments::requestRevision($pdo, $id, $teacherId, (string)($_POST['note'] ?? ''));
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
            exit;
        }
        if ($res['ok']) {
            auditLog('assignment_' . $action, "Submission #{$id}");
        }
        echo json_encode($res + ['success' => $res['ok']]);
        exit;
    }

    $sub = Assignments::ownedSubmission($pdo, (int)($_GET['submission_id'] ?? 0), $teacherId);
    if ($sub === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Devoir introuvable.']);
        exit;
    }
    $h = $pdo->prepare("SELECT attempt, file_name, link, comment, submitted_at, score, feedback, graded_at FROM lesson_assignment_history WHERE submission_id = :s ORDER BY attempt DESC");
    $h->execute(['s' => $sub['id']]);
    echo json_encode([
        'success' => true,
        'submission' => [
            'id' => (int)$sub['id'],
            'student' => $sub['account_name'], 'matricule' => $sub['student_matricule'], 'email' => $sub['student_email'],
            'assignment' => $sub['assignment_title'] ?: $sub['lesson_title'], 'course' => $sub['course_title'],
            'max' => (float)$sub['assignment_max_score'],
            'submitted_at' => $sub['submitted_at'], 'late' => (int)$sub['is_late'] === 1, 'attempt' => (int)$sub['attempt_count'],
            'file_name' => $sub['submitted_file_name'], 'file_url' => $sub['submitted_file_path'] ? '/download.php?type=assignment&file=' . rawurlencode((string)$sub['submitted_file_path']) : null,
            'link' => $sub['submitted_link'], 'comment' => $sub['student_comment'],
            'score' => $sub['score'] !== null ? (float)$sub['score'] : null, 'feedback' => $sub['feedback'], 'graded_at' => $sub['graded_at'],
            'revision_requested_at' => $sub['revision_requested_at'], 'revision_note' => $sub['revision_note'],
        ],
        'history' => $h->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    logServerError($e, 'teacher/grade-assignment');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}
