<?php
declare(strict_types=1);

require_once __DIR__ . '/Careers.php';
require_once __DIR__ . '/CareersScreening.php';

/**
 * Job postings from Markdown files. An administrator writes one posting in a template file (docs/careers/job-template.md), uploads it, reads
 * a preview with every problem listed, and approves: the posting is created with the same rules as the form (Careers::saveJob).
 *
 * File shape
 *   ---                                  a header of "key: value" lines (title, team, location, work_mode, type, level, openings, closes, salary,
 *   title: Senior Backend Engineer       code, status, screening, screening_threshold, auto_decline_days, min_years, min_education)
 *   team: engineering
 *   ---
 *   ## Summary ... ## About the role ... ## Responsibilities ... ## Requirements ... ## Nice to have ... ## Benefits
 *   ## Screening
 *   ### Must have        one keyword per line; "!" in front = knock-out; "a|b" = alternatives
 *   ### Nice to have
 *
 * Headings and keys are understood in French and English. HTML comments (<!-- -->) are ignored, so the template can explain itself.
 * parse() never writes anything: it returns the posting as it would be saved, plus errors (the file cannot be approved) and warnings (it can).
 * Messages are codes with parameters; the workspace translates them. Nothing is cut silently: a text that is too long is an error.
 */
final class CareersMarkdown
{
    public const MAX_BYTES = 204800;

    private const KEYS = [
        'title' => 'title', 'titre' => 'title', 'intitule' => 'title', 'poste' => 'title',
        'team' => 'team', 'department' => 'team', 'equipe' => 'team', 'departement' => 'team',
        'location' => 'location', 'lieu' => 'location', 'localisation' => 'location', 'ville' => 'location',
        'work_mode' => 'work_mode', 'mode' => 'work_mode', 'organisation' => 'work_mode', 'workplace' => 'work_mode', 'work_style' => 'work_mode',
        'type' => 'type', 'contract' => 'type', 'contrat' => 'type', 'employment_type' => 'type', 'type_de_contrat' => 'type',
        'level' => 'level', 'niveau' => 'level', 'seniority' => 'level',
        'openings' => 'openings', 'postes' => 'openings', 'positions' => 'openings', 'nombre_de_postes' => 'openings',
        'closes' => 'closes', 'deadline' => 'closes', 'date_limite' => 'closes', 'cloture' => 'closes', 'closing_date' => 'closes',
        'salary' => 'salary', 'pay' => 'salary', 'remuneration' => 'salary', 'compensation' => 'salary',
        'code' => 'code', 'status' => 'status', 'statut' => 'status',
        'screening' => 'screening', 'ats' => 'screening', 'lecture_auto' => 'screening', 'lecture_automatique' => 'screening',
        'screening_threshold' => 'threshold', 'threshold' => 'threshold', 'seuil' => 'threshold',
        'auto_decline_days' => 'auto_decline', 'auto_decline' => 'auto_decline', 'refus_auto' => 'auto_decline',
        'min_years' => 'min_years', 'experience_min' => 'min_years', 'annees_min' => 'min_years', 'min_experience' => 'min_years',
        'min_education' => 'min_education', 'education_min' => 'min_education', 'etudes_min' => 'min_education',
    ];

    private const SECTIONS = [
        'summary' => 'summary', 'resume' => 'summary', 'overview' => 'summary', 'en bref' => 'summary', 'tl dr' => 'summary', 'accroche' => 'summary',
        'description' => 'description', 'about the role' => 'description', 'the role' => 'description', 'le poste' => 'description', 'a propos du poste' => 'description',
        'about' => 'description', 'role' => 'description', 'presentation' => 'description', 'about the position' => 'description',
        'responsibilities' => 'responsibilities', 'what you will do' => 'responsibilities', 'missions' => 'responsibilities', 'vos missions' => 'responsibilities',
        'ce que vous ferez' => 'responsibilities', 'duties' => 'responsibilities', 'key responsibilities' => 'responsibilities',
        'requirements' => 'requirements', 'what we are looking for' => 'requirements', 'profile' => 'requirements', 'profil' => 'requirements',
        'profil recherche' => 'requirements', 'exigences' => 'requirements', 'prerequis' => 'requirements', 'ce que nous cherchons' => 'requirements', 'qualifications' => 'requirements',
        'nice to have' => 'nice', 'un plus' => 'nice', 'atouts' => 'nice', 'bonus' => 'nice', 'plus' => 'nice', 'appreciated' => 'nice',
        'benefits' => 'benefits', 'what we offer' => 'benefits', 'ce que nous offrons' => 'benefits', 'avantages' => 'benefits', 'perks' => 'benefits', 'we offer' => 'benefits',
        'screening' => 'screening', 'lecture automatique' => 'screening', 'lecture auto' => 'screening', 'ats' => 'screening', 'automatic screening' => 'screening',
    ];
    private const SUB_MUST = ['must have', 'must haves', 'required', 'requis', 'indispensables', 'indispensable', 'obligatoires', 'obligatoire', 'keywords', 'mots cles', 'competences indispensables'];
    private const SUB_NICE = ['nice to have', 'nice to haves', 'plus', 'un plus', 'atouts', 'souhaitables', 'souhaite', 'bonus', 'preferred', 'competences souhaitees'];

    private const MODES = ['onsite' => ['onsite', 'on site', 'on-site', 'sur site', 'presentiel', 'bureau', 'office'], 'hybrid' => ['hybrid', 'hybride'], 'remote' => ['remote', 'a distance', 'distance', 'teletravail', 'full remote']];
    private const TYPES = [
        'full_time' => ['full time', 'full-time', 'fulltime', 'temps plein', 'cdi', 'permanent', 'plein temps'],
        'part_time' => ['part time', 'part-time', 'parttime', 'temps partiel'],
        'internship' => ['internship', 'intern', 'stage', 'stagiaire'],
        'contract' => ['contract', 'contractor', 'contrat', 'cdd', 'freelance', 'fixed term'],
        'volunteer' => ['volunteer', 'volunteering', 'benevole', 'benevolat'],
    ];
    private const EDU = [
        'highschool' => ['highschool', 'high school', 'baccalaureat', 'bac'], 'associate' => ['associate', 'bac 2', 'bac+2', 'bts', 'dut', 'hnd'],
        'bachelor' => ['bachelor', 'licence', 'bac 3', 'bac+3', 'bsc', 'degree'], 'master' => ['master', 'bac 5', 'bac+5', 'msc', 'ingenieur', 'engineer'],
        'doctorate' => ['doctorate', 'doctorat', 'phd'],
    ];

    /** Plain text of a heading, key or value for comparing: lower case, no accents, single spaces. */
    private static function norm(string $v): string
    {
        return Careers::plain($v);
    }

    private static function pick(array $map, string $v): string
    {
        $n = self::norm($v);
        foreach ($map as $key => $words) {
            if ($n === $key || in_array($n, array_map([self::class, 'norm'], $words), true)) {
                return $key;
            }
        }
        return '';
    }

    /** Markdown inline marks out; text in. */
    private static function clean(string $s): string
    {
        $s = preg_replace('/!\[[^\]]*\]\([^)]*\)/u', '', $s) ?? '';
        $s = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/u', '$1', $s) ?? '';
        $s = preg_replace('/(\*\*|__)(.+?)\1/u', '$2', $s) ?? '';
        $s = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '$1', $s) ?? '';
        $s = preg_replace('/(?<![\w])_(?!\s)(.+?)(?<!\s)_(?![\w])/u', '$1', $s) ?? '';
        $s = str_replace(['`', '~~'], '', $s);
        $s = preg_replace('/^\s*>\s?/u', '', $s) ?? '';
        return trim(preg_replace('/[ \t]+/u', ' ', $s) ?? '');
    }

    private static function err(array &$list, string $code, string $field = '', int $line = 0, array $params = []): void
    {
        $list[] = ['code' => $code, 'field' => $field, 'line' => $line, 'params' => $params];
    }

    /**
     * @return array{ok:bool,errors:array,warnings:array,job:array,preview:array}
     */
    public static function parse(string $md, ?PDO $pdo = null): array
    {
        $errors = [];
        $warnings = [];
        $job = [];
        $out = function () use (&$errors, &$warnings, &$job): array {
            return ['ok' => !$errors, 'errors' => $errors, 'warnings' => $warnings, 'job' => $job, 'preview' => []];
        };

        if (strlen($md) > self::MAX_BYTES) {
            self::err($errors, 'too_big');
            return $out();
        }
        if (!mb_check_encoding($md, 'UTF-8')) {
            self::err($errors, 'not_utf8');
            return $out();
        }
        $md = preg_replace('/^\xEF\xBB\xBF/', '', $md) ?? $md;
        $md = str_replace(["\r\n", "\r"], "\n", $md);
        $md = preg_replace_callback('/<!--.*?-->/s', static fn($m) => str_repeat("\n", substr_count($m[0], "\n")), $md) ?? $md;
        $lines = explode("\n", $md);
        $n = count($lines);

        // ---- header
        $i = 0;
        while ($i < $n && trim($lines[$i]) === '') {
            $i++;
        }
        $fm = [];
        $fmLine = [];
        if ($i >= $n || trim($lines[$i]) !== '---') {
            self::err($errors, 'no_header', '', $i + 1);
            return $out();
        }
        $i++;
        $closed = false;
        for (; $i < $n; $i++) {
            $raw = $lines[$i];
            if (trim($raw) === '---') {
                $closed = true;
                $i++;
                break;
            }
            if (trim($raw) === '' || str_starts_with(ltrim($raw), '#')) {
                continue;
            }
            if (!preg_match('/^\s*([^:]+?)\s*:\s*(.*)$/u', $raw, $m)) {
                self::err($warnings, 'bad_header_line', '', $i + 1, ['text' => mb_substr(trim($raw), 0, 60)]);
                continue;
            }
            $k = self::KEYS[str_replace(' ', '_', self::norm($m[1]))] ?? null;
            if ($k === null) {
                self::err($warnings, 'unknown_key', '', $i + 1, ['key' => trim($m[1])]);
                continue;
            }
            $v = trim($m[2]);
            if (strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
                $v = substr($v, 1, -1);
            }
            $fm[$k] = trim($v);
            $fmLine[$k] = $i + 1;
        }
        if (!$closed) {
            self::err($errors, 'header_not_closed');
            return $out();
        }

        // ---- body, section by section
        $sec = null;
        $sub = null;
        $buf = [];
        $h1 = '';
        for (; $i < $n; $i++) {
            $raw = $lines[$i];
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/u', $raw, $h)) {
                $level = strlen($h[1]);
                $text = trim($h[2]);
                if ($level === 1) {
                    $h1 = $h1 !== '' ? $h1 : self::clean($text);
                    $sec = null;
                    continue;
                }
                if ($level === 2) {
                    $sub = null;
                    $f = self::SECTIONS[self::norm($text)] ?? null;
                    if ($f === null) {
                        self::err($warnings, 'unknown_section', '', $i + 1, ['heading' => $text]);
                        $sec = '_ignored';
                    } else {
                        $sec = $f;
                        if (isset($buf[$f])) {
                            self::err($warnings, 'duplicate_section', $f, $i + 1, ['heading' => $text]);
                        }
                    }
                    continue;
                }
                if ($sec === 'screening') {
                    $nt = self::norm($text);
                    $sub = in_array($nt, self::SUB_MUST, true) ? 'kw_must' : (in_array($nt, self::SUB_NICE, true) ? 'kw_nice' : null);
                    if ($sub === null) {
                        self::err($warnings, 'unknown_subsection', '', $i + 1, ['heading' => $text]);
                    }
                    continue;
                }
                $raw = $text;          // a deeper heading in another section is kept as a line of text
            }
            if ($sec === null || $sec === '_ignored') {
                if ($sec === null && trim($raw) !== '') {
                    self::err($warnings, 'text_before_section', '', $i + 1, ['text' => mb_substr(trim($raw), 0, 50)]);
                    $sec = '_ignored';
                }
                continue;
            }
            $key = $sec === 'screening' ? ($sub ?? '_ignored') : $sec;
            if ($key === '_ignored') {
                continue;
            }
            $buf[$key][] = [$i + 1, $raw];
        }

        // ---- sections to fields
        $listOf = function (string $field, bool $keywords = false) use (&$buf, &$warnings): array {
            $items = [];
            $hasBullets = false;
            foreach ($buf[$field] ?? [] as [$ln, $raw]) {
                if (preg_match('/^\s*(?:[-*+•]|\d+[.)])\s+(.*)$/u', $raw, $m)) {
                    $hasBullets = true;
                    break;
                }
            }
            $last = null;
            foreach ($buf[$field] ?? [] as [$ln, $raw]) {
                if (trim($raw) === '') {
                    $last = null;
                    continue;
                }
                if (preg_match('/^\s*(?:[-*+•]|\d+[.)])\s+(.*)$/u', $raw, $m)) {
                    $items[] = ['t' => $m[1], 'l' => $ln];
                    $last = count($items) - 1;
                } elseif ($last !== null && preg_match('/^\s{2,}\S/u', $raw)) {
                    $items[$last]['t'] .= ' ' . trim($raw);                    // a wrapped bullet
                } else {
                    if ($hasBullets && !$keywords) {
                        self::err($warnings, 'stray_text', $field, $ln, ['text' => mb_substr(trim($raw), 0, 50)]);
                    }
                    $items[] = ['t' => trim($raw), 'l' => $ln];
                    $last = count($items) - 1;
                    if (!$hasBullets) {
                        $last = null;
                    }
                }
            }
            if ($items && !$hasBullets && !$keywords) {
                self::err($warnings, 'list_not_bulleted', $field, $items[0]['l']);
            }
            $clean = [];
            foreach ($items as $it) {
                $t = $keywords ? trim(str_replace('`', '', preg_replace('/(\*\*|__)/u', '', $it['t']) ?? '')) : self::clean($it['t']);
                if ($t !== '') {
                    $clean[] = ['t' => $t, 'l' => $it['l']];
                }
            }
            return $clean;
        };
        $textOf = function (string $field, bool $paragraphs) use (&$buf): string {
            $paras = [];
            $cur = [];
            foreach ($buf[$field] ?? [] as [$ln, $raw]) {
                if (trim($raw) === '') {
                    if ($cur) { $paras[] = implode(' ', $cur); $cur = []; }
                    continue;
                }
                if (preg_match('/^\s*(?:[-*+•]|\d+[.)])\s+(.*)$/u', $raw, $m)) {
                    if ($cur) { $paras[] = implode(' ', $cur); $cur = []; }
                    $paras[] = '• ' . self::clean($m[1]);
                    continue;
                }
                $cur[] = self::clean($raw);
            }
            if ($cur) { $paras[] = implode(' ', $cur); }
            $paras = array_values(array_filter($paras, static fn($p) => trim($p) !== ''));
            if (!$paragraphs) {
                return trim(implode(' ', $paras));
            }
            // consecutive bullets stay one block (single line breaks), paragraphs are separated by a blank line
            $out = '';
            $prevBullet = false;
            foreach ($paras as $p) {
                $isB = str_starts_with($p, '• ');
                $out .= ($out === '' ? '' : ($isB && $prevBullet ? "\n" : "\n\n")) . $p;
                $prevBullet = $isB;
            }
            return $out;
        };

        // ---- header values
        $title = $fm['title'] ?? $h1;
        if ($title === '') {
            self::err($errors, 'title_required', 'title', $fmLine['title'] ?? 0);
        } elseif (mb_strlen($title) < 3 || mb_strlen($title) > 160) {
            self::err($errors, 'title_length', 'title', $fmLine['title'] ?? 0, ['min' => 3, 'max' => 160]);
        }

        $teamRaw = $fm['team'] ?? '';
        $team = Careers::teamKey($teamRaw);
        if ($teamRaw === '') {
            self::err($errors, 'team_required', 'team');
        } elseif ($team === '') {
            self::err($errors, 'team_invalid', 'team', $fmLine['team'] ?? 0, ['value' => $teamRaw, 'allowed' => implode(', ', array_keys(Careers::TEAMS))]);
        }

        $location = Careers::line($fm['location'] ?? '', 120);
        if ($location === '') {
            self::err($errors, 'location_required', 'location');
        } elseif (mb_strlen($fm['location']) > 120) {
            self::err($errors, 'too_long', 'location', $fmLine['location'] ?? 0, ['max' => 120]);
        }

        $mode = isset($fm['work_mode']) ? self::pick(self::MODES, $fm['work_mode']) : '';
        if (!isset($fm['work_mode']) || $fm['work_mode'] === '') {
            self::err($errors, 'work_mode_required', 'work_mode');
        } elseif ($mode === '') {
            self::err($errors, 'work_mode_invalid', 'work_mode', $fmLine['work_mode'] ?? 0, ['value' => $fm['work_mode'], 'allowed' => 'onsite, hybrid, remote']);
        }
        $type = isset($fm['type']) ? self::pick(self::TYPES, $fm['type']) : '';
        if (!isset($fm['type']) || $fm['type'] === '') {
            self::err($errors, 'type_required', 'type');
        } elseif ($type === '') {
            self::err($errors, 'type_invalid', 'type', $fmLine['type'] ?? 0, ['value' => $fm['type'], 'allowed' => 'full_time, part_time, internship, contract, volunteer']);
        }

        $level = Careers::line($fm['level'] ?? '', 40);
        $openings = 1;
        if (isset($fm['openings']) && $fm['openings'] !== '') {
            if (!ctype_digit($fm['openings']) || (int)$fm['openings'] < 1 || (int)$fm['openings'] > 99) {
                self::err($errors, 'openings_invalid', 'openings', $fmLine['openings'] ?? 0, ['value' => $fm['openings']]);
            } else {
                $openings = (int)$fm['openings'];
            }
        }
        $salary = Careers::line($fm['salary'] ?? '', 160);
        if (mb_strlen($fm['salary'] ?? '') > 160) {
            self::err($errors, 'too_long', 'salary', $fmLine['salary'] ?? 0, ['max' => 160]);
        }

        $status = 'open';
        if (isset($fm['status']) && $fm['status'] !== '') {
            $sv = self::norm($fm['status']);
            if (in_array($sv, ['open', 'published', 'publish', 'ouvert', 'ouverte', 'publie', 'publiee'], true)) {
                $status = 'open';
            } elseif (in_array($sv, ['draft', 'brouillon'], true)) {
                $status = 'draft';
            } else {
                self::err($errors, 'status_invalid', 'status', $fmLine['status'] ?? 0, ['value' => $fm['status'], 'allowed' => 'open, draft']);
            }
        }

        $closes = '';
        if (isset($fm['closes']) && $fm['closes'] !== '') {
            $cv = $fm['closes'];
            $d = DateTime::createFromFormat('Y-m-d', $cv) ?: DateTime::createFromFormat('d/m/Y', $cv);
            if (!$d || !in_array($d->format('Y-m-d'), [$cv], true) && $d->format('d/m/Y') !== $cv) {
                self::err($errors, 'closes_invalid', 'closes', $fmLine['closes'] ?? 0, ['value' => $cv]);
            } else {
                $closes = $d->format('Y-m-d');
                if ($closes < date('Y-m-d') && $status === 'open') {
                    self::err($errors, 'closes_past', 'closes', $fmLine['closes'] ?? 0, ['value' => $closes]);
                }
            }
        }

        $codeRaw = trim($fm['code'] ?? '');
        $code = Careers::normalizeCode($codeRaw);
        if ($codeRaw !== '' && $code === '') {
            self::err($errors, 'code_invalid', 'code', $fmLine['code'] ?? 0, ['value' => $codeRaw]);
        } elseif ($code !== '' && $pdo && Careers::codeTaken($pdo, $code)) {
            self::err($errors, 'code_taken', 'code', $fmLine['code'] ?? 0, ['value' => $code]);
        }

        $onOff = static function (string $v): ?bool {
            $n = Careers::plain($v);
            return in_array($n, ['on', 'true', 'yes', 'oui', '1', 'enabled', 'active', 'activee'], true) ? true : (in_array($n, ['off', 'false', 'no', 'non', '0', 'disabled', 'desactivee'], true) ? false : null);
        };
        $screenOn = true;
        if (isset($fm['screening']) && $fm['screening'] !== '') {
            $b = $onOff($fm['screening']);
            if ($b === null) {
                self::err($errors, 'bool_invalid', 'screening', $fmLine['screening'] ?? 0, ['value' => $fm['screening']]);
            } else {
                $screenOn = $b;
            }
        }
        $num = static function (string $k, int $min, int $max, int $default) use (&$fm, &$fmLine, &$errors): int {
            if (!isset($fm[$k]) || $fm[$k] === '') {
                return $default;
            }
            if (!ctype_digit($fm[$k]) || (int)$fm[$k] < $min || (int)$fm[$k] > $max) {
                self::err($errors, 'number_range', $k, $fmLine[$k] ?? 0, ['field' => $k, 'value' => $fm[$k], 'min' => $min, 'max' => $max]);
                return $default;
            }
            return (int)$fm[$k];
        };
        $threshold = $num('threshold', 10, 80, 35);
        $autoDecline = $num('auto_decline', 0, 90, 0);
        $minYears = $num('min_years', 0, 40, 0);
        $minEdu = '';
        if (isset($fm['min_education']) && $fm['min_education'] !== '' && self::norm($fm['min_education']) !== 'none' && self::norm($fm['min_education']) !== 'aucun') {
            $minEdu = self::pick(self::EDU, $fm['min_education']);
            if ($minEdu === '') {
                self::err($errors, 'education_invalid', 'min_education', $fmLine['min_education'] ?? 0, ['value' => $fm['min_education'], 'allowed' => 'highschool, associate, bachelor, master, doctorate']);
            }
        }

        // ---- text sections
        $summary = $textOf('summary', false);
        if ($summary === '') {
            self::err($errors, 'summary_required', 'summary');
        } elseif (mb_strlen($summary) < 20) {
            self::err($errors, 'summary_short', 'summary', 0, ['min' => 20]);
        } elseif (mb_strlen($summary) > 400) {
            self::err($errors, 'summary_long', 'summary', 0, ['max' => 400, 'now' => mb_strlen($summary)]);
        }
        $description = $textOf('description', true);
        if ($description === '') {
            self::err($errors, 'description_required', 'description');
        } elseif (mb_strlen($description) > 6000) {
            self::err($errors, 'description_long', 'description', 0, ['max' => 6000, 'now' => mb_strlen($description)]);
        } elseif (mb_strlen($description) < 300) {
            self::err($warnings, 'description_thin', 'description', 0, ['now' => mb_strlen($description)]);
        }

        $lists = [];
        foreach (['responsibilities' => 'responsibilities', 'requirements' => 'requirements', 'nice' => 'nice_to_have', 'benefits' => 'benefits'] as $src => $dst) {
            $items = $listOf($src);
            if (count($items) > 25) {
                self::err($errors, 'too_many_items', $dst, $items[25]['l'], ['max' => 25, 'now' => count($items)]);
            }
            foreach ($items as $it) {
                if (mb_strlen($it['t']) > 300) {
                    self::err($errors, 'item_long', $dst, $it['l'], ['max' => 300, 'now' => mb_strlen($it['t'])]);
                }
            }
            $lists[$dst] = array_map(static fn($it) => $it['t'], $items);
        }
        if (!$lists['responsibilities']) {
            self::err($errors, 'responsibilities_required', 'responsibilities');
        } elseif (count($lists['responsibilities']) < 4) {
            self::err($warnings, 'few_items', 'responsibilities', 0, ['now' => count($lists['responsibilities']), 'min' => 4]);
        }
        if (!$lists['requirements']) {
            self::err($errors, 'requirements_required', 'requirements');
        } elseif (count($lists['requirements']) < 3) {
            self::err($warnings, 'few_items', 'requirements', 0, ['now' => count($lists['requirements']), 'min' => 3]);
        }
        if (!$lists['benefits']) {
            self::err($warnings, 'no_benefits', 'benefits');
        }

        // ---- screening keywords
        $must = array_map(static fn($it) => $it['t'], $listOf('kw_must', true));
        $nice = array_map(static fn($it) => $it['t'], $listOf('kw_nice', true));
        if (count($must) > 40 || count($nice) > 40) {
            self::err($errors, 'too_many_items', 'screening', 0, ['max' => 40, 'now' => max(count($must), count($nice))]);
        }
        foreach (array_merge($must, $nice) as $kw) {
            if (mb_strlen($kw) > 80) {
                self::err($errors, 'item_long', 'screening', 0, ['max' => 80, 'now' => mb_strlen($kw)]);
                break;
            }
        }
        if ($screenOn && !$must) {
            self::err($warnings, 'no_must_have', 'screening');
        }
        if (!$screenOn) {
            self::err($warnings, 'screening_off', 'screening');
        }

        $job = [
            'title' => $title, 'department' => $team, 'location' => $location, 'work_mode' => $mode, 'employment_type' => $type, 'level' => $level,
            'openings' => $openings, 'closes_at' => $closes, 'salary_note' => $salary, 'code' => $code, 'status' => $status,
            'summary' => $summary, 'description' => $description,
            'responsibilities' => implode("\n", $lists['responsibilities']), 'requirements' => implode("\n", $lists['requirements']),
            'nice_to_have' => implode("\n", $lists['nice_to_have']), 'benefits' => implode("\n", $lists['benefits']),
            'screen_enabled' => $screenOn ? 1 : 0, 'screen_threshold' => $threshold, 'auto_decline_days' => $autoDecline, 'min_years' => $minYears, 'min_education' => $minEdu,
            'must_have' => implode("\n", $must), 'nice_have' => implode("\n", $nice),
        ];

        // ---- what the database says (only when a connection is given)
        $preview = ['code' => $code, 'code_generated' => $code === '', 'derived' => [], 'team_label' => ['fr' => Careers::teamLabel($team, 'fr'), 'en' => Careers::teamLabel($team, 'en')]];
        if ($pdo) {
            if ($code === '' && $title !== '') {
                $preview['code'] = Careers::uniqueCode($pdo, $title);
            }
            if ($title !== '') {
                foreach ($pdo->query('SELECT id, title, status FROM career_jobs')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (Careers::plain((string)$row['title']) === Careers::plain($title)) {
                        self::err($warnings, 'duplicate_title', 'title', 0, ['id' => (int)$row['id'], 'status' => $row['status']]);
                        break;
                    }
                }
            }
        } elseif ($code === '' && $title !== '') {
            $preview['code'] = Careers::suggestCode($title);
        }
        if ($screenOn && !$must) {
            $preview['derived'] = array_column(CareersScreening::deriveKeywords($job), 'label');
        }
        $preview['slug'] = Careers::slugify($title);
        return ['ok' => !$errors, 'errors' => $errors, 'warnings' => $warnings, 'job' => $job, 'preview' => $preview];
    }

    /**
     * Creates the posting from a file that parses without errors. $status overrides the file's own status ('open' publishes, 'draft' keeps it private).
     * @return array{ok:bool,errors?:array,warnings?:array,id?:int,slug?:string,job?:array}
     */
    public static function import(PDO $pdo, int $adminId, string $md, ?string $status = null): array
    {
        $p = self::parse($md, $pdo);
        if (!$p['ok']) {
            return ['ok' => false, 'errors' => $p['errors'], 'warnings' => $p['warnings']];
        }
        $in = $p['job'];
        if ($status !== null && in_array($status, ['open', 'draft'], true)) {
            $in['status'] = $status;
        }
        $res = Careers::saveJob($pdo, $adminId, null, $in);
        if (!$res['ok']) {
            $errs = [];
            foreach (($res['fields'] ?? ['_' => 'invalid']) as $f => $c) {
                self::err($errs, 'save_' . $c, (string)$f);
            }
            return ['ok' => false, 'errors' => $errs, 'warnings' => $p['warnings']];
        }
        return ['ok' => true, 'id' => (int)$res['id'], 'slug' => (string)$res['slug'], 'job' => Careers::job($pdo, (int)$res['id']), 'warnings' => $p['warnings']];
    }

    /** The template file (English or French) an administrator fills in. */
    public static function template(string $lang = 'en'): string
    {
        $file = dirname(__DIR__) . '/docs/careers/' . ($lang === 'fr' ? 'job-template.fr.md' : 'job-template.md');
        return is_file($file) ? (string)file_get_contents($file) : '';
    }
}
