<?php
declare(strict_types=1);

/**
 * Génération d'une épreuve écrite de QCM sous format PDF (mise en page double colonne) via LaTeX.
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

    // Récupérer les questions de la session
    $stmt = $pdo->prepare("
        SELECT * FROM live_eval_questions
        WHERE session_id = :sid
        ORDER BY id ASC
    ");
    $stmt->execute(['sid' => $sessionId]);
    $questions = $stmt->fetchAll();
    $totalQuestions = count($questions);

    // Formater les questions pour le document LaTeX
    $latexQuestions = [];
    $idx = 1;
    foreach ($questions as $q) {
        $qText = LatexCompiler::escape($q['question_text']);
        $optA = LatexCompiler::escape($q['option_a']);
        $optB = LatexCompiler::escape($q['option_b']);
        $optC = LatexCompiler::escape($q['option_c']);
        $optD = LatexCompiler::escape($q['option_d']);
        
        $latexQuestions[] = <<<QUESTION
\\noindent\\textbf{Question {$idx}. } {$qText}
\\begin{itemize}[label=\\text{\\textbf{--}}, leftmargin=1.5em]
    \\item[\$\\square\$] A. {$optA}
    \\item[\$\\square\$] B. {$optB}
    \\item[\$\\square\$] C. {$optC}
    \\item[\$\\square\$] D. {$optD}
\\end{itemize}
\\vspace{1em}
QUESTION;
        $idx++;
    }

    $questionsString = implode("\n", $latexQuestions);

    // Escape metadata
    $courseTitle = LatexCompiler::escape($session['course_title']);
    $sessionTitle = LatexCompiler::escape($session['title']);
    $sessionCode = LatexCompiler::escape($session['session_code']);
    $sessionDate = date('d/m/Y', strtotime($session['start_time']));

    // Source LaTeX
    $latexTemplate = <<<LATEX
\documentclass[10pt,a4paper]{article}
\usepackage[utf8]{inputenc}
\usepackage[T1]{fontenc}
\usepackage[french]{babel}
\usepackage{geometry}
\geometry{a4paper, margin=0.6in}
\usepackage{multicol}
\usepackage{tcolorbox}
\usepackage{amssymb}
\usepackage{enumitem}
\usepackage{fancyhdr}
\usepackage{helvet}
\renewcommand{\familydefault}{\sfdefault}

\pagestyle{fancy}
\fancyhf{}
\rhead{\scriptsize Code Séance : {$sessionCode}}
\lhead{\scriptsize {$courseTitle}}
\rfoot{\scriptsize Page \thepage}
\lfoot{\scriptsize Épreuve imprimée — StudyVibe}

\begin{document}

% Cadre d'identification de l'apprenant
\begin{tcolorbox}[colback=white,colframe=black,arc=2mm,boxrule=0.8pt]
\begin{center}
    {\large \textbf{STUDYVIBE LMS — ÉPREUVE ÉCRITE DE QCM}} \\
    \vspace{0.3em}
    \textbf{Cours :} {$courseTitle} \\
    \textbf{Évaluation :} {$sessionTitle} \\
    \textbf{Date :} {$sessionDate}
\end{center}
\vspace{0.5em}
\noindent
\begin{tabular}{p{4.2in}l}
\textbf{Nom \& Prénom :} \hrulefill & \textbf{Note :} \\[0.5em]
\textbf{Classe / Matricule :} \hrulefill & \textbf{/ {$totalQuestions$}} \\
\textbf{Signature :} \hrulefill & 
\end{tabular}
\end{tcolorbox}

\vspace{1em}

\begin{multicols}{2}
{$questionsString}
\end{multicols}

\end{document}
LATEX;

    $pdfData = LatexCompiler::compile($latexTemplate);

    if (!$pdfData) {
        http_response_code(500);
        exit('Erreur lors de la compilation du sujet PDF via LaTeX.');
    }

    $slug = preg_replace('/[^a-z0-9_-]+/i', '_', (string)$session['title']) ?: 'live_eval';
    $filename = 'sujet_exam_' . mb_strtolower($slug) . '_' . date('Y-m-d') . '.pdf';

    auditLog('export_live_questions_latex', "Session #{$sessionId}");
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfData));
    echo $pdfData;
    exit;

} catch (Exception $e) {
    http_response_code(500);
    exit('Erreur lors du traitement LaTeX.');
}
