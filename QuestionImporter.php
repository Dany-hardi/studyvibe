<?php
declare(strict_types=1);

/**
 * Import en masse de questions QCM depuis CSV ou tableaux JSON.
 * Colonnes : question, option_a, option_b, option_c, option_d, correct
 */
class QuestionImporter
{
    /** Parse un fichier CSV (délimiteur , ou ;). */
    public static function parseCsv(string $content): array
    {
        $content = trim(preg_replace('/^\xEF\xBB\xBF/', '', $content));
        if ($content === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $delimiter = self::detectDelimiter($lines[0] ?? '');
        $rows      = [];
        $header    = null;

        foreach ($lines as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $cols = str_getcsv($line, $delimiter);
            if ($i === 0 && self::looksLikeHeader($cols)) {
                $header = self::normalizeHeader($cols);
                continue;
            }
            $rows[] = $header ? self::mapRow($header, $cols) : self::mapRowDefault($cols);
        }

        return self::validateRows($rows);
    }

    /** Parse un tableau JSON (lignes SheetJS). */
    public static function parseJsonRows(array $rows): array
    {
        if (empty($rows)) {
            return ['questions' => [], 'errors' => ['Fichier vide.']];
        }

        $parsed = [];
        $first  = $rows[0];
        $header = null;

        if (self::looksLikeHeaderArray($first)) {
            $header = self::normalizeHeader($first);
            array_shift($rows);
        }

        foreach ($rows as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }
            $values = array_values($row);
            if (self::rowIsEmpty($values)) {
                continue;
            }
            $parsed[] = $header ? self::mapRow($header, $values) : self::mapRowDefault($values);
        }

        return self::validateRows($parsed);
    }

    public static function importLessonQuestions(PDO $pdo, int $lessonId, array $questions): int
    {
        $stmt = $pdo->prepare("
            INSERT INTO lesson_questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option)
            VALUES (:lid, :qt, :a, :b, :c, :d, :co)
        ");
        $count = 0;
        foreach ($questions as $q) {
            $stmt->execute([
                'lid' => $lessonId,
                'qt'  => $q['question_text'],
                'a'   => $q['option_a'],
                'b'   => $q['option_b'],
                'c'   => $q['option_c'],
                'd'   => $q['option_d'],
                'co'  => $q['correct_option'],
            ]);
            $count++;
        }
        return $count;
    }

    public static function importCourseQuestions(PDO $pdo, int $courseId, array $questions): int
    {
        $stmt = $pdo->prepare("
            INSERT INTO course_questions (course_id, question_text, option_a, option_b, option_c, option_d, correct_option)
            VALUES (:cid, :qt, :a, :b, :c, :d, :co)
        ");
        $count = 0;
        foreach ($questions as $q) {
            $stmt->execute([
                'cid' => $courseId,
                'qt'  => $q['question_text'],
                'a'   => $q['option_a'],
                'b'   => $q['option_b'],
                'c'   => $q['option_c'],
                'd'   => $q['option_d'],
                'co'  => $q['correct_option'],
            ]);
            $count++;
        }
        return $count;
    }

    public static function importLiveQuestions(PDO $pdo, int $sessionId, array $questions): int
    {
        $stmt = $pdo->prepare("
            INSERT INTO live_eval_questions (session_id, question_text, option_a, option_b, option_c, option_d, correct_option)
            VALUES (:sid, :qt, :a, :b, :c, :d, :co)
        ");
        $count = 0;
        foreach ($questions as $q) {
            $stmt->execute([
                'sid' => $sessionId,
                'qt'  => $q['question_text'],
                'a'   => $q['option_a'],
                'b'   => $q['option_b'],
                'c'   => $q['option_c'],
                'd'   => $q['option_d'],
                'co'  => $q['correct_option'],
            ]);
            $count++;
        }
        return $count;
    }

    private static function detectDelimiter(string $line): string
    {
        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    private static function looksLikeHeader(array $cols): bool
    {
        return self::looksLikeHeaderArray($cols);
    }

    private static function looksLikeHeaderArray(array $cols): bool
    {
        $joined = strtolower(implode(' ', array_map('strval', $cols)));
        return str_contains($joined, 'question')
            || str_contains($joined, 'option')
            || str_contains($joined, 'reponse')
            || str_contains($joined, 'réponse')
            || str_contains($joined, 'correct');
    }

    private static function normalizeHeader(array $cols): array
    {
        $map = [];
        foreach ($cols as $i => $col) {
            $key = strtolower(trim((string)$col));
            $key = str_replace(['é', 'è', 'ê'], 'e', $key);
            $key = preg_replace('/[^a-z0-9_]/', '_', $key);
            $map[$i] = match (true) {
                str_contains($key, 'question'), str_contains($key, 'libelle'), str_contains($key, 'enonce') => 'question',
                $key === 'a', str_contains($key, 'option_a') => 'option_a',
                $key === 'b', str_contains($key, 'option_b') => 'option_b',
                $key === 'c', str_contains($key, 'option_c') => 'option_c',
                $key === 'd', str_contains($key, 'option_d') => 'option_d',
                str_contains($key, 'correct'), str_contains($key, 'reponse'), str_contains($key, 'bonne') => 'correct',
                default => $key,
            };
        }
        return $map;
    }

    private static function mapRow(array $header, array $cols): array
    {
        $row = [
            'question_text'  => '',
            'option_a'       => '',
            'option_b'       => '',
            'option_c'       => '',
            'option_d'       => '',
            'correct_option' => '',
        ];
        foreach ($header as $i => $field) {
            $val = trim((string)($cols[$i] ?? ''));
            if (isset($row[$field])) {
                $row[$field] = $val;
            } elseif ($field === 'question') {
                $row['question_text'] = $val;
            } elseif ($field === 'correct') {
                $row['correct_option'] = $val;
            }
        }
        return $row;
    }

    private static function mapRowDefault(array $cols): array
    {
        return [
            'question_text'  => trim((string)($cols[0] ?? '')),
            'option_a'       => trim((string)($cols[1] ?? '')),
            'option_b'       => trim((string)($cols[2] ?? '')),
            'option_c'       => trim((string)($cols[3] ?? '')),
            'option_d'       => trim((string)($cols[4] ?? '')),
            'correct_option' => trim((string)($cols[5] ?? '')),
        ];
    }

    private static function validateRows(array $rows): array
    {
        $questions = [];
        $errors    = [];

        foreach ($rows as $i => $row) {
            $line = $i + 1;
            $q    = trim($row['question_text'] ?? '');
            $a    = trim($row['option_a'] ?? '');
            $b    = trim($row['option_b'] ?? '');
            $c    = trim($row['option_c'] ?? '');
            $d    = trim($row['option_d'] ?? '');
            $co   = self::normalizeCorrect($row['correct_option'] ?? '');

            if ($q === '' && $a === '' && $b === '') {
                continue;
            }
            if ($q === '' || $a === '' || $b === '' || $c === '' || $d === '') {
                $errors[] = "Ligne {$line} : champs incomplets.";
                continue;
            }
            if (!in_array($co, ['A', 'B', 'C', 'D'], true)) {
                $errors[] = "Ligne {$line} : réponse correcte invalide (utilisez A, B, C ou D).";
                continue;
            }
            $questions[] = [
                'question_text'  => $q,
                'option_a'       => $a,
                'option_b'       => $b,
                'option_c'       => $c,
                'option_d'       => $d,
                'correct_option' => $co,
            ];
        }

        return ['questions' => $questions, 'errors' => $errors];
    }

    private static function normalizeCorrect(string $raw): string
    {
        $raw = strtoupper(trim($raw));
        if (in_array($raw, ['A', 'B', 'C', 'D'], true)) {
            return $raw;
        }
        if (preg_match('/^[1-4]$/', $raw)) {
            return ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'][$raw];
        }
        if (str_contains($raw, 'OPTION ')) {
            $raw = trim(str_replace('OPTION ', '', $raw));
        }
        return in_array($raw, ['A', 'B', 'C', 'D'], true) ? $raw : '';
    }

    private static function rowIsEmpty(array $values): bool
    {
        foreach ($values as $v) {
            if (trim((string)$v) !== '') {
                return false;
            }
        }
        return true;
    }
}
