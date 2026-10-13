<?php
declare(strict_types=1);

require_once __DIR__ . '/MediaStore.php';

/**
 * Careers: job postings, applications and the review of those applications.
 *
 * Everything about hiring goes through this class and nothing else in the app touches the career_* tables:
 *   - public pages (careers.php, career.php, career-apply.php) read open postings and submit applications;
 *   - the administrator's workspace (promoter/careers/) writes postings and reviews applications.
 * Applicants' résumés are stored in their own storage kind ("career_resume"), outside what download.php can serve, and are handed
 * out only by promoter/careers/resume.php to a signed-in administrator.
 *
 * Methods that change data return ['ok' => bool, 'error' => code, 'message' => text, ...]; the endpoints turn them into JSON.
 */
final class Careers
{
    public const JOB_STATUSES  = ['draft', 'open', 'paused', 'closed'];
    public const APP_STATUSES  = ['new', 'reviewing', 'shortlisted', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'];
    public const BUCKETS       = ['inbox', 'screened_out', 'archived'];
    public const EDUCATION     = ['highschool', 'associate', 'bachelor', 'master', 'doctorate', 'other'];
    public const WORK_PREFS    = ['any', 'onsite', 'hybrid', 'remote'];
    public const HEARD         = ['website', 'linkedin', 'referral', 'social', 'school', 'event', 'other'];
    /** Months an application is kept after its last move when the person did not ask to stay in the talent pool (hired people excepted). */
    /**
     * The teams of StudyVibe. A posting belongs to exactly one, chosen from this list (never typed). The key is what is stored in
     * career_jobs.department; labels are shown in the visitor's or administrator's language.
     */
    public const TEAMS = [
        'engineering' => ['fr' => 'Ingénierie', 'en' => 'Engineering'],
        'education'   => ['fr' => 'Éducation et pédagogie', 'en' => 'Education'],
        'product'     => ['fr' => 'Produit et design', 'en' => 'Product & Design'],
        'sales'       => ['fr' => 'Ventes et partenariats', 'en' => 'Sales & Partnerships'],
        'success'     => ['fr' => 'Succès clients et support', 'en' => 'Customer Success'],
        'marketing'   => ['fr' => 'Marketing et croissance', 'en' => 'Marketing & Growth'],
        'data'        => ['fr' => 'Données et IA', 'en' => 'Data & AI'],
        'operations'  => ['fr' => 'Opérations et finance', 'en' => 'Operations & Finance'],
        'people'      => ['fr' => 'Personnes et culture', 'en' => 'People & Culture'],
    ];

    /** Words that mean each team (used to map older free-text values and Markdown files), lower case, without accents. */
    private const TEAM_ALIASES = [
        'engineering' => ['engineering', 'ingenierie', 'dev', 'developpement', 'development', 'tech', 'technology', 'technique', 'it'],
        'education' => ['education', 'pedagogie', 'academic', 'academique', 'learning', 'content', 'contenu', 'formation', 'education et pedagogie'],
        'product' => ['product', 'produit', 'design', 'ux', 'product design', 'produit et design'],
        'sales' => ['sales', 'vente', 'ventes', 'commercial', 'business development', 'partnerships', 'partenariats', 'ventes et partenariats'],
        'success' => ['success', 'succes', 'support', 'customer success', 'succes clients', 'succes clients et support'],
        'marketing' => ['marketing', 'growth', 'communication', 'marketing et croissance'],
        'data' => ['data', 'donnees', 'ai', 'ia', 'analytics', 'donnees et ia'],
        'operations' => ['operations', 'ops', 'finance', 'administration', 'operations et finance'],
        'people' => ['people', 'rh', 'hr', 'ressources humaines', 'culture', 'personnes et culture', 'recrutement'],
    ];

    /** Code shared by every open (spontaneous) application, so its reference has the same shape as the others. */
    public const OPEN_CODE = 'OPEN';
    private const REF_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';     // no 0/O, 1/I/L: a reference is read aloud and typed
    private const CODE_STOP = ['AND', 'ET', 'DE', 'DU', 'LA', 'LE', 'LES', 'THE', 'OF', 'FOR', 'POUR', 'AU', 'AUX', 'DES', 'UN', 'UNE', 'A', 'H', 'F', 'M'];
    public const RETENTION_MONTHS = 12;
    public const RETENTION_POOL_MONTHS = 24;
    public const TYPES         = ['full_time', 'part_time', 'internship', 'contract', 'volunteer'];
    public const MODES         = ['onsite', 'hybrid', 'remote'];

    public const RESUME_MAX    = 5242880;                 // 5 MB
    public const RESUME_EXT    = ['pdf', 'doc', 'docx'];
    public const SPONTANEOUS   = 'spontaneous';           // slug of the "open application" page (no posting behind it)

    // ---------------------------------------------------------------------------------------------------------------
    // Text helpers
    // ---------------------------------------------------------------------------------------------------------------

    public static function slugify(string $s): string
    {
        $s = trim($s);
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($t) && $t !== '') {
                $s = $t;
            }
        }
        $s = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $s) ?? '');
        $s = trim($s, '-');
        return substr($s !== '' ? $s : 'poste', 0, 90);
    }

    public static function uniqueSlug(PDO $pdo, string $title, ?int $ignoreId = null): string
    {
        $base = self::slugify($title);
        if ($base === self::SPONTANEOUS) {
            $base .= '-role';
        }
        $slug = $base;
        for ($i = 2; $i < 200; $i++) {
            $st = $pdo->prepare('SELECT id FROM career_jobs WHERE slug = :s' . ($ignoreId ? ' AND id <> :i' : '') . ' LIMIT 1');
            $args = ['s' => $slug];
            if ($ignoreId) {
                $args['i'] = $ignoreId;
            }
            $st->execute($args);
            if (!$st->fetchColumn()) {
                return $slug;
            }
            $slug = $base . '-' . $i;
        }
        return $base . '-' . bin2hex(random_bytes(3));
    }

    /** Single-line text: control characters out, spaces collapsed, cut to $max characters. */
    public static function line(mixed $v, int $max): string
    {
        $v = (string)$v;
        $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v) ?? '';
        $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');
        return mb_substr($v, 0, $max);
    }

    /** Multi-line text: line breaks kept, control characters out, cut to $max characters. */
    public static function block(mixed $v, int $max): string
    {
        $v = str_replace(["\r\n", "\r"], "\n", (string)$v);
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
        $v = preg_replace("/\n{3,}/", "\n\n", trim($v)) ?? '';
        return mb_substr($v, 0, $max);
    }

    /** A list typed one item per line: bullets and numbers at the start of a line are removed. */
    public static function listText(mixed $v, int $maxItems = 25, int $maxLen = 300): string
    {
        $out = [];
        foreach (explode("\n", str_replace("\r", '', (string)$v)) as $l) {
            $l = self::line(preg_replace('/^\s*(?:[-*•·–—]|\d+[.)])\s+/u', '', $l) ?? '', $maxLen);
            if ($l !== '') {
                $out[] = $l;
            }
            if (count($out) >= $maxItems) {
                break;
            }
        }
        return implode("\n", $out);
    }

    /** @return string[] */
    public static function items(?string $text): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", (string)$text)), static fn($l) => $l !== ''));
    }

    /** @return string[] paragraphs of a free text */
    public static function paragraphs(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split("/\n{2,}/", str_replace("\r", '', (string)$text)) ?: []), static fn($l) => $l !== ''));
    }

    /** An http(s) address, or '' when it is not one. A bare "linkedin.com/in/x" gets https:// in front. */
    public static function url(mixed $v): string
    {
        $v = self::line($v, 255);
        if ($v === '') {
            return '';
        }
        if (!preg_match('~^https?://~i', $v)) {
            $v = 'https://' . $v;
        }
        $parts = parse_url($v);
        if (!$parts || empty($parts['host']) || !str_contains($parts['host'], '.') || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return '';
        }
        return filter_var($v, FILTER_VALIDATE_URL) ? $v : '';
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Job postings
    // ---------------------------------------------------------------------------------------------------------------

    /** Open for applications today: published, and the closing date (if any) is not past. */
    public static function isOpen(array $job): bool
    {
        if (($job['status'] ?? '') !== 'open') {
            return false;
        }
        return empty($job['closes_at']) || (string)$job['closes_at'] >= date('Y-m-d');
    }

    /** @return array<int,array<string,mixed>> postings visible on the public page, newest first */
    public static function publicJobs(PDO $pdo): array
    {
        $st = $pdo->prepare("SELECT * FROM career_jobs WHERE status = 'open' AND (closes_at IS NULL OR closes_at >= :d) ORDER BY published_at DESC, id DESC");
        $st->execute(['d' => date('Y-m-d')]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function jobBySlug(PDO $pdo, string $slug): ?array
    {
        if (!preg_match('/^[a-z0-9-]{1,120}$/', $slug)) {
            return null;
        }
        $st = $pdo->prepare('SELECT * FROM career_jobs WHERE slug = :s LIMIT 1');
        $st->execute(['s' => $slug]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function job(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT * FROM career_jobs WHERE id = :i');
        $st->execute(['i' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Postings for the administrator, each with its number of applications and how many are new. */
    public static function adminJobs(PDO $pdo): array
    {
        $rows = $pdo->query("
            SELECT j.*,
                   (SELECT COUNT(*) FROM career_applications a WHERE a.job_id = j.id) AS applicants,
                   (SELECT COUNT(*) FROM career_applications a WHERE a.job_id = j.id AND a.status = 'new' AND a.bucket = 'inbox') AS new_applicants,
                   (SELECT COUNT(*) FROM career_applications a WHERE a.job_id = j.id AND a.bucket = 'screened_out') AS held_applicants
            FROM career_jobs j
            ORDER BY FIELD(j.status,'open','paused','draft','closed'), j.updated_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['is_open_now'] = self::isOpen($r);
        }
        return $rows;
    }

    /**
     * Creates ($id null) or updates a posting.
     * @param array<string,mixed> $in text fields as typed in the form
     */
    public static function saveJob(PDO $pdo, int $adminId, ?int $id, array $in): array
    {
        $title = self::line($in['title'] ?? '', 160);
        $status = (string)($in['status'] ?? 'draft');
        $mode = (string)($in['work_mode'] ?? 'onsite');
        $type = (string)($in['employment_type'] ?? 'full_time');
        $summary = self::line($in['summary'] ?? '', 400);
        $description = self::block($in['description'] ?? '', 6000);
        $resp = self::listText($in['responsibilities'] ?? '');
        $req = self::listText($in['requirements'] ?? '');
        $nice = self::listText($in['nice_to_have'] ?? '');
        $ben = self::listText($in['benefits'] ?? '');
        $openings = max(1, min(99, (int)($in['openings'] ?? 1)));
        $must = self::listText($in['must_have'] ?? '', 40, 80);
        $niceKw = self::listText($in['nice_have'] ?? '', 40, 80);
        $minYears = max(0, min(40, (int)($in['min_years'] ?? 0)));
        $minEdu = in_array((string)($in['min_education'] ?? ''), self::EDUCATION, true) && ($in['min_education'] ?? '') !== 'other' ? (string)$in['min_education'] : '';
        // Screening is on unless the form says otherwise (a posting saved without the field keeps the safe default)
        $screenOn = !array_key_exists('screen_enabled', $in) || (!empty($in['screen_enabled']) && $in['screen_enabled'] !== '0') ? 1 : 0;
        $threshold = max(10, min(80, (int)($in['screen_threshold'] ?? 35)));
        $autoDecline = max(0, min(90, (int)($in['auto_decline_days'] ?? 0)));
        $closes = trim((string)($in['closes_at'] ?? ''));
        $fields = [];
        // The team is chosen from the list: required on creation, kept when an update does not send it
        $teamIn = array_key_exists('department', $in) ? trim((string)$in['department']) : null;
        $team = $teamIn === null ? '' : self::teamKey($teamIn);
        if ($teamIn !== null && $team === '') {
            $fields['department'] = $teamIn === '' ? 'required' : 'invalid';
        }
        $codeRaw = trim((string)($in['code'] ?? ''));
        $code = self::normalizeCode($codeRaw);
        if ($codeRaw !== '' && $code === '') {
            $fields['code'] = 'invalid';
        }

        if (mb_strlen($title) < 3) {
            $fields['title'] = 'required';
        }
        if (!in_array($status, self::JOB_STATUSES, true)) {
            $status = 'draft';
        }
        if (!in_array($mode, self::MODES, true)) {
            $fields['work_mode'] = 'invalid';
        }
        if (!in_array($type, self::TYPES, true)) {
            $fields['employment_type'] = 'invalid';
        }
        if ($closes !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $closes);
            if (!$d || $d->format('Y-m-d') !== $closes) {
                $fields['closes_at'] = 'invalid';
            }
        } else {
            $closes = '';
        }
        if ($status === 'open') {
            // A posting that visitors will see must be readable.
            if (mb_strlen($summary) < 20) {
                $fields['summary'] = 'too_short';
            }
            if ($description === '' && $resp === '') {
                $fields['description'] = 'required';
            }
            if ($closes !== '' && !isset($fields['closes_at']) && $closes < date('Y-m-d')) {
                $fields['closes_at'] = 'past';
            }
        }
        if ($fields) {
            return ['ok' => false, 'error' => 'validation', 'fields' => $fields, 'message' => 'Vérifiez les champs signalés.'];
        }

        $existing = $id ? self::job($pdo, $id) : null;
        if ($id && !$existing) {
            return ['ok' => false, 'error' => 'not_found', 'message' => 'Offre introuvable.'];
        }
        if ($code !== '' && self::codeTaken($pdo, $code, $id ?: null)) {
            return ['ok' => false, 'error' => 'validation', 'fields' => ['code' => 'taken'], 'message' => 'Ce code est déjà utilisé.'];
        }
        if ($teamIn === null) {
            if ($existing && isset(self::TEAMS[(string)$existing['department']])) {
                $team = (string)$existing['department'];
            } else {
                return ['ok' => false, 'error' => 'validation', 'fields' => ['department' => 'required'], 'message' => 'Choisissez une équipe.'];
            }
        }
        if ($code === '') {
            $code = $existing ? (string)$existing['code'] : self::uniqueCode($pdo, $title);
        }

        $data = [
            'title' => $title, 'department' => $team, 'location' => self::line($in['location'] ?? '', 120),
            'mode' => $mode, 'type' => $type, 'level' => self::line($in['level'] ?? '', 40), 'summary' => $summary,
            'description' => $description, 'resp' => $resp, 'req' => $req, 'nice' => $nice, 'ben' => $ben,
            'salary' => self::line($in['salary_note'] ?? '', 160), 'openings' => $openings, 'status' => $status,
            'closes' => $closes !== '' ? $closes : null,
            'code' => $code, 'must' => $must, 'niceKw' => $niceKw, 'minYears' => $minYears, 'minEdu' => $minEdu, 'screenOn' => $screenOn, 'threshold' => $threshold, 'autoDecline' => $autoDecline,
        ];

        if ($existing) {
            $publishedAt = $existing['published_at'];
            $closedAt = $existing['closed_at'];
            if ($status === 'open' && !$publishedAt) {
                $publishedAt = date('Y-m-d H:i:s');
            }
            if ($status === 'closed' && $existing['status'] !== 'closed') {
                $closedAt = date('Y-m-d H:i:s');
            } elseif ($status !== 'closed') {
                $closedAt = null;
            }
            $pdo->prepare("
                UPDATE career_jobs SET title=:title, department=:department, location=:location, work_mode=:mode, employment_type=:type, level=:level,
                    summary=:summary, description=:description, responsibilities=:resp, requirements=:req, nice_to_have=:nice, benefits=:ben,
                    salary_note=:salary, openings=:openings, status=:status, closes_at=:closes, published_at=:pub, closed_at=:cl,
                    code=:code, must_have=:must, nice_have=:niceKw, min_years=:minYears, min_education=:minEdu, screen_enabled=:screenOn, screen_threshold=:threshold,
                    auto_decline_days=:autoDecline, screen_version=screen_version + :bump
                WHERE id=:id
            ")->execute($data + ['pub' => $publishedAt, 'cl' => $closedAt, 'id' => $id, 'bump' => self::criteriaChanged($existing, $data) ? 1 : 0]);
            return ['ok' => true, 'id' => $id, 'slug' => $existing['slug'], 'created' => false];
        }

        $slug = self::uniqueSlug($pdo, $title);
        $pdo->prepare("
            INSERT INTO career_jobs (slug, title, department, location, work_mode, employment_type, level, summary, description, responsibilities,
                requirements, nice_to_have, benefits, salary_note, openings, status, closes_at, published_at, closed_at, created_by,
                code, must_have, nice_have, min_years, min_education, screen_enabled, screen_threshold, auto_decline_days)
            VALUES (:slug, :title, :department, :location, :mode, :type, :level, :summary, :description, :resp, :req, :nice, :ben,
                :salary, :openings, :status, :closes, :pub, :cl, :by, :code, :must, :niceKw, :minYears, :minEdu, :screenOn, :threshold, :autoDecline)
        ")->execute($data + [
            'slug' => $slug, 'pub' => $status === 'open' ? date('Y-m-d H:i:s') : null,
            'cl' => $status === 'closed' ? date('Y-m-d H:i:s') : null, 'by' => $adminId,
        ]);
        return ['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'slug' => $slug, 'created' => true];
    }

    /** True when what the screening reads changed: the applications of this posting are then read again by the worker. */
    private static function criteriaChanged(array $old, array $new): bool
    {
        return (string)$old['must_have'] !== $new['must'] || (string)$old['nice_have'] !== $new['niceKw'] || (int)$old['min_years'] !== $new['minYears']
            || (string)$old['min_education'] !== $new['minEdu'] || (int)$old['screen_enabled'] !== $new['screenOn'] || (int)$old['screen_threshold'] !== $new['threshold']
            || (string)$old['requirements'] !== $new['req'] || (string)$old['title'] !== $new['title'];
    }

    /** Publish, pause, reopen or close a posting. */
    public static function setJobStatus(PDO $pdo, int $id, string $status): array
    {
        if (!in_array($status, self::JOB_STATUSES, true)) {
            return ['ok' => false, 'error' => 'invalid', 'message' => 'Statut invalide.'];
        }
        $job = self::job($pdo, $id);
        if (!$job) {
            return ['ok' => false, 'error' => 'not_found', 'message' => 'Offre introuvable.'];
        }
        if ($status === 'open') {
            if (mb_strlen((string)$job['summary']) < 20 || ((string)$job['description'] === '' && (string)$job['responsibilities'] === '')) {
                return ['ok' => false, 'error' => 'incomplete', 'message' => 'Complétez le résumé et la description avant de publier.'];
            }
            if (!empty($job['closes_at']) && (string)$job['closes_at'] < date('Y-m-d')) {
                return ['ok' => false, 'error' => 'past_date', 'message' => 'La date de clôture est dépassée : modifiez-la avant de publier.'];
            }
        }
        $pdo->prepare("
            UPDATE career_jobs SET status = :s,
                published_at = IF(:s2 = 'open' AND published_at IS NULL, NOW(), published_at),
                closed_at = IF(:s3 = 'closed', NOW(), NULL)
            WHERE id = :i
        ")->execute(['s' => $status, 's2' => $status, 's3' => $status, 'i' => $id]);
        return ['ok' => true, 'status' => $status];
    }

    /** A posting that received applications is closed, not deleted: the applications keep its title and stay reviewable. */
    public static function deleteJob(PDO $pdo, int $id): array
    {
        $job = self::job($pdo, $id);
        if (!$job) {
            return ['ok' => false, 'error' => 'not_found', 'message' => 'Offre introuvable.'];
        }
        $n = $pdo->prepare('SELECT COUNT(*) FROM career_applications WHERE job_id = :i');
        $n->execute(['i' => $id]);
        if ((int)$n->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'has_applications', 'message' => 'Cette offre a des candidatures : clôturez-la plutôt que de la supprimer.'];
        }
        $pdo->prepare('DELETE FROM career_jobs WHERE id = :i')->execute(['i' => $id]);
        return ['ok' => true];
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Applications (public side)
    // ---------------------------------------------------------------------------------------------------------------

    /** Checks the fields of the application form. @return array<string,string> field => error code (empty when all good) */
    public static function validateApplication(array $in): array
    {
        $e = [];
        $name = self::line($in['full_name'] ?? '', 120);
        if (mb_strlen($name) < 2) {
            $e['full_name'] = 'required';
        }
        $email = strtolower(self::line($in['email'] ?? '', 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $e['email'] = 'invalid';
        }
        $phone = self::line($in['phone'] ?? '', 40);
        if ($phone !== '' && !preg_match('/^[0-9 +().\-]{6,40}$/', $phone)) {
            $e['phone'] = 'invalid';
        }
        foreach (['linkedin_url', 'portfolio_url'] as $f) {
            $raw = self::line($in[$f] ?? '', 255);
            if ($raw !== '' && self::url($raw) === '') {
                $e[$f] = 'invalid';
            }
        }
        $cover = self::block($in['cover_letter'] ?? '', 5000);
        if (mb_strlen($cover) > 0 && mb_strlen($cover) < 20) {
            $e['cover_letter'] = 'too_short';
        }
        $y = trim((string)($in['years_experience'] ?? ''));
        if ($y !== '' && (!ctype_digit($y) || (int)$y > 60)) {
            $e['years_experience'] = 'invalid';
        }
        foreach ([['education_level', self::EDUCATION], ['work_pref', self::WORK_PREFS], ['heard_from', self::HEARD]] as [$f, $allowed]) {
            $v = (string)($in[$f] ?? '');
            if ($v !== '' && !in_array($v, $allowed, true)) {
                $e[$f] = 'invalid';
            }
        }
        if (empty($in['consent'])) {
            $e['consent'] = 'required';
        }
        return $e;
    }

    /**
     * Checks that an uploaded file really is the kind of document its name says (the browser's word and the extension prove nothing).
     * @return string|null error code: bad_type or null when fine
     */
    public static function resumeKind(string $tmp, string $ext): ?string
    {
        $fh = @fopen($tmp, 'rb');
        if (!$fh) {
            return 'upload';
        }
        $head = (string)fread($fh, 1024);
        fclose($fh);
        $ok = match ($ext) {
            'pdf'  => str_contains($head, '%PDF-'),
            'docx' => str_starts_with($head, "PK\x03\x04"),
            'doc'  => str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
            default => false,
        };
        return $ok ? null : 'bad_type';
    }

    /** The team key for a key, a label in either language, or an alias ('' when it is none of them). */
    public static function teamKey(string $v): string
    {
        $n = self::plain($v);
        if ($n === '') {
            return '';
        }
        if (isset(self::TEAMS[$n])) {
            return $n;
        }
        foreach (self::TEAMS as $k => $labels) {
            if (self::plain($labels['fr']) === $n || self::plain($labels['en']) === $n) {
                return $k;
            }
        }
        foreach (self::TEAM_ALIASES as $k => $words) {
            if (in_array($n, $words, true)) {
                return $k;
            }
        }
        return '';
    }

    /** Label of a team in a language; a value that is not a team (an older free-text one) is shown as it is. */
    public static function teamLabel(string $key, string $lang = 'fr'): string
    {
        return self::TEAMS[$key][$lang === 'en' ? 'en' : 'fr'] ?? $key;
    }

    /** Teams as [key => label] in a language, for dropdowns. */
    public static function teamsFor(string $lang): array
    {
        $o = [];
        foreach (self::TEAMS as $k => $l) {
            $o[$k] = $l[$lang === 'en' ? 'en' : 'fr'];
        }
        return $o;
    }

    /** Lower case, no accents, no punctuation: for comparing words. */
    public static function plain(string $v): string
    {
        $v = mb_strtolower(trim($v), 'UTF-8');
        if (class_exists('Normalizer')) {
            $n = Normalizer::normalize($v, Normalizer::FORM_D);
            $v = $n !== false ? (string)preg_replace('/\p{Mn}+/u', '', $n) : $v;
        }
        return trim((string)preg_replace('/[^a-z0-9 ]+/', ' ', str_replace(['&', '+'], [' ', ' '], $v)) ?: '');
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Posting code and application reference
    // ---------------------------------------------------------------------------------------------------------------

    /** 4 capital letters or digits, or '' when the text is not one. */
    public static function normalizeCode(mixed $v): string
    {
        $v = strtoupper(trim((string)$v));
        return preg_match('/^[A-Z0-9]{4}$/', $v) ? $v : '';
    }

    /**
     * A readable code from a title: initials when there are four words or more, otherwise the first letters of each word
     * ("Senior Full-Stack Developer" gives SFSD, "Product Designer" gives PRDE, "Intern" gives INTE).
     */
    public static function suggestCode(string $title): string
    {
        $ascii = strtoupper(str_replace('-', ' ', self::slugify($title)));
        $words = array_values(array_filter(explode(' ', preg_replace('/[^A-Z ]/', ' ', $ascii) ?? ''), static fn($w) => $w !== '' && !in_array($w, self::CODE_STOP, true)));
        if (!$words) {
            return 'JOB' . random_int(1, 9);
        }
        $n = count($words);
        $code = match (true) {
            $n === 1 => substr($words[0], 0, 4),
            $n === 2 => substr($words[0], 0, 2) . substr($words[1], 0, 2),
            $n === 3 => substr($words[0], 0, 2) . $words[1][0] . $words[2][0],
            default  => $words[0][0] . $words[1][0] . $words[2][0] . $words[3][0],
        };
        $i = strlen($code);
        $pool = implode('', $words);
        while (strlen($code) < 4) {                       // a short word: borrow the next letters of the title
            $code .= $pool[$i] ?? 'X';
            $i++;
        }
        return substr($code, 0, 4);
    }

    public static function codeTaken(PDO $pdo, string $code, ?int $ignoreId = null): bool
    {
        if ($code === self::OPEN_CODE) {
            return true;
        }
        $st = $pdo->prepare('SELECT 1 FROM career_jobs WHERE code = :c' . ($ignoreId ? ' AND id <> :i' : '') . ' LIMIT 1');
        $args = ['c' => $code];
        if ($ignoreId) {
            $args['i'] = $ignoreId;
        }
        $st->execute($args);
        return (bool)$st->fetchColumn();
    }

    /** A free code: the suggestion, then the same with another last character, then a random one. */
    public static function uniqueCode(PDO $pdo, string $title, ?int $ignoreId = null): string
    {
        $base = self::suggestCode($title);
        if (!self::codeTaken($pdo, $base, $ignoreId)) {
            return $base;
        }
        foreach (str_split(self::REF_ALPHABET) as $c) {
            $try = substr($base, 0, 3) . $c;
            if (!self::codeTaken($pdo, $try, $ignoreId)) {
                return $try;
            }
        }
        for ($i = 0; $i < 50; $i++) {
            $try = '';
            for ($k = 0; $k < 4; $k++) {
                $try .= self::REF_ALPHABET[random_int(0, strlen(self::REF_ALPHABET) - 1)];
            }
            if (!self::codeTaken($pdo, $try, $ignoreId)) {
                return $try;
            }
        }
        return strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    }

    /** Postings that existed before codes were introduced get theirs. Safe to run again. */
    public static function backfillCodes(PDO $pdo): int
    {
        $n = 0;
        foreach ($pdo->query("SELECT id, title FROM career_jobs WHERE code = '' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $j) {
            $pdo->prepare('UPDATE career_jobs SET code = :c WHERE id = :i')->execute(['c' => self::uniqueCode($pdo, (string)$j['title'], (int)$j['id']), 'i' => $j['id']]);
            $n++;
        }
        return $n;
    }

    /** A reference as handed out today (CODE + 2-digit year + 5 characters, e.g. FSDV26K7M2X) or as before codes existed (CA-XXXXXX). */
    public static function isReference(string $s): bool
    {
        return (bool)preg_match('/^(?:[A-Z0-9]{4}\d{2}[A-Z0-9]{5}|CA-[A-Z0-9]{4,8})$/', $s);
    }

    /**
     * The reference of a new application: the posting's 4-character code, the year's last two digits and 5 random characters,
     * written together without any separator (FSDV26K7M2X). Open applications use the code OPEN. Always unique.
     */
    public static function newReference(PDO $pdo, ?array $job): string
    {
        $code = $job ? self::normalizeCode($job['code'] ?? '') : self::OPEN_CODE;
        $code = $code !== '' ? $code : 'JOB0';
        $yy = date('y');
        for ($try = 0; $try < 30; $try++) {
            $tail = '';
            for ($i = 0; $i < 5; $i++) {
                $tail .= self::REF_ALPHABET[random_int(0, strlen(self::REF_ALPHABET) - 1)];
            }
            $ref = $code . $yy . $tail;
            $st = $pdo->prepare('SELECT 1 FROM career_applications WHERE reference = :r');
            $st->execute(['r' => $ref]);
            if (!$st->fetchColumn()) {
                return $ref;
            }
        }
        return $code . $yy . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
    }

    private static function reference(PDO $pdo, ?array $job = null): string
    {
        return self::newReference($pdo, $job);
    }

    /**
     * Stores an application.
     *
     * @param array|null $job  the posting (null for an open application)
     * @param array      $in   form fields
     * @param array      $file an entry of $_FILES for the résumé
     * @return array{ok:bool,error?:string,fields?:array,message?:string,id?:int,reference?:string}
     */
    public static function submit(PDO $pdo, ?array $job, array $in, array $file, string $lang, string $ip): array
    {
        if ($job !== null && !self::isOpen($job)) {
            return ['ok' => false, 'error' => 'closed', 'message' => 'closed'];
        }
        $fields = self::validateApplication($in);

        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $hasFile = $err !== UPLOAD_ERR_NO_FILE;
        if (!$hasFile) {
            $fields['resume'] = 'required';
        } elseif ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || (int)($file['size'] ?? 0) > self::RESUME_MAX) {
            $fields['resume'] = 'too_big';
        } elseif ($err !== UPLOAD_ERR_OK) {
            $fields['resume'] = 'upload';
        } else {
            $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($ext, self::RESUME_EXT, true)) {
                $fields['resume'] = 'bad_type';
            } elseif (($bad = self::resumeKind((string)($file['tmp_name'] ?? ''), $ext)) !== null) {
                $fields['resume'] = $bad;
            }
        }
        if ($fields) {
            return ['ok' => false, 'error' => 'validation', 'fields' => $fields, 'message' => 'validation'];
        }

        $email = strtolower(self::line($in['email'] ?? '', 190));
        $jobId = $job ? (int)$job['id'] : null;

        // One application per person per posting (an open application: one per 30 days).
        if ($jobId !== null) {
            $dup = $pdo->prepare('SELECT reference FROM career_applications WHERE job_id = :j AND email = :e LIMIT 1');
            $dup->execute(['j' => $jobId, 'e' => $email]);
        } else {
            $dup = $pdo->prepare('SELECT reference FROM career_applications WHERE job_id IS NULL AND email = :e AND created_at >= NOW() - INTERVAL 30 DAY LIMIT 1');
            $dup->execute(['e' => $email]);
        }
        if ($dup->fetchColumn()) {
            return ['ok' => false, 'error' => 'duplicate', 'message' => 'duplicate'];
        }

        $saved = MediaStore::saveDocument($pdo, $file, 'career_resume', self::RESUME_EXT, self::RESUME_MAX, 'cv_');
        if (!$saved['ok']) {
            return ['ok' => false, 'error' => 'validation', 'fields' => ['resume' => (string)($saved['error'] ?? 'upload')], 'message' => 'validation'];
        }

        $ref = self::reference($pdo, $job);
        $token = bin2hex(random_bytes(20));
        $secret = defined('APP_SECRET') ? (string)APP_SECRET : 'studyvibe';
        try {
            $pdo->prepare("
                INSERT INTO career_applications
                    (reference, job_id, job_title, full_name, email, phone, location, linkedin_url, portfolio_url, cover_letter, resume_file, resume_name,
                     resume_size, availability, salary_expectation, source, lang, consent_at, ip_hash,
                     years_experience, current_title, current_company, education_level, skills, languages, work_pref, relocate, heard_from, talent_consent,
                     track_token, bucket, bucket_changed_at, screen_state)
                VALUES (:ref, :job, :jt, :name, :email, :phone, :loc, :li, :pf, :cover, :rf, :rn, :rs, :av, :sal, :src, :lang, NOW(), :ip,
                     :yrs, :ctitle, :ccomp, :edu, :skills, :langs, :wpref, :reloc, :heard, :talent,
                     :tok, 'inbox', NOW(), 'pending')
            ")->execute([
                'yrs' => trim((string)($in['years_experience'] ?? '')) === '' ? null : (int)$in['years_experience'],
                'ctitle' => self::line($in['current_title'] ?? '', 120), 'ccomp' => self::line($in['current_company'] ?? '', 120),
                'edu' => in_array((string)($in['education_level'] ?? ''), self::EDUCATION, true) ? (string)$in['education_level'] : '',
                'skills' => self::skills($in['skills'] ?? ''), 'langs' => self::line($in['languages'] ?? '', 160),
                'wpref' => in_array((string)($in['work_pref'] ?? ''), self::WORK_PREFS, true) ? (string)$in['work_pref'] : '',
                'reloc' => isset($in['relocate']) && $in['relocate'] !== '' ? 1 : null,
                'heard' => in_array((string)($in['heard_from'] ?? ''), self::HEARD, true) ? (string)$in['heard_from'] : '',
                'talent' => !empty($in['talent_consent']) ? 1 : 0, 'tok' => $token,
                'ref' => $ref, 'job' => $jobId, 'jt' => $job ? (string)$job['title'] : '',
                'name' => self::line($in['full_name'] ?? '', 120), 'email' => $email,
                'phone' => self::line($in['phone'] ?? '', 40), 'loc' => self::line($in['location'] ?? '', 120),
                'li' => self::url($in['linkedin_url'] ?? ''), 'pf' => self::url($in['portfolio_url'] ?? ''),
                'cover' => self::block($in['cover_letter'] ?? '', 5000) ?: null,
                'rf' => $saved['file'], 'rn' => self::line(pathinfo((string)($file['name'] ?? 'resume'), PATHINFO_BASENAME), 160) ?: 'resume',
                'rs' => (int)($saved['size'] ?? 0),
                'av' => self::line($in['availability'] ?? '', 80), 'sal' => self::line($in['salary_expectation'] ?? '', 80),
                'src' => preg_replace('/[^a-z0-9_.-]/', '', strtolower(self::line($in['source'] ?? '', 60))) ?? '',
                'lang' => $lang === 'en' ? 'en' : 'fr', 'ip' => hash_hmac('sha256', $ip, $secret),
            ]);
        } catch (PDOException $ex) {
            MediaStore::delete($pdo, 'career_resume', (string)$saved['file']);
            if ($ex->getCode() === '23000') {      // two clicks at once
                return ['ok' => false, 'error' => 'duplicate', 'message' => 'duplicate'];
            }
            throw $ex;
        }
        $newId = (int)$pdo->lastInsertId();
        self::event($pdo, $newId, null, 'status', null, 'new', null, true);
        return ['ok' => true, 'id' => $newId, 'reference' => $ref, 'token' => $token];
    }

    /** Skills typed as a list: split on commas, semicolons and new lines, trimmed, de-duplicated, kept short. */
    public static function skills(mixed $v): string
    {
        $seen = []; $out = [];
        foreach (preg_split('/[,;\n]+/', (string)$v) ?: [] as $t) {
            $t = self::line($t, 40);
            if ($t !== '' && !isset($seen[mb_strtolower($t)])) {
                $seen[mb_strtolower($t)] = true;
                $out[] = $t;
            }
            if (count($out) >= 25) {
                break;
            }
        }
        return mb_substr(implode(', ', $out), 0, 500);
    }

    /** Address of the page where the candidate follows their application. */
    public static function trackUrl(string $token, array $q = []): string
    {
        $base = defined('APP_URL') && (string)APP_URL !== '' ? rtrim((string)APP_URL, '/') : ((!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? ''));
        return $base . '/track.php?' . http_build_query(['t' => $token] + $q);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Applications (administrator side)
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * Applications for the list, newest first. Filters: job_id (int, or 'none' for open applications), status, q (name, e-mail, reference),
     * starred (bool). The cover letter is left out (it can be long): the detail call returns it.
     */
    public static function applications(PDO $pdo, array $f = [], int $limit = 500): array
    {
        $where = ['1=1'];
        $args = [];
        if (isset($f['job_id']) && $f['job_id'] !== '' && $f['job_id'] !== null) {
            if ($f['job_id'] === 'none') {
                $where[] = 'a.job_id IS NULL';
            } else {
                $where[] = 'a.job_id = :job';
                $args['job'] = (int)$f['job_id'];
            }
        }
        if (!empty($f['status']) && in_array($f['status'], self::APP_STATUSES, true)) {
            $where[] = 'a.status = :st';
            $args['st'] = $f['status'];
        }
        if (!empty($f['starred'])) {
            $where[] = 'a.is_starred = 1';
        }
        // The list a reviewer works from is the inbox; the screening section asks for 'screened_out' or 'archived'; 'any' is everything.
        $bucket = (string)($f['bucket'] ?? 'inbox');
        if ($bucket !== 'any') {
            $where[] = 'a.bucket = :bk';
            $args['bk'] = in_array($bucket, self::BUCKETS, true) ? $bucket : 'inbox';
        }
        if (!empty($f['verdict']) && in_array($f['verdict'], ['strong', 'potential', 'review', 'weak'], true)) {
            $where[] = 'a.screen_verdict = :vd';
            $args['vd'] = $f['verdict'];
        }
        if (isset($f['q']) && trim((string)$f['q']) !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], self::line($f['q'], 80)) . '%';
            $where[] = '(a.full_name LIKE :q1 OR a.email LIKE :q2 OR a.reference LIKE :q3 OR a.job_title LIKE :q4)';
            $args += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        $sql = "SELECT a.id, a.reference, a.job_id, a.job_title, a.full_name, a.email, a.phone, a.location, a.status, a.rating, a.is_starred,
                       a.created_at, a.status_changed_at, (a.cover_letter IS NOT NULL AND a.cover_letter <> '') AS has_cover, j.slug AS job_slug,
                       a.bucket, a.bucket_changed_at, a.screen_score, a.screen_verdict, a.screen_state, a.years_experience, a.current_title, a.talent_consent,
                       (SELECT MIN(i.scheduled_at) FROM career_interviews i WHERE i.application_id = a.id AND i.status IN ('scheduled','confirmed','reschedule_requested') AND i.scheduled_at >= NOW()) AS next_interview,
                       IF(a.screen_report IS NULL, NULL, JSON_EXTRACT(a.screen_report, '$.missing')) AS rep_missing, IF(a.screen_report IS NULL, NULL, JSON_EXTRACT(a.screen_report, '$.knockouts')) AS rep_ko,
                       IF(a.screen_report IS NULL, NULL, JSON_EXTRACT(a.screen_report, '$.flags')) AS rep_flags, j.auto_decline_days
                FROM career_applications a LEFT JOIN career_jobs j ON j.id = a.job_id
                WHERE " . implode(' AND ', $where) . " ORDER BY a.created_at DESC, a.id DESC LIMIT " . max(1, min(2000, $limit));
        $st = $pdo->prepare($sql);
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function application(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare("
            SELECT a.*, j.slug AS job_slug, j.status AS job_status, u.name AS reviewer_name
            FROM career_applications a
            LEFT JOIN career_jobs j ON j.id = a.job_id
            LEFT JOIN users u ON u.id = a.reviewed_by
            WHERE a.id = :i
        ");
        $st->execute(['i' => $id]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a) {
            return null;
        }
        unset($a['ip_hash']);
        $a['screen'] = $a['screen_report'] ? (json_decode((string)$a['screen_report'], true) ?: null) : null;
        unset($a['screen_report']);
        $iv = $pdo->prepare('SELECT * FROM career_interviews WHERE application_id = :i ORDER BY scheduled_at DESC');
        $iv->execute(['i' => $id]);
        $a['interviews'] = $iv->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $tx = $pdo->prepare('SELECT chars, via FROM career_application_texts WHERE application_id = :i');
        $tx->execute(['i' => $id]);
        $a['resume_info'] = $tx->fetch(PDO::FETCH_ASSOC) ?: null;
        $ev = $pdo->prepare("
            SELECT e.id, e.kind, e.from_status, e.to_status, e.body, e.created_at, e.visible, u.name AS actor_name
            FROM career_application_events e LEFT JOIN users u ON u.id = e.actor_id
            WHERE e.application_id = :i ORDER BY e.created_at DESC, e.id DESC
        ");
        $ev->execute(['i' => $id]);
        $a['events'] = $ev->fetchAll(PDO::FETCH_ASSOC) ?: [];
        // The same person's other applications, so a reviewer sees the whole picture.
        $other = $pdo->prepare('SELECT id, reference, job_title, status, created_at FROM career_applications WHERE email = :e AND id <> :i ORDER BY created_at DESC LIMIT 10');
        $other->execute(['e' => $a['email'], 'i' => $id]);
        $a['others'] = $other->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $a;
    }

    /** One line of the trail. $visible: shown to the candidate on their tracking page. */
    public static function event(PDO $pdo, int $appId, ?int $actor, string $kind, ?string $from, ?string $to, ?string $body, bool $visible = false): void
    {
        $pdo->prepare('INSERT INTO career_application_events (application_id, actor_id, kind, from_status, to_status, body, visible) VALUES (:a, :u, :k, :f, :t, :b, :v)')
            ->execute(['a' => $appId, 'u' => $actor, 'k' => $kind, 'f' => $from, 't' => $to, 'b' => $body, 'v' => $visible ? 1 : 0]);
    }

    /**
     * Moves an application to another stage. With $notify the candidate gets an e-mail (the administrator's own text, or a default one).
     * @return array{ok:bool,error?:string,message?:string,mailed?:bool}
     */
    public static function setStatus(PDO $pdo, int $id, int $adminId, string $to, string $message = '', bool $notify = false): array
    {
        if (!in_array($to, self::APP_STATUSES, true) || $to === 'withdrawn') {
            return ['ok' => false, 'error' => 'invalid', 'message' => 'Statut invalide.'];
        }
        $a = self::application($pdo, $id);
        if (!$a) {
            return ['ok' => false, 'error' => 'not_found', 'message' => 'Candidature introuvable.'];
        }
        $message = self::block($message, 2000);
        $from = (string)$a['status'];
        if ($from !== $to) {
            $pdo->prepare('UPDATE career_applications SET status = :s, status_changed_at = NOW(), reviewed_by = :u WHERE id = :i')
                ->execute(['s' => $to, 'u' => $adminId, 'i' => $id]);
            self::event($pdo, $id, $adminId, 'status', $from, $to, null, true);
            // Working on an application takes it out of the screening section: it is now part of the pipeline.
            if ($a['bucket'] !== 'inbox') {
                self::moveBucket($pdo, $id, $adminId, 'inbox');
            }
            // A person who is out of the process gets no more interview reminders.
            if (in_array($to, ['rejected', 'hired'], true)) {
                require_once __DIR__ . '/CareersInterviews.php';
                CareersInterviews::cancelOpen($pdo, $id, 'stage');
            }
        }
        $mailed = false;
        if ($notify) {
            $mailed = CareersMail::status($a, $to, $message);
            self::event($pdo, $id, $adminId, 'email', null, $to, ($mailed ? '' : '[not sent] ') . ($message !== '' ? $message : '(default message)'));
        }
        return ['ok' => true, 'mailed' => $mailed, 'changed' => $from !== $to];
    }

    /** Puts an application in the inbox, the screening section ("screened_out") or the archive. */
    public static function moveBucket(PDO $pdo, int $id, ?int $adminId, string $bucket): array
    {
        if (!in_array($bucket, self::BUCKETS, true)) {
            return ['ok' => false, 'error' => 'invalid', 'message' => 'Section invalide.'];
        }
        $st = $pdo->prepare('SELECT bucket FROM career_applications WHERE id = :i');
        $st->execute(['i' => $id]);
        $from = $st->fetchColumn();
        if ($from === false) {
            return ['ok' => false, 'error' => 'not_found', 'message' => 'Candidature introuvable.'];
        }
        if ($from !== $bucket) {
            $pdo->prepare('UPDATE career_applications SET bucket = :b, bucket_changed_at = NOW(), archived_at = IF(:b2 = \'archived\', NOW(), NULL) WHERE id = :i')
                ->execute(['b' => $bucket, 'b2' => $bucket, 'i' => $id]);
            self::event($pdo, $id, $adminId, 'bucket', (string)$from, $bucket, null);
        }
        return ['ok' => true, 'bucket' => $bucket, 'changed' => $from !== $bucket];
    }

    /**
     * One action on several applications: archive | restore | inbox | delete.
     * @param int[] $ids
     */
    public static function bulk(PDO $pdo, array $ids, int $adminId, string $op): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0)));
        if (!$ids || count($ids) > 500) {
            return ['ok' => false, 'error' => 'invalid', 'message' => 'Sélection invalide.'];
        }
        if (!in_array($op, ['archive', 'restore', 'inbox', 'delete'], true)) {
            return ['ok' => false, 'error' => 'invalid', 'message' => 'Action invalide.'];
        }
        $done = 0;
        foreach ($ids as $id) {
            $r = $op === 'delete' ? self::deleteApplication($pdo, $id)
               : self::moveBucket($pdo, $id, $adminId, $op === 'archive' ? 'archived' : 'inbox');
            $done += !empty($r['ok']) ? 1 : 0;
        }
        return ['ok' => true, 'done' => $done];
    }

    /**
     * The polite decline of an application the screening held back and nobody reviewed within the posting's delay (auto_decline_days).
     * Moves it to "rejected", archives it, and writes to the candidate. Only the worker calls this.
     */
    public static function autoDecline(PDO $pdo, int $id): bool
    {
        $a = self::application($pdo, $id);
        if (!$a || $a['status'] !== 'new' || $a['bucket'] !== 'screened_out') {
            return false;
        }
        // Somebody already looked at it (a note, a rating, an e-mail): it is not "unreviewed", so it is not declined by the clock.
        $seen = $pdo->prepare("SELECT 1 FROM career_application_events WHERE application_id = :i AND actor_id IS NOT NULL AND kind IN ('note','rating','email','status','interview','bucket') LIMIT 1");
        $seen->execute(['i' => $id]);
        if ($seen->fetchColumn()) {
            return false;
        }
        $pdo->prepare("UPDATE career_applications SET status = 'rejected', status_changed_at = NOW() WHERE id = :i")->execute(['i' => $id]);
        self::event($pdo, $id, null, 'status', 'new', 'rejected', 'auto', true);
        self::moveBucket($pdo, $id, null, 'archived');
        $mailed = CareersMail::status($a, 'rejected', '');
        self::event($pdo, $id, null, 'email', null, 'rejected', ($mailed ? '' : '[not sent] ') . '(automatic decline)');
        return true;
    }

    /** What the candidate sees of an application: the visible events only, never notes, scores or who reviewed it. */
    public static function publicTrail(PDO $pdo, int $id): array
    {
        $st = $pdo->prepare("SELECT kind, from_status, to_status, body, created_at FROM career_application_events WHERE application_id = :i AND visible = 1 ORDER BY created_at ASC, id ASC");
        $st->execute(['i' => $id]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function byToken(PDO $pdo, string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
            return null;
        }
        $st = $pdo->prepare('SELECT a.*, j.slug AS job_slug FROM career_applications a LEFT JOIN career_jobs j ON j.id = a.job_id WHERE a.track_token = :t');
        $st->execute(['t' => $token]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** The candidate withdraws: the process stops, interviews are cancelled, administrators are told. */
    public static function withdraw(PDO $pdo, string $token): array
    {
        $a = self::byToken($pdo, $token);
        if (!$a) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if (in_array($a['status'], ['withdrawn', 'hired', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'closed'];
        }
        $pdo->prepare("UPDATE career_applications SET status = 'withdrawn', status_changed_at = NOW(), withdrawn_at = NOW() WHERE id = :i")->execute(['i' => $a['id']]);
        self::event($pdo, (int)$a['id'], null, 'status', (string)$a['status'], 'withdrawn', null, true);
        require_once __DIR__ . '/CareersInterviews.php';
        CareersInterviews::cancelOpen($pdo, (int)$a['id'], 'withdrawn');
        if ($a['bucket'] === 'screened_out') {
            self::moveBucket($pdo, (int)$a['id'], null, 'archived');
        }
        CareersMail::candidateActed($pdo, $a, 'withdrawn');
        return ['ok' => true];
    }

    public static function addNote(PDO $pdo, int $id, int $adminId, string $body): array
    {
        $body = self::block($body, 3000);
        if ($body === '') {
            return ['ok' => false, 'error' => 'empty', 'message' => 'La note est vide.'];
        }
        if (!self::exists($pdo, $id)) {
            return ['ok' => false, 'error' => 'not_found', 'message' => 'Candidature introuvable.'];
        }
        self::event($pdo, $id, $adminId, 'note', null, null, $body);
        return ['ok' => true];
    }

    public static function setRating(PDO $pdo, int $id, int $adminId, ?int $rating): array
    {
        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            return ['ok' => false, 'error' => 'invalid', 'message' => 'Note invalide.'];
        }
        if (!self::exists($pdo, $id)) {
            return ['ok' => false, 'error' => 'not_found', 'message' => 'Candidature introuvable.'];
        }
        $pdo->prepare('UPDATE career_applications SET rating = :r, reviewed_by = :u WHERE id = :i')->execute(['r' => $rating, 'u' => $adminId, 'i' => $id]);
        self::event($pdo, $id, $adminId, 'rating', null, null, $rating === null ? '' : (string)$rating);
        return ['ok' => true];
    }

    public static function setStar(PDO $pdo, int $id, bool $on): array
    {
        if (!self::exists($pdo, $id)) {
            return ['ok' => false, 'error' => 'not_found', 'message' => 'Candidature introuvable.'];
        }
        $pdo->prepare('UPDATE career_applications SET is_starred = :s WHERE id = :i')->execute(['s' => $on ? 1 : 0, 'i' => $id]);
        return ['ok' => true];
    }

    /** Removes an application and its résumé for good (a candidate's right to erasure). */
    public static function deleteApplication(PDO $pdo, int $id): array
    {
        $a = $pdo->prepare('SELECT resume_file FROM career_applications WHERE id = :i');
        $a->execute(['i' => $id]);
        $row = $a->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'not_found', 'message' => 'Candidature introuvable.'];
        }
        $pdo->prepare('DELETE FROM career_applications WHERE id = :i')->execute(['i' => $id]);
        if (!empty($row['resume_file'])) {
            MediaStore::delete($pdo, 'career_resume', (string)$row['resume_file']);
        }
        return ['ok' => true];
    }

    public static function exists(PDO $pdo, int $id): bool
    {
        $st = $pdo->prepare('SELECT 1 FROM career_applications WHERE id = :i');
        $st->execute(['i' => $id]);
        return (bool)$st->fetchColumn();
    }

    /** Numbers for the overview and the badge in the dashboard menu. */
    public static function stats(PDO $pdo): array
    {
        $byStatus = array_fill_keys(self::APP_STATUSES, 0);
        foreach ($pdo->query("SELECT status, COUNT(*) c FROM career_applications WHERE bucket = 'inbox' GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $byStatus[(string)$r['status']] = (int)$r['c'];
        }
        $jobs = array_fill_keys(self::JOB_STATUSES, 0);
        foreach ($pdo->query('SELECT status, COUNT(*) c FROM career_jobs GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $jobs[(string)$r['status']] = (int)$r['c'];
        }
        $days = [];
        for ($i = 13; $i >= 0; $i--) {
            $days[date('Y-m-d', strtotime("-$i day"))] = 0;
        }
        foreach ($pdo->query('SELECT DATE(created_at) d, COUNT(*) c FROM career_applications WHERE created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY DATE(created_at)')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (isset($days[(string)$r['d']])) {
                $days[(string)$r['d']] = (int)$r['c'];
            }
        }
        $screen = $pdo->query("SELECT
                SUM(bucket = 'screened_out') AS held, SUM(bucket = 'archived') AS archived, SUM(screen_state = 'pending') AS pending, SUM(screen_state = 'error') AS errors,
                SUM(screened_at >= NOW() - INTERVAL 7 DAY) AS week, ROUND(AVG(screen_score)) AS avg_score,
                SUM(screen_verdict = 'strong' AND bucket = 'inbox' AND status = 'new') AS strong_new
            FROM career_applications")->fetch(PDO::FETCH_ASSOC) ?: [];
        $upcoming = (int)$pdo->query("SELECT COUNT(*) FROM career_interviews WHERE status IN ('scheduled','confirmed','reschedule_requested') AND scheduled_at >= NOW()")->fetchColumn();
        return [
            'screening' => ['held' => (int)($screen['held'] ?? 0), 'archived' => (int)($screen['archived'] ?? 0), 'pending' => (int)($screen['pending'] ?? 0), 'errors' => (int)($screen['errors'] ?? 0),
                            'week' => (int)($screen['week'] ?? 0), 'avg' => (int)($screen['avg_score'] ?? 0), 'strong_new' => (int)($screen['strong_new'] ?? 0)],
            'interviews_upcoming' => $upcoming,
            'applications' => array_sum($byStatus), 'by_status' => $byStatus, 'jobs' => $jobs, 'days' => $days,
            'this_week' => (int)$pdo->query('SELECT COUNT(*) FROM career_applications WHERE created_at >= NOW() - INTERVAL 7 DAY')->fetchColumn(),
        ];
    }

    /** Count of applications nobody has opened yet (the badge). Never throws: the dashboard must load even if the tables are not there. */
    public static function newCount(PDO $pdo): int
    {
        try {
            return (int)$pdo->query("SELECT COUNT(*) FROM career_applications WHERE status = 'new' AND bucket = 'inbox'")->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

/**
 * The e-mails of the careers flow, drawn with the same EmailTheme as every other message of the app (the interview e-mails are the one
 * exception: see InterviewMail). Each one is sent in the candidate's language, with their tracking link where it helps.
 */
final class CareersMail
{
    private static function theme(): void
    {
        require_once __DIR__ . '/EmailTheme.php';
        require_once __DIR__ . '/../Mailer.php';
    }

    private static function app(): string
    {
        if (defined('APP_URL') && (string)APP_URL !== '') {
            return rtrim((string)APP_URL, '/');
        }
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        return $host === '' ? '' : ((!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $host);
    }

    private static function first(array $app): string
    {
        return explode(' ', trim((string)$app['full_name']))[0] ?? '';
    }

    /** @return string[] e-mail addresses of the active, verified administrators */
    public static function adminEmails(PDO $pdo): array
    {
        return $pdo->query("SELECT email FROM users WHERE role = 'promoter' AND is_active = 1 AND email_verified_at IS NOT NULL LIMIT 10")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /** "We received your application", with the tracking link. */
    public static function received(array $app): bool
    {
        self::theme();
        $en = ($app['lang'] ?? 'fr') === 'en';
        $role = (string)($app['job_title'] ?: ($en ? 'an open application' : 'une candidature spontanée'));
        $link = Careers::trackUrl((string)$app['track_token']);
        $html = EmailTheme::layout(
            EmailTheme::badge($en ? 'Application received' : 'Candidature reçue')
            . EmailTheme::title($en ? 'Thank you for applying' : 'Merci pour votre candidature')
            . EmailTheme::p(($en ? 'Hello ' : 'Bonjour ') . '<strong>' . EmailTheme::h(self::first($app)) . '</strong>,')
            . EmailTheme::lede($en
                ? 'We have received your application for <strong>' . EmailTheme::h($role) . '</strong>. A real person reads every application.'
                : 'Nous avons bien reçu votre candidature pour <strong>' . EmailTheme::h($role) . '</strong>. Chaque candidature est lue par une vraie personne.')
            . EmailTheme::facts([[$en ? 'Reference' : 'Référence', EmailTheme::code((string)$app['reference'])]])
            . EmailTheme::p($en
                ? 'You can follow every step of your application on your personal page: where it stands, interview details when there are some, and a way to withdraw. We also write to you at each important stage.'
                : 'Suivez chaque étape de votre candidature sur votre page personnelle : où elle en est, les détails d’un éventuel entretien, et un moyen de la retirer. Nous vous écrivons aussi à chaque étape importante.')
            . EmailTheme::button($link, $en ? 'Track my application' : 'Suivre ma candidature')
            . EmailTheme::linkFallback($link, $en ? 'en' : 'fr')
            . EmailTheme::signature($en ? 'en' : 'fr'),
            ['preheader' => $en ? 'We received your application.' : 'Nous avons bien reçu votre candidature.', 'lang' => $en ? 'en' : 'fr']
        );
        return Mailer::send((string)$app['email'], $en ? 'We received your application' : 'Votre candidature est bien reçue', $html);
    }

    /** "A new application" to every administrator. $screen: ['score' => int, 'verdict' => string, 'bucket' => string] once screened. */
    public static function notifyAdmins(PDO $pdo, array $app, ?array $screen = null): int
    {
        self::theme();
        $role = (string)($app['job_title'] ?: 'Candidature spontanée');
        $link = self::app() . '/promoter/careers/#apps';
        $facts = [['Référence', EmailTheme::code((string)$app['reference'])], ['E-mail', EmailTheme::h((string)$app['email'])]];
        if ($screen) {
            $v = ['strong' => 'Profil solide', 'potential' => 'Profil à étudier', 'review' => 'À lire par une personne', 'weak' => 'Peu pertinent'][$screen['verdict']] ?? $screen['verdict'];
            $facts[] = ['Pré-sélection', EmailTheme::h($screen['score'] . '/100 · ' . $v)];
        }
        $html = EmailTheme::layout(
            EmailTheme::badge('Recrutement')
            . EmailTheme::title('Nouvelle candidature')
            . EmailTheme::lede('<strong>' . EmailTheme::h((string)$app['full_name']) . '</strong> a postulé : ' . EmailTheme::h($role) . '.')
            . EmailTheme::facts($facts)
            . EmailTheme::button($link, 'Ouvrir la candidature'),
            ['preheader' => 'Nouvelle candidature : ' . $role, 'lang' => 'fr']
        );
        $sent = 0;
        foreach (self::adminEmails($pdo) as $to) {
            if (Mailer::send((string)$to, 'Nouvelle candidature : ' . $role, $html)) {
                $sent++;
            }
        }
        return $sent;
    }

    /** The administrator's answer on a stage change. $message is theirs; when empty a default text for the stage is used. */
    public static function status(array $app, string $stage, string $message): bool
    {
        self::theme();
        $en = ($app['lang'] ?? 'fr') === 'en';
        $role = (string)($app['job_title'] ?: ($en ? 'your open application' : 'votre candidature spontanée'));
        $defaults = $en ? [
            'reviewing'   => 'We are now reading your application carefully.',
            'shortlisted' => 'Good news: your application is on our shortlist. We will contact you soon about the next step.',
            'interview'   => 'We would like to meet you. We will write to you shortly with a date and a time.',
            'offer'       => 'We are happy to tell you that we want to make you an offer. We will contact you with the details.',
            'hired'       => 'Welcome aboard! We are delighted that you are joining us.',
            'rejected'    => 'Thank you for the time you put into your application. After careful reading, we will not continue with it this time. We keep your details for a short while in case another role fits, and you are welcome to apply again.',
            'new'         => 'We received your application.',
        ] : [
            'reviewing'   => 'Nous étudions maintenant votre candidature avec attention.',
            'shortlisted' => 'Bonne nouvelle : votre candidature fait partie de notre présélection. Nous vous contactons bientôt pour la suite.',
            'interview'   => 'Nous aimerions vous rencontrer. Nous vous écrivons très vite avec une date et une heure.',
            'offer'       => 'Nous avons le plaisir de vous annoncer que nous souhaitons vous faire une proposition. Nous vous contactons avec les détails.',
            'hired'       => 'Bienvenue à bord ! Nous sommes ravis que vous nous rejoigniez.',
            'rejected'    => 'Merci du temps passé sur votre candidature. Après une lecture attentive, nous ne donnerons pas suite cette fois-ci. Nous gardons vos coordonnées un moment au cas où un autre poste vous irait, et vous pouvez postuler de nouveau.',
            'new'         => 'Nous avons bien reçu votre candidature.',
        ];
        $text = $message !== '' ? nl2br(EmailTheme::h($message)) : EmailTheme::h($defaults[$stage] ?? $defaults['new']);
        $link = Careers::trackUrl((string)$app['track_token']);
        $subject = ($en ? 'About your application: ' : 'À propos de votre candidature : ') . $role;
        $html = EmailTheme::layout(
            EmailTheme::badge($en ? 'Your application' : 'Votre candidature')
            . EmailTheme::title(EmailTheme::h($role))
            . EmailTheme::p(($en ? 'Hello ' : 'Bonjour ') . '<strong>' . EmailTheme::h(self::first($app)) . '</strong>,')
            . EmailTheme::p($text)
            . EmailTheme::facts([[$en ? 'Reference' : 'Référence', EmailTheme::code((string)$app['reference'])]])
            . EmailTheme::button($link, $en ? 'See where it stands' : 'Voir où en est ma candidature', 'secondary')
            . EmailTheme::signature($en ? 'en' : 'fr'),
            ['preheader' => mb_substr(strip_tags($text), 0, 90), 'lang' => $en ? 'en' : 'fr']
        );
        return Mailer::send((string)$app['email'], $subject, $html);
    }

    /** The link to the tracking page, asked for with the reference and the address (the answer on screen never says whether they matched). */
    public static function magicLink(array $app): bool
    {
        self::theme();
        $en = ($app['lang'] ?? 'fr') === 'en';
        $link = Careers::trackUrl((string)$app['track_token']);
        $html = EmailTheme::layout(
            EmailTheme::badge($en ? 'Your application' : 'Votre candidature')
            . EmailTheme::title($en ? 'Your tracking link' : 'Votre lien de suivi')
            . EmailTheme::p(($en ? 'Hello ' : 'Bonjour ') . '<strong>' . EmailTheme::h(self::first($app)) . '</strong>,')
            . EmailTheme::p($en ? 'You asked for the link to follow your application. It is personal: do not share it.' : 'Vous avez demandé le lien pour suivre votre candidature. Il est personnel : ne le partagez pas.')
            . EmailTheme::button($link, $en ? 'Open my application' : 'Ouvrir ma candidature')
            . EmailTheme::linkFallback($link, $en ? 'en' : 'fr')
            . EmailTheme::signature($en ? 'en' : 'fr'),
            ['preheader' => $en ? 'Your personal tracking link.' : 'Votre lien de suivi personnel.', 'lang' => $en ? 'en' : 'fr']
        );
        return Mailer::send((string)$app['email'], $en ? 'Your application tracking link' : 'Votre lien de suivi de candidature', $html);
    }

    /** To the administrators: a candidate answered an interview invitation, withdrew or asked for deletion. */
    public static function candidateActed(PDO $pdo, array $app, string $what, string $note = '', ?array $iv = null): int
    {
        self::theme();
        $titles = ['confirmed' => 'Entretien confirmé', 'declined' => 'Entretien décliné', 'reschedule_requested' => 'Autre horaire demandé', 'withdrawn' => 'Candidature retirée', 'erased' => 'Données effacées à la demande du candidat'];
        $title = $titles[$what] ?? 'Mise à jour d’un candidat';
        $facts = [['Candidat', EmailTheme::h((string)$app['full_name'])], ['Poste', EmailTheme::h((string)($app['job_title'] ?: 'Candidature spontanée'))]];
        if ($iv) {
            $facts[] = ['Entretien', EmailTheme::h(date('d/m/Y H:i', strtotime((string)$iv['scheduled_at'])))];
        }
        if ($note !== '') {
            $facts[] = ['Message', EmailTheme::h($note)];
        }
        $html = EmailTheme::layout(
            EmailTheme::badge('Recrutement') . EmailTheme::title($title) . EmailTheme::facts($facts)
            . ($what === 'erased' ? '' : EmailTheme::button(self::app() . '/promoter/careers/#apps', 'Ouvrir l’espace recrutement')),
            ['preheader' => $title . ' — ' . $app['full_name'], 'lang' => 'fr']
        );
        $sent = 0;
        foreach (self::adminEmails($pdo) as $to) {
            if (Mailer::send((string)$to, $title . ' : ' . $app['full_name'], $html)) {
                $sent++;
            }
        }
        return $sent;
    }
}
