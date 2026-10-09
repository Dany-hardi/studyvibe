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
