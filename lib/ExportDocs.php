<?php
declare(strict_types=1);

require_once __DIR__ . '/ExportTheme.php';
require_once __DIR__ . '/LiveScoring.php';
require_once __DIR__ . '/MediaStore.php';

/**
 * The documents themselves. Each builder takes plain data (no database, no request) and returns LaTeX source plus the picture
 * files it needs, so the endpoints stay short and the documents can be tested on their own.
 *
 * Every builder returns ['tex' => string, 'assets' => array<string,string>] (assets: file name in the build folder => path).
 * The look comes from ExportTheme: article class, Latin Modern (the traditional LaTeX typeface), booktabs tables.
 */
final class ExportDocs
{
    private const PASS_MARK = 50.0;

    // ---------------------------------------------------------------------------------------------------------------
    // Question paper and answer key
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * @param array $session   needs title, course_title, session_code, start_time, default_time_limit
     * @param array $questions rows of live_eval_questions
     * @param string $mode     'subject' (blank paper for students) or 'correction' (answer key)
     */
    public static function questionPaper(PDO $pdo, array $session, array $questions, string $mode, string $lang = 'fr', ?int $cols = null): array
    {
        $key = $mode === 'correction';
        $en = $lang === 'en';
        $L = fn(string $k) => ExportTheme::label($k, $lang);
        $n = count($questions);

        $seconds = 0;
        $written = false;
        foreach ($questions as $q) {
            $seconds += $q['time_limit'] !== null ? (int)$q['time_limit'] : (int)$session['default_time_limit'];
            $written = $written || (string)($q['question_type'] ?? 'mcq') !== 'mcq';
        }
        $minutes = (int)ceil($seconds / 60);
        // Two columns for multiple-choice papers (short items, a lot per page); written questions need the full width
        $cols = $cols === 2 || ($cols === null && !$written && $n > 3) ? 2 : 1;

        $info = [
            [$L('course'), (string)$session['course_title']],
            [$L('date'), date('d/m/Y', strtotime((string)$session['start_time']))],
            [$L('questions'), (string)$n],
        ];
        if ($minutes > 0) {
            $info[] = [$L('duration'), $minutes . ' ' . $L('minutes')];
        }
        $info[] = [$L('points'), $L('one_point')];

        $body = ExportTheme::titleBlock($key ? ($en ? 'Answer key' : 'Corrigé') : ($en ? 'Written paper' : 'Épreuve écrite'), (string)$session['title'], '', $info);

        if (!$key) {
            $body .= ExportTheme::candidateBox($lang, $n);
            $body .= "\\vspace{0.7em}\n\\svnote{\\itshape " . ($en
                ? 'Read each question carefully. For a multiple-choice question tick one box only; for a written question answer on the lines provided.'
                : 'Lisez attentivement chaque question. Pour une question à choix multiples, cochez une seule case ; pour une question rédigée, répondez sur les lignes prévues.')
                . "}\n\\vspace{0.9em}\n";
        } else {
            $body .= "\\vspace{0.6em}\n";
        }

        $assets = [];
        $items = '';
        foreach ($questions as $i => $q) {
            $num = $i + 1;
            $type = (string)($q['question_type'] ?? 'mcq');
            $item = '\\svbadge{' . $num . '}\\hfill{\\scriptsize\\sffamily\\color{svgray}1~' . ExportTheme::esc($L('point')) . "}\\par\\vspace{0.15em}\\nopagebreak\n";
            $item .= ExportTheme::tex((string)$q['question_text']) . "\n";
            $item .= self::picture($pdo, $q, $num, $assets);

            if ($type === 'mcq') {
                foreach (['A', 'B', 'C', 'D'] as $letter) {
                    $text = trim((string)($q['option_' . strtolower($letter)] ?? ''));
                    if ($text === '') {
                        continue;   // true/false questions only fill A and B
                    }
                    $isRight = $key && (string)$q['correct_option'] === $letter;
                    $t = ExportTheme::tex($text, true);
                    $item .= '\\svopt{' . ($isRight ? 'svgreenbg' : 'white') . '}{'
                        . ($isRight ? '\\textcolor{svpine}{$\\blacksquare$}' : '$\\square$') . "}{{$letter}}{" . ($isRight ? "\\textbf{{$t}}" : $t) . "}\n";
                }
            } elseif (!$key) {
                $item .= ExportTheme::answerLines(4);
            }

            if ($key) {
                $note = '';
                if ($type !== 'mcq') {
                    $note .= '\\textbf{' . ExportTheme::esc($L('correct_answer')) . '.} ' . ExportTheme::tex(LiveScoring::displayAnswer((string)$q['correct_option'], $lang), true);
                }
                if (!empty($q['explanation'])) {
                    $note .= ($note !== '' ? '\\par ' : '') . '\\textbf{' . ExportTheme::esc($L('justification')) . '.} ' . ExportTheme::tex((string)$q['explanation'], true);
                }
                if ($note !== '') {
                    $item .= "\\vspace{3pt}\n\\svnote{" . $note . "}\n";
                }
            }
            // a question stays whole in its column unless it is very long
            $items .= strlen($item) < 2600
                ? "\\begin{minipage}[t]{\\linewidth}\n{$item}\\end{minipage}\\par\\vspace{0.6em}\n"
                : "\\needspace{8\\baselineskip}\n{$item}\\par\\vspace{0.8em}\n";
        }
        $body .= ExportTheme::columns($items, $cols);

        $tex = ExportTheme::document($body, [
            'lang' => $lang,
            'title' => ($key ? ($en ? 'Answer key: ' : 'Corrigé : ') : ($en ? 'Paper: ' : 'Épreuve : ')) . $session['title'],
            'header_left' => (string)$session['course_title'],
            'header_right' => (string)$session['title'],
            'footer_left' => 'StudyVibe · ' . ($key ? ($en ? 'answer key' : 'corrigé') : ($en ? 'paper' : 'épreuve')),
        ]);
        return ['tex' => $tex, 'assets' => $assets];
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Grade report and order of merit
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * Full report: facts, statistics, distribution of marks, then the sheet of marks in alphabetical order.
     *
     * @param array $registrations rows with name, email, score (percent or null)
     */
    public static function gradesReport(array $session, array $registrations, int $totalQuestions, string $lang = 'fr'): array
    {
        $en = $lang === 'en';
        $st = self::statistics($registrations);
        $mode = (int)($session['is_async'] ?? 0) === 1 ? ($en ? 'Open assignment (asynchronous)' : 'Devoir libre (asynchrone)') : ($en ? 'Live evaluation (synchronous)' : 'Évaluation en direct (synchrone)');

        $body = ExportTheme::titleBlock($en ? 'Grade report' : 'Rapport de notes', (string)$session['title'], (string)$session['course_title'], [
            [ExportTheme::label('course', $lang), (string)$session['course_title']],
            [$en ? 'Mode' : 'Mode', $mode],
            [ExportTheme::label('questions', $lang), (string)$totalQuestions],
            [$en ? 'Report date' : 'Date du rapport', self::now($lang)],
        ]);

        $body .= '\\section*{' . ($en ? 'Summary' : 'Synthèse') . "}\n";
        $body .= "\\begin{center}\\small\\begin{tabular}{@{}rcccc@{}}\\toprule\n";
        $body .= ($en ? 'Registered & Submitted & Mean & Highest & Pass rate' : 'Inscrits & Copies rendues & Moyenne & Meilleure note & Taux de réussite') . " \\\\\\midrule\n";
        $body .= "{$st['total']} & {$st['submitted']} & " . self::pct($st['mean']) . ' & ' . self::pct($st['max']) . ' & ' . self::pct($st['pass_rate']) . " \\\\\\bottomrule\n\\end{tabular}\n";
        $body .= "\\par\\smallskip\\textit{" . ($en ? 'Pass mark: 50\\,\\%.' : 'Seuil de réussite : 50\\,\\%.') . "}\\end{center}\n";

        if ($st['submitted'] > 0) {
            $labels = [];
            for ($i = 0; $i < 10; $i++) {
                $labels[] = ($i * 10) . '–' . ($i === 9 ? '100' : ($i * 10 + 9));
            }
            $body .= '\\section*{' . ($en ? 'Distribution of marks (percent)' : 'Répartition des notes (en pourcentage)') . "}\n";
            $body .= "\\begin{center}\n" . ExportTheme::barChart($labels, $st['buckets'], 80.0) . "\\end{center}\n";
        }

        $body .= '\\section*{' . ($en ? 'Marks sheet' : 'Feuille de notes') . "}\n";
        $rows = [];
        foreach ($registrations as $r) {
            $rows[] = self::markRow($r, $totalQuestions, $lang, null);
        }
        if (!$rows) {
            $body .= '\\textit{' . ($en ? 'No participant registered.' : 'Aucun participant enregistré.') . "}\n";
        } else {
            $body .= ExportTheme::table(self::markColumns($lang, false), $rows);
        }

        return ['tex' => self::wrap($body, $session, $lang, $en ? 'Grade report' : 'Rapport de notes'), 'assets' => []];
    }

    /** Order of merit: the same data ranked, ties sharing a rank. */
    public static function rankingReport(array $session, array $registrations, int $totalQuestions, string $lang = 'fr'): array
    {
        $en = $lang === 'en';
        $body = ExportTheme::titleBlock($en ? 'Order of merit' : 'Classement', (string)$session['title'], (string)$session['course_title'], [
            [ExportTheme::label('course', $lang), (string)$session['course_title']],
            [$en ? 'Participants' : 'Participants', (string)count($registrations)],
            [$en ? 'Report date' : 'Date du rapport', self::now($lang)],
        ]);

        $rows = [];
        $rank = 0;
        $prev = null;
        foreach ($registrations as $i => $r) {
            if ($r['score'] !== null && ($prev === null || (float)$r['score'] < $prev)) {
                $rank = $i + 1;
            }
            $prev = $r['score'] !== null ? (float)$r['score'] : $prev;
            $rows[] = self::markRow($r, $totalQuestions, $lang, $r['score'] !== null ? (string)$rank : '--');
        }
        $body .= '\\section*{' . ($en ? 'Ranking' : 'Classement des candidats') . "}\n";
        $body .= $rows ? ExportTheme::table(self::markColumns($lang, true), $rows) : '\\textit{' . ($en ? 'No participant registered.' : 'Aucun participant enregistré.') . "}\n";
        return ['tex' => self::wrap($body, $session, $lang, $en ? 'Order of merit' : 'Classement'), 'assets' => []];
    }

    // ---------------------------------------------------------------------------------------------------------------
    // A student's correction report
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * @param array $registration name, session_title, course_title
     * @param array $answers      rows already arranged in the order the student saw them (LiveScoring::asSeen)
     */
    public static function studentReport(PDO $pdo, array $registration, array $answers, string $lang = 'fr', int $cols = 2): array
    {
        $en = $lang === 'en';
        $L = fn(string $k) => ExportTheme::label($k, $lang);
        $total = count($answers);
        $correct = 0;
        $blank = 0;
        foreach ($answers as $a) {
            $correct += LiveScoring::isCorrect((string)($a['question_type'] ?? 'mcq'), (string)$a['selected_option'], (string)$a['correct_option']) ? 1 : 0;
            $blank += trim((string)$a['selected_option']) === '' ? 1 : 0;
        }
        $wrong = $total - $correct - $blank;
        $percent = $total > 0 ? round($correct / $total * 100, 1) : 0.0;
        $pct = str_replace('.', ',', (string)$percent);
        $assets = [];

        $body = ExportTheme::titleBlock($en ? 'Correction report' : 'Rapport de correction', (string)$registration['session_title'], (string)$registration['course_title'], [
            [$en ? 'Candidate' : 'Candidat', (string)$registration['name']],
            [$L('course'), (string)$registration['course_title']],
            [$en ? 'Issued' : 'Édité le', self::now($lang)],
        ]);

        // Result card: the percentage large, a bar, and the three counts
        $barMm = 95.0;
        $on = round($barMm * $percent / 100, 1);
        $body .= "\\par\\vspace{0.5em}\\noindent{\\setlength{\\fboxsep}{9pt}\\colorbox{svcard}{\\begin{minipage}{\\dimexpr\\textwidth-18pt\\relax}\n"
            . "\\begin{minipage}[c]{3.4cm}{\\fontsize{30}{32}\\selectfont\\sffamily\\bfseries\\color{svclay}" . $pct . "\\,\\%}\\end{minipage}\\hfill\n"
            . "\\begin{minipage}[c]{\\dimexpr\\linewidth-3.6cm\\relax}{\\sffamily\\small\\bfseries " . $correct . ' / ' . $total . ' ' . ($en ? 'correct answers' : 'bonnes réponses') . "}\\par\\vspace{0.5em}\n"
            . '\\textcolor{svpine}{' . ($on > 0 ? "\\rule{{$on}mm}{2.6mm}" : '') . '}\\textcolor{svline}{\\rule{' . round($barMm - $on, 1) . "mm}{2.6mm}}\\par\\vspace{0.5em}\n"
            . '{\\scriptsize\\sffamily\\color{svpine}\\textbf{' . $correct . '} ' . ($en ? 'correct' : 'correctes')
            . '\\quad\\color{svclay}\\textbf{' . $wrong . '} ' . ($en ? 'incorrect' : 'incorrectes')
            . '\\quad\\color{svgray}\\textbf{' . $blank . '} ' . ($en ? 'unanswered' : 'sans réponse') . "}\\end{minipage}\n"
            . "\\end{minipage}}}\\par\\vspace{1em}\n";

        $items = '';
        foreach ($answers as $i => $qa) {
            $num = $i + 1;
            $type = (string)($qa['question_type'] ?? 'mcq');
            $given = trim((string)$qa['selected_option']);
            $ok = LiveScoring::isCorrect($type, (string)$qa['selected_option'], (string)$qa['correct_option']);
            if ($ok) {
                $chip = '\\colorbox{svgreenbg}{\\scriptsize\\sffamily\\bfseries\\color{svpine}$\\checkmark$~' . ($en ? 'Correct, +1' : 'Correct, +1') . '}';
            } elseif ($given === '') {
                $chip = '\\colorbox{svcard}{\\scriptsize\\sffamily\\bfseries\\color{svgray}' . ($en ? 'No answer, 0' : 'Sans réponse, 0') . '}';
            } else {
                $chip = '\\colorbox{svredbg}{\\scriptsize\\sffamily\\bfseries\\color{svclay}$\\times$~' . ($en ? 'Incorrect, 0' : 'Incorrect, 0') . '}';
            }
            $item = '\\svbadge{' . $num . '}\\hfill ' . $chip . "\\par\\vspace{0.15em}\\nopagebreak\n";
            $item .= ExportTheme::tex((string)$qa['question_text']) . "\n";
            $item .= self::picture($pdo, $qa, $num, $assets);

            if ($type === 'mcq') {
                foreach (['A', 'B', 'C', 'D'] as $letter) {
                    $text = trim((string)($qa['option_' . strtolower($letter)] ?? ''));
                    if ($text === '') {
                        continue;
                    }
                    $isRight = (string)$qa['correct_option'] === $letter;
                    $isPick = $given === $letter;
                    if ($isRight) {
                        $bg = 'svgreenbg'; $mark = '\\textcolor{svpine}{$\\checkmark$}';
                    } elseif ($isPick) {
                        $bg = 'svredbg'; $mark = '\\textcolor{svclay}{$\\times$}';
                    } else {
                        $bg = 'white'; $mark = '\\textcolor{svgray}{$\\square$}';
                    }
                    $note = $isPick ? ' \\textit{\\small(' . ExportTheme::esc($L('your_answer')) . ')}' : '';
                    $t = ExportTheme::tex($text, true);
                    $item .= "\\svopt{{$bg}}{{$mark}}{{$letter}}{" . ($isRight ? "\\textbf{{$t}}" : $t) . $note . "}\n";
                }
            } else {
                $shown = $given !== '' ? ExportTheme::tex((string)$qa['selected_option'], true) : ExportTheme::esc($L('none'));
                $item .= "\\vspace{3pt}\n\\svnote{\\textbf{" . ExportTheme::esc($L('your_answer')) . '.} ' . $shown
                    . '\\par\\textbf{' . ExportTheme::esc($L('correct_answer')) . '.} ' . ExportTheme::tex(LiveScoring::displayAnswer((string)$qa['correct_option'], $lang), true) . "}\n";
            }
            if (!empty($qa['explanation'])) {
                $item .= "\\vspace{3pt}\n\\svnote{\\textbf{" . ExportTheme::esc($L('justification')) . '.} ' . ExportTheme::tex((string)$qa['explanation'], true) . "}\n";
            }
            $items .= strlen($item) < 2600
                ? "\\begin{minipage}[t]{\\linewidth}\n{$item}\\end{minipage}\\par\\vspace{0.6em}\n"
                : "\\needspace{8\\baselineskip}\n{$item}\\par\\vspace{0.8em}\n";
        }
        $body .= ExportTheme::columns($items, $cols);

        $tex = ExportTheme::document($body, [
            'lang' => $lang,
            'title' => ($en ? 'Correction report: ' : 'Rapport de correction : ') . $registration['session_title'],
            'header_left' => (string)$registration['name'],
            'header_right' => (string)$registration['session_title'],
            'footer_left' => 'StudyVibe · ' . ($en ? 'correction report' : 'rapport de correction'),
        ]);
        return ['tex' => $tex, 'assets' => $assets];
    }

    /**
     * Any sheet of the Excel exports (name, headers, rows) as a landscape PDF table, so the promoter console can offer both.
     *
     * @param array{name:string,headers:array,rows:array,title?:string,meta?:array} $sheet
     */
    public static function sheetReport(array $sheet, string $lang = 'fr'): array
    {
        $en = $lang === 'en';
        $headers = array_values((array)$sheet['headers']);
        $n = max(1, count($headers));

        // Column widths in proportion to what each column holds, within sensible bounds
        $len = [];
        foreach ($headers as $c => $h) {
            $len[$c] = max(6, min(40, mb_strlen((string)$h)));
        }
        foreach (array_slice((array)$sheet['rows'], 0, 300) as $row) {
            foreach (array_values($row) as $c => $cell) {
                $len[$c] = max($len[$c] ?? 6, min(40, mb_strlen((string)$cell)));
            }
        }
        $len = array_map(fn($l) => $l + 4, $len);   // room for the column gap and for a little extra width at small size
        $sum = max(1, array_sum($len));
        $usable = 0.985 - 0.012 * $n;   // fraction of the text width left after the column gaps
        $cols = [];
        foreach ($headers as $c => $h) {
            $w = round($len[$c] / $sum * $usable, 3);
            $cols[] = [(string)$h, 'p{' . $w . '\\textwidth}'];
        }

        $title = (string)($sheet['title'] ?? $sheet['name']);
        $info = [[$en ? 'Report date' : 'Date du rapport', self::now($lang)], [$en ? 'Rows' : 'Lignes', (string)count((array)$sheet['rows'])]];
        foreach ((array)($sheet['meta'] ?? []) as $pair) {
            $info[] = [(string)($pair[0] ?? ''), (string)($pair[1] ?? '')];
        }
        $body = ExportTheme::titleBlock($en ? 'Report' : 'Rapport', $title, $en ? 'StudyVibe' : 'Plateforme StudyVibe', $info);

        $rows = [];
        foreach ((array)$sheet['rows'] as $r) {
            // underscores in identifiers (login_success_2fa) may break the line instead of running into the next column
            $rows[] = array_map(fn($cell) => str_replace('\\_', '\\_\\allowbreak{}', ExportTheme::esc((string)($cell ?? ''))), array_values($r));
        }
        $body .= $rows ? '{\\footnotesize' . ExportTheme::table($cols, $rows) . "}\n" : '\\textit{' . ($en ? 'Nothing to list.' : 'Rien à lister.') . "}\n";

        $tex = ExportTheme::document($body, [
            'lang' => $lang, 'landscape' => true, 'title' => $title,
            'header_left' => $title, 'header_right' => date('d/m/Y'), 'footer_left' => 'StudyVibe · ' . ExportTheme::label('confidential', $lang),
        ]);
        return ['tex' => $tex, 'assets' => []];
    }

    /** The picture of a question, when it has one: restored from the database copy if the disk lost it, staged under a plain name. */
    private static function picture(PDO $pdo, array $q, int $num, array &$assets): string
    {
        if (empty($q['image_path'])) {
            return '';
        }
        $file = basename((string)$q['image_path']);
        $path = MediaStore::path('live_question', $file);
        if (preg_match('/^[A-Za-z0-9_.-]+\.(jpg|jpeg|png)$/i', $file, $m) !== 1 || !MediaStore::restore($pdo, 'live_question/' . $file, $path)) {
            return '';
        }
        $name = 'qimg' . $num . '.' . strtolower($m[1]);
        $assets[$name] = $path;
        return "\\begin{center}\\includegraphics[width=0.78\\linewidth,height=6cm,keepaspectratio]{{$name}}\\end{center}\n";
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Shared bits
    // ---------------------------------------------------------------------------------------------------------------

    /** @return array{total:int,submitted:int,mean:float,max:float,pass_rate:float,buckets:int[]} */
    public static function statistics(array $registrations): array
    {
        $scores = [];
        foreach ($registrations as $r) {
            if ($r['score'] !== null) {
                $scores[] = (float)$r['score'];
            }
        }
        $buckets = array_fill(0, 10, 0);
        foreach ($scores as $s) {
            $buckets[min(9, (int)floor($s / 10))]++;
        }
        $n = count($scores);
        return [
            'total'     => count($registrations),
            'submitted' => $n,
            'mean'      => $n ? array_sum($scores) / $n : 0.0,
            'max'       => $n ? max($scores) : 0.0,
            'pass_rate' => $n ? count(array_filter($scores, fn($s) => $s >= self::PASS_MARK)) / $n * 100 : 0.0,
            'buckets'   => $buckets,
        ];
    }

    /** Date and time as text. In French the colon of "15:32" would get a space from the language rules, so it reads "15 h 32". */
    private static function now(string $lang): string
    {
        return $lang === 'en' ? date('d/m/Y H:i') : date('d/m/Y \à H \h i');
    }

    private static function pct(float $v): string
    {
        return str_replace('.', ',', (string)round($v, 1)) . '\\,\\%';
    }

    /** @return array<int,array{0:string,1:string}> */
    private static function markColumns(string $lang, bool $withRank): array
    {
        $en = $lang === 'en';
        $cols = [];
        if ($withRank) {
            $cols[] = [$en ? 'Rank' : 'Rang', 'r@{\\hspace{1em}}'];
        }
        $cols[] = [$en ? 'Full name' : 'Nom complet', 'p{0.30\\textwidth}'];
        $cols[] = [$en ? 'E-mail' : 'Adresse e-mail', 'p{0.30\\textwidth}'];
        $cols[] = [$en ? 'Mark' : 'Note', 'r'];
        $cols[] = ['%', 'r@{\\hspace{1em}}'];
        $cols[] = [$en ? 'Result' : 'Résultat', 'l'];
        return $cols;
    }

    private static function markRow(array $r, int $total, string $lang, ?string $rank): array
    {
        $en = $lang === 'en';
        $row = [];
        if ($rank !== null) {
            $row[] = $rank;
        }
        $row[] = ExportTheme::esc((string)$r['name']);
        $row[] = '{\\small ' . ExportTheme::esc((string)$r['email']) . '}';
        if ($r['score'] !== null) {
            $raw = (int)round((float)$r['score'] / 100 * $total);
            $row[] = "{$raw}\\,/\\,{$total}";
            $row[] = str_replace('.', ',', (string)round((float)$r['score'], 1));
            $row[] = (float)$r['score'] >= self::PASS_MARK ? '\\textbf{' . ($en ? 'Passed' : 'Admis') . '}' : ($en ? 'Failed' : 'Ajourné');
        } else {
            $row[] = '--';
            $row[] = '--';
            $row[] = '\\textit{' . (!empty($r['cancelled_at']) ? ($en ? 'Cancelled' : 'Annulé') : ($en ? 'Not submitted' : 'Non rendu')) . '}';
        }
        return $row;
    }

    private static function wrap(string $body, array $session, string $lang, string $kind): string
    {
        return ExportTheme::document($body, [
            'lang' => $lang,
            'title' => $kind . ' : ' . $session['title'],
            'header_left' => (string)$session['course_title'],
            'header_right' => (string)$session['title'],
            'footer_left' => 'StudyVibe · ' . ExportTheme::label('confidential', $lang),
        ]);
    }
}
