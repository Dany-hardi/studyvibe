<?php
declare(strict_types=1);

require_once __DIR__ . '/MediaStore.php';

/**
 * Course material sent by anyone through the public form (contribute.php): the PDFs of a course, so the quizzes are built from
 * the real content of the course. Super teachers (and promoters) read them in teacher/contributions.php.
 *
 * A submission: email, optional name, filière, level, course name, optional note, 1 to 5 PDFs of at most 10 MB each.
 * Status: new -> in_review -> accepted (linked to a course) | rejected (with a reason) -> used (a quiz was built from it).
 * The files are stored like the other private uploads (disk + database copy) and are only ever sent as attachments.
 */
final class CourseMaterials
{
    public const MAX_FILES = 5;
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const STATUSES = ['new', 'in_review', 'accepted', 'rejected', 'used'];
    public const LEVELS = ['L1', 'L2', 'L3', 'M1', 'M2', 'BTS', 'Autre'];

    /** The active filières, for the form. */
    public static function filieres(PDO $pdo, bool $all = false): array
    {
        return $pdo->query("SELECT id, name, is_active FROM filieres" . ($all ? '' : " WHERE is_active = 1") . " ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function addFiliere(PDO $pdo, string $name): bool
    {
        $name = self::line($name, 120);
        if ($name === '') {
            return false;
        }
        $pdo->prepare("INSERT INTO filieres (name) VALUES (:n) ON DUPLICATE KEY UPDATE is_active = 1")->execute(['n' => $name]);
        return true;
    }

    public static function toggleFiliere(PDO $pdo, int $id): void
    {
        $pdo->prepare("UPDATE filieres SET is_active = 1 - is_active WHERE id = :i")->execute(['i' => $id]);
    }

    /**
     * $_FILES['files'] (a multiple input) as a plain list of uploads.
     * @return array<int,array{name:string,tmp_name:string,error:int,size:int}>
     */
    public static function normalizeFiles(?array $f): array
    {
        if (!$f || !isset($f['name'])) {
            return [];
        }
        if (!is_array($f['name'])) {
            return [$f];
        }
        $out = [];
        foreach ($f['name'] as $i => $name) {
            if ((int)($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = ['name' => (string)$name, 'tmp_name' => (string)($f['tmp_name'][$i] ?? ''), 'error' => (int)($f['error'][$i] ?? 0), 'size' => (int)($f['size'][$i] ?? 0)];
        }
        return $out;
    }

    /**
     * Checks the fields and the files, stores everything.
     * @return array{ok:bool,reference?:string,id?:int,errors?:array<string,string>}
     *         error codes: email, filiere, course, files_none, files_many, file_big:<name>, file_type:<name>, server
     */
    public static function submit(PDO $pdo, array $in, array $uploads, string $ipHash = '', string $lang = 'fr'): array
    {
        $errors = [];
        $email = strtolower(trim((string)($in['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors['email'] = 'email';
        }
        $filiere = self::line((string)($in['filiere'] ?? ''), 120);
        if ($filiere === '__other') {
            $filiere = self::line((string)($in['filiere_other'] ?? ''), 120);
        }
        if ($filiere === '') {
            $errors['filiere'] = 'filiere';
        }
        $course = self::line((string)($in['course'] ?? ''), 190);
        if (mb_strlen($course) < 2) {
            $errors['course'] = 'course';
        }
        $level = in_array((string)($in['level'] ?? ''), self::LEVELS, true) ? (string)$in['level'] : null;
        $name = self::line((string)($in['name'] ?? ''), 120);
        $note = mb_substr(trim(str_replace("\r\n", "\n", (string)($in['note'] ?? ''))), 0, 2000);

        if (!$uploads) {
            $errors['files'] = 'files_none';
        } elseif (count($uploads) > self::MAX_FILES) {
            $errors['files'] = 'files_many';
        } else {
            foreach ($uploads as $u) {
                $label = self::line((string)$u['name'], 190);
                $err = (int)$u['error'];
                if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || (int)$u['size'] > self::MAX_BYTES
                    || ($err === UPLOAD_ERR_OK && is_file($u['tmp_name']) && filesize($u['tmp_name']) > self::MAX_BYTES)) {
                    $errors['files'] = 'file_big:' . $label;
                    break;
                }
                if ($err !== UPLOAD_ERR_OK || !self::isPdf($u['tmp_name']) || strtolower(pathinfo($label, PATHINFO_EXTENSION)) !== 'pdf') {
                    $errors['files'] = 'file_type:' . $label;
                    break;
                }
            }
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stored = [];
        try {
            $pdo->beginTransaction();
            $ref = self::reference($pdo);
            $pdo->prepare("INSERT INTO material_submissions (reference, email, name, filiere, level, course_label, course_id, note, ip_hash, lang)
                           VALUES (:r, :e, :n, :f, :l, :c, :cid, :note, :ip, :lang)")
                ->execute(['r' => $ref, 'e' => $email, 'n' => $name !== '' ? $name : null, 'f' => $filiere, 'l' => $level, 'c' => $course,
                           'cid' => self::guessCourse($pdo, $course), 'note' => $note !== '' ? $note : null, 'ip' => $ipHash ?: null, 'lang' => $lang === 'en' ? 'en' : 'fr']);
            $id = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO material_files (submission_id, file, original_name, size, sha1) VALUES (:s, :f, :o, :z, :h)");
            foreach ($uploads as $u) {
                $sha = (string)sha1_file($u['tmp_name']);
                $res = MediaStore::saveDocument($pdo, $u, 'material', ['pdf'], self::MAX_BYTES);
                if (!$res['ok']) {
                    throw new RuntimeException('store: ' . ($res['error'] ?? '?'));
                }
                $stored[] = $res['file'];
                $ins->execute(['s' => $id, 'f' => $res['file'], 'o' => self::line((string)$u['name'], 190), 'z' => (int)$res['size'], 'h' => $sha]);
            }
            $pdo->commit();
            return ['ok' => true, 'reference' => $ref, 'id' => $id];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($stored as $f) {
                MediaStore::delete($pdo, 'material', $f);
            }
            error_log('[materials] submit: ' . $e->getMessage());
            return ['ok' => false, 'errors' => ['server' => 'server']];
        }
    }

    /** A real PDF starts with %PDF (a renamed script or picture is refused). */
    public static function isPdf(string $path): bool
    {
        if ($path === '' || !is_file($path)) {
            return false;
        }
        $h = @fopen($path, 'rb');
        if (!$h) {
            return false;
        }
        $head = (string)fread($h, 1024);
        fclose($h);
        return str_contains(substr($head, 0, 1024), '%PDF-');
    }

    /**
     * The list for the Contributions page.
     * @param array{status?:string,filiere?:string,q?:string} $f
     */
    public static function search(PDO $pdo, array $f = [], int $limit = 200): array
    {
        $where = [];
        $p = [];
        if (!empty($f['status']) && in_array($f['status'], self::STATUSES, true)) {
            $where[] = 's.status = :st';
            $p['st'] = $f['status'];
        }
        if (!empty($f['filiere'])) {
            $where[] = 's.filiere = :fi';
            $p['fi'] = $f['filiere'];
        }
        if (!empty($f['q'])) {
            $where[] = '(s.course_label LIKE :q OR s.email LIKE :q OR s.reference LIKE :q OR s.name LIKE :q)';
            $p['q'] = '%' . str_replace(['%', '_'], ['\%', '\_'], (string)$f['q']) . '%';
        }
        $sql = "SELECT s.*, c.title AS course_title, u.name AS reviewer_name,
                       (SELECT COUNT(*) FROM material_files mf WHERE mf.submission_id = s.id) AS file_count,
                       (SELECT COALESCE(SUM(mf.size), 0) FROM material_files mf WHERE mf.submission_id = s.id) AS total_size
                FROM material_submissions s
                LEFT JOIN courses c ON c.id = s.course_id
                LEFT JOIN users u ON u.id = s.reviewed_by"
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . " ORDER BY FIELD(s.status, 'new', 'in_review', 'accepted', 'used', 'rejected'), s.created_at DESC LIMIT " . max(1, min(500, $limit));
        $st = $pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function counts(PDO $pdo): array
    {
        $out = array_fill_keys(self::STATUSES, 0);
        foreach ($pdo->query("SELECT status, COUNT(*) n FROM material_submissions GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[$r['status']] = (int)$r['n'];
        }
        return $out;
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare("SELECT s.*, c.title AS course_title FROM material_submissions s LEFT JOIN courses c ON c.id = s.course_id WHERE s.id = :i");
        $st->execute(['i' => $id]);
        $s = $st->fetch(PDO::FETCH_ASSOC);
        if (!$s) {
            return null;
        }
        $f = $pdo->prepare("SELECT mf.*, (SELECT COUNT(*) FROM material_files d WHERE d.sha1 = mf.sha1 AND d.submission_id <> mf.submission_id) AS dup_count
                            FROM material_files mf WHERE mf.submission_id = :i ORDER BY mf.id");
        $f->execute(['i' => $id]);
        $s['files'] = $f->fetchAll(PDO::FETCH_ASSOC);
        return $s;
    }

    public static function file(PDO $pdo, int $fileId): ?array
    {
        $st = $pdo->prepare("SELECT * FROM material_files WHERE id = :i");
        $st->execute(['i' => $fileId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Moves a submission on. accepted needs a course, rejected needs a reason.
     * @return array{ok:bool,error?:string,previous?:string}
     */
    public static function setStatus(PDO $pdo, int $id, string $status, int $reviewerId, ?int $courseId = null, string $reason = ''): array
    {
        if (!in_array($status, self::STATUSES, true)) {
            return ['ok' => false, 'error' => 'status'];
        }
        $s = self::find($pdo, $id);
        if (!$s) {
            return ['ok' => false, 'error' => 'missing'];
        }
        $reason = self::line($reason, 500);
        if ($courseId !== null && $courseId > 0) {
            $c = $pdo->prepare("SELECT 1 FROM courses WHERE id = :c");
            $c->execute(['c' => $courseId]);
            if (!$c->fetchColumn()) {
                return ['ok' => false, 'error' => 'course'];
            }
        } else {
            $courseId = $s['course_id'] !== null ? (int)$s['course_id'] : null;
        }
        if (in_array($status, ['accepted', 'used'], true) && !$courseId) {
            return ['ok' => false, 'error' => 'course'];
        }
        if ($status === 'rejected' && $reason === '') {
            return ['ok' => false, 'error' => 'reason'];
        }
        $pdo->prepare("UPDATE material_submissions SET status = :st, course_id = :c, reason = :r, reviewed_by = :u, reviewed_at = NOW() WHERE id = :i")
            ->execute(['st' => $status, 'c' => $courseId, 'r' => $status === 'rejected' ? $reason : null, 'u' => $reviewerId, 'i' => $id]);
        return ['ok' => true, 'previous' => (string)$s['status']];
    }

    /** People who receive the material: super teachers and promoters, active. */
    public static function reviewers(PDO $pdo): array
    {
        return $pdo->query("SELECT id, name, email, lang FROM users WHERE role IN ('super_teacher', 'promoter') AND is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function guessCourse(PDO $pdo, string $label): ?int
    {
        $st = $pdo->prepare("SELECT id FROM courses WHERE LOWER(title) = LOWER(:t) LIMIT 2");
        $st->execute(['t' => $label]);
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);
        return count($ids) === 1 ? (int)$ids[0] : null;   // only when the name matches exactly one course
    }

    private static function reference(PDO $pdo): string
    {
        $chk = $pdo->prepare("SELECT 1 FROM material_submissions WHERE reference = :r");
        do {
            $ref = 'MAT-' . date('y') . strtoupper(substr(str_replace(['0', 'O', '1', 'I'], '', base_convert(bin2hex(random_bytes(6)), 16, 36)), 0, 6));
            $chk->execute(['r' => $ref]);
        } while ($chk->fetchColumn());
        return $ref;
    }

    public static function line(string $s, int $max): string
    {
        $s = trim((string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s));
        return mb_substr((string)preg_replace('/\s+/u', ' ', $s), 0, $max);
    }
}
