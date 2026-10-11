<?php
declare(strict_types=1);

/**
 * Order of merit of a live evaluation as a PDF, typeset with LaTeX (lib/ExportDocs.php).
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/ExportDocs.php';

// Verify teacher access
if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    header('Location: /index.php');
    exit;
}

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$teacherId = (int)$_SESSION['user_id'];

if ($sessionId <= 0) {
    http_response_code(400);
    exit('Identifiant de séance non valide.');
}

try {
    $pdo = Database::getInstance();

    // Verify session ownership
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

    // Fetch total questions count
    $qCountStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_questions WHERE session_id = :sid");
    $qCountStmt->execute(['sid' => $sessionId]);
    $totalQuestions = (int)$qCountStmt->fetchColumn();

    // Fetch registrations ordered by score descending (order of merit)
    $stmt = $pdo->prepare("
        SELECT name, email, score, registered_at, cancelled_at
        FROM live_eval_registrations
        WHERE session_id = :sid
        ORDER BY CASE WHEN score IS NULL THEN 1 ELSE 0 END, score DESC, name ASC
    ");
    $stmt->execute(['sid' => $sessionId]);
    $registrations = $stmt->fetchAll();

    // Preferred: the LaTeX document (traditional typeface, repeated table header, page x / y)
    $lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
    $doc  = ExportDocs::rankingReport($session, $registrations, $totalQuestions, $lang);
    $pdfData = LatexCompiler::compile($doc['tex']);
    if ($pdfData) {
        $slug = 'classement_' . ExportTheme::slug((string)$session['title'], 'evaluation') . '_' . date('Y-m-d');
        auditLog('export_live_pdf', "Session #{$sessionId}");
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $slug . '.pdf"');
        header('Content-Length: ' . strlen($pdfData));
        echo $pdfData;
        exit;
    }

    // No technical details for the teacher: the LaTeX log stays on the server
    http_response_code(503);
    exit($lang === 'en' ? 'The PDF could not be produced right now. Please try again later.' : 'Le PDF n’a pas pu être produit pour le moment. Réessayez plus tard.');

} catch (PDOException $e) {
    http_response_code(500);
    exit('Erreur serveur lors de la generation du PDF.');
}
