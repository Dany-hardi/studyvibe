<?php
declare(strict_types=1);

/**
 * The marking grid ("barème", rubric) of an assignment.
 *
 * A rubric is a list of criteria. Each criterion has a title, an optional description, a maximum number of points and, if the
 * teacher wants, named levels ("Excellent : 4", "Correct : 2"...) that are shortcuts for a number of points.
 *
 * Stored as JSON on the lesson (lessons.assignment_rubric). When a piece of work is marked, a SNAPSHOT of the grid with the points
 * and comments given is stored on the submission (rubric_scores): editing the rubric later never changes marks already given.
 * The total of the criteria is the mark; the teacher's form never sends a total, the server adds the points up itself.
 */
final class AssignmentRubric
{
    public const MAX_CRITERIA = 20;
    public const MAX_LEVELS   = 8;
    public const MAX_TOTAL    = 1000.0;

    /**
     * Cleans what the lesson form sends.
     *
     * @param string|array|null $raw a JSON string or an already decoded list
     * @return array{ok:bool,criteria:array<int,array<string,mixed>>,total:float,error?:string}
     *         errors: bad_json, too_many, bad_title, bad_max, too_big, bad_level
     */
    public static function normalize(string|array|null $raw): array
    {
        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '' || $raw === '[]' || $raw === 'null') {
                return ['ok' => true, 'criteria' => [], 'total' => 0.0];
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return ['ok' => false, 'criteria' => [], 'total' => 0.0, 'error' => 'bad_json'];
            }
            $raw = $decoded;
        }
        $raw = array_values($raw ?? []);
        if (count($raw) > self::MAX_CRITERIA) {
            return ['ok' => false, 'criteria' => [], 'total' => 0.0, 'error' => 'too_many'];
        }
        $out = [];
        $ids = [];
        $total = 0.0;
        foreach ($raw as $i => $c) {
            if (!is_array($c)) {
                return ['ok' => false, 'criteria' => [], 'total' => 0.0, 'error' => 'bad_json'];
            }
            $title = trim(mb_substr(trim((string)($c['title'] ?? '')), 0, 120));
            if ($title === '') {
                return ['ok' => false, 'criteria' => [], 'total' => 0.0, 'error' => 'bad_title'];
            }
            $max = self::number($c['max'] ?? null);
            if ($max === null || $max <= 0) {
                return ['ok' => false, 'criteria' => [], 'total' => 0.0, 'error' => 'bad_max'];
            }
            $levels = [];
            foreach (array_slice(array_values((array)($c['levels'] ?? [])), 0, self::MAX_LEVELS + 1) as $l) {
                $label = trim(mb_substr(trim((string)($l['label'] ?? '')), 0, 60));
                $pts = self::number($l['points'] ?? null);
                if ($label === '' && $pts === null) {
                    continue;   // an empty line of the form
                }
                if ($label === '' || $pts === null || $pts < 0 || $pts > $max) {
                    return ['ok' => false, 'criteria' => [], 'total' => 0.0, 'error' => 'bad_level'];
                }
                $levels[] = ['label' => $label, 'points' => $pts, 'description' => trim(mb_substr(trim((string)($l['description'] ?? '')), 0, 200))];
            }
            if (count($levels) > self::MAX_LEVELS) {
                return ['ok' => false, 'criteria' => [], 'total' => 0.0, 'error' => 'bad_level'];
            }
            usort($levels, fn($a, $b) => $b['points'] <=> $a['points']);
            $id = (string)($c['id'] ?? '');
            if (!preg_match('/^c\d{1,3}$/', $id) || isset($ids[$id])) {
                $n = $i + 1;
                while (isset($ids['c' . $n])) {
                    $n++;
                }
                $id = 'c' . $n;
            }
            $ids[$id] = true;
            $total += $max;
            $out[] = [
                'id' => $id, 'title' => $title,
                'description' => trim(mb_substr(trim((string)($c['description'] ?? '')), 0, 300)),
                'max' => $max, 'levels' => $levels,
            ];
        }
        $total = round($total, 2);
        if ($total > self::MAX_TOTAL) {
            return ['ok' => false, 'criteria' => [], 'total' => 0.0, 'error' => 'too_big'];
        }
        return ['ok' => true, 'criteria' => $out, 'total' => $total];
    }

    public static function encode(array $criteria): ?string
    {
        return $criteria === [] ? null : json_encode(array_values($criteria), JSON_UNESCAPED_UNICODE);
    }

    /** @return array<int,array<string,mixed>> the criteria stored on a lesson, [] when there is no rubric */
    public static function decode(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $r = self::normalize($json);
        return $r['ok'] ? $r['criteria'] : [];
    }

    public static function total(array $criteria): float
    {
        return round(array_sum(array_map(fn($c) => (float)$c['max'], $criteria)), 2);
    }

    /**
     * Adds up the points a teacher gave. Every criterion must be given a number between 0 and its maximum.
     *
     * @param array<string,array{points?:mixed,comment?:mixed}> $input criterion id => what the form sent
     * @return array{ok:bool,total?:float,rows?:array<int,array<string,mixed>>,error?:string} errors: incomplete, bad_points
     */
    public static function score(array $criteria, array $input): array
    {
        $rows = [];
        $total = 0.0;
        foreach ($criteria as $c) {
            $given = $input[$c['id']] ?? null;
            $raw = is_array($given) ? trim((string)($given['points'] ?? '')) : '';
            if ($raw === '') {
                return ['ok' => false, 'error' => 'incomplete'];
            }
            $pts = self::number($raw);
            if ($pts === null || $pts < 0 || $pts > (float)$c['max']) {
                return ['ok' => false, 'error' => 'bad_points'];
            }
            $level = null;
            foreach ($c['levels'] as $l) {
                if (abs((float)$l['points'] - $pts) < 0.005) {
                    $level = $l['label'];
                    break;
                }
            }
            $total += $pts;
            $rows[] = [
                'id' => $c['id'], 'title' => $c['title'], 'max' => (float)$c['max'], 'points' => $pts, 'level' => $level,
                'comment' => trim(mb_substr(trim((string)($given['comment'] ?? '')), 0, 500)),
            ];
        }
        return ['ok' => true, 'total' => round($total, 2), 'rows' => $rows];
    }

    /** @return array<int,array<string,mixed>> the snapshot stored on a submission, [] when it was marked without a rubric */
    public static function decodeScores(?string $json): array
    {
        $d = $json !== null && $json !== '' ? json_decode($json, true) : null;
        return is_array($d) ? array_values($d) : [];
    }

    /** "4", "4.5" and "4,5" -> float rounded to 2 decimals, null when it is not a number. */
    public static function number(mixed $v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return round((float)$v, 2);
        }
        $s = trim(str_replace(',', '.', (string)$v));
        return ($s !== '' && is_numeric($s)) ? round((float)$s, 2) : null;
    }
}
