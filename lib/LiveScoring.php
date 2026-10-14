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
        if ($type === 'code' || $type === 'open') {
            // A code or written-answer answer is marked afterwards: its stored "selected option" is the verdict (CODE_OK / CODE_KO / CODE_PENDING)
            return $selected === 'CODE_OK';
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

    /**
     * Computes and saves the final score of a registration and queues the results email (once). A student whose code answers are still being
     * marked gets a provisional score and no email yet: LiveCodeReview calls this again when the last one is marked.
     * @return float score in percent
     */
    public static function finalize(PDO $pdo, array $session, array $registration, array $questions): float
    {
        $regId = (int)$registration['id'];
        $stmt = $pdo->prepare("SELECT question_id, selected_option FROM live_eval_answers WHERE registration_id = :rid");
        $stmt->execute(['rid' => $regId]);
        $submitted = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $correctCount = 0;
        $total = count($questions);
        $qasDetails = [];
        foreach ($questions as $q) {
            $selected = (string)($submitted[$q['id']] ?? '');
            if (self::isCorrect((string)($q['question_type'] ?? 'mcq'), $selected, (string)$q['correct_option'])) {
                $correctCount++;
            }
            // The email shows the options in the order this student saw them
            $seen = self::asSeen($q, $selected, $regId, !empty($session['shuffle_options']));
            $qasDetails[] = [
                'question_text' => $seen['question_text'], 'option_a' => $seen['option_a'], 'option_b' => $seen['option_b'],
                'option_c' => $seen['option_c'], 'option_d' => $seen['option_d'], 'correct_option' => $seen['correct_option'],
                'selected_option' => $seen['selected_option'], 'question_type' => $q['question_type'] ?? 'mcq',
            ];
        }
        $score = $total > 0 ? round(($correctCount / $total) * 100, 2) : 100.00;
        $pdo->prepare("UPDATE live_eval_registrations SET score = :score WHERE id = :id")->execute(['score' => $score, 'id' => $regId]);

        $waiting = 0;
        try {
            $w = $pdo->prepare("SELECT COUNT(*) FROM live_eval_code_reviews WHERE registration_id = :r AND status IN ('pending', 'working')");
            $w->execute(['r' => $regId]);
            $waiting = (int)$w->fetchColumn();
        } catch (Throwable $e) { /* table absent on a schema that has not migrated yet */ }
        if ($waiting > 0) {
            return (float)$score;
        }

        // The results email is queued and sent in the background (see lib/LiveMailQueue.php): sending it here made every
        // student wait 1 to 3 seconds for the mail server, all at the same moment, when the exam ended.
        require_once __DIR__ . '/LiveMailQueue.php';
        try {
            LiveMailQueue::enqueue($pdo, $regId, [
                'email' => $registration['email'], 'name' => $registration['name'], 'session_title' => $session['title'],
                'correct' => $correctCount, 'total' => $total, 'qas' => $qasDetails,
            ]);
        } catch (Throwable $e) { error_log('live mail queue: ' . $e->getMessage()); }
        return (float)$score;
    }

    /** Shuffling only makes sense for a full A to D question: a true/false question keeps "Vrai" before "Faux". */
    public static function canShuffle(array $q): bool
    {
        if (($q['question_type'] ?? 'mcq') !== 'mcq') {   // written and code questions have no options to shuffle
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
