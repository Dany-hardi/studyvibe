<?php
declare(strict_types=1);

require_once __DIR__ . '/ExportTheme.php';

/**
 * The documents a student takes away, as LaTeX: the certificate, the transcript and the report of a failed certification attempt.
 * They used to be made in the browser (html2pdf: a photograph of the page); they are now real PDFs with selectable text, the same
 * typefaces and colours as every other export, built here from plain data and compiled by LatexCompiler.
 *
 * Every builder takes the words already translated ($t, keys of lib/student_i18n.php) so this file knows no language.
 * All values are plain text: they are escaped here.
 */
final class StudentDocs
{
    /** The diploma-style certificate, A4 landscape, with the validation QR code drawn by LaTeX (no external service). */
    public static function certificate(array $cert, array $t, string $verifyUrl, string $verifyShort): array
    {
        $e = fn(string $s) => ExportTheme::esc($s);
        $name = $e((string)$cert['student_name']);
        $course = $e((string)($cert['course_title'] ?? $t['unknown_course']));
        $url = self::urlForQr($verifyUrl);

        $tex = <<<'TEX'
\documentclass[11pt,a4paper]{article}
\usepackage[utf8]{inputenc}
\usepackage[T1]{fontenc}
\usepackage[sc]{mathpazo}
\usepackage[scaled=.92]{helvet}
\usepackage[@@babel@@]{babel}
\usepackage[a4paper,landscape,margin=0pt]{geometry}
\usepackage{microtype,xcolor,graphicx,tikz,qrcode}
\usepackage[hidelinks,pdftitle={@@pdftitle@@},pdfauthor={StudyVibe}]{hyperref}
@@brand@@
\definecolor{svgray}{HTML}{6B665C}
\definecolor{svpaper}{HTML}{FBF8F2}
\pagestyle{empty}
\setlength{\parindent}{0pt}
\newsavebox{\svqr}
\begin{document}
% the address goes through an environment, not a macro argument, so the French punctuation shorthands cannot touch it
\begin{lrbox}{\svqr}\shorthandoff{:;!?}\qrcode[height=2.1cm]{@@qr@@}\end{lrbox}
\begin{tikzpicture}[remember picture,overlay]
  \fill[svpaper] (current page.south west) rectangle (current page.north east);
  \draw[svclay,line width=1.6pt] ([shift={(11mm,11mm)}]current page.south west) rectangle ([shift={(-11mm,-11mm)}]current page.north east);
  \draw[svochre,line width=0.5pt] ([shift={(14.5mm,14.5mm)}]current page.south west) rectangle ([shift={(-14.5mm,-14.5mm)}]current page.north east);
  \foreach \c/\sx/\sy in {south west/1/1,south east/-1/1,north west/1/-1,north east/-1/-1}
    \fill[svclay] ([shift={(\sx*11mm,\sy*11mm)}]current page.\c) circle (2.2mm);
\end{tikzpicture}%
\vspace*{26mm}
\begin{center}
  \svlogo[4.4cm]\par\vspace{2mm}
  {\sffamily\scriptsize\bfseries\color{svgray}\MakeUppercase{@@platform@@}\par}
  \vspace{9mm}
  {\sffamily\small\bfseries\color{svclay}\textls[220]{\MakeUppercase{@@kicker@@}}\par}
  \vspace{7mm}
  {\color{svgray}\itshape @@awarded@@\par}
  \vspace{3mm}
  {\fontsize{40}{44}\selectfont\itshape @@name@@\par}
  \vspace{3mm}
  {\color{svochre}\rule{9cm}{0.8pt}\par}
  \vspace{4mm}
  {\color{svgray}\itshape @@for@@\par}
  \vspace{2.5mm}
  {\sffamily\fontsize{21}{25}\selectfont\bfseries\parbox{20cm}{\centering @@course@@}\par}
  \vspace{5mm}
  {\small\color{svgray}\parbox{17cm}{\centering @@body@@}\par}
\end{center}
\vfill
\begin{center}
\begin{tabular}{@{}p{7.2cm}@{}c@{}p{7.2cm}@{}}
  \raggedright\hspace{26mm}\parbox[b]{6.5cm}{\raggedright
    {\sffamily\scriptsize\bfseries\color{svgray}\MakeUppercase{@@issued@@}}\par\vspace{1mm}
    {\large\bfseries @@year@@}\par{\small\color{svgray}@@signed@@}} &
  \begin{tikzpicture}
    \draw[svclay,line width=1.2pt] (0,0) circle (1.25cm);
    \draw[svclay,line width=0.4pt] (0,0) circle (1.1cm);
    \draw[svochre,line width=0.4pt,dash pattern=on 1pt off 2pt] (0,0) circle (1.18cm);
    \node[text=svclay,align=center,font=\sffamily\bfseries\footnotesize] at (0,0.12) {STUDY\\[-1pt]VIBE};
    \node[text=svgray,font=\sffamily\tiny] at (0,-0.5) {@@sealword@@};
  \end{tikzpicture} &
  \raggedleft\parbox[b]{7.2cm}{\raggedleft
    \begin{tabular}{@{}r@{\hspace{3mm}}l@{}}
      \parbox[b]{4.3cm}{\raggedleft{\sffamily\scriptsize\bfseries\color{svgray}\MakeUppercase{@@codelabel@@}}\par\vspace{1mm}
        {\ttfamily\small @@code@@}\par\vspace{1mm}{\scriptsize\color{svgray}@@verifyat@@}\par{\color{svgray}\mbox{\fontsize{6.5pt}{8pt}\selectfont\sffamily @@verifyshort@@}}} &
      \usebox{\svqr}
    \end{tabular}\hspace{26mm}}
\end{tabular}
\end{center}
\vspace*{20mm}
\end{document}
TEX;
        $lang = $t['_lang'] === 'en' ? 'english' : 'french';
        return ['tex' => strtr($tex, [
            '@@babel@@' => $lang, '@@brand@@' => Brand::latexPreamble(),
            '@@pdftitle@@' => $e($t['doc_cert_word'] . ' - ' . (string)($cert['course_title'] ?? 'StudyVibe')),
            '@@platform@@' => $e($t['doc_platform']), '@@kicker@@' => $e($t['cert_kicker']), '@@awarded@@' => $e($t['doc_cert_awarded']),
            '@@name@@' => $name, '@@for@@' => $e($t['doc_cert_for']), '@@course@@' => $course, '@@body@@' => $e($t['doc_cert_body']),
            '@@issued@@' => $e($t['doc_issued']), '@@year@@' => $e($t['issued_year']), '@@signed@@' => $e($t['doc_signed']),
            '@@sealword@@' => $e(mb_strtoupper($t['doc_cert_word'])), '@@codelabel@@' => $e($t['doc_validation_code']),
            '@@code@@' => preg_replace('/[^A-Za-z0-9_-]/', '', (string)$cert['certificate_code']),
            '@@verifyat@@' => $e($t['doc_verify_at']), '@@verifyshort@@' => $e($verifyShort), '@@qr@@' => $url,
        ]), 'assets' => []];
    }

    /**
     * The transcript: courses (progress, best score, attempts), completed lessons with their scores, failed certification attempts.
     *
     * @param array{name:string,matricule?:?string} $user
     */
    public static function transcript(array $user, array $courses, array $lessons, array $failed, array $t): array
    {
        $e = fn(string $s) => ExportTheme::esc($s);
        $info = [];
        if (!empty($user['matricule'])) {
            $info[] = [$t['matricule'], (string)$user['matricule']];
        }
        $info[] = [$t['doc_generated_label'], $t['generated_at']];
        $body = ExportTheme::titleBlock($t['doc_transcript'], (string)$user['name'], '', $info);

        $sub = fn(string $main, string $small) => $e($main) . ($small !== '' ? '\\newline{\\scriptsize\\color{svgray}' . $e($small) . '}' : '');
        $body .= '\\section*{' . $e($t['by_course']) . "}\n";
        if (!$courses) {
            $body .= '\\textit{' . $e($t['doc_no_enrolment']) . "}\n";
        } else {
            $rows = [];
            foreach ($courses as $c) {
                $rows[] = [$sub((string)$c['course_title'], (string)$c['module_title']), (int)$c['progress_percent'] . '\\,\\%',
                    ($c['best_text'] ?? '') !== '' ? $e((string)$c['best_text']) . '\\,\\%' : '---', (string)(int)$c['attempts']];
            }
            $body .= ExportTheme::table([[$t['th_course'], 'p{9.6cm}'], [$t['th_progress'], 'r'], [$t['th_best'], 'r'], [$t['th_attempts'], 'r']], $rows);
        }

        if ($lessons) {
            $body .= '\\section*{' . $e($t['doc_completed_lessons']) . "}\n";
            $rows = [];
            foreach ($lessons as $l) {
                $rows[] = [$sub((string)$l['lesson_title'], (string)$l['course_title']), $e((string)$l['date_text']), (int)$l['score'] . '\\,\\%'];
            }
            $body .= ExportTheme::table([[$t['th_lesson'], 'p{12.2cm}'], [$t['th_date'], 'r'], [$t['th_score'], 'r']], $rows);
        }

        if ($failed) {
            $body .= '\\section*{' . $e($t['doc_failed_certs']) . "}\n";
            $rows = [];
            foreach ($failed as $a) {
                $rows[] = [$e((string)$a['course_title']), $e((string)$a['date_text']), '\\textcolor{svclay}{' . $e((string)$a['score_text']) . '\\,\\%}',
                    '\\textcolor{svclay}{$\\times$ ' . $e($t['doc_not_validated']) . '}'];
            }
            $body .= ExportTheme::table([[$t['th_course'], 'p{8.8cm}'], [$t['th_date'], 'r'], [$t['th_score'], 'r'], [$t['doc_th_result'], 'r']], $rows);
            $body .= '\\vspace{0.6em}\\svnote{' . $e($t['doc_failed_note']) . "}\n";
        }
        $body .= '\\vfill\\begin{center}{\\scriptsize\\color{svgray}' . $e($t['doc_footer']) . "}\\end{center}\n";

        return ['tex' => ExportTheme::document($body, [
            'lang' => $t['_lang'], 'title' => $t['doc_transcript'] . ': ' . $user['name'],
            'header_left' => (string)$user['name'], 'header_right' => $t['doc_transcript'], 'footer_left' => 'StudyVibe · ' . $t['doc_transcript'],
        ]), 'assets' => []];
    }

    /** The report of a certification attempt that was not passed: the score against the required one, and the notice. */
    public static function attemptReport(array $attempt, array $t, float $threshold, ?int $totalQ): array
    {
        $e = fn(string $s) => ExportTheme::esc($s);
        $score = (float)$attempt['score'];
        $info = [[$t['doc_learner'], (string)$attempt['student_name']], [$t['doc_attempt_date'], $t['date_text']]];
        $body = ExportTheme::titleBlock($t['doc_attempt_title'], (string)$attempt['course_title'], '', $info);

        $on = round(95.0 * min(100.0, max(0.0, $score)) / 100, 1);
        $mark = round(95.0 * $threshold / 100, 1);
        $body .= "\\par\\vspace{0.6em}\\noindent{\\setlength{\\fboxsep}{10pt}\\colorbox{svcard}{\\begin{minipage}{\\dimexpr\\textwidth-20pt\\relax}\n"
            . '\\begin{minipage}[c]{4.2cm}{\\fontsize{34}{36}\\selectfont\\sffamily\\bfseries\\color{svclay}' . $e($t['score_text']) . "\\,\\%}\\end{minipage}\\hfill\n"
            . '\\begin{minipage}[c]{\\dimexpr\\linewidth-4.4cm\\relax}\\colorbox{svredbg}{\\scriptsize\\sffamily\\bfseries\\color{svclay}$\\times$~' . $e($t['not_passed']) . "}\\par\\vspace{0.7em}\n"
            . '\\textcolor{svclay}{' . ($on > 0 ? "\\rule{{$on}mm}{2.6mm}" : '') . '}\\textcolor{svline}{\\rule{' . round(95.0 - $on, 1) . "mm}{2.6mm}}\\par\\vspace{0.4em}\n"
            . '{\\scriptsize\\sffamily\\color{svgray}' . $e($t['doc_required']) . ' : \\textbf{' . (int)$threshold . "\\,\\%}}\\end{minipage}\n"
            . "\\end{minipage}}}\\par\\vspace{1em}\n";

        $sum = [[$t['doc_result'], $t['not_passed']], [$t['doc_score'], $t['score_text'] . ' %'], [$t['doc_required'], (int)$threshold . ' %']];
        if ($totalQ) {
            $sum[] = [$t['doc_exam_questions'], (string)$totalQ];
        }
        $body .= '\\section*{' . $e($t['doc_attempt_summary']) . "}\n" . ExportTheme::infoTable($sum);
        $body .= '\\vspace{1em}\\svnote{' . $e($t['doc_attempt_notice']) . "}\n";
        $body .= '\\vfill\\begin{center}{\\scriptsize\\color{svgray}' . $e($t['doc_footer']) . "}\\end{center}\n";

        return ['tex' => ExportTheme::document($body, [
            'lang' => $t['_lang'], 'title' => $t['doc_attempt_title'] . ': ' . $attempt['course_title'],
            'header_left' => (string)$attempt['course_title'], 'header_right' => $t['doc_attempt_title'], 'footer_left' => 'StudyVibe · ' . $t['doc_attempt_title'],
        ]), 'assets' => []];
    }

    /** Every translated word the builders use, in the active language, from lib/student_i18n.php (which must be loaded). */
    public static function strings(array $extra = [], ?string $lang = null): array
    {
        $keys = ['doc_cert_word', 'doc_platform', 'cert_kicker', 'doc_cert_awarded', 'doc_cert_for', 'doc_cert_body', 'doc_issued', 'doc_signed',
            'doc_validation_code', 'doc_verify_at', 'matricule', 'doc_transcript', 'by_course', 'doc_no_enrolment', 'th_course', 'th_progress', 'th_best',
            'th_attempts', 'doc_completed_lessons', 'th_lesson', 'th_date', 'th_score', 'doc_failed_certs', 'doc_th_result', 'doc_not_validated',
            'doc_failed_note', 'doc_attempt_title', 'doc_learner', 'doc_attempt_date', 'not_passed', 'doc_required', 'doc_result', 'doc_score',
            'doc_exam_questions', 'doc_attempt_summary', 'doc_attempt_notice', 'unknown_course'];
        $lang = $lang ?? sdLang();
        $d = sdDict();
        $get = fn(string $k, array $r = []) => str_replace(array_map(fn($x) => ':' . $x, array_keys($r)), array_values($r), (string)($d[$lang][$k] ?? $d['fr'][$k] ?? $k));
        $t = ['_lang' => $lang];
        foreach ($keys as $k) {
            $t[$k] = $get($k);
        }
        $t['doc_footer'] = $get('doc_footer', ['date' => date('d/m/Y')]);
        $t['doc_generated_label'] = trim($get('doc_generated', ['date' => '']));
        return $extra + $t;
    }

    /** Compiles and sends the document as a PDF download; a plain message when LaTeX is not available. */
    public static function send(array $doc, string $filename, string $auditAction = ''): never
    {
        $pdf = LatexCompiler::compile($doc['tex'], $doc['assets']);
        if (!$pdf) {
            http_response_code(503);
            exit(sdLang() === 'en' ? 'The PDF could not be produced right now. Please try again later.' : 'Le PDF n’a pas pu être produit pour le moment. Réessayez plus tard.');
        }
        if ($auditAction !== '' && function_exists('auditLog')) {
            auditLog($auditAction, $filename);
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /** A URL made safe for \qrcode{}: only the characters of a normal web address (anything else is dropped). */
    private static function urlForQr(string $url): string
    {
        return preg_replace('/[^A-Za-z0-9:\/\.\-_~?=]/', '', $url) ?? '';
    }
}
