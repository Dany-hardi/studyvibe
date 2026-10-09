<?php
declare(strict_types=1);

/**
 * How a live-evaluation answer is judged, and how options are shuffled per student.
 *
 * Every page that shows or computes a live score asks this class, so the exam room, the results page, the PDF and the
 * emails can never disagree about whether an answer was right.
 *
 * Written answers: the stored answer may list several accepted answers separated by "|" (for example "x^2|x²").
 * An accepted answer written as "2.5~0.1" accepts any number from 2.4 to 2.6. Commas and spaces are ignored,
 * and when both sides are numbers they are compared as numbers (so "2.50" equals "2.5").
 */
final class LiveScoring
{
    public static function isCorrect(string $type, string $selected, string $correct): bool
    {
        if ($type === 'written') {
            return self::isCorrectWritten($selected, $correct);
        }
        return $selected !== '' && $selected === $correct;
    }

    public static function isCorrectWritten(string $selected, string $correct): bool
    {
        $given = self::normalise($selected);
        if ($given === '') {
            return false;
        }
        foreach (explode('|', $correct) as $accepted) {
            $tolerance = 0.0;
            if (str_contains($accepted, '~')) {
                [$accepted, $tol] = explode('~', $accepted, 2);
                $tol = str_replace(',', '.', trim($tol));
                $tolerance = is_numeric($tol) ? abs((float)$tol) : 0.0;
            }
            $want = self::normalise($accepted);
            if ($want === '') {
                continue;
            }
            if (is_numeric($given) && is_numeric($want)) {
                if (abs((float)$given - (float)$want) <= $tolerance + 1e-9) {
                    return true;
                }
            } elseif ($given === $want) {
                return true;
            }
        }
        return false;
    }

    private static function normalise(string $v): string
    {
        return str_replace([',', ' '], ['.', ''], mb_strtolower(trim($v)));
    }

    /** Shuffling only makes sense for a full A to D question: a true/false question keeps "Vrai" before "Faux". */
    public static function canShuffle(array $q): bool
    {
        if (($q['question_type'] ?? 'mcq') !== 'mcq') {
            return false;
        }
        foreach (['a', 'b', 'c', 'd'] as $l) {
            if (trim((string)($q['option_' . $l] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * The accepted answers of a written question in words, for documents a person reads: "2.5|5/2" becomes "2,5 ou 5/2" and
     * "2.5~0.1" becomes "2,5 (± 0,1)".
     */
    public static function displayAnswer(string $stored, string $lang = 'fr'): string
    {
        $out = [];
        foreach (explode('|', $stored) as $alt) {
            $alt = trim($alt);
            if ($alt === '') {
                continue;
            }
            $tol = '';
            if (str_contains($alt, '~')) {
                [$alt, $t] = explode('~', $alt, 2);
                $tol = ' (± ' . trim($t) . ')';
            }
            $out[] = trim($alt) . $tol;
        }
        $glue = $lang === 'en' ? ' or ' : ' ou ';
        return implode($glue, $out);
    }

    /**
     * A question as one particular student saw it: when the session shuffled the options, the four texts come back in the
     * order that student had on screen, and the correct letter and the student's answer are moved to match. Used by the
     * results page, the PDF report and the results email, so what the student reads there is what they saw in the exam.
     * Scoring is not affected: a letter and its text move together.
     *
     * @param array $q needs option_a..option_d, correct_option and the question id under 'question_id' or 'id'
     * @return array the same array with option_a..d, correct_option and selected_option rewritten
     */
    public static function asSeen(array $q, string $selected, int $registrationId, bool $shuffle): array
    {
        $q['selected_option'] = $selected;
        if (!$shuffle || !self::canShuffle($q)) {
            return $q;
        }
        $qid = (int)($q['question_id'] ?? $q['id'] ?? 0);
        $order = self::optionOrder($registrationId, $qid);   // display position => original letter
        $letters = ['A', 'B', 'C', 'D'];
        $original = [];
        foreach ($letters as $l) {
            $original[$l] = (string)($q['option_' . strtolower($l)] ?? '');
        }
        $position = array_flip($order);                      // original letter => display position
        foreach ($order as $i => $orig) {
            $q['option_' . strtolower($letters[$i])] = $original[$orig];
        }
        $q['correct_option'] = isset($position[(string)$q['correct_option']]) ? $letters[$position[(string)$q['correct_option']]] : $q['correct_option'];
        $q['selected_option'] = isset($position[$selected]) ? $letters[$position[$selected]] : $selected;
        return $q;
    }

    /**
     * The order in which one student sees options A to D for one question. The same student always gets the same order
     * (the poll runs every second), and two students almost always get different ones.
     *
     * @return string[] the original letters in display order, e.g. ['C','A','D','B']
     */
    public static function optionOrder(int $registrationId, int $questionId): array
    {
        $letters = ['A', 'B', 'C', 'D'];
        $seed = crc32($registrationId . ':' . $questionId);
        // Fisher-Yates with a small linear congruential generator, so the result does not depend on PHP's mt_srand state
        for ($i = 3; $i > 0; $i--) {
            $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
            $j = $seed % ($i + 1);
            [$letters[$i], $letters[$j]] = [$letters[$j], $letters[$i]];
        }
        return $letters;
    }
}
