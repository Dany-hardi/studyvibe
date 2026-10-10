<?php
declare(strict_types=1);

require_once __DIR__ . '/MediaStore.php';

/**
 * Similarity check between the submissions of one assignment (teacher only; students never see it).
 *
 * What it does: the text of each handed-in file is extracted, normalised (lower case, no accents, no punctuation) and cut into
 * overlapping 5-word "shingles". Two submissions are compared by the share of the SMALLER one's shingles that the other also contains
 * (containment): copying a whole report into a longer one scores about 100 %, two honest answers to the same question score low.
 * Shingles found in half of all submissions or more (the statement copied by everybody, a template) are ignored.
 *
 * What it cannot do: it compares students with each other, not with the web or earlier years; it cannot read scans or pictures; a
 * high score is a reason to LOOK, not a proof. The matching passages are shown so the teacher can judge.
 *
 * Formats read: txt, md, csv, code files, ipynb, docx, odt, pdf (pdftotext when installed, otherwise a basic reader).
 */
final class AssignmentSimilarity
{
    public const SHINGLE    = 5;
    public const MIN_WORDS  = 30;
    public const STORE_MIN  = 15.0;   // pairs below this are not kept
    public const MEDIUM     = 30.0;
    public const HIGH       = 60.0;
    private const MAX_CHARS = 400000;

    private const TEXT = ['txt', 'md', 'csv', 'py', 'java', 'c', 'h', 'cpp', 'cs', 'sql', 'json', 'r', 'm', 'tex', 'css', 'xml', 'yml', 'yaml', 'rs', 'go', 'kt', 'ts', 'html', 'htm', 'js'];

    // -----------------------------------------------------------------------------------------------------------------------------
    // Text
    // -----------------------------------------------------------------------------------------------------------------------------

    /** The text of a file, or null when it cannot be read as text (scan, picture, archive, unknown format). */
    public static function extractText(string $path, string $ext): ?string
    {
        $ext = strtolower($ext);
        if (!is_file($path)) {
            return null;
        }
        $text = null;
        if (in_array($ext, self::TEXT, true)) {
            $text = (string)@file_get_contents($path, false, null, 0, 2_000_000);
        } elseif ($ext === 'ipynb') {
            $j = json_decode((string)@file_get_contents($path, false, null, 0, 8_000_000), true);
            $parts = [];
            foreach ((array)($j['cells'] ?? []) as $c) {
                $src = $c['source'] ?? '';
                $parts[] = is_array($src) ? implode('', $src) : (string)$src;
            }
            $text = implode("\n", $parts);
        } elseif ($ext === 'docx') {
            $text = self::zipXml($path, 'word/document.xml', ['</w:p>', '<w:br/>', '</w:tr>']);
        } elseif ($ext === 'odt') {
            $text = self::zipXml($path, 'content.xml', ['</text:p>', '</text:h>', '<text:line-break/>']);
        } elseif ($ext === 'pdf') {
            $text = self::pdfText($path);
        }
        if ($text === null) {
            return null;
        }
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }
        return mb_substr($text, 0, self::MAX_CHARS);
    }

    private static function zipXml(string $path, string $entry, array $breaks): ?string
    {
        if (!class_exists('ZipArchive')) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }
        $xml = $zip->getFromName($entry);
        $zip->close();
        if ($xml === false) {
            return null;
        }
        $xml = str_replace($breaks, "\n", (string)$xml);
        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function pdfText(string $path): ?string
    {
        $bin = trim((string)@shell_exec('command -v pdftotext 2>/dev/null'));
        if ($bin !== '' && function_exists('proc_open')) {
            $p = @proc_open([$bin, '-enc', 'UTF-8', '-q', '-nopgbrk', $path, '-'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (is_resource($p)) {
                stream_set_blocking($pipes[1], false);
                $out = '';
                $t0 = time();
                while (time() - $t0 < 20) {
                    $chunk = (string)fread($pipes[1], 65536);
                    $out .= $chunk;
                    if (strlen($out) > 3_000_000) { break; }
                    $st = proc_get_status($p);
                    if (!$st['running'] && $chunk === '') { break; }
                    if ($chunk === '') { usleep(20000); }
                }
                $st = proc_get_status($p);
                if ($st['running']) { proc_terminate($p); }
                @fclose($pipes[1]); @fclose($pipes[2]); proc_close($p);
                return trim($out) !== '' ? $out : self::pdfFallback($path);
            }
        }
        return self::pdfFallback($path);
    }

    /**
     * A modest PDF reader for when pdftotext is not installed: the text operators (Tj, TJ) of the content streams, flate-compressed or
     * not. It reads simple PDFs (word processors, most exports); fonts that use custom encodings come out empty, and the submission is
     * then reported as unreadable instead of being compared on garbage.
     */
    public static function pdfFallback(string $path): ?string
    {
        $raw = (string)@file_get_contents($path, false, null, 0, 20_000_000);
        if ($raw === '' || !preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $m)) {
            return null;
        }
        $out = '';
        foreach ($m[1] as $s) {
            $d = @gzuncompress($s);
            if ($d === false) {
                $d = @gzinflate(substr($s, 2));
            }
            if ($d === false) {
                $d = $s;
            }
            if (!preg_match_all('/\[((?:[^\]\\\\]|\\\\.)*)\]\s*TJ|\(((?:[^)\\\\]|\\\\.)*)\)\s*Tj/s', $d, $t, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($t as $x) {
                if (($x[1] ?? '') !== '') {
                    preg_match_all('/\(((?:[^)\\\\]|\\\\.)*)\)/s', $x[1], $in);
                    $out .= implode('', $in[1]);
                } else {
                    $out .= $x[2] ?? '';
                }
                $out .= ' ';
            }
            $out .= "\n";
        }
        $out = stripcslashes($out);
        return mb_strlen(trim($out)) >= 20 ? $out : null;
    }

    // -----------------------------------------------------------------------------------------------------------------------------
    // Words, shingles, comparison (pure functions: unit-tested)
    // -----------------------------------------------------------------------------------------------------------------------------

    /** @return string[] lower-case words without accents or punctuation */
    public static function words(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae', 'ñ' => 'n']);
        $w = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return $w === false ? [] : $w;
    }

    /** @return array<int,int> shingle hash => position of its first occurrence */
    public static function shingles(array $words, int $k = self::SHINGLE): array
    {
        $out = [];
        $n = count($words);
        for ($i = 0; $i + $k <= $n; $i++) {
            $h = crc32(implode(' ', array_slice($words, $i, $k)));
            if (!isset($out[$h])) {
                $out[$h] = $i;
            }
        }
        return $out;
    }

    /**
     * @param array<int,int> $a shingles of one document   @param array<int,int> $b shingles of another
     * @return array{score:float,shared:int} score = shared / size of the smaller set, in percent
     */
    public static function compare(array $a, array $b): array
    {
        $min = min(count($a), count($b));
        if ($min === 0) {
            return ['score' => 0.0, 'shared' => 0];
        }
        $shared = count(array_intersect_key($a, $b));
        return ['score' => round($shared / $min * 100, 2), 'shared' => $shared];
    }

    /**
     * The longest passages two documents have in common, from the shared shingles of the FIRST one.
     *
     * @param string[] $words words of the first document
     * @return string[] up to $max passages (normalised words), longest first
     */
    public static function passages(array $words, array $shingles, array $other, int $max = 3, int $k = self::SHINGLE): array
    {
        $pos = [];
        foreach ($shingles as $h => $i) {
            if (isset($other[$h])) {
                $pos[] = $i;
            }
        }
        sort($pos);
        $runs = [];
        $start = null;
        $last = null;
        foreach ($pos as $p) {
            if ($start === null) { $start = $last = $p; continue; }
            if ($p - $last <= 1) { $last = $p; continue; }
            $runs[] = [$start, $last + $k];
            $start = $last = $p;
        }
        if ($start !== null) {
            $runs[] = [$start, $last + $k];
        }
        usort($runs, fn($x, $y) => ($y[1] - $y[0]) <=> ($x[1] - $x[0]));
        $out = [];
        foreach (array_slice($runs, 0, $max) as [$s, $e]) {
            $slice = array_slice($words, $s, min($e - $s, 60));
            $out[] = implode(' ', $slice) . ($e - $s > 60 ? ' …' : '');
        }
        return $out;
    }

    public static function level(float $score): string
    {
        return $score >= self::HIGH ? 'high' : ($score >= self::MEDIUM ? 'medium' : 'low');
    }

    // -----------------------------------------------------------------------------------------------------------------------------
    // The analysis of one assignment
    // -----------------------------------------------------------------------------------------------------------------------------

    /** True when the teacher owns the lesson's course. */
    private static function owns(PDO $pdo, int $lessonId, int $teacherId): bool
    {
        $st = $pdo->prepare("SELECT 1 FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id JOIN courses c ON c.id = ch.course_id WHERE l.id = :l AND c.teacher_id = :t AND l.has_assignment = 1");
        $st->execute(['l' => $lessonId, 't' => $teacherId]);
        return (bool)$st->fetchColumn();
    }

    /**
     * Compares every pair of submitted files of an assignment and stores the pairs that look alike.
     *
     * @return array{ok:bool,error?:string,analysed?:int,skipped?:array<int,array{id:int,reason:string}>,pairs?:int,flagged?:int,ignored_common?:int}
     *         skipped reasons: no_file, unreadable, too_short
     */
    public static function analyse(PDO $pdo, int $lessonId, int $teacherId): array
    {
        if (!self::owns($pdo, $lessonId, $teacherId)) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $st = $pdo->prepare("SELECT id, submitted_file_path, submitted_file_name FROM lesson_assignment_submissions WHERE lesson_id = :l ORDER BY id");
        $st->execute(['l' => $lessonId]);
        $docs = [];
        $skipped = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $id = (int)$s['id'];
            $file = (string)($s['submitted_file_path'] ?? '');
            if ($file === '') {
                $skipped[] = ['id' => $id, 'reason' => 'no_file'];
                continue;
            }
            $path = MediaStore::path('assignment', $file);
            if (!is_file($path)) {
                MediaStore::restore($pdo, 'assignment/' . $file, $path);
            }
            $text = self::extractText($path, pathinfo($file, PATHINFO_EXTENSION));
            if ($text === null) {
                $skipped[] = ['id' => $id, 'reason' => 'unreadable'];
                continue;
            }
            $w = self::words($text);
            if (count($w) < self::MIN_WORDS) {
                $skipped[] = ['id' => $id, 'reason' => 'too_short'];
                continue;
            }
            $docs[$id] = ['words' => $w, 'sh' => self::shingles($w)];
        }

        // What nearly everybody has (the statement, a template) proves nothing
        $ignored = 0;
        if (count($docs) >= 4) {
            $count = [];
            foreach ($docs as $d) {
                foreach ($d['sh'] as $h => $_) {
                    $count[$h] = ($count[$h] ?? 0) + 1;
                }
            }
            $limit = max(3, (int)ceil(count($docs) / 2));
            $common = array_filter($count, fn($c) => $c >= $limit);
            $ignored = count($common);
            if ($common) {
                foreach ($docs as $id => $d) {
                    $docs[$id]['sh'] = array_diff_key($d['sh'], $common);
                }
            }
        }

        $ids = array_keys($docs);
        $pairs = [];
        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $a = $docs[$ids[$i]];
                $b = $docs[$ids[$j]];
                if (count($a['sh']) < 3 || count($b['sh']) < 3) {
                    continue;
                }
                $r = self::compare($a['sh'], $b['sh']);
                if ($r['score'] >= self::STORE_MIN) {
                    // The passages are read in the longer document so the quotation is complete
                    $first = count($a['sh']) >= count($b['sh']) ? $a : $b;
                    $other = $first === $a ? $b['sh'] : $a['sh'];
                    $pairs[] = [$ids[$i], $ids[$j], $r['score'], $r['shared'], self::passages($first['words'], $first['sh'], $other)];
                }
            }
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM lesson_assignment_similarity WHERE lesson_id = :l")->execute(['l' => $lessonId]);
            $ins = $pdo->prepare("INSERT INTO lesson_assignment_similarity (lesson_id, sub_a, sub_b, score, shared, excerpts, computed_at) VALUES (:l, :a, :b, :s, :n, :e, NOW())");
            foreach ($pairs as [$a, $b, $score, $shared, $ex]) {
                $ins->execute(['l' => $lessonId, 'a' => $a, 'b' => $b, 's' => $score, 'n' => $shared, 'e' => json_encode($ex, JSON_UNESCAPED_UNICODE)]);
            }
            $pdo->prepare("UPDATE lessons SET assignment_similarity_at = NOW() WHERE id = :l")->execute(['l' => $lessonId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return [
            'ok' => true, 'analysed' => count($docs), 'skipped' => $skipped, 'pairs' => count($pairs),
            'flagged' => count(array_filter($pairs, fn($p) => $p[2] >= self::MEDIUM)), 'ignored_common' => $ignored,
        ];
    }

    /**
     * The stored result for an assignment: the pairs (names, matricules, score, passages), the worst score of each submission, and
     * whether newer work has arrived since the analysis.
     *
     * @return array{ok:bool,error?:string,checked_at:?string,stale:bool,pairs:array<int,array<string,mixed>>,by_submission:array<int,array{score:float,with:string}>}
     */
    public static function results(PDO $pdo, int $lessonId, int $teacherId): array
    {
        if (!self::owns($pdo, $lessonId, $teacherId)) {
            return ['ok' => false, 'error' => 'not_found', 'checked_at' => null, 'stale' => false, 'pairs' => [], 'by_submission' => []];
        }
        $at = $pdo->prepare("SELECT assignment_similarity_at FROM lessons WHERE id = :l");
        $at->execute(['l' => $lessonId]);
        $checked = $at->fetchColumn() ?: null;

        $st = $pdo->prepare(
            "SELECT p.sub_a, p.sub_b, p.score, p.shared, p.excerpts,
                    ua.name AS name_a, sa.student_matricule AS mat_a, ub.name AS name_b, sb.student_matricule AS mat_b
             FROM lesson_assignment_similarity p
             JOIN lesson_assignment_submissions sa ON sa.id = p.sub_a JOIN users ua ON ua.id = sa.student_id
             JOIN lesson_assignment_submissions sb ON sb.id = p.sub_b JOIN users ub ON ub.id = sb.student_id
             WHERE p.lesson_id = :l ORDER BY p.score DESC, p.id"
        );
        $st->execute(['l' => $lessonId]);
        $pairs = [];
        $by = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $score = (float)$r['score'];
            $pairs[] = [
                'a' => (int)$r['sub_a'], 'b' => (int)$r['sub_b'], 'name_a' => $r['name_a'], 'mat_a' => $r['mat_a'], 'name_b' => $r['name_b'], 'mat_b' => $r['mat_b'],
                'score' => $score, 'shared' => (int)$r['shared'], 'level' => self::level($score),
                'excerpts' => json_decode((string)$r['excerpts'], true) ?: [],
            ];
            foreach ([[(int)$r['sub_a'], $r['name_b']], [(int)$r['sub_b'], $r['name_a']]] as [$sid, $other]) {
                if (!isset($by[$sid]) || $by[$sid]['score'] < $score) {
                    $by[$sid] = ['score' => $score, 'with' => (string)$other];
                }
            }
        }
        $stale = false;
        if ($checked !== null) {
            $n = $pdo->prepare("SELECT COUNT(*) FROM lesson_assignment_submissions WHERE lesson_id = :l AND submitted_at > :c");
            $n->execute(['l' => $lessonId, 'c' => $checked]);
            $stale = (int)$n->fetchColumn() > 0;
        }
        return ['ok' => true, 'checked_at' => $checked, 'stale' => $stale, 'pairs' => $pairs, 'by_submission' => $by];
    }
}
