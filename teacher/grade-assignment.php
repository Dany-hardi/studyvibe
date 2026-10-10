<?php
declare(strict_types=1);

/**
 * Marking one assignment submission, for the teacher of the course.
 *
 *   GET  ?submission_id=5                                   everything the marking window shows (student, file, link, comment, history)
 *   POST action=grade     submission_id, score, feedback    saves the mark and the feedback; the student is told (unless marks are held)
 *        + rubric_json   {"c1":{"points":"3","comment":".."},...}   the marking grid, when the lesson has a rubric (the total is added up here)
 *        + feedback_file an annotated copy (multipart upload)       + remove_file=1 to drop the current one
 *   POST action=revision  submission_id, note               asks for a new version (allowed even after the deadline); the student is told
 *   POST action=release   submission_id                     releases one held mark         (the student is told)
 *   POST action=release_all lesson_id                       releases every held mark of an assignment
 *   POST action=withdraw_all lesson_id                      hides again every released mark of an assignment (nobody is told)
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Assignments.php';
require_once __DIR__ . '/../lib/AssignmentSimilarity.php';
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
            $rubric = json_decode((string)($_POST['rubric_json'] ?? ''), true);
            $res = Assignments::grade($pdo, $id, $teacherId, (string)($_POST['score'] ?? ''), (string)($_POST['feedback'] ?? ''), [
                'rubric' => is_array($rubric) ? $rubric : [],
                'file' => $_FILES['feedback_file'] ?? null,
                'remove_file' => !empty($_POST['remove_file']),
            ]);
        } elseif ($action === 'release') {
            $own = Assignments::ownedSubmission($pdo, $id, $teacherId);
            $res = $own ? Assignments::releaseMarks($pdo, (int)$own['lesson_id'], $teacherId, $id) : ['ok' => false, 'error' => 'not_found'];
        } elseif ($action === 'release_all') {
            $id = (int)($_POST['lesson_id'] ?? 0);
            $res = Assignments::releaseMarks($pdo, $id, $teacherId);
        } elseif ($action === 'withdraw_all') {
            $id = (int)($_POST['lesson_id'] ?? 0);
            $res = Assignments::withdrawMarks($pdo, $id, $teacherId);
        } elseif ($action === 'revision') {
            $res = Assignments::requestRevision($pdo, $id, $teacherId, (string)($_POST['note'] ?? ''));
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
            exit;
        }
        if ($res['ok']) {
            auditLog('assignment_' . $action, in_array($action, ['release_all', 'withdraw_all'], true) ? "Lesson #{$id}" : "Submission #{$id}");
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
    $h = $pdo->prepare("SELECT attempt, file_name, link, comment, submitted_at, score, feedback, graded_at, rubric_scores, feedback_file_name FROM lesson_assignment_history WHERE submission_id = :s ORDER BY attempt DESC");
    $h->execute(['s' => $sub['id']]);
    $history = $h->fetchAll(PDO::FETCH_ASSOC);
    foreach ($history as &$row) {
        $row['rubric_scores'] = AssignmentRubric::decodeScores((string)($row['rubric_scores'] ?? ''));
    }
    unset($row);
    $sim = AssignmentSimilarity::results($pdo, (int)$sub['lesson_id'], $teacherId);
    echo json_encode([
        'success' => true,
        'submission' => [
            'id' => (int)$sub['id'], 'lesson_id' => (int)$sub['lesson_id'],
            'student' => $sub['account_name'], 'matricule' => $sub['student_matricule'], 'email' => $sub['student_email'],
            'assignment' => $sub['assignment_title'] ?: $sub['lesson_title'], 'course' => $sub['course_title'],
            'max' => (float)$sub['assignment_max_score'],
            'submitted_at' => $sub['submitted_at'], 'late' => (int)$sub['is_late'] === 1, 'attempt' => (int)$sub['attempt_count'],
            'file_name' => $sub['submitted_file_name'], 'file_url' => $sub['submitted_file_path'] ? '/download.php?type=assignment&file=' . rawurlencode((string)$sub['submitted_file_path']) : null,
            'link' => $sub['submitted_link'], 'comment' => $sub['student_comment'],
            'score' => $sub['score'] !== null ? (float)$sub['score'] : null, 'feedback' => $sub['feedback'], 'graded_at' => $sub['graded_at'],
            'revision_requested_at' => $sub['revision_requested_at'], 'revision_note' => $sub['revision_note'],
            'release_mode' => ($sub['assignment_release_mode'] ?? 'immediate') === 'manual' ? 'manual' : 'immediate',
            'released_at' => $sub['released_at'], 'held' => Assignments::isHeld($sub),
            'rubric' => AssignmentRubric::decode($sub['assignment_rubric'] ?? null),
            'rubric_scores' => AssignmentRubric::decodeScores((string)($sub['rubric_scores'] ?? '')),
            'feedback_file_name' => $sub['feedback_file_name'],
            'feedback_file_url' => $sub['feedback_file_path'] ? '/download.php?type=assignment_feedback&file=' . rawurlencode((string)$sub['feedback_file_path']) : null,
            'similarity' => $sim['by_submission'][(int)$sub['id']] ?? null,
        ],
        'history' => $history,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    logServerError($e, 'teacher/grade-assignment');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}
