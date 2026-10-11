<?php
declare(strict_types=1);

/** The report of one failed certification attempt of the signed-in student, as a LaTeX-built PDF. */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/student_i18n.php';
require_once __DIR__ . '/../lib/StudentDocs.php';
requireRole('student');

$attemptId = (int)($_GET['attempt_id'] ?? 0);
$user = getCurrentUser();
$attempt = null;
if ($attemptId > 0 && $user) {
    try {
        $stmt = Database::getInstance()->prepare("
            SELECT ca.*, c.title AS course_title, u.name AS student_name
            FROM certification_attempts ca JOIN courses c ON c.id = ca.course_id JOIN users u ON u.id = ca.student_id
            WHERE ca.id = :id AND ca.student_id = :sid AND ca.passed = 0
        ");
        $stmt->execute(['id' => $attemptId, 'sid' => $user['id']]);
        $attempt = $stmt->fetch();
    } catch (PDOException $e) {
        $attempt = null;
    }
}
if (!$attempt) {
    http_response_code(404);
    exit('Relevé introuvable.');
}

$totalQ = (int)($attempt['total_questions'] ?? 0);
$t = StudentDocs::strings([
    'date_text' => sdDate($attempt['attempted_at'], 'year') . ' · ' . sdDate($attempt['attempted_at'], 'time'),
    'score_text' => sdScore((float)$attempt['score']),
]);
StudentDocs::send(StudentDocs::attemptReport($attempt, $t, 80.0, $totalQ > 0 ? $totalQ : null), 'releve-tentative-' . $attemptId . '.pdf', 'export_attempt_pdf');
