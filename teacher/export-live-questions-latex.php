<?php
declare(strict_types=1);

/**
 * Génération d'une épreuve écrite sous format PDF (mise en page double colonne) via LaTeX.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/LatexCompiler.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    header('Location: /index.php');
    exit;
}

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$teacherId = (int)$_SESSION['user_id'];
$mode      = isset($_GET['mode']) && $_GET['mode'] === 'correction' ? 'correction' : 'subject';

if ($sessionId <= 0) {
    http_response_code(400);
    exit('Identifiant de séance non valide.');
}

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

    // Dictionnaire des caractères mathématiques Unicode vers LaTeX
    $unicodeMath = [
        '∂' => '\partial',
        '₀' => '_0',
        '₁' => '_1',
        '₂' => '_2',
        '₃' => '_3',
        '₄' => '_4',
        '₅' => '_5',
        '₆' => '_6',
        '₇' => '_7',
        '₈' => '_8',
        '₉' => '_9',
        '₊' => '_+',
        '₋' => '_-',
        '₌' => '_=',
        '⁽' => '^{(',
        '⁾' => ')}',
        '⁺' => '^{+}',
        '⁻' => '^{-}',
        '⁼' => '^{=}',
        '⁰' => '^0',
        '¹' => '^1',
        '²' => '^2',
        '³' => '^3',
        '⁴' => '^4',
        '⁵' => '^5',
        '⁶' => '^6',
        '⁷' => '^7',
        '⁸' => '^8',
        '⁹' => '^9',
        'σ' => '\sigma',
        'θ' => '\theta',
        'Δ' => '\Delta',
        'π' => '\pi',
        'α' => '\alpha',
        'β' => '\beta',
        'γ' => '\gamma',
        'λ' => '\lambda',
        'μ' => '\mu',
        'ρ' => '\rho',
        'φ' => '\phi',
        'ω' => '\omega',
        'Ω' => '\Omega',
        '≈' => '\approx',
        '≠' => '\neq',
        '≤' => '\leq',
        '≥' => '\geq',
        '±' => '\pm',
        '×' => '\times',
        '÷' => '\div',
        '·' => '\cdot',
        '→' => '\rightarrow',
        '⇒' => '\Rightarrow',
        '⇔' => '\Leftrightarrow',
        '∞' => '\infty',
        '∈' => '\in',
        '∉' => '\notin',
        '⊂' => '\subset',
        '⊃' => '\supset',
        '⊆' => '\subseteq',
        '⊇' => '\supplement',
    ];

    // Découper le texte pour isoler les équations mathématiques ($...$ et $$...$$)
    $parts = preg_split('/(\$\$.*?\$\$|\$.*?\$)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $text;
    }

    foreach ($parts as $idx => &$part) {
        // Si c'est un bloc mathématique
        if (str_starts_with($part, '$')) {
            $part = strtr($part, $unicodeMath);
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
            '>'  => '\\textgreater{}',
            '—'  => '--',
            '’'  => "'",
        ]);

        // Traduire les symboles mathématiques Unicode trouvés en dehors du bloc math
        foreach ($unicodeMath as $uni => $lat) {
            if (str_contains($part, $uni)) {
                $part = str_replace($uni, '$' . $lat . '$', $part);
            }
        }
    }
    return implode('', $parts);
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
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute(['sid' => $sessionId]);
    $questions = $stmt->fetchAll();
    $totalQuestions = count($questions);

    // Escape metadata
    $courseTitle = escapeLatex($session['course_title']);
    $sessionTitle = escapeLatex($session['title']);
    $sessionCode = escapeLatex($session['session_code']);
    $sessionDate = date('d/m/Y', strtotime($session['start_time']));

    // Formater les questions pour le document LaTeX
    $latexQuestions = [];
    $idx = 1;
    foreach ($questions as $q) {
        $qText = escapeLatex($q['question_text']);
        $qType = $q['question_type'] ?? 'mcq';
        $correctOpt = $q['correct_option'];
        
        $questionContent = "";
        
        if ($qType === 'mcq') {
            $optA = escapeLatex($q['option_a']);
            $optB = escapeLatex($q['option_b']);
            $optC = escapeLatex($q['option_c']);
            $optD = escapeLatex($q['option_d']);
            
            $questionContent .= "{$qText}\n";
            $questionContent .= "\\begin{itemize}\n";
            
            if ($mode === 'correction') {
                foreach (['A' => $optA, 'B' => $optB, 'C' => $optC, 'D' => $optD] as $letter => $val) {
                    if ($letter === $correctOpt) {
                        $questionContent .= "    \\item[{\\ding{51}}] \\textbf{Option {$letter} (Correcte) :} {$val}\n";
                    } else {
                        $questionContent .= "    \\item[{\\ding{113}}] \\textbf{Option {$letter} :} {$val}\n";
                    }
                }
            } else {
                $questionContent .= "    \\item[{\\ding{113}}] \\textbf{Option A :} {$optA}\n";
                $questionContent .= "    \\item[{\\ding{113}}] \\textbf{Option B :} {$optB}\n";
                $questionContent .= "    \\item[{\\ding{113}}] \\textbf{Option C :} {$optC}\n";
                $questionContent .= "    \\item[{\\ding{113}}] \\textbf{Option D :} {$optD}\n";
            }
            $questionContent .= "\\end{itemize}\n";
        } else {
            // Written/calculation question
            $questionContent .= "{$qText}\n";
        }
        
        $texMarkup = "\\begin{questionbox}{{$idx}}\n";
        $texMarkup .= $questionContent;
        $texMarkup .= "\\end{questionbox}\n";
        
        // Answer box beneath
        if ($mode === 'correction') {
            $escExplanation = !empty($q['explanation']) ? escapeLatex($q['explanation']) : 'Aucune';
            $escCorrectOpt = escapeLatex($correctOpt);
            
            $texMarkup .= "\\begin{correctanswerbox}\n";
            if ($qType === 'mcq') {
                $texMarkup .= "\\textbf{R\\'{e}ponse correcte : } Option {$escCorrectOpt} \\\\\n";
            } else {
                $texMarkup .= "\\textbf{R\\'{e}ponse correcte : } {$escCorrectOpt} \\\\\n";
            }
            $texMarkup .= "\\par\\smallskip\n";
            $texMarkup .= "\\textbf{Justification : } {$escExplanation}\n";
            $texMarkup .= "\\end{correctanswerbox}\n";
        } else {
            $texMarkup .= "\\begin{answerbox}\n";
            if ($qType === 'mcq') {
                $texMarkup .= "\\textbf{Votre r\\'{e}ponse : } Option \\dots\\dots ~\\hfill~ $\\square$ A ~ $\\square$ B ~ $\\square$ C ~ $\\square$ D\n";
            } else {
                $texMarkup .= "\\textbf{Votre r\\'{e}ponse : } \\hrulefill\n";
                $texMarkup .= "\\vspace{1.5em}\n";
            }
            $texMarkup .= "\\end{answerbox}\n";
        }
        $texMarkup .= "\\vspace{0.8em}\n";
        
        $latexQuestions[] = $texMarkup;
        $idx++;
    }

    $questionsString = implode("\n", $latexQuestions);

    // Source LaTeX
    $latexTemplate = <<<LATEX
\\documentclass[10pt,twocolumn,a4paper]{article}
\\usepackage[utf8]{inputenc}
\\usepackage[T1]{fontenc}
\\usepackage[margin=1.2cm]{geometry}
\\usepackage{amsmath,amssymb}
\\usepackage{tcolorbox}
\\usepackage{pifont}
\\usepackage{fancyhdr}
\\usepackage{helvet}
\\renewcommand{\\familydefault}{\\sfdefault}

\\tcbset{
    boxrule=0.6pt,
    arc=2pt,
    boxsep=3pt,
    top=5pt,
    bottom=5pt,
    left=6pt,
    right=6pt,
}

\\newtcolorbox{questionbox}[1]{
    colback=gray!4,
    colframe=gray!40,
    title={\\textbf{Question #1}},
    coltitle=black,
    fonttitle=\\bfseries\\sffamily\\small,
    fontupper=\\sffamily\\small
}

\\newtcolorbox{answerbox}{
    colback=white,
    colframe=gray!40,
    fontupper=\\sffamily\\small
}

\\newtcolorbox{correctanswerbox}{
    colback=green!3,
    colframe=green!50!black,
    fontupper=\\sffamily\\small
}

\\pagestyle{fancy}
\\fancyhf{}
\\rhead{\\scriptsize Code S\\'{e}ance : {$sessionCode}}
\\lhead{\\scriptsize {$courseTitle}}
\\rfoot{\\scriptsize Page \\thepage}
\\lfoot{\\scriptsize \\'{E}preuve imprim\\'{e}e ~--~ StudyVibe}

\\begin{document}

LATEX;

    if ($mode === 'correction') {
        $latexTemplate .= <<<LATEX
\\twocolumn[
\\noindent
\\setlength{\\fboxrule}{0.8pt}
\\setlength{\\fboxsep}{8pt}
\\fbox{%
\\begin{minipage}{\\dimexpr\\textwidth-2\\fboxsep-2\\fboxrule\\relax}
\\begin{center}
    {\\large \\textbf{STUDYVIBE LMS ~--~ CLEF DE CORRECTION}} \\\\
    \\vspace{0.3em}
    \\textbf{Cours :} {$courseTitle} \\\\
    \\textbf{Evaluation :} {$sessionTitle} \\\\
    \\textbf{Date :} {$sessionDate}
\\end{center}
\\end{minipage}%
}
\\vspace{1.5em}
]

LATEX;
    } else {
        $latexTemplate .= <<<LATEX
\\twocolumn[
\\noindent
\\setlength{\\fboxrule}{0.8pt}
\\setlength{\\fboxsep}{8pt}
\\fbox{%
\\begin{minipage}{\\dimexpr\\textwidth-2\\fboxsep-2\\fboxrule\\relax}
\\begin{center}
    {\\large \\textbf{STUDYVIBE LMS ~--~ \\'{E}PREUVE \\'{E}CRITE}} \\\\
    \\vspace{0.3em}
    \\textbf{Cours :} {$courseTitle} \\\\
    \\textbf{Evaluation :} {$sessionTitle} \\\\
    \\textbf{Date :} {$sessionDate}
\\end{center}
\\vspace{0.5em}
\\noindent
\\begin{tabular}{p{4.2in}l}
\\textbf{Nom \\& Pr\\'{e}nom :} \\hrulefill & \\textbf{Note :} \\\\[0.5em]
\\textbf{Classe / Matricule :} \\hrulefill & \\textbf{/ {$totalQuestions}} \\\\
\\textbf{Signature :} \\hrulefill & 
\\end{tabular}
\\end{minipage}%
}
\\vspace{1.5em}
]

LATEX;
    }

    $latexTemplate .= <<<LATEX
{$questionsString}

\\end{document}
LATEX;

    $pdfData = LatexCompiler::compile($latexTemplate);

    if (!$pdfData) {
        http_response_code(500);
        exit('Erreur lors de la compilation de l\'épreuve PDF via LaTeX.');
    }

    $prefix = $mode === 'correction' ? 'corrigé_exam_' : 'sujet_exam_';
    $slug = preg_replace('/[^a-z0-9_-]+/i', '_', (string)$session['title']) ?: 'live_eval';
    $filename = $prefix . mb_strtolower($slug) . '_' . date('Y-m-d') . '.pdf';

    auditLog('export_live_questions_latex', "Session #{$sessionId} (Mode: {$mode})");
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfData));
    echo $pdfData;
    exit;

} catch (Exception $e) {
    http_response_code(500);
    exit('Erreur lors du traitement LaTeX : ' . $e->getMessage());
}
