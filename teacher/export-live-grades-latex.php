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

    // Calculate summary statistics
    $totalCount = count($registrations);
    $submittedCount = 0;
    $sumScore = 0.0;
    $maxScore = 0.0;
    $passedCount = 0;

    foreach ($registrations as $r) {
        if ($r['score'] !== null) {
            $submittedCount++;
            $scoreVal = (float)$r['score'];
            $sumScore += $scoreVal;
            if ($scoreVal > $maxScore) { $maxScore = $scoreVal; }
            if ($scoreVal >= 50.0) { $passedCount++; }
        }
    }

    $avgScore = $submittedCount > 0 ? round($sumScore / $submittedCount, 1) : 0.0;
    $passRate = $submittedCount > 0 ? round(($passedCount / $submittedCount) * 100, 1) : 0.0;
    $maxScorePercent = round($maxScore, 1);

    // Formater les lignes du tableau LaTeX
    $latexRows = [];
    foreach ($registrations as $r) {
        $nameEsc = LatexCompiler::escape($r['name']);
        $emailEsc = LatexCompiler::escape($r['email']);
        
        if ($r['score'] !== null) {
            $rawScore = round(((float)$r['score'] / 100) * $totalQuestions);
            $scoreDisplay = "{$rawScore} / {$totalQuestions}";
            $percentDisplay = round((float)$r['score'], 1) . '\%';
            $statusDisplay = ((float)$r['score'] >= 50) ? '\textbf{\color{green!50!black}Admis}' : '\color{red!60!black}Ajourné';
        } else {
            $scoreDisplay = 'Non finalisé';
            $percentDisplay = '--';
            $statusDisplay = '\color{gray}Non soumis';
        }
        
        $latexRows[] = "{$nameEsc} & {$emailEsc} & {$scoreDisplay} & {$percentDisplay} & {$statusDisplay} \\\\";
    }
    
    $rowsString = implode("\n\\midrule\n", $latexRows);
    if (empty($rowsString)) {
        $rowsString = "\multicolumn{5}{c}{\textit{Aucun participant enregistré.}} \\\\";
    }

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
\geometry{a4paper, margin=0.7in}
\usepackage{booktabs}
\usepackage{xcolor}
\usepackage{fancyhdr}
\usepackage{tcolorbox}
\usepackage{helvet}
\usepackage{tabularx}
\renewcommand{\familydefault}{\sfdefault}

\definecolor{studyvibegreen}{HTML}{004B23}
\definecolor{studyvibedark}{HTML}{111111}

\pagestyle{fancy}
\fancyhf{}
\rhead{\scriptsize \textbf{StudyVibe LMS} — Plateforme Académique}
\lhead{\scriptsize Rapport d'Évaluation Officiel}
\rfoot{\scriptsize Page \thepage}
\lfoot{\scriptsize Confidentiel — Usage Enseignant Uniquement}

\begin{document}

\begin{tcolorbox}[colback=studyvibegreen!8!white,colframe=studyvibegreen,arc=2mm,title={\Large \textbf{StudyVibe — Rapport Synthetique de Téléévaluation}}]
\vspace{0.2em}
\begin{tabular}{ll}
\textbf{Cours :} & {$courseTitle} \\
\textbf{Session :} & {$sessionTitle} \\
\textbf{Mode d'évaluation :} & {$sessionMode} \\
\textbf{Date d'extraction :} & {$reportDate} \\
\textbf{Total d'épreuves :} & {$totalQuestions} question(s) au barème
\end{tabular}
\end{tcolorbox}

\vspace{1em}

\begin{tcolorbox}[colback=gray!5!white,colframe=studyvibedark,title={\textbf{Statistiques Globlales de la Promotion}},arc=1mm]
\begin{tabularx}{\textwidth}{XXXXX}
\textbf{Inscrits} & \textbf{Soumissions} & \textbf{Moyenne} & \textbf{Meilleur Score} & \textbf{Taux de Réussite} \\
{$totalCount} élève(s) & {$submittedCount} copie(s) & {$avgScore}\% & {$maxScorePercent}\% & {$passRate}\%
\end{tabularx}
\end{tcolorbox}

\vspace{1.5em}

\section*{Feuille de Notes Générales}

\begin{center}
\begin{tabularx}{\textwidth}{X X c c c}
\toprule
\textbf{Nom complet} & \textbf{Adresse e-mail} & \textbf{Note brut} & \textbf{Score (\%)} & \textbf{Statut} \\
\midrule
{$rowsString}
\bottomrule
\end{tabularx}
\end{center}

\vspace{2em}
\vfill
\begin{center}
\scriptsize \color{gray} Rapport produit automatiquement par le moteur de compilation LaTeX StudyVibe LMS. \\
Signature numérique d'authenticité institutionnelle.
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

