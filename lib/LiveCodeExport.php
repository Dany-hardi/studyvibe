<?php
declare(strict_types=1);

require_once __DIR__ . '/LiveCodeReview.php';

/**
 * The corrections of a session's code questions as one ZIP for the teacher.
 *
 * One file per student and per code question, named "Name (email).ext" with the name and the email the student gave when registering for the
 * evaluation. The file starts with the verdict and the feedback written as comments, then the corrected script. When the session has several
 * code questions the files are put in one folder per question. A _summary.csv lists every answer.
 */
final class LiveCodeExport
{
    /** A name that is safe as a file name on every system: no path parts, no reserved characters, no control characters. */
    public static function safeName(string $s, int $max = 150): string
    {
        $s = (string)preg_replace('/[\x00-\x1F\x7F]/u', '', $s);
        $s = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|', '..'], ['-', '-', '-', '', '', '', '', '', '-', '.'], $s);
        $s = trim((string)preg_replace('/\s+/u', ' ', $s), " .\t");
        $s = mb_substr($s, 0, $max);
        return $s === '' ? 'student' : $s;
    }

    /** "Name (email)" of a registration. */
    public static function baseName(string $name, string $email): string
    {
        $email = self::safeName($email, 80);
        return self::safeName(trim($name) . ' (' . $email . ')', 170);
    }

    /** Line comments for a language. */
    public static function commentLines(string $text, string $prefix): string
    {
        $out = [];
        foreach (explode("\n", str_replace("\r", '', $text)) as $line) {
            $out[] = rtrim($prefix . ' ' . $line);
        }
        return implode("\n", $out);
    }

    /** The content of one correction file. */
    public static function fileContent(array $row, array $session, array $question, int $number, bool $en = false): string
    {
        $lang = LiveCodeReview::langOf($question['code_lang'] ?? null);
        $prefix = LiveCodeReview::LANGS[$lang][2];
        $r = LiveCodeReview::effective($row);
        $verdict = $r['final'] === null ? ($en ? 'PENDING REVIEW (no verdict yet)' : 'EN ATTENTE DE RÉVISION (pas encore de verdict)')
            : ($r['final'] ? ($en ? 'CORRECT' : 'CORRECT') : ($en ? 'INCORRECT' : 'INCORRECT'));
        $who = $r['override_verdict'] !== null ? ($en ? 'decided by the teacher' : 'décidé par l’enseignant') : ($en ? 'marked by AI' : 'corrigé par l’IA');
        $first = trim((string)strtok((string)$question['question_text'], "\n"));

        $head = [
            'StudyVibe — ' . ($en ? 'Correction' : 'Correction'),
            ($en ? 'Student: ' : 'Étudiant : ') . trim((string)$row['student_name']) . ' (' . trim((string)$row['student_email']) . ')',
            ($en ? 'Evaluation: ' : 'Évaluation : ') . $session['title'],
            ($en ? 'Question ' : 'Question ') . $number . ' — ' . mb_substr($first, 0, 200),
            ($en ? 'Language: ' : 'Langage : ') . LiveCodeReview::LANGS[$lang][0],
            ($en ? 'Verdict: ' : 'Verdict : ') . $verdict . ' (' . $who . ')',
        ];
        if (trim((string)$row['feedback']) !== '') {
            $head[] = '';
            $head[] = ($en ? 'Feedback:' : 'Commentaire :');
            $head[] = wordwrap(trim((string)$row['feedback']), 96, "\n", true);
        }
        if ($r['issues_list']) {
            $head[] = '';
            $head[] = $en ? 'Problems found:' : 'Problèmes relevés :';
            foreach ($r['issues_list'] as $i) {
                $head[] = '- [' . ($i['severity'] === 'blocking' ? ($en ? 'blocking' : 'bloquant') : ($en ? 'minor' : 'mineur')) . '] '
                    . ($i['where'] !== '' ? $i['where'] . ': ' : '') . $i['description'];
            }
        }
        if ($row['override_note'] !== null && trim((string)$row['override_note']) !== '') {
            $head[] = '';
            $head[] = ($en ? 'Teacher note: ' : 'Note de l’enseignant : ') . trim((string)$row['override_note']);
        }
        $head[] = '';
        $head[] = $r['corrected_code'] !== null && $r['corrected_code'] !== ''
            ? '----- ' . ($en ? 'Corrected script' : 'Script corrigé') . ' -----'
            : '----- ' . ($en ? 'Submitted script (not corrected yet)' : 'Script soumis (pas encore corrigé)') . ' -----';
        $code = ($r['corrected_code'] !== null && $r['corrected_code'] !== '') ? $r['corrected_code'] : (string)$row['code'];
        return self::commentLines(implode("\n", $head), $prefix) . "\n\n" . rtrim($code) . "\n";
    }

    /**
     * Builds the ZIP for a session of this teacher.
     * @return array{ok:bool,path?:string,name?:string,count?:int,error?:string} path is a temporary file the caller sends and deletes
     */
    public static function build(PDO $pdo, int $sessionId, int $teacherId, bool $en = false): array
    {
        $s = $pdo->prepare("SELECT s.*, c.title AS course_title FROM live_eval_sessions s JOIN courses c ON c.id = s.course_id WHERE s.id = :i AND s.teacher_id = :t");
        $s->execute(['i' => $sessionId, 't' => $teacherId]);
        $session = $s->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $qs = $pdo->prepare("SELECT * FROM live_eval_questions WHERE session_id = :i AND question_type = 'code' ORDER BY sort_order, id");
        $qs->execute(['i' => $sessionId]);
        $questions = $qs->fetchAll(PDO::FETCH_ASSOC);
        if (!$questions) {
            return ['ok' => false, 'error' => 'no_code_question'];
        }
        // the numbering is that of the whole exam, as the student saw it
        $all = $pdo->prepare("SELECT id FROM live_eval_questions WHERE session_id = :i ORDER BY sort_order, id");
        $all->execute(['i' => $sessionId]);
        $numbers = array_flip(array_map('intval', $all->fetchAll(PDO::FETCH_COLUMN)));

        $zip = new ZipArchive();
        $path = tempnam(sys_get_temp_dir(), 'svcode');
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            return ['ok' => false, 'error' => 'zip'];
        }
        $csv = [[$en ? 'Student' : 'Étudiant', 'Email', $en ? 'Question' : 'Question', $en ? 'Verdict' : 'Verdict', $en ? 'Decided by' : 'Décidé par', $en ? 'Feedback' : 'Commentaire', $en ? 'File' : 'Fichier']];
        $count = 0;
        $multi = count($questions) > 1;
        $used = [];
        foreach ($questions as $q) {
            $n = ($numbers[(int)$q['id']] ?? 0) + 1;
            $rows = $pdo->prepare("
                SELECT cr.*, r.name AS student_name, r.email AS student_email
                FROM live_eval_code_reviews cr JOIN live_eval_registrations r ON r.id = cr.registration_id
                WHERE cr.question_id = :q AND r.session_id = :s ORDER BY r.name, r.id
            ");
            $rows->execute(['q' => $q['id'], 's' => $sessionId]);
            $ext = LiveCodeReview::LANGS[LiveCodeReview::langOf($q['code_lang'] ?? null)][1];
            $dir = $multi ? self::safeName('Q' . $n . ' - ' . mb_substr(trim((string)strtok((string)$q['question_text'], "\n")), 0, 50), 80) . '/' : '';
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $file = $dir . self::baseName((string)$row['student_name'], (string)$row['student_email']);
                $k = 2; $unique = $file;
                while (isset($used[strtolower($unique . '.' . $ext)])) {
                    $unique = $file . ' - ' . $k++;
                }
                $name = $unique . '.' . $ext;
                $used[strtolower($name)] = true;
                $zip->addFromString($name, self::fileContent($row, $session, $q, $n, $en));
                $r = LiveCodeReview::effective($row);
                $csv[] = [(string)$row['student_name'], (string)$row['student_email'], 'Q' . $n,
                    $r['final'] === null ? ($en ? 'pending' : 'en attente') : ($r['final'] ? ($en ? 'correct' : 'correct') : ($en ? 'incorrect' : 'incorrect')),
                    $r['override_verdict'] !== null ? ($en ? 'teacher' : 'enseignant') : 'IA', (string)$row['feedback'], $name];
                $count++;
            }
        }
        $zip->addFromString('_summary.csv', self::csv($csv));
        $zip->close();
        if ($count === 0) {
            @unlink($path);
            return ['ok' => false, 'error' => 'no_answers'];
        }
        return ['ok' => true, 'path' => $path, 'count' => $count,
            'name' => self::safeName(($en ? 'code-corrections' : 'corrections-code') . ' - ' . $session['title'], 120) . '.zip'];
    }

    /** CSV with the UTF-8 mark Excel needs; a cell that starts like a formula is neutralised. */
    private static function csv(array $rows): string
    {
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $r) {
            $out .= implode(';', array_map(static function ($v): string {
                $v = str_replace(["\r", "\n"], ' ', (string)$v);
                if ($v !== '' && strpos('=+-@', $v[0]) !== false) {
                    $v = "'" . $v;
                }
                return '"' . str_replace('"', '""', $v) . '"';
            }, $r)) . "\r\n";
        }
        return $out;
    }
}
