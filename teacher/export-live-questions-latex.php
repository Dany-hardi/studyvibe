<?php
declare(strict_types=1);

/**
 * Question paper (mode=subject) or answer key (mode=correction) of a live evaluation, as a PDF typeset with LaTeX.
 *
 *   /teacher/export-live-questions-latex.php?session_id=12&mode=subject
 *   /teacher/export-live-questions-latex.php?session_id=12&mode=correction&format=tex     the LaTeX source (zip)
 *
 * The document itself is built in lib/ExportDocs.php with the look from lib/ExportTheme.php.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/ExportDocs.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    header('Location: /index.php');
    exit;
}

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$teacherId = (int)$_SESSION['user_id'];
$mode      = ($_GET['mode'] ?? '') === 'correction' ? 'correction' : 'subject';
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

    $stmt = $pdo->prepare("SELECT * FROM live_eval_questions WHERE session_id = :sid ORDER BY sort_order ASC, id ASC");
    $stmt->execute(['sid' => $sessionId]);
    $questions = $stmt->fetchAll();

    $doc  = ExportDocs::questionPaper($pdo, $session, $questions, $mode, $lang);
    $slug = ($mode === 'correction' ? 'corrige_' : 'sujet_') . ExportTheme::slug((string)$session['title'], 'evaluation') . '_' . date('Y-m-d');

    if (($_GET['format'] ?? '') === 'tex') {
        auditLog('export_live_questions_latex_source', "Session #{$sessionId} (Mode: {$mode})");
        LatexCompiler::sendSource($doc['tex'], $slug, $doc['assets']);
    }

    $pdfData = LatexCompiler::compile($doc['tex'], $doc['assets']);
    if (!$pdfData) {
        // LaTeX is missing or failed on this server: hand over the source so the export still works
        LatexCompiler::sendSource($doc['tex'], $slug, $doc['assets']);
    }

    auditLog('export_live_questions_latex', "Session #{$sessionId} (Mode: {$mode})");
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $slug . '.pdf"');
    header('Content-Length: ' . strlen($pdfData));
    echo $pdfData;
    exit;
} catch (Throwable $e) {
    logServerError($e, 'export-live-questions-latex');
    http_response_code(500);
    exit('Erreur lors de la génération du document.');
}
