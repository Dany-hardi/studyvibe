<?php
declare(strict_types=1);

/**
 * Video chaining. POST action=start|complete, lesson_id, video_key (+ duration in seconds for complete).
 * A video can only be started or completed once every video before it in the lesson is done and the lesson itself is open.
 * Completion also needs real time to have passed since the start, measured on the server.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/LessonFlow.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode de requête non autorisée.']);
    exit;
}

$studentId = (int)$_SESSION['user_id'];
$lessonId  = (int)($_POST['lesson_id'] ?? 0);
$key       = (string)($_POST['video_key'] ?? '');
$action    = (string)($_POST['action'] ?? '');
$duration  = max(0, min(4 * 3600, (int)($_POST['duration'] ?? 0)));

if ($lessonId <= 0 || $key === '' || !in_array($action, ['start', 'complete'], true)) {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
    exit;
}

try {
    $pdo    = Database::getInstance();
    $course = LessonFlow::courseOf($pdo, $lessonId);
    if (!$course) {
        echo json_encode(['success' => false, 'message' => 'Leçon introuvable.']);
        exit;
    }
    $enr = $pdo->prepare('SELECT 1 FROM enrollments WHERE student_id = :s AND course_id = :c');
    $enr->execute(['s' => $studentId, 'c' => $course['course_id']]);
    if (!$enr->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Vous n’êtes pas inscrit à ce cours.']);
        exit;
    }
    if (LessonFlow::blockerFor($pdo, $studentId, $course['course_id'], $lessonId) !== null) {
        echo json_encode(['success' => false, 'code' => 'locked', 'message' => 'Terminez d’abord la leçon précédente.']);
        exit;
    }

    $videos  = LessonFlow::videos($pdo, $lessonId);
    $current = LessonFlow::currentVideoKey($pdo, $studentId, $lessonId);
    $known   = array_column($videos, 'key');
    if (!in_array($key, $known, true)) {
        echo json_encode(['success' => false, 'message' => 'Vidéo introuvable.']);
        exit;
    }
    $done = LessonFlow::videosDone($pdo, $studentId, $lessonId);
    if (isset($done[$key])) {
        echo json_encode(['success' => true, 'already' => true, 'next_key' => $current]);
        exit;
    }
    if ($key !== $current) {
        echo json_encode(['success' => false, 'code' => 'video_locked', 'message' => 'Terminez d’abord la vidéo précédente.']);
        exit;
    }

    if ($action === 'start') {
        $pdo->prepare("
            INSERT INTO lesson_video_progress (student_id, lesson_id, video_key, started_at)
            VALUES (:s, :l, :k, NOW())
            ON DUPLICATE KEY UPDATE started_at = COALESCE(started_at, NOW())
        ")->execute(['s' => $studentId, 'l' => $lessonId, 'k' => $key]);
        echo json_encode(['success' => true]);
        exit;
    }

    // complete
    $stmt = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, started_at, NOW()) FROM lesson_video_progress WHERE student_id = :s AND lesson_id = :l AND video_key = :k AND started_at IS NOT NULL");
    $stmt->execute(['s' => $studentId, 'l' => $lessonId, 'k' => $key]);
    $elapsed = $stmt->fetchColumn();
    $needed  = $duration > 0 ? max(5, (int)floor($duration * 0.7)) : 10;
    if ($elapsed === false || (int)$elapsed < $needed) {
        echo json_encode(['success' => false, 'code' => 'too_fast', 'message' => 'Regardez la vidéo jusqu’au bout pour passer à la suivante.']);
        exit;
    }
    $pdo->prepare("UPDATE lesson_video_progress SET completed_at = NOW() WHERE student_id = :s AND lesson_id = :l AND video_key = :k")
        ->execute(['s' => $studentId, 'l' => $lessonId, 'k' => $key]);

    $next = LessonFlow::currentVideoKey($pdo, $studentId, $lessonId);
    echo json_encode(['success' => true, 'next_key' => $next, 'all_done' => $next === null]);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'video-progress.php');
}
