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
        $geometry = (!empty($o['landscape']) ? 'a4paper,landscape' : 'a4paper') . ',top=2.4cm,bottom=2.2cm,left=2.2cm,right=2.2cm,headheight=14pt,footskip=1.1cm';
        $size = in_array($o['font'] ?? '11pt', ['10pt', '11pt', '12pt'], true) ? ($o['font'] ?? '11pt') : '11pt';

        $pre = <<<'TEX'
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
     * Logo, kind of document in small capitals, title, subtitle, then a rule and a two-column table of facts.
     *
     * @param array<int,array{0:string,1:string}> $info [label, value] pairs; values are plain text
     */
    public static function titleBlock(string $kind, string $title, string $subtitle = '', array $info = []): string
    {
        $out = "\\begin{center}\n\\svlogomono[3.2cm]\\par\\vspace{1.4em}\n";
        $out .= '{\\normalsize\\scshape ' . self::esc($kind) . "\\par}\\vspace{0.5em}\n";
        $out .= '{\\LARGE\\bfseries ' . self::esc($title) . "\\par}\n";
        if ($subtitle !== '') {
            $out .= '\\vspace{0.4em}{\\large ' . self::esc($subtitle) . "\\par}\n";
        }
        $out .= "\\end{center}\n\\vspace{-0.4em}\\noindent\\rule{\\textwidth}{0.8pt}\\par\n";
        if ($info) {
            $out .= self::infoTable($info);
        }
        return $out;
    }

    /** @param array<int,array{0:string,1:string}> $rows */
    public static function infoTable(array $rows): string
    {
        $out = "\\begin{center}\n\\begin{tabular}{@{}r@{\\hspace{1.2em}}l@{}}\n";
        foreach ($rows as [$label, $value]) {
            $out .= '\\textit{' . self::esc($label) . '} & ' . self::esc($value) . " \\\\\n";
        }
        return $out . "\\end{tabular}\n\\end{center}\n";
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
        $out = "{\\small\n\\begin{longtable}{@{}{$spec}@{}}\n\\toprule\n{$heads} \\\\\n\\midrule\n\\endfirsthead\n";
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
            $out .= "\\noindent\\rule[-0.6ex]{\\textwidth}{0.25pt}\\\\[2.6ex]\n";
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
            $out .= self::esc((string)$label) . ' & ' . ($w > 0 ? "\\rule{{$w}mm}{2.4mm}" : '\\rule{0pt}{2.4mm}') . ' & ' . (int)$values[$i] . " \\\\\n";
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
                $parts[$i] = strtr($part, $unicodeMath);
                continue;
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

    /** A file name made of safe characters, for Content-Disposition and the .tex zip. */
    public static function slug(string $title, string $fallback = 'document'): string
    {
        $s = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '_', (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title)), '_'));
        return $s !== '' ? $s : $fallback;
    }
}
