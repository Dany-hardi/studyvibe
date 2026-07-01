<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

// Exiger que l'utilisateur soit connecté
if (!isLoggedIn()) {
    http_response_code(401);
    exit('Veuillez vous connecter pour accéder à ce document.');
}

$regId = (int)($_GET['registration_id'] ?? 0);
$token = trim((string)($_GET['token'] ?? ''));

if ($regId <= 0 || empty($token)) {
    http_response_code(400);
    exit('Paramètres de requête manquants ou invalides.');
}

// Validation du jeton sécurisé
$expectedToken = hash_hmac('sha256', (string)$regId, APP_SECRET);
if (!hash_equals($expectedToken, $token)) {
    http_response_code(403);
    exit('Jeton de sécurité invalide ou expiré.');
}

$pdo = Database::getInstance();
$currentUser = getCurrentUser();

// Charger l'inscription avec les détails de la séance
try {
    $stmt = $pdo->prepare("
        SELECT r.*, s.title AS session_title, s.course_id, c.title AS course_title
        FROM live_eval_registrations r
        JOIN live_eval_sessions s ON r.session_id = s.id
        JOIN courses c ON s.course_id = c.id
        WHERE r.id = :id
    ");
    $stmt->execute(['id' => $regId]);
    $registration = $stmt->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    exit('Erreur lors du chargement des données de la séance.');
}

if (!$registration) {
    http_response_code(404);
    exit('Inscription ou séance d\'évaluation introuvable.');
}

// Vérification de propriété : seul l'étudiant concerné, l'enseignant ou le promoteur peut voir le rapport
if ($currentUser['role'] === 'student' && (int)$registration['student_id'] !== $currentUser['id'] && $registration['email'] !== $currentUser['email']) {
    http_response_code(403);
    exit('Vous n\'êtes pas autorisé à accéder aux résultats de cette personne.');
}

// Charger les réponses soumises et les questions associées
try {
    $stmt = $pdo->prepare("
        SELECT q.id AS question_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option, q.explanation, q.question_type, a.selected_option
        FROM live_eval_answers a
        JOIN live_eval_questions q ON a.question_id = q.id
        WHERE a.registration_id = :reg_id
        ORDER BY q.id ASC
    ");
    $stmt->execute(['reg_id' => $regId]);
    $answers = $stmt->fetchAll();
} catch (PDOException $e) {
    http_response_code(500);
    exit('Erreur lors du chargement des réponses.');
}

$totalQuestions = count($answers);
$correctCount = 0;
foreach ($answers as $ans) {
    $isCorrect = false;
    if (($ans['question_type'] ?? 'mcq') === 'written') {
        $normalizedSelected = str_replace([',', ' '], ['.', ''], strtolower(trim($ans['selected_option'])));
        $normalizedCorrect = str_replace([',', ' '], ['.', ''], strtolower(trim($ans['correct_option'])));
        $isCorrect = ($normalizedSelected === $normalizedCorrect);
    } else {
        $isCorrect = ($ans['selected_option'] === $ans['correct_option']);
    }
    if ($isCorrect) {
        $correctCount++;
    }
}
$scorePercent = $totalQuestions > 0 ? ($correctCount / $totalQuestions) * 100 : 0.0;

/**
 * Filtre et échappe les caractères spéciaux pour LaTeX tout en préservant le code LaTeX mathématique.
 */
function escapeLatex(string $text): string
{
    // Remplacer les sauts de ligne HTML
    $text = preg_replace('/<br\s*\/?>/i', "\n\n", $text);
    
    // Normaliser la syntaxe inline et block LaTeX existante
    $text = str_replace(['\(', '\)'], '$', $text);
    $text = str_replace(['\[', '\]'], '$$', $text);

    // Découper le texte pour isoler les équations mathématiques ($...$ et $$...$$)
    $parts = preg_split('/(\$\$.*?\$\$|\$.*?\$)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $text;
    }

    foreach ($parts as $idx => &$part) {
        // Si c'est un bloc mathématique, ne pas toucher aux caractères spéciaux de LaTeX
        if (str_starts_with($part, '$')) {
            continue;
        }

        // Sinon, échapper les caractères spéciaux dans la partie texte standard
        $part = strtr($part, [
            '\\' => '\\textbackslash{}',
            '%'  => '\\%',
            '_'  => '\\_',
            '&'  => '\\&',
            '#'  => '\\#',
            '{'  => '\\{',
            '}'  => '\\}',
            '~'  => '\\textasciitilde{}',
            '^'  => '\\textasciicircum{}',
            '<'  => '\\textless{}',
            '>'  => '\\textgreater{}'
        ]);
    }
    return implode('', $parts);
}

// Construction du document LaTeX
$tex = "";
$tex .= "\\documentclass[10pt,twocolumn,a4paper]{article}\n";
$tex .= "\\usepackage[utf8]{inputenc}\n";
$tex .= "\\usepackage[T1]{fontenc}\n";
$tex .= "\\usepackage[french]{babel}\n";
$tex .= "\\usepackage{amsmath,amssymb}\n";
$tex .= "\\usepackage{tcolorbox}\n";
$tex .= "\\usepackage{color}\n";
$tex .= "\\usepackage{pifont}\n";
$tex .= "\\usepackage{enumitem}\n";
$tex .= "\\usepackage[margin=1.2cm]{geometry}\n";
$tex .= "\n";
$tex .= "\\tcbset{\n";
$tex .= "    boxrule=0.6pt,\n";
$tex .= "    arc=2pt,\n";
$tex .= "    boxsep=3pt,\n";
$tex .= "    top=6pt,\n";
$tex .= "    bottom=6pt,\n";
$tex .= "    left=6pt,\n";
$tex .= "    right=6pt,\n";
$tex .= "}\n";
$tex .= "\n";
$tex .= "\\newtcolorbox{questionbox}[2]{\n";
$tex .= "    colback=gray!4,\n";
$tex .= "    colframe=gray!40,\n";
$tex .= "    title={Question #1 ~~\\hfill~~ #2},\n";
$tex .= "    coltitle=black,\n";
$tex .= "    fonttitle=\\bfseries\\sffamily\\small,\n";
$tex .= "    fontupper=\\sffamily\\small\n";
$tex .= "}\n";
$tex .= "\n";
$tex .= "\\newtcolorbox{correctbox}{\n";
$tex .= "    colback=green!3,\n";
$tex .= "    colframe=green!50!black,\n";
$tex .= "    fontupper=\\sffamily\\small\n";
$tex .= "}\n";
$tex .= "\n";
$tex .= "\\newtcolorbox{incorrectbox}{\n";
$tex .= "    colback=red!3,\n";
$tex .= "    colframe=red!50!black,\n";
$tex .= "    fontupper=\\sffamily\\small\n";
$tex .= "}\n";
$tex .= "\n";
$tex .= "\\begin{document}\n";
$tex .= "\n";

// Échapper les métadonnées pour LaTeX
$escSessionTitle = escapeLatex($registration['session_title']);
$escCourseTitle = escapeLatex($registration['course_title']);
$escStudentName = escapeLatex($registration['name']);
$escDate = escapeLatex(date('d/m/Y H:i'));
$escScorePercent = escapeLatex((string)round($scorePercent, 1));

$tex .= "\\twocolumn[\n";
$tex .= "  \\begin{center}\n";
$tex .= "    \\sffamily\n";
$tex .= "    {\\large\\bfseries STUDYVIBE ~--~ RAPPORT D'\\'{E}VALUATION \\par}\n";
$tex .= "    \\vspace{0.4em}\n";
$tex .= "    {\\LARGE\\bfseries Session : {$escSessionTitle} \\par}\n";
$tex .= "    \\vspace{0.4em}\n";
$tex .= "    {\\large Cours : {$escCourseTitle} ~\\hfill~ Candidat : {$escStudentName} \\par}\n";
$tex .= "    \\vspace{0.2em}\n";
$tex .= "    {\\small Date : {$escDate} ~\\hfill~ Score : {$correctCount} / {$totalQuestions} ({$escScorePercent} \\%) \\par}\n";
$tex .= "    \\vspace{0.8em}\n";
$tex .= "    \\hrule height 1pt\n";
$tex .= "    \\vspace{1.2em}\n";
$tex .= "  \\end{center}\n";
$tex .= "]\n";
$tex .= "\n";

foreach ($answers as $index => $qa) {
    $num = $index + 1;
    
    $isCorrect = false;
    if (($qa['question_type'] ?? 'mcq') === 'written') {
        $normalizedSelected = str_replace([',', ' '], ['.', ''], strtolower(trim($qa['selected_option'])));
        $normalizedCorrect = str_replace([',', ' '], ['.', ''], strtolower(trim($qa['correct_option'])));
        $isCorrect = ($normalizedSelected === $normalizedCorrect);
    } else {
        $isCorrect = ($qa['selected_option'] === $qa['correct_option']);
    }
    
    $statusText = $isCorrect ? "Correct (+1)" : "Incorrect (0)";
    
    $escQuestionText = escapeLatex($qa['question_text']);
    $escExplanation = escapeLatex($qa['explanation']);
    
    $tex .= "\\begin{questionbox}{{$num}}{{$statusText}}\n";
    $tex .= "{$escQuestionText}\n";
    
    if (($qa['question_type'] ?? 'mcq') === 'written') {
        // No itemize for written questions
        $tex .= "\\end{questionbox}\n";
        
        $boxType = $isCorrect ? "correctbox" : "incorrectbox";
        $selectedDisplay = !empty($qa['selected_option']) ? escapeLatex($qa['selected_option']) : 'Aucune';
        $correctDisplay = escapeLatex($qa['correct_option']);
        
        $tex .= "\\begin{{$boxType}}\n";
        $tex .= "\\textbf{Votre r\\'{e}ponse :} {$selectedDisplay} ~\\hfill~ \\textbf{R\\'{e}ponse correcte :} {$correctDisplay} \\\\\n";
        $tex .= "\\par\\smallskip\n";
        $tex .= "\\textbf{Justification :} {$escExplanation}\n";
        $tex .= "\\end{{$boxType}}\n";
        $tex .= "\\vspace{0.8em}\n\n";
    } else {
        $tex .= "\\begin{itemize}[leftmargin=*,noitemsep,topsep=4pt]\n";
        
        foreach (['A', 'B', 'C', 'D'] as $opt) {
            $optVal = $qa['option_' . strtolower($opt)];
            $escOptVal = escapeLatex($optVal);
            $isSelected = ($qa['selected_option'] === $opt);
            $isCorrectOpt = ($qa['correct_option'] === $opt);
            
            if ($isCorrectOpt) {
                if ($isSelected) {
                    $tex .= "    \\item[\\color{green!60!black}\\ding{51}] \\textbf{Option {$opt} (Votre r\\'{e}ponse / Correcte) :} {$escOptVal}\n";
                } else {
                    $tex .= "    \\item[\\color{green!60!black}\\ding{51}] \\textbf{Option {$opt} (R\\'{e}ponse correcte) :} {$escOptVal}\n";
                }
            } elseif ($isSelected) {
                $tex .= "    \\item[\\color{red!60!black}\\ding{55}] \\textbf{Option {$opt} (Votre r\\'{e}ponse) :} {$escOptVal}\n";
            } else {
                $tex .= "    \\item[$\\square$] \\textbf{Option {$opt} :} {$escOptVal}\n";
            }
        }
        
        $tex .= "\\end{itemize}\n";
        $tex .= "\\end{questionbox}\n";
        
        // Boîte de correction juste en-dessous
        $boxType = $isCorrect ? "correctbox" : "incorrectbox";
        $selectedDisplay = !empty($qa['selected_option']) ? $qa['selected_option'] : 'Aucune';
        
        $tex .= "\\begin{{$boxType}}\n";
        $tex .= "\\textbf{Votre r\\'{e}ponse :} Option {$selectedDisplay} ~\\hfill~ \\textbf{R\\'{e}ponse correcte :} Option {$qa['correct_option']} \\\\\n";
        $tex .= "\\par\\smallskip\n";
        $tex .= "\\textbf{Justification :} {$escExplanation}\n";
        $tex .= "\\end{{$boxType}}\n";
        $tex .= "\\vspace{0.8em}\n";
    }
}

$tex .= "\\end{document}\n";

// Enregistrement temporaire et compilation LaTeX
$tempDir = __DIR__ . '/../uploads/temp_pdf';
if (!is_dir($tempDir)) {
    @mkdir($tempDir, 0755, true);
}

$uniqId = uniqid('eval_');
$texFile = "{$tempDir}/{$uniqId}.tex";
$pdfFile = "{$tempDir}/{$uniqId}.pdf";
$logFile = "{$tempDir}/{$uniqId}.log";
$auxFile = "{$tempDir}/{$uniqId}.aux";

if (file_put_contents($texFile, $tex) === false) {
    http_response_code(500);
    exit('Erreur d\'écriture du fichier LaTeX temporaire.');
}

// Exécuter pdflatex avec HOME configuré sur le répertoire temporaire pour éviter les blocages d'écriture de cache de polices par www-data
$cmd = "HOME=" . escapeshellarg($tempDir) . " /usr/bin/pdflatex -interaction=nonstopmode -output-directory=" . escapeshellarg($tempDir) . " " . escapeshellarg($texFile) . " 2>&1";
exec($cmd, $execOutput, $returnVar);

if (file_exists($pdfFile) && $returnVar === 0) {
    // Audit log
    auditLog('export_evaluation_pdf_latex', "Registration #{$regId}");
    
    // Nettoyer les fichiers auxiliaires
    @unlink($texFile);
    @unlink($auxFile);
    @unlink($logFile);
    
    // Streamer le PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Rapport_Correction_' . preg_replace('/[^a-zA-Z0-9]/', '_', $registration['session_title']) . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    readfile($pdfFile);
    
    // Nettoyer le PDF après envoi
    @unlink($pdfFile);
    exit;
} else {
    // Si la compilation échoue, renvoyer le log d'erreur LaTeX
    $logContent = file_exists($logFile) ? file_get_contents($logFile) : '';
    $shellOutput = implode("\n", $execOutput);
    
    // Nettoyer tout
    @unlink($texFile);
    @unlink($auxFile);
    @unlink($logFile);
    if (file_exists($pdfFile)) {
        @unlink($pdfFile);
    }
    
    http_response_code(500);
    echo "<h1>Erreur de compilation du document PDF via LaTeX</h1>";
    echo "<p>Veuillez contacter votre administrateur.</p>";
    if (!empty($logContent)) {
        echo "<h3>Journal de compilation LaTeX :</h3>";
        echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ccc; overflow:auto; max-height:300px;'>" . htmlspecialchars($logContent) . "</pre>";
    }
    echo "<h3>Sortie du terminal (Shell Output) :</h3>";
    echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ccc; overflow:auto; max-height:300px;'>" . htmlspecialchars($shellOutput) . "</pre>";
    echo "<p>Commande exécutée : <code>" . htmlspecialchars($cmd) . "</code></p>";
    exit;
}
