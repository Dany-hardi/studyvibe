<?php
declare(strict_types=1);

/** The transcript of the signed-in student (courses, lesson scores, failed certification attempts) as a LaTeX-built PDF. */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/student_i18n.php';
require_once __DIR__ . '/../lib/StudentDocs.php';
require_once __DIR__ . '/../lib/Transcripts.php';
requireRole('student');

$user = getCurrentUser();
$pdo  = Database::getInstance();
try {
    $stmt = $pdo->prepare("
        SELECT c.title AS course_title, m.title AS module_title, e.progress_percent,
               (SELECT MAX(ca.score) FROM certification_attempts ca WHERE ca.student_id = e.student_id AND ca.course_id = e.course_id) AS best_score,
               (SELECT COUNT(*) FROM certification_attempts ca WHERE ca.student_id = e.student_id AND ca.course_id = e.course_id) AS attempts
        FROM enrollments e JOIN courses c ON c.id = e.course_id JOIN modules m ON m.id = c.module_id
        WHERE e.student_id = :sid ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute(['sid' => $user['id']]);
    $courses = $stmt->fetchAll();
    foreach ($courses as $i => $c) {
        $courses[$i]['best_text'] = $c['best_score'] !== null ? sdScore($c['best_score']) : '';
    }

    $stmt = $pdo->prepare("
        SELECT l.title AS lesson_title, c.title AS course_title, lp.score, lp.completed_at
        FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id JOIN chapters ch ON ch.id = l.chapter_id JOIN courses c ON c.id = ch.course_id
        WHERE lp.student_id = :sid AND lp.completed = 1 AND lp.score IS NOT NULL ORDER BY lp.completed_at DESC LIMIT 100
    ");
    $stmt->execute(['sid' => $user['id']]);
    $lessons = $stmt->fetchAll();
    foreach ($lessons as $i => $l) {
        $lessons[$i]['date_text'] = sdDate($l['completed_at'], 'date');
    }

    $stmt = $pdo->prepare("
        SELECT ca.score, ca.attempted_at, c.title AS course_title
        FROM certification_attempts ca JOIN courses c ON c.id = ca.course_id
        WHERE ca.student_id = :sid AND ca.passed = 0 ORDER BY ca.attempted_at DESC
    ");
    $stmt->execute(['sid' => $user['id']]);
    $failed = $stmt->fetchAll();
    foreach ($failed as $i => $a) {
        $failed[$i]['date_text'] = sdDate($a['attempted_at'], 'date');
        $failed[$i]['score_text'] = sdScore($a['score']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Erreur lors de la récupération de votre relevé.');
}

// Record this issue: its code and QR let anybody check the document on /verify.php
$issue = null;
try {
    $issue = Transcripts::issue($pdo, (int)$user['id'], Transcripts::summarize($courses, $lessons, $failed), getClientIp());
} catch (Throwable $e) {
    error_log('[Transcripts] could not record the issue: ' . $e->getMessage());   // the transcript is still produced, without a QR code
}
$verifyUrl = $issue ? rtrim((string)APP_URL, '/') . '/verify.php?code=' . urlencode($issue['code']) : '';

$t = StudentDocs::strings(['generated_at' => sdDate(time(), 'year') . ' · ' . sdDate(time(), 'time')]);
if ($issue) {
    auditLog('transcript_issued', $issue['code']);
}
StudentDocs::send(StudentDocs::transcript($user, $courses, $lessons, $failed, $t, $issue, $verifyUrl), 'releve-notes-' . ($issue['code'] ?? 'studyvibe') . '.pdf');
