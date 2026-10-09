<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Massive Question Importer Service
 * 
 * Facilitates the batch import of multiple-choice (MCQs) and written/calculation questions 
 * into lesson, course, or live session question banks. Supports parsing CSV structures 
 * (automatically detecting separators) and raw JSON datasets (Excel uploads mapped by SheetJS).
 * 
 * Columns mapped: question, option_a, option_b, option_c, option_d, correct, explanation, type
 * 
 * @package    StudyVibe
 * @author     Advanced Engineering Team
 */
class QuestionImporter
{
    // =========================================================================
    // SECTION 1: PARSING ROUTINES
    // =========================================================================

    /**
     * Parses CSV data, automatically resolving delimiters and column indexes.
     * 
     * @param string $content Raw CSV file string.
     * @return array{questions: array, errors: array} Parsed questions list and validation error logs.
     */
    public static function parseCsv(string $content): array
    {
        // Strip UTF-8 Byte Order Mark (BOM) if present
        $content = trim(preg_replace('/^\xEF\xBB\xBF/', '', $content));
        if ($content === '') {
            return ['questions' => [], 'errors' => ['Fichier CSV vide.']];
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
            
            // Check if first line looks like a header label row
            if ($i === 0 && self::looksLikeHeader($cols)) {
                $header = self::normalizeHeader($cols);
                continue;
            }
            
            // Map row to data fields using resolved headers or default numeric index map
            $rows[] = $header ? self::mapRow($header, $cols) : self::mapRowDefault($cols);
        }

        return self::validateRows($rows);
    }

    /**
     * Parses raw rows received from JSON converters (such as client-side SheetJS arrays).
     * 
     * @param array $rows Array of raw rows.
     * @return array{questions: array, errors: array} Parsed records list and error logs.
     */
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

        foreach ($rows as $row) {
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

    // =========================================================================
    // SECTION 2: BULK DATABASE IMPORT ACTIONS
    // =========================================================================

    /**
     * Imports parsed questions into a target lesson quiz bank.
     * 
     * @param PDO   $pdo       Database connection instance.
     * @param int   $lessonId  Target lesson primary key.
     * @param array $questions List of validated question arrays.
     * @return int Count of successfully imported questions.
     */
    public static function importLessonQuestions(PDO $pdo, int $lessonId, array $questions): int
    {
        $stmt = $pdo->prepare("
            INSERT INTO lesson_questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation)
            VALUES (:lid, :qt, :a, :b, :c, :d, :co, :exp)
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
                'exp' => $q['explanation'] ?? null,
            ]);
            $count++;
        }
        return $count;
    }

    /**
     * Imports parsed questions into a course evaluation bank.
     * 
     * @param PDO   $pdo      Database connection instance.
     * @param int   $courseId Target course primary key.
     * @param array $questions List of validated question arrays.
     * @return int Count of successfully imported questions.
     */
    public static function importCourseQuestions(PDO $pdo, int $courseId, array $questions): int
    {
        $stmt = $pdo->prepare("
            INSERT INTO course_questions (course_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation)
            VALUES (:cid, :qt, :a, :b, :c, :d, :co, :exp)
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
                'exp' => $q['explanation'] ?? null,
            ]);
            $count++;
        }
        return $count;
    }

    /**
     * Imports parsed questions into a live evaluation session bank.
     * 
     * @param PDO   $pdo       Database connection instance.
     * @param int   $sessionId Target live evaluation session primary key.
     * @param array $questions List of validated question arrays.
     * @return int Count of successfully imported questions.
     */
    public static function importLiveQuestions(PDO $pdo, int $sessionId, array $questions): int
    {
        $stmt = $pdo->prepare("
            INSERT INTO live_eval_questions (session_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, question_type)
            VALUES (:sid, :qt, :a, :b, :c, :d, :co, :exp, :type)
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
                'exp' => $q['explanation'] ?? null,
                'type' => $q['question_type'] ?? 'mcq',
            ]);
            $count++;
        }
        return $count;
    }

    // =========================================================================
    // SECTION 3: PARSING UTILITIES
    // =========================================================================

    /**
     * Detects delimiter character in first line of CSV inputs (semicolons vs commas).
     * 
     * @param string $line First line of CSV file.
     * @return string Semicolon (;) or comma (,).
     */
    private static function detectDelimiter(string $line): string
    {
        $c = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($c);
        return (string)array_key_first($c);
    }

    /**
     * Wrapper checking if string array contains header labels.
     * 
     * @param array $cols Columns.
     * @return bool True if header detected.
     */
    private static function looksLikeHeader(array $cols): bool
    {
        return self::looksLikeHeaderArray($cols);
    }

    /**
     * Analyzes if a line contains column headers (matches at least 2 column keywords).
     * 
     * @param array $cols Columns.
     * @return bool True if header detected.
     */
    private static function looksLikeHeaderArray(array $cols): bool
    {
        $found = [];
        foreach ($cols as $col) {
            $raw = trim((string)$col);
            if (mb_strlen($raw) < 2 || mb_strlen($raw) > 30) {
                continue;   // long free text, or a single letter: a data row, not a label
            }
            $field = self::classifyHeader(self::slug($raw));
            if ($field !== 'ignore') {
                $found[$field] = true;
            }
        }
        // A real header names the question column, or at least four known columns
        return isset($found['question']) || count($found) >= 4;
    }

    /**
     * Standardizes header labels into system keys.
     * 
     * @param array $cols Header column labels.
     * @return array Normalized header names list.
     */
    private static function normalizeHeader(array $cols): array
    {
        $map  = [];
        $used = [];
        foreach ($cols as $i => $col) {
            $key = self::slug((string)$col);
            $field = self::classifyHeader($key);
            // The first column of a kind wins. A second "question"-like column (a number, an id) never overrides it.
            if ($field !== 'ignore' && isset($used[$field])) {
                $field = 'ignore';
            }
            $used[$field] = true;
            $map[$i] = $field;
        }
        return $map;
    }

    /** Lowercase, accents removed, anything else turned into underscores ("N° Question" -> "n_question"). */
    private static function slug(string $label): string
    {
        $label = trim(mb_strtolower($label, 'UTF-8'));
        $label = strtr($label, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', '°' => '_']);
        $label = preg_replace('/[^a-z0-9]+/', '_', $label) ?? '';
        return trim($label, '_');
    }

    private static function classifyHeader(string $key): string
    {
        if ($key === '' || $key === 'n' || $key === 'no' || $key === 'num' || $key === 'numero' || $key === 'nb' || $key === 'id' || $key === 'index' || $key === 'ordre' || $key === 'order' || $key === 'rang' || $key === 'number') {
            return 'ignore';
        }
        if (preg_match('/^(?:option|opt|choix|proposition|reponse|answer)?_?([abcd])$/', $key, $m)) {
            return 'option_' . $m[1];
        }
        if (in_array($key, ['correct', 'correct_option', 'correct_answer', 'bonne_reponse', 'reponse_correcte', 'bonne', 'answer', 'solution', 'reponse', 'corrige'], true)
            || str_starts_with($key, 'correct') || str_starts_with($key, 'bonne_rep')) {
            return 'correct';
        }
        if (preg_match('/explanation|explication|justification|commentaire|correction/', $key)) {
            return 'explanation';
        }
        if ($key === 'type' || $key === 'question_type' || $key === 'type_question') {
            return 'question_type';
        }
        $tokens = explode('_', $key);
        $numberish = array_intersect($tokens, ['id', 'n', 'no', 'num', 'numero', 'nb', 'index', 'ordre', 'order', 'rang', 'number']);
        if (in_array($key, ['question', 'question_text', 'libelle', 'enonce', 'intitule', 'texte', 'text'], true)) {
            return 'question';
        }
        if (!$numberish && count($tokens) <= 3 && preg_match('/question|libelle|enonce|intitule/', $key)) {
            return 'question';
        }
        return 'ignore';
    }

    /**
     * Maps CSV columns to database fields using resolved header positions.
     *
     * @param array $header Normalized header names.
     * @param array $cols Raw row columns.
     * @return array Standardized question row array.
     */
    private static function mapRow(array $header, array $cols): array
    {
        $row = [
            'question_text'  => '',
            'option_a'       => '',
            'option_b'       => '',
            'option_c'       => '',
            'option_d'       => '',
            'correct_option' => '',
            'explanation'    => '',
            'question_type'  => '',
        ];
        $loose = [];
        foreach ($header as $i => $field) {
            $val = trim((string)($cols[$i] ?? ''));
            if ($field === 'question') {
                $row['question_text'] = $val;
            } elseif ($field === 'correct') {
                $row['correct_option'] = $val;
            } elseif (isset($row[$field])) {
                $row[$field] = $val;
            } elseif ($val !== '') {
                $loose[] = $val;
            }
        }
        // A question that is only a number came from a numbering column: take the longest unmapped text instead.
        if (preg_match('/^\d{1,4}[.)\-]?$/', $row['question_text']) && $loose) {
            usort($loose, fn($x, $y) => mb_strlen($y) <=> mb_strlen($x));
            if (mb_strlen($loose[0]) > 8) {
                $row['question_text'] = $loose[0];
            }
        }
        return $row;
    }

    /**
     * Maps CSV columns using default index positions (no header row detected).
     * 
     * @param array $cols Columns.
     * @return array Standardized question row array.
     */
    private static function mapRowDefault(array $cols): array
    {
        return [
            'question_text'  => trim((string)($cols[0] ?? '')),
            'option_a'       => trim((string)($cols[1] ?? '')),
            'option_b'       => trim((string)($cols[2] ?? '')),
            'option_c'       => trim((string)($cols[3] ?? '')),
            'option_d'       => trim((string)($cols[4] ?? '')),
            'correct_option' => trim((string)($cols[5] ?? '')),
            'explanation'    => trim((string)($cols[6] ?? '')),
            'question_type'  => trim((string)($cols[7] ?? '')),
        ];
    }

    // =========================================================================
    // SECTION 4: DATA NORMALIZATION AND VALIDATION
    // =========================================================================

    /**
     * Validates parsed question rows, resolving types (MCQs vs open written text).
     * 
     * @param array $rows Standardized rows list.
     * @return array{questions: array, errors: array} Valid questions and error logs list.
     */
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
            $rawCorrect = $row['correct_option'] ?? '';
            $co   = self::normalizeCorrect($rawCorrect);
            $exp  = trim($row['explanation'] ?? '');
            $type = isset($row['question_type']) ? trim((string)$row['question_type']) : '';

            // Ignore empty rows
            if ($q === '' && $a === '' && $b === '') {
                continue;
            }

            // Determine if question is open written text or multiple choice
            $isWritten = false;
            if ($type === 'written' || $type === 'calculation') {
                $isWritten = true;
                $co = trim($rawCorrect);
            } elseif ($a === '' && $b === '' && $c === '' && $d === '') {
                $isWritten = true;
                $co = trim($rawCorrect);
            } elseif ($rawCorrect !== '' && !in_array($co, ['A', 'B', 'C', 'D'], true)) {
                $isWritten = true;
                $co = trim($rawCorrect);
            }

            if (!$isWritten) {
                if ($q === '' || $a === '' || $b === '' || $c === '' || $d === '') {
                    $errors[] = "Ligne {$line} : champs de QCM incomplets.";
                    continue;
                }
                if (!in_array($co, ['A', 'B', 'C', 'D'], true)) {
                    $errors[] = "Ligne {$line} : réponse correcte QCM invalide (utilisez A, B, C ou D).";
                    continue;
                }
            } else {
                if ($q === '') {
                    $errors[] = "Ligne {$line} : énoncé de question ouverte vide.";
                    continue;
                }
                if ($co === '') {
                    $errors[] = "Ligne {$line} : réponse attendue de question ouverte vide.";
                    continue;
                }
            }

            $questions[] = [
                'question_text'  => $q,
                'option_a'       => $a,
                'option_b'       => $b,
                'option_c'       => $c,
                'option_d'       => $d,
                'correct_option' => $co,
                'explanation'    => $exp,
                'question_type'  => $isWritten ? 'written' : 'mcq',
            ];
        }

        return ['questions' => $questions, 'errors' => $errors];
    }

    /**
     * Normalizes MCQ option letters (A, B, C, D) and numeric options (1->A, etc.).
     * 
     * @param string $raw Raw correct answer string.
     * @return string Normalized key.
     */
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

    /**
     * Checks if a values array is entirely empty.
     * 
     * @param array $values Columns.
     * @return bool True if all cells are blank.
     */
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
