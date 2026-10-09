<?php
declare(strict_types=1);

/**
 * Grade report of a live evaluation (summary, statistics, distribution of marks, sheet of marks), typeset with LaTeX.
 *
 *   /teacher/export-live-grades-latex.php?session_id=12
 *   /teacher/export-live-grades-latex.php?session_id=12&format=tex     the LaTeX source (zip)
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/ExportDocs.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    header('Location: /index.php');
    exit;
}

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$teacherId = (int)$_SESSION['user_id'];
$lang      = TranslationService::getLang() === 'en' ? 'en' : 'fr';

if ($sessionId <= 0) {
    http_response_code(400);
    exit('Identifiant de séance non valide.');
}

try {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("
        SELECT s.*, c.title AS course_title
        FROM live_eval_sessions s
        JOIN courses c ON s.course_id = c.id
        WHERE s.id = :sid AND s.teacher_id = :tid
    ");
    $stmt->execute(['sid' => $sessionId, 'tid' => $teacherId]);
    $session = $stmt->fetch();
    if (!$session) {
        http_response_code(403);
        exit('Séance introuvable ou non autorisée.');
    }

    $q = $pdo->prepare("SELECT COUNT(*) FROM live_eval_questions WHERE session_id = :sid");
    $q->execute(['sid' => $sessionId]);
    $totalQuestions = (int)$q->fetchColumn();

    // Alphabetical, as a marks sheet is read; the ranking has its own export
    $stmt = $pdo->prepare("SELECT name, email, score, cancelled_at FROM live_eval_registrations WHERE session_id = :sid ORDER BY name ASC");
    $stmt->execute(['sid' => $sessionId]);
    $registrations = $stmt->fetchAll();

    $doc  = ExportDocs::gradesReport($session, $registrations, $totalQuestions, $lang);
    $slug = 'rapport_notes_' . ExportTheme::slug((string)$session['title'], 'evaluation') . '_' . date('Y-m-d');

    if (($_GET['format'] ?? '') === 'tex') {
        auditLog('export_live_grades_latex_source', "Session #{$sessionId}");
        LatexCompiler::sendSource($doc['tex'], $slug);
    }
    $pdfData = LatexCompiler::compile($doc['tex']);
    if (!$pdfData) {
        LatexCompiler::sendSource($doc['tex'], $slug);
    }

    auditLog('export_live_grades_latex', "Session #{$sessionId}");
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $slug . '.pdf"');
    header('Content-Length: ' . strlen($pdfData));
    echo $pdfData;
    exit;
} catch (Throwable $e) {
    logServerError($e, 'export-live-grades-latex');
    http_response_code(500);
    exit('Erreur lors de la génération du rapport.');
}
