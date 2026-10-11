<?php
declare(strict_types=1);

require_once __DIR__ . '/LatexCompiler.php';

/**
 * The look of every LaTeX document StudyVibe produces (question papers, answer keys, grade reports, correction reports).
 *
 * The design is the classic one: the article class, black on white, the traditional LaTeX typeface (Computer Modern, in its
 * Latin Modern form so it stays crisp on screen and in print and has every French accent), booktabs tables, a thin rule under
 * the running header, "page x / y" at the bottom. The only colour is in the logo. Nothing here depends on the data: each export
 * builds its body with the helpers below and hands it to document().
 *
 *   $body  = ExportTheme::titleBlock('Corrigé', 'Contrôle de synthèse', 'Programmation Web', [['Date', '20/10/2026']]);
 *   $body .= '...';
 *   $tex   = ExportTheme::document($body, ['lang' => 'fr', 'title' => 'Corrigé', 'header_left' => '...', 'header_right' => '...']);
 *   $pdf   = LatexCompiler::compile($tex);
 */
final class ExportTheme
{
    /** Words the documents use, in the two languages of the app. */
    private const LABELS = [
        'fr' => [
            'course' => 'Cours', 'session' => 'Évaluation', 'date' => 'Date', 'questions' => 'Questions', 'duration' => 'Durée',
            'points' => 'Barème', 'one_point' => '1 point par question', 'minutes' => 'min', 'name' => 'Nom et prénom', 'class' => 'Classe / Matricule',
            'signature' => 'Signature', 'mark' => 'Note', 'question' => 'Question', 'point' => 'point', 'answer' => 'Réponse', 'your_answer' => 'Votre réponse',
            'correct_answer' => 'Réponse correcte', 'justification' => 'Justification', 'none' => 'Aucune', 'page' => 'Page',
            'generated' => 'Document généré le', 'confidential' => 'Confidentiel, usage enseignant',
        ],
        'en' => [
            'course' => 'Course', 'session' => 'Assessment', 'date' => 'Date', 'questions' => 'Questions', 'duration' => 'Duration',
            'points' => 'Marking', 'one_point' => '1 point per question', 'minutes' => 'min', 'name' => 'Full name', 'class' => 'Class / Student number',
            'signature' => 'Signature', 'mark' => 'Mark', 'question' => 'Question', 'point' => 'point', 'answer' => 'Answer', 'your_answer' => 'Your answer',
            'correct_answer' => 'Correct answer', 'justification' => 'Explanation', 'none' => 'None', 'page' => 'Page',
            'generated' => 'Generated on', 'confidential' => 'Confidential, for teachers only',
        ],
    ];

    public static function label(string $key, string $lang = 'fr'): string
    {
        return self::LABELS[$lang === 'en' ? 'en' : 'fr'][$key] ?? $key;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The document
    // ---------------------------------------------------------------------------------------------------------------

    /** The traditional preamble: Computer Modern (Latin Modern), black on white, booktabs: used by the exam paper and the answer key. */
    private static function classicPreamble(): string
    {
        return <<<'TEX'
\documentclass[@@size@@,a4paper]{article}
\usepackage[utf8]{inputenc}
\usepackage[T1]{fontenc}
\usepackage{lmodern}% Latin Modern: the traditional Computer Modern typeface, as vector fonts with all accents
\usepackage[@@babel@@]{babel}
\usepackage[@@geometry@@]{geometry}
\usepackage{microtype}
\usepackage{amsmath,amssymb}
\usepackage{booktabs,longtable,array,tabularx}
\usepackage{enumitem}
\usepackage{needspace}
\usepackage{fancyhdr}
\usepackage{lastpage}
\usepackage{graphicx}
\usepackage{xcolor}
\usepackage[hidelinks,pdftitle={@@title@@},pdfauthor={StudyVibe},pdfcreator={StudyVibe}]{hyperref}
@@brand@@
\setlength{\parindent}{0pt}
\setlength{\parskip}{0.55em}
\renewcommand{\arraystretch}{1.18}
\setlength{\emergencystretch}{2em}

\fancypagestyle{svpage}{%
  \fancyhf{}%
  \renewcommand{\headrulewidth}{0.4pt}%
  \lhead{\small\textsc{@@hl@@}}%
  \rhead{\small @@hr@@}%
  \lfoot{\scriptsize @@fl@@}%
  \cfoot{\small \thepage\,/\,\pageref{LastPage}}%
}
\fancypagestyle{svfirst}{%
  \fancyhf{}%
  \renewcommand{\headrulewidth}{0pt}%
  \lfoot{\scriptsize @@fl@@}%
  \cfoot{\small \thepage\,/\,\pageref{LastPage}}%
}
\pagestyle{svpage}

% Section titles in the plain article style, only a little smaller
\makeatletter
\renewcommand{\section}{\@startsection{section}{1}{0pt}{2.2ex plus .4ex}{1ex}{\normalfont\large\bfseries}}
\makeatother

\begin{document}
\thispagestyle{svfirst}

TEX;
    }

    /**
     * @param array{lang?:string,title?:string,header_left?:string,header_right?:string,footer_left?:string,font?:string} $o
     *        header_*, footer_left and title are plain text (they are escaped here)
     */
    public static function document(string $body, array $o = []): string
    {
        $lang = ($o['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
        $babel = $lang === 'en' ? 'english' : 'french';
        $title = self::esc((string)($o['title'] ?? 'StudyVibe'));
        $hl = self::esc((string)($o['header_left'] ?? ''));
        $hr = self::esc((string)($o['header_right'] ?? ''));
        $fl = self::esc((string)($o['footer_left'] ?? 'StudyVibe'));
        $geometry = (!empty($o['landscape']) ? 'a4paper,landscape' : 'a4paper') . ',top=2.3cm,bottom=2.2cm,left=2cm,right=2cm,headheight=14pt,footskip=1.1cm';
        $size = in_array($o['font'] ?? '11pt', ['10pt', '11pt', '12pt'], true) ? ($o['font'] ?? '11pt') : '11pt';

        $classic = !empty($o['classic']);
        if ($classic) {
            $geometry = (!empty($o['landscape']) ? 'a4paper,landscape' : 'a4paper') . ',top=2.4cm,bottom=2.2cm,left=2.2cm,right=2.2cm,headheight=14pt,footskip=1.1cm';
        }
        $pre = $classic ? self::classicPreamble() : <<<'TEX'
\documentclass[@@size@@,a4paper]{article}
\usepackage[utf8]{inputenc}
\usepackage[T1]{fontenc}
\usepackage[sc]{mathpazo}% Palatino for the text and the maths: a book face that prints well and has every French accent
\usepackage[scaled=.92]{helvet}% Helvetica for titles, badges and labels
\usepackage[@@babel@@]{babel}
\usepackage[@@geometry@@]{geometry}
\usepackage{microtype}
\usepackage{amsmath,amssymb}
\usepackage{booktabs,longtable,array,tabularx,colortbl}
\usepackage{enumitem}
\usepackage{needspace}
\usepackage{multicol}
\usepackage{fancyhdr}
\usepackage{lastpage}
\usepackage{graphicx}
\usepackage{xcolor}
\usepackage{qrcode}
\usepackage[hidelinks,pdftitle={@@title@@},pdfauthor={StudyVibe},pdfcreator={StudyVibe}]{hyperref}
@@brand@@
\definecolor{svpine}{HTML}{24402F}
\definecolor{svgreenbg}{HTML}{E2EFE4}
\definecolor{svredbg}{HTML}{F9E3DB}
\definecolor{svgray}{HTML}{6B665C}
\definecolor{svline}{HTML}{DDD5C3}
\definecolor{svcard}{HTML}{F6F2E9}
\linespread{1.04}
\setlength{\parindent}{0pt}
\setlength{\parskip}{0.5em}
\renewcommand{\arraystretch}{1.18}
\setlength{\emergencystretch}{2em}
\setlength{\columnsep}{9mm}
\setlength{\columnseprule}{0.4pt}
\renewcommand{\columnseprulecolor}{\color{svline}}
\setlist{nosep}

% Number badge, a labelled option row (shaded when it is the right or the chosen one), and a calm note with a bar on the left
\newcommand{\svbadge}[1]{\colorbox{svclay}{\hbox to 1.5em{\hss\color{white}\sffamily\bfseries\small #1\hss}}}
\newcommand{\svopt}[4]{\par\vspace{1pt}\noindent\colorbox{#1}{\parbox{\dimexpr\linewidth-2\fboxsep\relax}{\hangindent=2.3em\hangafter=1\raggedright #2\hspace{0.4em}{\sffamily\bfseries\color{svclay}#3}\hspace{0.5em}#4\strut}}}
\newcommand{\svnote}[1]{\par\noindent\begin{tabular}{@{}!{\color{svclay}\vrule width 2.4pt}>{\columncolor{svcard}\raggedright\arraybackslash}p{\dimexpr\linewidth-2.4pt-2\tabcolsep\relax}}\small #1\end{tabular}\par}
\newcommand{\svfield}[1]{{\sffamily\scriptsize\bfseries\color{svgray}#1}}

\fancypagestyle{svpage}{%
  \fancyhf{}%
  \renewcommand{\headrule}{\ifdim\headrulewidth>0pt{\color{svclay}\hrule width\headwidth height\headrulewidth}\fi}%
  \renewcommand{\footrule}{\ifdim\footrulewidth>0pt{\color{svline}\hrule width\headwidth height\footrulewidth}\fi}%
  \renewcommand{\headrulewidth}{0.5pt}\renewcommand{\footrulewidth}{0.4pt}%
  \lhead{\sffamily\scriptsize\bfseries\color{svgray}@@hl@@}%
  \rhead{\sffamily\scriptsize\color{svgray}@@hr@@}%
  \lfoot{\sffamily\scriptsize\color{svgray}@@fl@@}%
  \rfoot{\sffamily\scriptsize\color{svgray}\thepage\,/\,\pageref{LastPage}}%
}
\fancypagestyle{svfirst}{%
  \fancyhf{}%
  \renewcommand{\headrulewidth}{0pt}%
  \renewcommand{\footrule}{\ifdim\footrulewidth>0pt{\color{svline}\hrule width\headwidth height\footrulewidth}\fi}%
  \renewcommand{\footrulewidth}{0.4pt}%
  \lfoot{\sffamily\scriptsize\color{svgray}@@fl@@}%
  \rfoot{\sffamily\scriptsize\color{svgray}\thepage\,/\,\pageref{LastPage}}%
}
\pagestyle{svpage}

% Section titles: sans, clay, a little smaller than the article default
\makeatletter
\renewcommand{\section}{\@startsection{section}{1}{0pt}{2.4ex plus .4ex}{1ex}{\sffamily\large\bfseries\color{svclay}}}
\makeatother

\begin{document}
\thispagestyle{svfirst}

TEX;
        $pre = strtr($pre, [
            '@@size@@' => $size, '@@babel@@' => $babel, '@@title@@' => $title, '@@hl@@' => $hl, '@@hr@@' => $hr, '@@fl@@' => $fl,
            '@@brand@@' => Brand::latexPreamble(), '@@geometry@@' => $geometry,
        ]);
        return $pre . $body . "\n\\end{document}\n";
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Pieces
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * Compact banner: logo on the left, the kind of document in small capitals, the title and the subtitle on the right, a clay rule,
     * then the facts as a strip of labelled cells.
     *
     * @param array<int,array{0:string,1:string}> $info [label, value] pairs; values are plain text
     */
    public static function titleBlock(string $kind, string $title, string $subtitle = '', array $info = []): string
    {
        $out = "\\noindent\\begin{tabularx}{\\textwidth}{@{}l@{\\hspace{1em}}>{\\raggedleft\\arraybackslash}X@{}}\n";
        $out .= "\\raisebox{-0.2ex}{\\svlogo[3.3cm]} & {\\textsc{\\color{svclay}" . self::esc($kind) . "}\\par\\vspace{0.3em}\n";
        $out .= '{\\Large\\sffamily\\bfseries\\raggedleft ' . self::esc($title) . "\\par}\n";
        if ($subtitle !== '') {
            $out .= '\\vspace{0.2em}{\\small\\color{svgray} ' . self::esc($subtitle) . "\\par}\n";
        }
        $out .= "}\n\\end{tabularx}\\par\\vspace{0.5em}\n{\\color{svclay}\\rule{\\textwidth}{1.3pt}}\\par\\vspace{-0.3em}\n";
        if ($info) {
            $out .= self::infoTable($info);
        }
        return $out;
    }

    /**
     * The facts as a shaded strip of cells, a small grey label over a bold value. At most four to a row.
     *
     * @param array<int,array{0:string,1:string}> $rows
     */
    public static function infoTable(array $rows): string
    {
        $n = count($rows);
        $perRow = $n <= 4 ? max(1, $n) : (int)ceil($n / (int)ceil($n / 4));
        $out = "\\par\\vspace{0.3em}\\noindent{\\setlength{\\fboxsep}{7pt}\\colorbox{svcard}{\\begin{minipage}{\\dimexpr\\textwidth-14pt\\relax}\\renewcommand{\\arraystretch}{1.3}\n"
            . '\\begin{tabularx}{\\linewidth}{@{}*{' . $perRow . "}{>{\\raggedright\\arraybackslash}X}@{}}\n";
        foreach (array_chunk($rows, $perRow) as $chunk) {
            $cells = [];
            foreach ($chunk as [$label, $value]) {
                $cells[] = '\\svfield{' . self::esc($label) . '}\\newline{\\small\\sffamily\\bfseries ' . self::esc($value) . '}';
            }
            while (count($cells) < $perRow) {
                $cells[] = '';
            }
            $out .= implode(" &\n", $cells) . " \\\\\n";
        }
        return $out . "\\end{tabularx}\\end{minipage}}}\\par\n";
    }

    /**
     * Defines \svqr, a box holding the QR code of $url. It goes through an environment (not a macro argument) so that the French
     * punctuation shorthands of babel cannot touch the address. Put it before the first use of \usebox{\svqr}.
     */
    public static function qrBox(string $url, string $height = '2.6cm'): string
    {
        $url = preg_replace('/[^A-Za-z0-9:\/\.\-_~?=]/', '', $url) ?? '';
        return "\\newsavebox{\\svqr}\n\\begin{lrbox}{\\svqr}\\shorthandoff{:;!?}\\qrcode[height={$height}]{" . $url . "}\\end{lrbox}\n";
    }

    /**
     * A row of shaded figure cards: a large number over a small label.
     *
     * @param array<int,array{0:string,1:string}> $cards [value, label], values and labels are plain text
     */
    public static function statCards(array $cards): string
    {
        $n = max(1, count($cards));
        $w = round((1 - 0.02 * ($n - 1)) / $n, 4);
        $out = "\\par\\vspace{0.4em}\\noindent\n";
        foreach ($cards as $i => [$value, $label]) {
            $out .= '{\\setlength{\\fboxsep}{8pt}\\colorbox{svcard}{\\begin{minipage}[t]{\\dimexpr' . $w . '\\textwidth-16pt\\relax}'
                . '{\\fontsize{22}{24}\\selectfont\\sffamily\\bfseries\\color{svclay}' . self::esc($value) . '}\\par\\vspace{1pt}'
                . '{\\scriptsize\\sffamily\\color{svgray}' . self::esc($label) . "}\\end{minipage}}}"
                . ($i < $n - 1 ? "\\hfill\n" : "\n");
        }
        return $out . "\\par\n";
    }

    /** A small progress bar of $percent (0-100), $mm wide, in pine over a grey track. */
    public static function miniBar(float $percent, float $mm = 22.0, string $color = 'svpine'): string
    {
        $on = round($mm * max(0.0, min(100.0, $percent)) / 100, 2);
        return '\\textcolor{' . $color . '}{' . ($on > 0 ? "\\rule{{$on}mm}{1.7mm}" : '') . '}\\textcolor{svline}{\\rule{' . round($mm - $on, 2) . 'mm}{1.7mm}}';
    }

    /** Lines to fill in: name, student number, mark and signature, in a light frame. */
    public static function candidateBox(string $lang, int $outOf): string
    {
        $L = fn(string $k) => self::label($k, $lang);
        $f = fn(string $k) => '\\rule{0pt}{4.4ex}\\svfield{' . self::esc($L($k)) . '}';
        return "\\par\\vspace{0.4em}\\noindent{\\arrayrulecolor{svline}\\setlength{\\tabcolsep}{8pt}\\renewcommand{\\arraystretch}{1}\n"
            . "\\begin{tabularx}{\\textwidth}{|>{\\raggedright\\arraybackslash}p{0.5\\textwidth}|>{\\raggedright\\arraybackslash}p{0.2\\textwidth}|>{\\raggedright\\arraybackslash}X|}\\hline\n"
            . $f('name') . ' & ' . $f('mark') . ' \\hfill {\\small/\\,' . $outOf . '} & ' . $f('signature') . " \\\\[2.8ex]\\hline\n"
            . $f('class') . ' & \\multicolumn{2}{l|}{' . $f('date') . "} \\\\[2.8ex]\\hline\n"
            . "\\end{tabularx}}\\par\n";
    }

    /** The traditional title block: centred logo, kind in small capitals, title, subtitle, a rule and a two-column list of facts. */
    public static function titleBlockClassic(string $kind, string $title, string $subtitle = '', array $info = []): string
    {
        $out = "\\begin{center}\n\\svlogomono[3.2cm]\\par\\vspace{1.4em}\n";
        $out .= '{\\normalsize\\scshape ' . self::esc($kind) . "\\par}\\vspace{0.5em}\n";
        $out .= '{\\LARGE\\bfseries ' . self::esc($title) . "\\par}\n";
        if ($subtitle !== '') {
            $out .= '\\vspace{0.4em}{\\large ' . self::esc($subtitle) . "\\par}\n";
        }
        $out .= "\\end{center}\n\\vspace{-0.4em}\\noindent\\rule{\\textwidth}{0.8pt}\\par\n";
        if ($info) {
            $out .= "\\begin{center}\n\\begin{tabular}{@{}r@{\\hspace{1.2em}}l@{}}\n";
            foreach ($info as [$label, $value]) {
                $out .= '\\textit{' . self::esc($label) . '} & ' . self::esc($value) . " \\\\\n";
            }
            $out .= "\\end{tabular}\n\\end{center}\n";
        }
        return $out;
    }

    /** Lines to write on, plain black rules (traditional papers). */
    public static function answerLinesClassic(int $n = 3): string
    {
        $out = "\\par\\vspace{0.2em}\n";
        for ($i = 0; $i < $n; $i++) {
            $out .= "\\noindent\\rule[-0.6ex]{\\textwidth}{0.25pt}\\\\[2.6ex]\n";
        }
        return $out;
    }

    /** The question list in $cols columns (1 or 2), with a thin rule between the columns. */
    public static function columns(string $content, int $cols = 1): string
    {
        return $cols >= 2 ? "\\begin{multicols}{2}\n{$content}\\end{multicols}\n" : $content;
    }

    /**
     * A long table that repeats its header on every page. Cells are given already escaped (so they can hold \textbf{...}).
     *
     * @param array<int,array{0:string,1:string}> $columns [header (plain), column spec such as 'l', 'r', 'p{4cm}']
     * @param array<int,array<int,string>> $rows
     */
    public static function table(array $columns, array $rows): string
    {
        $spec = implode('', array_map(fn($c) => $c[1], $columns));
        $heads = implode(' & ', array_map(fn($c) => '\\textbf{' . self::esc($c[0]) . '}', $columns));
        $out = "{\\small\\setlength{\\LTleft}{0pt}\\setlength{\\LTright}{0pt}\n\\begin{longtable}{@{}{$spec}@{}}\n\\toprule\n{$heads} \\\\\n\\midrule\n\\endfirsthead\n";
        $out .= "\\toprule\n{$heads} \\\\\n\\midrule\n\\endhead\n\\bottomrule\n\\endfoot\n\\bottomrule\n\\endlastfoot\n";
        foreach ($rows as $r) {
            $out .= implode(' & ', $r) . " \\\\\n";
        }
        return $out . "\\end{longtable}\n}\n";
    }

    /** A line of dots or rule to write on, as many as asked. */
    public static function answerLines(int $n = 3): string
    {
        $out = "\\par\\vspace{0.2em}\n";
        for ($i = 0; $i < $n; $i++) {
            $out .= "\\noindent{\\color{svline}\\rule[-0.6ex]{\\linewidth}{0.5pt}}\\\\[3ex]\n";
        }
        return $out;
    }

    /** "n" lines of a horizontal bar chart: one row per label, the bar as wide as value/max. */
    public static function barChart(array $labels, array $values, float $maxWidthMm = 90.0): string
    {
        $max = max(1, ...array_map('intval', $values));
        $out = "{\\small\\begin{tabular}{@{}r@{\\hspace{0.8em}}l@{\\hspace{0.6em}}l@{}}\n";
        foreach ($labels as $i => $label) {
            $w = round($values[$i] / $max * $maxWidthMm, 1);
            $out .= self::esc((string)$label) . ' & ' . ($w > 0 ? "\\textcolor{svclay}{\\rule{{$w}mm}{2.4mm}}" : '\\rule{0pt}{2.4mm}') . ' & ' . (int)$values[$i] . " \\\\\n";
        }
        return $out . "\\end{tabular}}\n";
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Text
    // ---------------------------------------------------------------------------------------------------------------

    /** Plain text made safe for LaTeX (no math, no line breaks kept). For titles, names, e-mail addresses. */
    public static function esc(string $text): string
    {
        // French typesetting puts a space before ":" ; not wanted inside times, addresses and identifiers ("15:26", "https://...")
        return preg_replace('/(?<=\S):(?=\S)/u', '\\string:', LatexCompiler::escape($text)) ?? '';
    }

    /**
     * Teacher-written text (questions, options, explanations) made safe for LaTeX while keeping math: $...$ and $$...$$ stay
     * as written, \( \) and \[ \] are accepted, and Unicode symbols (², ≤, π, →...) become math. <br> becomes a new paragraph.
     */
    public static function tex(?string $text, bool $inline = false): string
    {
        $text = (string)$text;
        $text = preg_replace('/<br\s*\/?>/i', "\n\n", $text) ?? $text;
        $text = str_replace(['\\(', '\\)'], '$', $text);
        $text = str_replace(['\\[', '\\]'], '$$', $text);

        static $unicodeMath = [
            '∂' => '\\partial', '₀' => '_0', '₁' => '_1', '₂' => '_2', '₃' => '_3', '₄' => '_4', '₅' => '_5', '₆' => '_6', '₇' => '_7',
            '₈' => '_8', '₉' => '_9', '₊' => '_+', '₋' => '_-', '₌' => '_=', '⁽' => '^{(', '⁾' => ')}', '⁺' => '^{+}', '⁻' => '^{-}',
            '⁼' => '^{=}', '⁰' => '^0', '¹' => '^1', '²' => '^2', '³' => '^3', '⁴' => '^4', '⁵' => '^5', '⁶' => '^6', '⁷' => '^7',
            '⁸' => '^8', '⁹' => '^9', 'σ' => '\\sigma', 'θ' => '\\theta', 'Δ' => '\\Delta', 'π' => '\\pi', 'α' => '\\alpha', 'β' => '\\beta',
            'γ' => '\\gamma', 'λ' => '\\lambda', 'μ' => '\\mu', 'ρ' => '\\rho', 'φ' => '\\phi', 'ω' => '\\omega', 'Ω' => '\\Omega',
            '≈' => '\\approx', '≠' => '\\neq', '≤' => '\\leq', '≥' => '\\geq', '±' => '\\pm', '×' => '\\times', '÷' => '\\div', '·' => '\\cdot',
            '→' => '\\rightarrow', '⇒' => '\\Rightarrow', '⇔' => '\\Leftrightarrow', '∞' => '\\infty', '∈' => '\\in', '∉' => '\\notin',
            '⊂' => '\\subset', '⊃' => '\\supset', '⊆' => '\\subseteq', '⊇' => '\\supseteq', '∑' => '\\sum', '∫' => '\\int', '√' => '\\sqrt{}',
        ];

        $parts = preg_split('/(\$\$.*?\$\$|\$.*?\$)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return self::esc($text);
        }
        foreach ($parts as $i => $part) {
            if (str_starts_with($part, '$') && strlen($part) > 1) {
                $math = strtr($part, $unicodeMath);
                if (self::mathIsSafe($math)) {
                    $parts[$i] = $math;
                    continue;
                }
                // a formula LaTeX would choke on (unbalanced braces, open environment...) or that reads files: shown as plain text, the PDF still builds
            }
            $part = strtr($part, [
                '\\' => '\\textbackslash{}', '%' => '\\%', '_' => '\\_', '&' => '\\&', '#' => '\\#', '{' => '\\{', '}' => '\\}',
                '~' => '\\textasciitilde{}', '^' => '\\textasciicircum{}', '<' => '\\textless{}', '>' => '\\textgreater{}',
                '$' => '\\$', '—' => '---', '–' => '--',
            ]);
            foreach ($unicodeMath as $uni => $lat) {
                if (str_contains($part, $uni)) {
                    $part = str_replace($uni, '$' . $lat . '$', $part);
                }
            }
            $parts[$i] = $part;
        }
        $out = LatexCompiler::sanitize(implode('', $parts));
        return $inline ? trim((string)preg_replace('/\s*\n\s*/', ' ', $out)) : $out;
    }

    /** True when a $...$ / $$...$$ formula is balanced and uses nothing but typesetting commands, so it cannot abort the build or read files. */
    private static function mathIsSafe(string $math): bool
    {
        $inner = preg_replace('/^\$\$?|\$\$?$/', '', $math) ?? $math;
        if (preg_match('/\n\s*\n/', $inner)) {
            return false;
        }
        if (preg_match('/\\\\(?:input|include|includegraphics|write|openin|openout|read|readline|immediate|catcode|csname|expandafter|def|edef|gdef|xdef|let|newcommand|renewcommand|providecommand|usepackage|documentclass|verb|url|href|special|jobname|scantokens|detokenize|string|number|romannumeral|newwrite|newread|loop|obeyspaces|endinput|batchmode|errorstopmode|stop|end\{document\}|begin\{document\})(?![A-Za-z])/', $inner)) {
            return false;
        }
        $depth = 0;
        $flat = preg_replace('/\\\\[\\\\{}]/', '', $inner) ?? $inner;   // escaped backslash and braces do not count
        foreach (str_split($flat) as $ch) {
            if ($ch === '{') { $depth++; } elseif ($ch === '}' && --$depth < 0) { return false; }
        }
        if ($depth !== 0) {
            return false;
        }
        return preg_match_all('/\\\\begin\{/', $inner) === preg_match_all('/\\\\end\{/', $inner)
            && preg_match_all('/\\\\left(?![A-Za-z])/', $inner) === preg_match_all('/\\\\right(?![A-Za-z])/', $inner);
    }

    /** A file name made of safe characters, for Content-Disposition and the .tex zip. */
    public static function slug(string $title, string $fallback = 'document'): string
    {
        $s = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '_', (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title)), '_'));
        return $s !== '' ? $s : $fallback;
    }
}
