<?php
declare(strict_types=1);

/**
 * Génération d'un rapport PDF des notes de téléévaluation via le compilateur LaTeX.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/LatexCompiler.php';

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

    // Récupérer la session et le cours associé
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

    // Récupérer le nombre total de questions
    $qCountStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_questions WHERE session_id = :sid");
    $qCountStmt->execute(['sid' => $sessionId]);
    $totalQuestions = (int)$qCountStmt->fetchColumn();

    // Récupérer les inscriptions et les notes
    $stmt = $pdo->prepare("
        SELECT name, email, score
        FROM live_eval_registrations
        WHERE session_id = :sid
        ORDER BY CASE WHEN score IS NULL THEN 1 ELSE 0 END, score DESC, name ASC
    ");
    $stmt->execute(['sid' => $sessionId]);
    $registrations = $stmt->fetchAll();

    // Formater les lignes du tableau LaTeX
    $latexRows = [];
    foreach ($registrations as $r) {
        $nameEsc = LatexCompiler::escape($r['name']);
        $emailEsc = LatexCompiler::escape($r['email']);
        
        if ($r['score'] !== null) {
            $rawScore = round(((float)$r['score'] / 100) * $totalQuestions);
            $scoreDisplay = "{$rawScore} / {$totalQuestions}";
            $percentDisplay = round((float)$r['score'], 2) . '\%';
        } else {
            $scoreDisplay = 'Non finalisé';
            $percentDisplay = '--';
        }
        
        $latexRows[] = "{$nameEsc} & {$emailEsc} & {$scoreDisplay} & {$percentDisplay} \\\\";
    }
    
    $rowsString = implode("\n\\midrule\n", $latexRows);

    // Escape metadata
    $courseTitle = LatexCompiler::escape($session['course_title']);
    $sessionTitle = LatexCompiler::escape($session['title']);
    $reportDate = date('d/m/Y H:i');
    $sessionMode = (int)($session['is_async'] ?? 0) === 1 ? 'Devoir Libre (Asynchrone)' : 'Téléévaluation (Synchrone)';

    // Source LaTeX
    $latexTemplate = <<<LATEX
\documentclass[11pt,a4paper]{article}
\usepackage[utf8]{inputenc}
\usepackage[T1]{fontenc}
\usepackage{geometry}
\geometry{a4paper, margin=0.8in}
\usepackage{booktabs}
\usepackage{xcolor}
\usepackage{fancyhdr}
\usepackage{tcolorbox}
\usepackage{helvet}
\renewcommand{\familydefault}{\sfdefault}

\pagestyle{fancy}
\fancyhf{}
\rhead{\scriptsize StudyVibe LMS}
\lhead{\scriptsize Rapport d'Évaluation}
\rfoot{\scriptsize Page \thepage}
\lfoot{\scriptsize Document confidentiel}

\begin{document}

\begin{tcolorbox}[colback=green!5!white,colframe=green!40!black,title={StudyVibe — Rapport de Téléévaluation}]
\textbf{Cours :} {$courseTitle} \\
\textbf{Session :} {$sessionTitle} \\
\textbf{Date du rapport :} {$reportDate} \\
\textbf{Mode :} {$sessionMode} \\
\textbf{Nombre de questions :} {$totalQuestions}
\end{tcolorbox}

\vspace{1.5em}

\section*{Notes des Participants}

\begin{center}
\begin{tabular}{llcl}
\toprule
\textbf{Nom complet} & \textbf{Adresse e-mail} & \textbf{Note / {$totalQuestions$}} & \textbf{Score (\%)} \\
\midrule
{$rowsString}
\bottomrule
\end{tabular}
\end{center}

\end{document}
LATEX;

    $pdfData = LatexCompiler::compile($latexTemplate);

    if (!$pdfData) {
        http_response_code(500);
        exit('Erreur lors de la compilation du document PDF via LaTeX.');
    }

    $slug = preg_replace('/[^a-z0-9_-]+/i', '_', (string)$session['title']) ?: 'live_eval';
    $filename = 'rapport_notes_' . mb_strtolower($slug) . '_' . date('Y-m-d') . '.pdf';

    auditLog('export_live_grades_latex', "Session #{$sessionId}");
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfData));
    echo $pdfData;
    exit;

} catch (Exception $e) {
    http_response_code(500);
    exit('Erreur lors du traitement du rapport LaTeX.');
}
