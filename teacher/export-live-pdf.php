<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/PdfReportBuilder.php';

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
        SELECT name, email, score, registered_at
        FROM live_eval_registrations
        WHERE session_id = :sid
        ORDER BY CASE WHEN score IS NULL THEN 1 ELSE 0 END, score DESC, name ASC
    ");
    $stmt->execute(['sid' => $sessionId]);
    $registrations = $stmt->fetchAll();

    // Initialize PdfReportBuilder
    $pdf = new PdfReportBuilder();
    $pdf->addPage();

    $top = 841.89 - 42.0;

    // Header styling: Black and White only, clean fonts
    $pdf->drawText(42.0, $top - 10, "RAPPORT D'EVALUATION", 18, true);
    $pdf->drawText(42.0, $top - 28, "Session : " . $session['title'], 11, false);
    $pdf->drawText(42.0, $top - 44, "Cours : " . $session['course_title'], 11, false);
    $pdf->drawText(42.0, $top - 60, "Date de generation : " . date('d/m/Y H:i'), 10, false);
    $pdf->drawText(42.0, $top - 76, "Nombre de participants : " . count($registrations), 10, false);

    // Decorative line (separator)
    $pdf->drawLine(42.0, $top - 86, 595.28 - 42.0, $top - 86, 0.8);

    // Position vertical cursor under the header line
    $pdf->setCursorY($top - 110);

    // Build the table rows
    $headers = ['Rang', 'Nom complet', 'Adresse e-mail', 'Note / Score'];
    $rows = [];
    $prevScore = null;
    $actualRank = 1;

    foreach ($registrations as $index => $r) {
        if ($r['score'] !== null) {
            $rawScore = round(((float)$r['score'] / 100) * $totalQuestions);
            $scoreDisplay = "{$rawScore} / {$totalQuestions} ({$r['score']}%)";

            // Tie handling logic
            if ($prevScore !== null && (float)$r['score'] < $prevScore) {
                $actualRank = $index + 1;
            }
            $prevScore = (float)$r['score'];
            $rankDisplay = (string)$actualRank;
        } else {
            $scoreDisplay = 'Non finalise';
            $rankDisplay = '-';
        }

        $rows[] = [
            $rankDisplay,
            $r['name'],
            $r['email'],
            $scoreDisplay
        ];
    }

    // Column widths sum up to 511.28 (PAGE_WIDTH - 2 * MARGIN)
    $colWidths = [45.0, 180.0, 196.28, 90.0];

    $pdf->addTable($headers, $rows, $colWidths);

    // Clean file name
    $slug = preg_replace('/[^a-z0-9_-]+/i', '_', (string)$session['title']) ?: 'evaluation';
    $filename = 'rapport_' . mb_strtolower($slug) . '_' . date('Y-m-d') . '.pdf';

    auditLog('export_live_pdf', "Session #{$sessionId}");
    $pdf->sendDownload($filename);

} catch (PDOException $e) {
    http_response_code(500);
    exit('Erreur serveur lors de la generation du PDF.');
}
