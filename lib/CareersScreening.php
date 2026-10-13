<?php
declare(strict_types=1);

require_once __DIR__ . '/ResumeText.php';
require_once __DIR__ . '/MediaStore.php';

/**
 * The ATS screening engine. Reads each application against the posting it was sent to and answers three things: a score from 0 to 100,
 * a verdict (strong | potential | review | weak) and a bucket (inbox | screened_out), with the full reasoning kept in the application.
 *
 * Principles
 *   1. A human always has the last word. "screened_out" is a holding area the administrator reviews, restores, archives or deletes;
 *      nothing is rejected by the engine alone unless the posting enables the optional automatic polite decline after N days.
 *   2. When in doubt, a person looks. A résumé that could not be read, a spontaneous application, a posting without screening, or too
 *      little information all give "review" and stay in the inbox. Only a readable file that clearly misses the posting is held back.
 *   3. Only job-related facts are used: keywords the posting asks for, experience, education, role alignment, how complete the file is,
 *      working place. No name, age, gender, nationality or photo is read or collected.
 *   4. Every point is explained: matched and missing keywords, each criterion's share, and flags, so the administrator can agree or overrule.
 *
 * Criteria and weights (shares are normalised over the criteria that apply to the posting, so an unused criterion costs nothing)
 *   must      40  keywords the posting requires (its "must have" list, or ones derived from its requirements when the list is empty);
 *                 a line starting with "!" is a knock-out: missing it holds the application back whatever the rest says
 *   nice      10  keywords that are a plus
 *   years     15  years of experience against the posting's minimum (from the form, or read in the résumé)
 *   education 10  highest level against the posting's minimum
 *   title     10  the posting's title against the person's current title and the top of the résumé
 *   complete  10  a readable résumé, a letter, skills, links, a phone number
 *   place      5  remote posting, same place, willing to relocate or to work on site
 *   Penalties: disposable e-mail address, a résumé that is nearly empty, keyword stuffing, spam-like text.
 *
 * Re-run any time (criteria changed, engine updated): screenApplication(). A bucket chosen by an administrator is never undone by it.
 */
final class CareersScreening
{
    public const VERSION = 1;
    public const STRONG = 70;

    private const EDU = ['highschool' => 1, 'associate' => 2, 'bachelor' => 3, 'master' => 4, 'doctorate' => 5];

    private const STOP = [
        'avec', 'dans', 'pour', 'sans', 'sous', 'sur', 'vers', 'chez', 'plus', 'tres', 'etre', 'avoir', 'faire', 'sont', 'nous', 'vous', 'leur', 'leurs', 'cette', 'cela', 'comme', 'aussi', 'ainsi',
        'dont', 'entre', 'afin', 'tout', 'tous', 'toute', 'toutes', 'bien', 'peut', 'sera', 'ete', 'etes', 'des', 'les', 'une', 'aux', 'ces', 'ses', 'son', 'mon', 'ton', 'que', 'qui', 'quoi', 'par', 'pas',
        'the', 'and', 'for', 'with', 'you', 'your', 'our', 'are', 'will', 'have', 'has', 'from', 'that', 'this', 'their', 'they', 'who', 'what', 'when', 'where', 'into', 'than', 'then', 'also', 'such',
        'able', 'work', 'working', 'team', 'role', 'years', 'year', 'experience', 'experiences', 'knowledge', 'skills', 'skill', 'strong', 'good', 'great', 'excellent', 'ability', 'candidate', 'candidates',
        'must', 'should', 'need', 'needs', 'looking', 'join', 'help', 'make', 'build', 'across', 'within', 'least', 'minimum', 'plus', 'etc', 'poste', 'equipe', 'mission', 'missions', 'profil', 'annees', 'annee',
        'experience', 'maitrise', 'connaissance', 'connaissances', 'capacite', 'esprit', 'sens', 'bonne', 'bonnes', 'bon', 'avoir', 'minimum', 'niveau', 'rejoindre', 'travail', 'travailler',
    ];
    private const SHORT_OK = ['php', 'sql', 'css', 'html', 'js', 'api', 'aws', 'git', 'ux', 'ui', 'seo', 'erp', 'crm', 'qa', 'ci', 'cd', 'c++', 'c#', 'vue', 'java', 'go', 'ios', 'ml', 'ai', 'bi', 'hr', 'rh'];
    private const DISPOSABLE = ['mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'yopmail.com', 'trashmail.com', 'getnada.com', 'sharklasers.com', 'dispostable.com', 'throwawaymail.com', 'maildrop.cc', 'fakeinbox.com'];

    // -----------------------------------------------------------------------------------------------------------------
    // Text and keywords
    // -----------------------------------------------------------------------------------------------------------------

    /** Lower case, no accents, punctuation turned into spaces except the signs that matter in skills (c++, c#, node.js, .net). */
    public static function norm(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        if (class_exists('Normalizer')) {
            $n = Normalizer::normalize($s, Normalizer::FORM_D);
            $s = $n !== false ? (string)preg_replace('/\p{Mn}+/u', '', $n) : $s;
        } else {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            $s = $t !== false ? $t : $s;
        }
        $s = preg_replace('/[^a-z0-9+#.\s]/u', ' ', $s) ?? '';
        return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    }

    /** @return array{label:string,alts:string[],knockout:bool}[] */
    public static function parseKeywords(?string $text): array
    {
        $out = [];
        foreach (preg_split('/[\n;]+/', (string)$text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $ko = false;
            if ($line[0] === '!') {
                $ko = true;
                $line = trim(substr($line, 1));
            }
            // "php, laravel" on one line are two keywords; "a|b" are alternatives of one keyword
            $parts = str_contains($line, '|') ? [$line] : preg_split('/\s*,\s*/', $line);
            foreach ($parts as $p) {
                $alts = array_values(array_filter(array_map(static fn($a) => self::norm($a), explode('|', $p)), static fn($a) => $a !== ''));
                if ($alts) {
                    $out[] = ['label' => trim(explode('|', $p)[0]), 'alts' => $alts, 'knockout' => $ko];
                }
            }
            if (count($out) >= 40) {
                break;
            }
        }
        return $out;
    }

    /** When a posting lists no keywords: the significant words of its requirements, responsibilities and title. */
    public static function deriveKeywords(array $job, int $max = 10): array
    {
        $freq = [];
        $weights = [['requirements', 3], ['title', 4], ['nice_to_have', 1], ['responsibilities', 2], ['summary', 1], ['description', 1]];
        foreach ($weights as [$field, $w]) {
            foreach (explode(' ', self::norm((string)($job[$field] ?? ''))) as $t) {
                $t = trim($t, '.');
                if ($t === '' || in_array($t, self::STOP, true) || ctype_digit($t)) {
                    continue;
                }
                if (strlen($t) < 4 && !in_array($t, self::SHORT_OK, true)) {
                    continue;
                }
                $t = preg_replace('/(?:s|x)$/', '', $t) ?: $t;
                $freq[$t] = ($freq[$t] ?? 0) + $w;
            }
        }
        arsort($freq);
        $out = [];
        foreach (array_slice(array_keys($freq), 0, $max) as $t) {
            $out[] = ['label' => $t, 'alts' => [$t], 'knockout' => false, 'derived' => true];
        }
        return $out;
    }

    /** True when the keyword (or one of its alternatives) is in the normalised text; also returns how many times. */
    private static function count(string $corpus, array $kw): int
    {
        $n = 0;
        foreach ($kw['alts'] as $a) {
            // every word may carry a plural mark: "développeurs web" matches "developpeur web"
            $words = array_filter(explode(' ', $a), static fn($w) => $w !== '');
            $parts = [];
            foreach ($words as $w) {
                $stem = strlen($w) > 3 ? (preg_replace('/(?:s|es|x)$/', '', $w) ?: $w) : $w;
                $parts[] = preg_quote($stem, '/') . (strlen($w) > 3 ? '(?:s|es|x)?' : '');
            }
            if (!$parts || strlen($a) < 2) {
                continue;
            }
            $n += (int)preg_match_all('/(?<![a-z0-9])' . implode('\s+', $parts) . '(?![a-z0-9])/', $corpus);
        }
        return $n;
    }

    private static function eduRank(string $level): int
    {
        return self::EDU[$level] ?? 0;
    }

    private static function eduFromText(string $corpus): int
    {
        $best = 0;
        $map = ['doctorate' => '/\b(phd|doctorat|doctorate|docteur)\b/', 'master' => '/\b(master|msc|m\.sc|mba|ingenieur|engineer degree|bac\s?\+\s?5)\b/',
                'bachelor' => '/\b(licence|bachelor|bsc|b\.sc|bac\s?\+\s?3|degree|diplome)\b/', 'associate' => '/\b(bts|dut|hnd|associate|bac\s?\+\s?2)\b/', 'highschool' => '/\b(baccalaureat|high school|bac)\b/'];
        foreach ($map as $lvl => $re) {
            if (preg_match($re, $corpus)) {
                $best = max($best, self::EDU[$lvl]);
            }
        }
        return $best;
    }

    private static function yearsFromText(string $corpus): ?int
    {
        $best = null;
        if (preg_match_all('/(\d{1,2})\s?\+?\s*(?:years?|yrs?|ans|annees?)\b/', $corpus, $m)) {
            foreach ($m[1] as $y) {
                $y = (int)$y;
                if ($y >= 1 && $y <= 45) {
                    $best = max($best ?? 0, $y);
                }
            }
        }
        return $best;
    }

    // -----------------------------------------------------------------------------------------------------------------
    // The evaluation (pure: no database)
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @param array|null $job  posting row (null for an open application)
     * @param array      $app  application row (profile fields, cover_letter, email, location)
     * @return array{score:int,verdict:string,bucket:string,report:array}
     */
    public static function evaluate(?array $job, array $app, string $resumeText, bool $readable): array
    {
        $resume = self::norm($resumeText);
        $profile = self::norm(implode(' ', [(string)($app['skills'] ?? ''), (string)($app['current_title'] ?? ''), (string)($app['current_company'] ?? ''), (string)($app['languages'] ?? ''), (string)($app['cover_letter'] ?? '')]));
        $corpus = trim($resume . ' ' . $profile);
        $flags = [];
        $groups = [];
        $matched = []; $missing = []; $niceMatched = []; $niceMissing = []; $knock = [];
        $stuffHits = 0;

        // ---- completeness (always applies)
        $cl = mb_strlen((string)($app['cover_letter'] ?? ''));
        $pts = 0;
        $pts += $readable ? (mb_strlen($resumeText) >= 600 ? 4 : 2) : 0;
        $pts += $cl >= 80 ? 2 : ($cl >= 20 ? 1 : 0);
        $pts += trim((string)($app['skills'] ?? '')) !== '' ? 1 : 0;
        $pts += (trim((string)($app['linkedin_url'] ?? '')) !== '' || trim((string)($app['portfolio_url'] ?? '')) !== '') ? 1 : 0;
        $pts += isset($app['years_experience']) && $app['years_experience'] !== null && $app['years_experience'] !== '' ? 1 : 0;
        $pts += trim((string)($app['phone'] ?? '')) !== '' ? 1 : 0;
        $groups['complete'] = ['max' => 10, 'pts' => min(10, $pts), 'on' => true];

        if ($job === null) {
            // An open application has no criteria to be read against: a person decides.
            $score = (int)round($groups['complete']['pts'] / 10 * 100);
            return ['score' => $score, 'verdict' => 'review', 'bucket' => 'inbox', 'report' => [
                'v' => self::VERSION, 'score' => $score, 'verdict' => 'review', 'readable' => $readable, 'groups' => $groups, 'matched' => [], 'missing' => [],
                'nice_matched' => [], 'nice_missing' => [], 'knockouts' => [], 'flags' => [['code' => 'open_application']], 'derived' => false,
            ]];
        }

        // ---- required keywords
        $must = self::parseKeywords($job['must_have'] ?? '');
        $derived = false;
        if (!$must) {
            $must = self::deriveKeywords($job);
            $derived = true;
        }
        if ($must) {
            $hit = 0;
            foreach ($must as $kw) {
                $c = self::count($corpus, $kw);
                if ($c > 0) {
                    $hit++;
                    $matched[] = $kw['label'];
                    $stuffHits += $c;
                } else {
                    $missing[] = $kw['label'];
                    if (!empty($kw['knockout'])) {
                        $knock[] = $kw['label'];
                    }
                }
            }
            $groups['must'] = ['max' => 40, 'pts' => round(40 * $hit / count($must), 1), 'on' => true];
        } else {
            $groups['must'] = ['max' => 40, 'pts' => 0, 'on' => false];
        }

        // ---- nice to have
        $nice = self::parseKeywords($job['nice_have'] ?? '');
        if ($nice) {
            $hit = 0;
            foreach ($nice as $kw) {
                if (self::count($corpus, $kw) > 0) {
                    $hit++;
                    $niceMatched[] = $kw['label'];
                } else {
                    $niceMissing[] = $kw['label'];
                }
            }
            $groups['nice'] = ['max' => 10, 'pts' => round(10 * $hit / count($nice), 1), 'on' => true];
        } else {
            $groups['nice'] = ['max' => 10, 'pts' => 0, 'on' => false];
        }

        // ---- experience
        $minY = (int)($job['min_years'] ?? 0);
        if ($minY > 0) {
            $y = ($app['years_experience'] ?? null) !== null && $app['years_experience'] !== '' ? (int)$app['years_experience'] : null;
            $inferred = false;
            if ($y === null) {
                $y = self::yearsFromText($corpus);
                $inferred = $y !== null;
            }
            if ($y === null) {
                $groups['years'] = ['max' => 15, 'pts' => 7.5, 'on' => true];
                $flags[] = ['code' => 'years_unknown'];
            } else {
                $groups['years'] = ['max' => 15, 'pts' => round(15 * min(1, $y / $minY), 1), 'on' => true, 'value' => $y, 'need' => $minY, 'inferred' => $inferred];
            }
        } else {
            $groups['years'] = ['max' => 15, 'pts' => 0, 'on' => false];
        }

        // ---- education
        $minE = self::eduRank((string)($job['min_education'] ?? ''));
        if ($minE > 0) {
            $e = self::eduRank((string)($app['education_level'] ?? ''));
            $inferred = false;
            if ($e === 0) {
                $e = self::eduFromText($corpus);
                $inferred = $e > 0;
            }
            $share = $e === 0 ? 0.5 : ($e >= $minE ? 1.0 : ($e === $minE - 1 ? 0.5 : 0.0));
            $groups['education'] = ['max' => 10, 'pts' => round(10 * $share, 1), 'on' => true, 'have' => $e, 'need' => $minE, 'inferred' => $inferred];
            if ($e === 0) {
                $flags[] = ['code' => 'education_unknown'];
            }
        } else {
            $groups['education'] = ['max' => 10, 'pts' => 0, 'on' => false];
        }

        // ---- role alignment
        $titleTokens = array_values(array_filter(explode(' ', self::norm((string)$job['title'])), static fn($t) => strlen($t) >= 3 && !in_array($t, self::STOP, true)));
        if ($titleTokens) {
            $top = self::norm(mb_substr($resumeText, 0, 800) . ' ' . (string)($app['current_title'] ?? '') . ' ' . (string)($app['skills'] ?? ''));
            $found = 0;
            foreach ($titleTokens as $t) {
                if (self::count($top, ['alts' => [$t]]) > 0) {
                    $found++;
                }
            }
            $share = $found === 0 ? 0.0 : min(1.0, 0.5 + 0.5 * $found / count($titleTokens));
            $groups['title'] = ['max' => 10, 'pts' => round(10 * $share, 1), 'on' => true];
        } else {
            $groups['title'] = ['max' => 10, 'pts' => 0, 'on' => false];
        }

        // ---- place
        $mode = (string)($job['work_mode'] ?? 'onsite');
        $loc = self::norm((string)($app['location'] ?? ''));
        $city = self::norm(explode(',', (string)($job['location'] ?? ''))[0]);
        if ($mode === 'remote' || $city === '') {
            $groups['place'] = ['max' => 5, 'pts' => 5, 'on' => false];
        } else {
            $same = $loc !== '' && str_contains($loc, $city);
            $share = $same || !empty($app['relocate']) ? 1.0 : (($app['work_pref'] ?? '') === 'remote' ? 0.0 : 0.5);
            $groups['place'] = ['max' => 5, 'pts' => 5 * $share, 'on' => true];
        }

        // ---- sum, normalised over the criteria that apply
        $sum = 0.0; $den = 0.0;
        foreach ($groups as $g) {
            if ($g['on']) {
                $sum += $g['pts'];
                $den += $g['max'];
            }
        }
        $score = $den > 0 ? $sum / $den * 100 : 0.0;

        // ---- penalties
        $domain = strtolower(substr(strrchr((string)($app['email'] ?? ''), '@') ?: '', 1));
        if ($domain !== '' && in_array($domain, self::DISPOSABLE, true)) {
            $score -= 15;
            $flags[] = ['code' => 'disposable_email'];
        }
        if ($readable && mb_strlen($resumeText) < 300) {
            $score -= 10;
            $flags[] = ['code' => 'thin_resume'];
        }
        $words = max(1, count(explode(' ', $corpus)));
        if ($readable && $stuffHits > 25 && $stuffHits / $words > 0.18) {
            $score -= 15;
            $flags[] = ['code' => 'keyword_stuffing'];
        }
        $letter = (string)($app['cover_letter'] ?? '');
        if ($letter !== '' && (preg_match_all('~https?://|www\.~i', $letter) > 6 || preg_match('/(.)\1{9,}/u', $letter))) {
            $score -= 20;
            $flags[] = ['code' => 'spam_text'];
        }
        if (!$readable) {
            $flags[] = ['code' => 'unreadable'];
        }
        $score = (int)max(0, min(100, round($score)));

        // ---- verdict and bucket
        $threshold = (int)($job['screen_threshold'] ?? 35);
        $enabled = !isset($job['screen_enabled']) || (int)$job['screen_enabled'] === 1;
        $verdict = $score >= self::STRONG ? 'strong' : ($score >= $threshold ? 'potential' : 'weak');
        if ($knock) {
            $verdict = 'weak';
            $flags[] = ['code' => 'knockout', 'items' => $knock];
        }
        $info = mb_strlen($corpus);
        if (!$readable && !$knock) {
            $verdict = 'review';             // nothing was read from the file: the score only reflects the form, a person must open the résumé
        } elseif ($info < 100 && !$knock) {
            $verdict = 'review';
            $flags[] = ['code' => 'too_little_info'];
        }
        $bucket = ($enabled && $verdict === 'weak') ? 'screened_out' : 'inbox';

        return ['score' => $score, 'verdict' => $verdict, 'bucket' => $bucket, 'report' => [
            'v' => self::VERSION, 'score' => $score, 'verdict' => $verdict, 'readable' => $readable, 'groups' => $groups, 'derived' => $derived,
            'matched' => $matched, 'missing' => $missing, 'nice_matched' => $niceMatched, 'nice_missing' => $niceMissing, 'knockouts' => $knock, 'flags' => $flags, 'threshold' => $threshold,
        ]];
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Applying it to a stored application
    // -----------------------------------------------------------------------------------------------------------------

    /** Reads the résumé (once; the text is kept), evaluates, stores score, report and bucket. Never throws. */
    public static function screenApplication(PDO $pdo, int $appId, bool $applyBucket = true): ?array
    {
        try {
            $st = $pdo->prepare('SELECT * FROM career_applications WHERE id = :i');
            $st->execute(['i' => $appId]);
            $app = $st->fetch(PDO::FETCH_ASSOC);
            if (!$app) {
                return null;
            }
            $job = null;
            if ($app['job_id']) {
                $js = $pdo->prepare('SELECT * FROM career_jobs WHERE id = :i');
                $js->execute(['i' => $app['job_id']]);
                $job = $js->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            $tx = $pdo->prepare('SELECT resume_text, chars, via FROM career_application_texts WHERE application_id = :i');
            $tx->execute(['i' => $appId]);
            $row = $tx->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $text = ''; $readable = false; $via = '';
                if (!empty($app['resume_file'])) {
                    $path = MediaStore::path('career_resume', (string)$app['resume_file']);
                    if (!is_file($path)) {
                        MediaStore::restore($pdo, 'career_resume/' . $app['resume_file'], $path);
                    }
                    $r = ResumeText::extract($path, strtolower(pathinfo((string)$app['resume_file'], PATHINFO_EXTENSION)));
                    $text = $r['text']; $readable = $r['readable']; $via = $r['via'];
                }
                $pdo->prepare('REPLACE INTO career_application_texts (application_id, resume_text, chars, via) VALUES (:i, :t, :c, :v)')
                    ->execute(['i' => $appId, 't' => $text, 'c' => mb_strlen($text), 'v' => $via]);
            } else {
                $text = (string)$row['resume_text'];
                $readable = ResumeText::looksLikeText($text);
            }

            $res = self::evaluate($job, $app, $text, $readable);

            // A bucket an administrator chose, or an application they already worked on, is not moved by a re-run.
            $touched = $pdo->prepare("SELECT 1 FROM career_application_events WHERE application_id = :i AND actor_id IS NOT NULL AND kind IN ('bucket','status','rating','note','interview','email') LIMIT 1");
            $touched->execute(['i' => $appId]);
            $handled = (bool)$touched->fetchColumn() || $app['status'] !== 'new' || $app['bucket'] === 'archived';
            $newBucket = ($applyBucket && !$handled) ? $res['bucket'] : $app['bucket'];

            $pdo->prepare("
                UPDATE career_applications SET screen_state = 'done', screen_score = :s, screen_verdict = :v, screen_report = :r, screened_at = NOW(), screen_version = :ver,
                       bucket_changed_at = IF(bucket <> :b2, NOW(), bucket_changed_at), bucket = :b
                WHERE id = :i
            ")->execute(['s' => $res['score'], 'v' => $res['verdict'], 'r' => json_encode($res['report'], JSON_UNESCAPED_UNICODE), 'ver' => (int)($job['screen_version'] ?? 1) * 100 + self::VERSION,
                         'b' => $newBucket, 'b2' => $newBucket, 'i' => $appId]);
            if ($newBucket !== $app['bucket'] || $app['screen_state'] !== 'done') {
                $pdo->prepare("INSERT INTO career_application_events (application_id, actor_id, kind, from_status, to_status, body) VALUES (:a, NULL, 'screen', :f, :t, :b)")
                    ->execute(['a' => $appId, 'f' => $app['bucket'], 't' => $newBucket, 'b' => json_encode(['score' => $res['score'], 'verdict' => $res['verdict']])]);
            }
            return ['score' => $res['score'], 'verdict' => $res['verdict'], 'bucket' => $newBucket, 'moved' => $newBucket !== $app['bucket']];
        } catch (Throwable $e) {
            error_log('[CareersScreening] application ' . $appId . ': ' . $e->getMessage());
            try {
                $pdo->prepare("UPDATE career_applications SET screen_state = 'error' WHERE id = :i")->execute(['i' => $appId]);
            } catch (Throwable $e2) {}
            return null;
        }
    }

    /** The version stamp an application must carry to be considered up to date for its posting. */
    public static function stamp(?array $job): int
    {
        return (int)($job['screen_version'] ?? 1) * 100 + self::VERSION;
    }
}
