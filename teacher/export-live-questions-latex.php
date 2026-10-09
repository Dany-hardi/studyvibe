<?php
declare(strict_types=1);

/**
 * Génération d'une épreuve écrite sous format PDF (mise en page double colonne) via LaTeX.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/LatexCompiler.php';
require_once __DIR__ . '/../lib/Brand.php';

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
function escapeLatex(?string $text): string
{
    $text = $text ?? '';
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
        '⊇' => '\supseteq',
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
    return LatexCompiler::sanitize(implode('', $parts));
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
                $texMarkup .= "\\textbf{Votre r\\'{e}ponse : } Option \\dotfill ~\\ding{113}~A ~\\ding{113}~B ~\\ding{113}~C ~\\ding{113}~D\n";
            } else {
                $texMarkup .= "\\textbf{Votre r\\'{e}ponse : } \\hrulefill \\par\\vspace{0.8em}\n";
            }
            $texMarkup .= "\\end{answerbox}\n";
        }
        
        $latexQuestions[] = $texMarkup;
        $idx++;
    }

    $questionsString = implode("\n", $latexQuestions);

    $brandPre = Brand::latexPreamble();

    // Source LaTeX
    $latexTemplate = <<<LATEX
\\documentclass[10pt,twocolumn,a4paper]{article}
\\usepackage[utf8]{inputenc}
\\usepackage[T1]{fontenc}
\\usepackage[margin=1.2cm]{geometry}
\\usepackage{amsmath,amssymb}
\\usepackage{tcolorbox}
\\tcbuselibrary{skins}
\\usepackage{pifont}
\\usepackage{fancyhdr}
\\usepackage{helvet}
\\renewcommand{\\familydefault}{\\sfdefault}
{$brandPre}

\\newtcolorbox{questionbox}[1]{
    blanker,
    left=8pt,
    top=3pt,
    bottom=3pt,
    borderline west={1.5pt}{0pt}{black},
    title={\\textbf{Question #1}},
    coltitle=black,
    fonttitle=\\bfseries\\sffamily\\small,
    fontupper=\\sffamily\\small,
    before skip=6pt,
    after skip=6pt
}

\\newenvironment{answerbox}{
    \\par\\smallskip\\noindent\\sffamily\\small
}{
    \\par
}

\\newtcolorbox{correctanswerbox}{
    blanker,
    left=8pt,
    top=3pt,
    bottom=3pt,
    borderline west={0.8pt}{0pt}{black},
    fontupper=\\sffamily\\small\\itshape,
    before skip=4pt,
    after skip=4pt
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
    \\svlogomono[3.6cm]\\\\[0.4em]
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
    \\svlogomono[3.6cm]\\\\[0.4em]
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

    $texSlug = ($mode === 'correction' ? 'corrige_' : 'sujet_') . ($session['title'] ?? 'live_eval') . '_' . date('Y-m-d');
    if (($_GET['format'] ?? '') === 'tex') {
        auditLog('export_live_questions_latex_source', "Session #{$sessionId} (Mode: {$mode})");
        LatexCompiler::sendSource($latexTemplate, $texSlug);
    }

    $pdfData = LatexCompiler::compile($latexTemplate);

    if (!$pdfData) {
        // pdflatex missing or failed on this server: hand over the source so the export still works
        LatexCompiler::sendSource($latexTemplate, $texSlug);
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
