<?php
declare(strict_types=1);

/**
 * Rule-based marking of assignment copies. No AI and no outside service: it runs on the server and follows the rules the teacher set.
 * It PROPOSES a mark; the teacher confirms or changes it in the grading bucket (teacher/marking.php).
 *
 * The rules (lessons.assignment_marking_rules, JSON), all optional:
 *   strictness      lenient | normal | strict      spelling tolerance and partial credit
 *   tone            encouraging | neutral | direct  the voice of the written feedback
 *   model_answer    text the copies are compared with (TF-IDF cosine on words and word pairs)
 *   min_words       a copy shorter than this gets the "short" penalty
 *   sections        headings that must appear ("Introduction", "Conclusion")
 *   penalties       late, short, section (each missing one), similarity (when the copy is >= similarity_at % like another one)
 *   criteria        criterion id => {keys: [[variant, variant...], ...], need: how many key points give full points}
 *   templates       high, mid, low (the opening line by level), closing: the teacher's own words, used instead of the defaults
 *
 * What it does with one copy:
 *   1. the text is read (AssignmentSimilarity::extractText); a scan or a picture gives no text: "to mark by hand"
 *   2. words are normalised: lower case, no accents, stop words out, a light French/English stemmer ("intégrales", "integrale",
 *      "intégrer" all become "integr"); numbers keep their value ("3,14" = "3.14")
 *   3. each key point is looked for: exact, a variant, or a close spelling (edit distance allowed by the strictness); a key point of
 *      several words must have its words close together
 *   4. points per criterion from the share of key points found; a criterion with no key points uses the closeness to the model answer
 *   5. structure: length, required sections; penalties
 *   6. feedback built from what was found and what is missing, in the teacher's tone
 *   7. a confidence level and its reasons: short or unreadable text, criteria with nothing to go on, few key points but close to the
 *      model answer (other words, maybe right), far from the rest of the class
 *
 * What it cannot do, said plainly: it reads words, it does not follow reasoning. It is reliable for expected content (definitions,
 * steps, results, key notions); for essays it gives a first sort and the teacher's check makes the mark fair.
 */
final class MarkingEngine
{
    public const STRICTNESS = ['lenient', 'normal', 'strict'];
    public const TONES = ['encouraging', 'neutral', 'direct'];
    public const MIN_READABLE = 15;      // fewer words than this: treated as unreadable
    public const SHORT_WORDS = 60;       // below this the confidence drops
    public const MAX_TEXT = 60000;       // characters kept for the teacher's view

    private const STOP = [
        // French
        'a','au','aux','avec','ce','ces','cet','cette','dans','de','des','du','elle','elles','en','et','est','etre','eux','il','ils','je','la','le','les',
        'leur','leurs','lui','ma','mais','me','mes','meme','moi','mon','ne','nos','notre','nous','on','ou','par','pas','pour','qu','que','qui','sa','se',
        'ses','son','sont','sur','ta','te','tes','toi','ton','tu','un','une','vos','votre','vous','c','d','j','l','m','n','s','t','y','ete','sont','ont',
        'donc','ainsi','alors','car','comme','si','ni','soit','tout','tous','toute','toutes','plus','moins','tres','aussi','cela','ceci','ca','dont',
        // English
        'an','and','are','as','at','be','been','by','for','from','has','have','he','her','his','i','in','is','it','its','of','on','or','she','that',
        'the','their','them','then','there','these','they','this','to','was','we','were','which','who','will','with','you','your','not','but','so',
        'can','do','does','did','if','into','than','also','very',
    ];

    /** Suffixes stripped by the stemmer, longest first. A word keeps at least 4 letters. */
    private const SUFFIXES = [
        'issements','issement','atrices','ateurs','ations','ements','iques','ables','ibles','istes','ismes','euses','ement','ation','ateur','atrice',
        'ences','ances','ments','ities','ingly','iques','ique','able','ible','iste','isme','euse','eurs','ence','ance','ment','ites','ales','aux','ity',
        'ers','ive','ifs','ing','ies','ees','ait','ant','ent','ales','ale','eur','ite','ion','ons','ez','er','ir','es','ee','ed','al','if','s','x','e',
    ];

    // ---------------------------------------------------------------------------------------------------------------
    // Rules
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * Cleans the rules sent by the teacher's form, so what is stored is always well formed.
     *
     * @param array<int,array<string,mixed>> $criteria the rubric criteria of the lesson ([] for a lesson marked on one number)
     */
    public static function normalizeRules(array $raw, array $criteria): array
    {
        $num = static function ($v, float $min, float $max, float $def): float {
            $s = trim(str_replace(',', '.', (string)$v));
            return ($s !== '' && is_numeric($s)) ? max($min, min($max, round((float)$s, 2))) : $def;
        };
        $str = static fn($v, int $len) => trim(mb_substr(trim((string)$v), 0, $len));
        $out = [
            'strictness' => in_array($raw['strictness'] ?? '', self::STRICTNESS, true) ? $raw['strictness'] : 'normal',
            'tone' => in_array($raw['tone'] ?? '', self::TONES, true) ? $raw['tone'] : 'encouraging',
            'model_answer' => $str($raw['model_answer'] ?? '', 20000),
            'min_words' => (int)$num($raw['min_words'] ?? 0, 0, 20000, 0),
            'sections' => [],
            'penalties' => [
                'late' => $num($raw['penalties']['late'] ?? 0, 0, 1000, 0),
                'short' => $num($raw['penalties']['short'] ?? 0, 0, 1000, 0),
                'section' => $num($raw['penalties']['section'] ?? 0, 0, 1000, 0),
                'similarity' => $num($raw['penalties']['similarity'] ?? 0, 0, 1000, 0),
                'similarity_at' => $num($raw['penalties']['similarity_at'] ?? 60, 15, 100, 60),
            ],
            'criteria' => [],
            'templates' => [],
        ];
        foreach (self::lines($raw['sections'] ?? []) as $s) {
            if (count($out['sections']) < 12) {
                $out['sections'][] = mb_substr($s, 0, 80);
            }
        }
        foreach (['high', 'mid', 'low', 'closing'] as $k) {
            $t = $str($raw['templates'][$k] ?? '', 400);
            if ($t !== '') {
                $out['templates'][$k] = $t;
            }
        }
        $ids = $criteria === [] ? ['all'] : array_map(fn($c) => (string)$c['id'], $criteria);
        foreach ($ids as $id) {
            $c = $raw['criteria'][$id] ?? [];
            $keys = [];
            foreach (self::lines($c['keys'] ?? []) as $line) {
                $variants = array_values(array_filter(array_map(fn($v) => mb_substr(trim($v), 0, 120), explode('|', $line)), fn($v) => $v !== ''));
                if ($variants && count($keys) < 30) {
                    $keys[] = array_slice($variants, 0, 10);
                }
            }
            $need = (int)$num($c['need'] ?? 0, 0, 30, 0);
            $out['criteria'][$id] = ['keys' => $keys, 'need' => ($need > 0 && $need <= count($keys)) ? $need : count($keys)];
        }
        return $out;
    }

    /** Stored rules, or the defaults. */
    public static function decodeRules(?string $json, array $criteria): array
    {
        $d = $json ? json_decode($json, true) : null;
        if (is_array($d)) {
            // keys are stored as lists of variants; the normaliser reads them back from "a | b" lines
            foreach ((array)($d['criteria'] ?? []) as $id => $c) {
                $d['criteria'][$id]['keys'] = array_map(fn($k) => is_array($k) ? implode(' | ', $k) : (string)$k, (array)($c['keys'] ?? []));
            }
        }
        return self::normalizeRules(is_array($d) ? $d : [], $criteria);
    }

    /** True when the teacher gave the engine something to work with. */
    public static function hasRules(array $rules): bool
    {
        if ($rules['model_answer'] !== '') {
            return true;
        }
        foreach ($rules['criteria'] as $c) {
            if ($c['keys'] !== []) {
                return true;
            }
        }
        return false;
    }

    /** @return string[] non-empty lines of a textarea, or of a list */
    private static function lines(mixed $v): array
    {
        $list = is_array($v) ? $v : preg_split('/\R/u', (string)$v);
        return array_values(array_filter(array_map(fn($l) => trim((string)$l), (array)$list), fn($l) => $l !== ''));
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Words (pure functions, unit-tested)
    // ---------------------------------------------------------------------------------------------------------------

    public static function fold(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        return strtr($s, ['à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'î' => 'i',
            'ï' => 'i', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae', 'ñ' => 'n', 'ÿ' => 'y',
            '’' => "'"]);
    }

    /**
     * Tokens of a text: words and numbers, in order, with their original spelling.
     *
     * @return array<int,array{w:string,raw:string,num:?float}> w = the normalised form (stem, or the number)
     */
    public static function tokens(string $text, bool $keepStop = false): array
    {
        preg_match_all('/\d+(?:[.,]\d+)?|[\p{L}]+(?:[\'’-][\p{L}]+)*/u', $text, $m);
        $out = [];
        foreach ($m[0] as $raw) {
            if (preg_match('/^\d/', $raw)) {
                $v = (float)str_replace(',', '.', $raw);
                $out[] = ['w' => rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.'), 'raw' => $raw, 'num' => $v];
                continue;
            }
            $f = self::fold($raw);
            // l'intégrale, d'une: the elided article goes
            $f = preg_replace("/^(?:l|d|j|m|n|s|t|c|qu)'/u", '', $f);
            foreach (preg_split("/['-]/u", $f) as $part) {
                if ($part === '' || (!$keepStop && in_array($part, self::STOP, true))) {
                    continue;
                }
                $out[] = ['w' => self::stem($part), 'raw' => $raw, 'num' => null];
            }
        }
        return $out;
    }

    /** A light stemmer for French and English: enough for "intégrales", "integrale" and "intégrer" to meet. */
    public static function stem(string $w): string
    {
        if (mb_strlen($w) <= 4) {
            return $w;
        }
        foreach (self::SUFFIXES as $suf) {
            $ls = strlen($suf);
            if (strlen($w) - $ls >= 4 && str_ends_with($w, $suf)) {
                return substr($w, 0, -$ls);
            }
        }
        return $w;
    }

    /** How many letters may differ for two words to count as the same, by strictness and word length. */
    public static function tolerance(int $len, string $strictness): int
    {
        return match ($strictness) {
            'strict' => 0,
            'lenient' => $len >= 7 ? 2 : ($len >= 4 ? 1 : 0),
            default => $len >= 9 ? 2 : ($len >= 5 ? 1 : 0),
        };
    }

    /** @return int 0 exact, 1 close spelling, -1 no match */
    private static function same(array $tok, array $want, string $strictness): int
    {
        if ($want['num'] !== null || $tok['num'] !== null) {
            if ($want['num'] === null || $tok['num'] === null) {
                return -1;
            }
            $tol = $strictness === 'strict' ? 1e-9 : max(1e-9, abs($want['num']) * 0.005);
            return abs($tok['num'] - $want['num']) <= $tol ? 0 : -1;
        }
        if ($tok['w'] === $want['w']) {
            return 0;
        }
        $t = self::tolerance(min(strlen($tok['w']), strlen($want['w'])), $strictness);
        if ($t > 0 && abs(strlen($tok['w']) - strlen($want['w'])) <= $t && levenshtein($tok['w'], $want['w']) <= $t) {
            return 1;
        }
        return -1;
    }

    /**
     * Looks for one variant (one or more words) in the copy. The words of a variant must appear in order, close together.
     *
     * @return array{found:bool,fuzzy:bool,raw:string[]}
     */
    public static function findVariant(array $copy, string $variant, string $strictness): array
    {
        $want = self::tokens($variant);
        if ($want === []) {
            $want = self::tokens($variant, true);     // a key point made only of short words ("pH", "NaCl" are fine; "de la" is not)
        }
        if ($want === []) {
            return ['found' => false, 'fuzzy' => false, 'raw' => []];
        }
        $n = count($copy);
        $span = count($want) + 3;
        for ($i = 0; $i < $n; $i++) {
            $s = self::same($copy[$i], $want[0], $strictness);
            if ($s < 0) {
                continue;
            }
            $fuzzy = $s === 1;
            $raw = [$copy[$i]['raw']];
            $j = $i + 1;
            $ok = true;
            for ($k = 1; $k < count($want); $k++) {
                $hit = false;
                for (; $j < $n && $j < $i + $span; $j++) {
                    $s2 = self::same($copy[$j], $want[$k], $strictness);
                    if ($s2 >= 0) {
                        $fuzzy = $fuzzy || $s2 === 1;
                        $raw[] = $copy[$j]['raw'];
                        $j++;
                        $hit = true;
                        break;
                    }
                }
                if (!$hit) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return ['found' => true, 'fuzzy' => $fuzzy, 'raw' => array_values(array_unique($raw))];
            }
        }
        return ['found' => false, 'fuzzy' => false, 'raw' => []];
    }

    /**
     * TF-IDF vectors (words and word pairs) and their cosine. $idfDocs is the class: every copy plus the model answer.
     *
     * @param array<int,string[]> $idfDocs lists of normalised words
     */
    public static function cosine(array $a, array $b, array $idfDocs): float
    {
        $terms = static function (array $w): array {
            $t = [];
            foreach ($w as $i => $x) {
                $t[$x] = ($t[$x] ?? 0) + 1;
                if (isset($w[$i + 1])) {
                    $p = $x . ' ' . $w[$i + 1];
                    $t[$p] = ($t[$p] ?? 0) + 1;
                }
            }
            return $t;
        };
        $df = [];
        foreach ($idfDocs as $d) {
            foreach (array_keys($terms($d)) as $t) {
                $df[$t] = ($df[$t] ?? 0) + 1;
            }
        }
        $N = max(1, count($idfDocs));
        $vec = static function (array $tf) use ($df, $N): array {
            $v = [];
            foreach ($tf as $t => $c) {
                $v[$t] = (1 + log($c)) * (log(($N + 1) / (($df[$t] ?? 0) + 1)) + 1);
            }
            return $v;
        };
        $va = $vec($terms($a));
        $vb = $vec($terms($b));
        $dot = 0.0;
        foreach ($va as $t => $x) {
            if (isset($vb[$t])) {
                $dot += $x * $vb[$t];
            }
        }
        $na = sqrt(array_sum(array_map(fn($x) => $x * $x, $va)));
        $nb = sqrt(array_sum(array_map(fn($x) => $x * $x, $vb)));
        return ($na > 0 && $nb > 0) ? round($dot / ($na * $nb), 4) : 0.0;
    }

    /** Rounds to the nearest quarter of a point. */
    public static function quarter(float $v): float
    {
        return round($v * 4) / 4;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Marking one copy (pure: no database)
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * @param string|null $text     the copy's text, null when it could not be read
     * @param array       $criteria rubric criteria ([] = one mark out of $max)
     * @param array       $rules    normalised rules
     * @param array       $ctx      max (float), late (bool), similarity (float|null, %), lang (fr|en), idf (array<int,string[]>, the class)
     * @return array{ok:bool,error?:string,score?:float,rows?:array,penalties?:array,feedback?:string,confidence?:string,reasons?:string[],
     *               words?:int,model_similarity?:?float,highlights?:string[],coverage?:float}
     */
    public static function mark(?string $text, array $criteria, array $rules, array $ctx): array
    {
        $max = (float)($ctx['max'] ?? 20);
        $lang = ($ctx['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
        if ($text === null) {
            return ['ok' => false, 'error' => 'unreadable'];
        }
        $copy = self::tokens($text);
        $nWords = count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY));
        if (count($copy) < self::MIN_READABLE) {
            return ['ok' => false, 'error' => 'too_short', 'words' => $nWords];
        }
        $strict = $rules['strictness'];
        $words = array_column($copy, 'w');

        $modelSim = null;
        if ($rules['model_answer'] !== '') {
            $modelWords = array_column(self::tokens($rules['model_answer']), 'w');
            $docs = $ctx['idf'] ?? [];
            $docs[] = $modelWords;
            $docs[] = $words;
            $modelSim = self::cosine($words, $modelWords, $docs);
        }
        // Closeness to the model answer -> a share of the points. Honest copies of a good answer land around 0.35-0.6.
        $modelRatio = $modelSim === null ? null : max(0.0, min(1.0, ($modelSim - 0.08) / 0.45));

        $list = $criteria === [] ? [['id' => 'all', 'title' => $lang === 'en' ? 'Overall' : 'Ensemble', 'max' => $max]] : $criteria;
        $rows = [];
        $reasons = [];
        $conf = 1.0;
        $foundAll = 0;
        $keysAll = 0;
        $fuzzyHits = 0;
        $highlights = [];
        $blind = 0;
        foreach ($list as $c) {
            $cr = $rules['criteria'][$c['id']] ?? ['keys' => [], 'need' => 0];
            $cmax = (float)$c['max'];
            $found = [];
            $missing = [];
            foreach ($cr['keys'] as $variants) {
                $hit = null;
                foreach ($variants as $v) {
                    $r = self::findVariant($copy, $v, $strict);
                    if ($r['found']) {
                        $hit = $r;
                        break;
                    }
                }
                if ($hit) {
                    $found[] = $variants[0];
                    $fuzzyHits += $hit['fuzzy'] ? 1 : 0;
                    foreach ($hit['raw'] as $rw) {
                        $highlights[$rw] = true;
                    }
                } else {
                    $missing[] = $variants[0];
                }
            }
            $nKeys = count($cr['keys']);
            $foundAll += count($found);
            $keysAll += $nKeys;
            if ($nKeys > 0) {
                $ratio = min(1.0, count($found) / max(1, (int)$cr['need']));
                if ($strict === 'strict') {
                    $ratio = $ratio >= 1.0 ? 1.0 : ($ratio >= 0.5 ? 0.5 : 0.0);
                } elseif ($strict === 'lenient') {
                    $ratio = min(1.0, $ratio * 1.15);
                }
                $basis = 'keys';
            } elseif ($modelRatio !== null) {
                $ratio = $modelRatio;
                $basis = 'model';
            } else {
                $ratio = null;
                $basis = 'none';
                $blind++;
            }
            $rows[] = [
                'id' => $c['id'], 'title' => $c['title'], 'max' => $cmax,
                'points' => $ratio === null ? null : min($cmax, self::quarter($cmax * $ratio)),
                'found' => $found, 'missing' => $missing, 'basis' => $basis,
            ];
        }

        // Structure and penalties
        $p = $rules['penalties'];
        $penalties = [];
        if (!empty($ctx['late']) && $p['late'] > 0) {
            $penalties[] = ['code' => 'late', 'points' => $p['late']];
        }
        if ($rules['min_words'] > 0 && $nWords < $rules['min_words'] && $p['short'] > 0) {
            $penalties[] = ['code' => 'short', 'points' => $p['short'], 'detail' => (string)$nWords];
        }
        $missingSections = [];
        $all = $rules['sections'] ? self::tokens($text, true) : [];
        foreach ($rules['sections'] as $sec) {
            if (!self::findVariant($all, $sec, 'normal')['found']) {
                $missingSections[] = $sec;
            }
        }
        if ($missingSections && $p['section'] > 0) {
            foreach ($missingSections as $sec) {
                $penalties[] = ['code' => 'section', 'points' => $p['section'], 'detail' => $sec];
            }
        }
        $sim = $ctx['similarity'] ?? null;
        if ($sim !== null && $p['similarity'] > 0 && $sim >= $p['similarity_at']) {
            $penalties[] = ['code' => 'similarity', 'points' => $p['similarity'], 'detail' => (string)round($sim)];
        }

        $sum = 0.0;
        foreach ($rows as $r) {
            $sum += (float)($r['points'] ?? 0);
        }
        $penTotal = array_sum(array_column($penalties, 'points'));
        $score = max(0.0, min($max, self::quarter($sum - $penTotal)));

        // Confidence
        if ($nWords < self::SHORT_WORDS) {
            $conf -= 0.35;
            $reasons[] = 'short_text';
        }
        if ($blind > 0) {
            $conf -= 0.6;
            $reasons[] = 'no_rules';
        }
        $coverage = $keysAll > 0 ? $foundAll / $keysAll : null;
        if ($coverage !== null && $coverage < 0.4 && $modelSim !== null && $modelSim >= 0.35) {
            $conf -= 0.35;
            $reasons[] = 'other_words';
        }
        // The key points say the copy is good, the model answer says it is far: a correct answer in the student's own words, most likely
        $modelUsed = in_array('model', array_column($rows, 'basis'), true);
        if ($modelUsed && $coverage !== null && $coverage >= 0.6 && $modelSim !== null && $modelSim < 0.2) {
            $conf -= 0.4;
            $reasons[] = 'model_disagrees';
        }
        if ($foundAll >= 3 && $fuzzyHits / $foundAll > 0.5) {
            $conf -= 0.1;
            $reasons[] = 'spelling';
        }
        if ($sim !== null && $sim >= 30) {
            $conf -= 0.15;
            $reasons[] = 'similar';
        }

        return [
            'ok' => true, 'score' => $score, 'rows' => $rows, 'penalties' => $penalties, 'missing_sections' => $missingSections,
            'confidence' => self::level($conf), 'conf_value' => round($conf, 2), 'reasons' => $reasons,
            'words' => $nWords, 'model_similarity' => $modelSim, 'coverage' => $coverage, 'highlights' => array_keys($highlights),
            'feedback' => self::feedback($rows, $penalties, $missingSections, $rules, $max > 0 ? $score / $max : 0, $lang),
        ];
    }

    public static function level(float $conf): string
    {
        return $conf >= 0.75 ? 'high' : ($conf >= 0.45 ? 'medium' : 'low');
    }

    /** Lowers the confidence of copies far from the rest of the class (run once every copy has a proposal). */
    public static function classCheck(array $results, float $max): array
    {
        $scores = [];
        foreach ($results as $id => $r) {
            if (!empty($r['ok'])) {
                $scores[$id] = (float)$r['score'];
            }
        }
        if (count($scores) < 5 || $max <= 0) {
            return $results;
        }
        $sorted = array_values($scores);
        sort($sorted);
        $mid = intdiv(count($sorted), 2);
        $median = count($sorted) % 2 ? $sorted[$mid] : ($sorted[$mid - 1] + $sorted[$mid]) / 2;
        foreach ($scores as $id => $s) {
            if (abs($s - $median) > 0.3 * $max && !in_array('far_from_class', $results[$id]['reasons'], true)) {
                $results[$id]['reasons'][] = 'far_from_class';
                $results[$id]['conf_value'] = round((float)$results[$id]['conf_value'] - 0.2, 2);
                $results[$id]['confidence'] = self::level((float)$results[$id]['conf_value']);
            }
        }
        return $results;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Feedback in the teacher's tone
    // ---------------------------------------------------------------------------------------------------------------

    private const WORDS = [
        'fr' => [
            'encouraging' => [
                'high' => 'Très bon travail, bravo !', 'mid' => 'Bon travail, tu es sur la bonne voie.',
                'low' => 'Des bases sont là. Reprends le cours sur les points ci-dessous, tu peux progresser.',
                'found' => '%s : bien vu (%s).', 'missing' => '%s : pense à aborder %s.', 'all' => '%s : tout y est.',
                'closing' => 'Continue comme ça !',
            ],
            'neutral' => [
                'high' => 'Travail complet.', 'mid' => 'Travail correct, avec des manques.', 'low' => 'Travail insuffisant.',
                'found' => '%s : présent : %s.', 'missing' => '%s : absent : %s.', 'all' => '%s : complet.',
                'closing' => '',
            ],
            'direct' => [
                'high' => 'Bon travail.', 'mid' => 'Des éléments importants manquent.', 'low' => 'Le travail ne répond pas à la consigne sur l’essentiel.',
                'found' => '%s : %s.', 'missing' => '%s : il manque %s.', 'all' => '%s : complet.',
                'closing' => '',
            ],
            'pen' => ['late' => 'Rendu en retard', 'short' => 'Copie trop courte (%s mots)', 'section' => 'Partie attendue absente : %s', 'similarity' => 'Copie très proche d’une autre (%s %%)'],
            'penalty' => 'Pénalité : %s (−%s).',
        ],
        'en' => [
            'encouraging' => [
                'high' => 'Very good work, well done!', 'mid' => 'Good work, you are on the right track.',
                'low' => 'Some basics are there. Go back over the points below, you can improve.',
                'found' => '%s: well spotted (%s).', 'missing' => '%s: remember to cover %s.', 'all' => '%s: everything is there.',
                'closing' => 'Keep it up!',
            ],
            'neutral' => [
                'high' => 'Complete work.', 'mid' => 'Correct work, with gaps.', 'low' => 'Insufficient work.',
                'found' => '%s: present: %s.', 'missing' => '%s: missing: %s.', 'all' => '%s: complete.',
                'closing' => '',
            ],
            'direct' => [
                'high' => 'Good work.', 'mid' => 'Important elements are missing.', 'low' => 'The work misses the core of the task.',
                'found' => '%s: %s.', 'missing' => '%s: missing %s.', 'all' => '%s: complete.',
                'closing' => '',
            ],
            'pen' => ['late' => 'Handed in late', 'short' => 'Copy too short (%s words)', 'section' => 'Expected part missing: %s', 'similarity' => 'Copy very close to another one (%s %%)'],
            'penalty' => 'Penalty: %s (−%s).',
        ],
    ];

    public static function feedback(array $rows, array $penalties, array $missingSections, array $rules, float $ratio, string $lang): string
    {
        $W = self::WORDS[$lang === 'en' ? 'en' : 'fr'];
        $T = $W[$rules['tone']] ?? $W['encouraging'];
        $level = $ratio >= 0.8 ? 'high' : ($ratio >= 0.5 ? 'mid' : 'low');
        $lines = [$rules['templates'][$level] ?? $T[$level]];
        $list = static fn(array $a) => implode(', ', array_slice($a, 0, 4)) . (count($a) > 4 ? '…' : '');
        foreach ($rows as $r) {
            if ($r['basis'] !== 'keys') {
                continue;
            }
            if ($r['missing'] === []) {
                $lines[] = sprintf($T['all'], $r['title']);
                continue;
            }
            if ($r['found'] !== [] && $rules['tone'] !== 'direct') {
                $lines[] = sprintf($T['found'], $r['title'], $list($r['found']));
            }
            $lines[] = sprintf($T['missing'], $r['title'], $list($r['missing']));
        }
        foreach ($penalties as $p) {
            $what = sprintf($W['pen'][$p['code']], $p['detail'] ?? '');
            $lines[] = sprintf($W['penalty'], $what, rtrim(rtrim(number_format((float)$p['points'], 2, ',', ''), '0'), ','));
        }
        $closing = $rules['templates']['closing'] ?? $T['closing'];
        if ($closing !== '') {
            $lines[] = $closing;
        }
        return implode("\n", $lines);
    }
}
