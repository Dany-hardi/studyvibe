<?php
declare(strict_types=1);

require_once __DIR__ . '/MediaStore.php';

/**
 * The rules of assignments (homework a teacher attaches to a lesson, students hand in a file or a link, the teacher marks them).
 *
 * A submission goes through: submitted -> graded (a mark and a written feedback, the student is told) -> optionally "new version
 * requested" by the teacher, which lets the student send another one even after the deadline. Rules enforced here, on the server:
 *   - the deadline: after it, no new submission, unless the teacher allows late work (then it is flagged late) or asked for a new version
 *   - resubmission: allowed until the deadline when the teacher permits it, and whenever a new version was requested
 *   - a file must really be what its extension says (a PDF starts with %PDF, a DOCX is a zip with a Word document inside...)
 *   - the student's name and matricule come from the account, never from the form
 * Old versions are archived with their mark and feedback.
 */
final class Assignments
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    /** Extensions that are never accepted, whatever the teacher lists. */
    private const FORBIDDEN = ['php', 'phtml', 'phar', 'exe', 'dll', 'bat', 'cmd', 'com', 'sh', 'bash', 'msi', 'jar', 'vbs', 'scr', 'htaccess', 'svg'];
    /** Plain-text formats: checked for being text. */
    private const TEXT = ['txt', 'md', 'csv', 'py', 'java', 'c', 'h', 'cpp', 'cs', 'sql', 'json', 'ipynb', 'r', 'm', 'tex', 'css', 'xml', 'yml', 'yaml', 'rs', 'go', 'kt', 'ts', 'html', 'htm', 'js'];

    // ---------------------------------------------------------------------------------------------------------------
    // State
    // ---------------------------------------------------------------------------------------------------------------

    /** submitted | graded | revision (a new version was requested) | none */
    public static function status(?array $sub): string
    {
        if ($sub === null) {
            return 'none';
        }
        $rev = $sub['revision_requested_at'] ?? null;
        if ($rev !== null && strtotime((string)$rev) >= strtotime((string)$sub['submitted_at'])) {
            return 'revision';
        }
        return ($sub['graded_at'] ?? null) !== null ? 'graded' : 'submitted';
    }

    /**
     * What the student may do right now.
     *
     * @return array{can_submit:bool,reason:string,late:bool,status:string,deadline_passed:bool}
     *         reason: '' | 'deadline' | 'graded' | 'locked' (resubmission not allowed)
     */
    public static function state(array $lesson, ?array $sub, ?int $now = null): array
    {
        $now ??= time();
        $deadline = !empty($lesson['assignment_deadline']) ? strtotime((string)$lesson['assignment_deadline']) : null;
        $passed = $deadline !== null && $now > $deadline;
        $status = self::status($sub);
        $out = ['can_submit' => false, 'reason' => '', 'late' => false, 'status' => $status, 'deadline_passed' => $passed];

        if ($status === 'revision') {                       // the teacher asked for a new version: always possible
            $out['can_submit'] = true;
            return $out;
        }
        if ($sub !== null) {
            if ($status === 'graded') {
                $out['reason'] = 'graded';
                return $out;
            }
            if (empty($lesson['assignment_allow_resubmit'])) {
                $out['reason'] = 'locked';
                return $out;
            }
        }
        if ($passed) {
            if (empty($lesson['assignment_allow_late'])) {
                $out['reason'] = 'deadline';
                return $out;
            }
            $out['late'] = true;
        }
        $out['can_submit'] = true;
        return $out;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Files
    // ---------------------------------------------------------------------------------------------------------------

    /** @return string[] lower-case extensions the teacher accepts, without anything dangerous */
    public static function allowedExtensions(?string $raw): array
    {
        $list = array_filter(array_map(fn($e) => preg_replace('/[^a-z0-9]/', '', strtolower(trim($e))), explode(',', (string)($raw ?: 'pdf,docx'))));
        $list = array_values(array_diff($list, self::FORBIDDEN));
        return $list ?: ['pdf'];
    }

    /** True when the content of the file is what its extension claims. */
    public static function contentMatches(string $path, string $ext): bool
    {
        $ext = strtolower($ext);
        if (in_array($ext, self::FORBIDDEN, true) || !is_file($path)) {
            return false;
        }
        $head = (string)@file_get_contents($path, false, null, 0, 8);
        switch ($ext) {
            case 'pdf':
                return str_starts_with($head, '%PDF');
            case 'doc': case 'xls': case 'ppt':
                return str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");   // the old Office container
            case 'docx': case 'xlsx': case 'pptx': case 'odt': case 'ods': case 'odp':
                return self::isOfficeZip($path, $ext);
            case 'zip':
                return str_starts_with($head, "PK\x03\x04") || str_starts_with($head, "PK\x05\x06");
            case 'png': case 'jpg': case 'jpeg': case 'gif': case 'webp':
                $info = @getimagesize($path);
                return $info !== false && in_array($info['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
        }
        if (in_array($ext, self::TEXT, true)) {
            $sample = (string)@file_get_contents($path, false, null, 0, 65536);
            return !str_contains($sample, "\0") && (mb_check_encoding($sample, 'UTF-8') || mb_check_encoding($sample, 'ISO-8859-1'));
        }
        return false;   // an extension we do not know how to check is not accepted
    }

    private static function isOfficeZip(string $path, string $ext): bool
    {
        if (!class_exists('ZipArchive')) {
            return str_starts_with((string)@file_get_contents($path, false, null, 0, 4), "PK\x03\x04");
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $need = match ($ext) {
            'docx' => 'word/', 'xlsx' => 'xl/', 'pptx' => 'ppt/', default => 'content.xml',
        };
        $ok = false;
        for ($i = 0; $i < $zip->numFiles && $i < 400; $i++) {
            $n = (string)$zip->getNameIndex($i);
            if (str_starts_with($n, $need) || $n === $need) {
                $ok = true;
                break;
            }
        }
        $zip->close();
        return $ok;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Submitting
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * Saves a submission (a first one or a new version). The previous version goes to the history.
     *
     * @param array $lesson     row of lessons (assignment_* columns)
     * @param array $student    row of users (id, name, matricule)
     * @param array|null $file  an entry of $_FILES, or null
     * @return array{ok:bool,error?:string,late?:bool,submission?:array}
     *         errors: closed, graded, locked, no_matricule, bad_type, bad_content, too_big, need_file, need_link, need_one, upload, server
     */
    public static function submit(PDO $pdo, array $lesson, array $student, ?array $file, string $link, string $comment): array
    {
        $lessonId = (int)$lesson['id'];
        $existing = self::submissionOf($pdo, $lessonId, (int)$student['id']);
        $state = self::state($lesson, $existing);
        if (!$state['can_submit']) {
            return ['ok' => false, 'error' => match ($state['reason']) { 'deadline' => 'closed', 'graded' => 'graded', default => 'locked' }];
        }
        $matricule = trim((string)($student['matricule'] ?? ''));
        if ($matricule === '') {
            return ['ok' => false, 'error' => 'no_matricule'];
        }

        $type = (string)($lesson['assignment_type'] ?? 'both');
        $link = trim($link);
        if ($link !== '') {
            if (!preg_match('~^https?://~i', $link)) {
                $link = 'https://' . $link;
            }
            if (filter_var($link, FILTER_VALIDATE_URL) === false || strlen($link) > 1000) {
                return ['ok' => false, 'error' => 'bad_link'];
            }
        }

        $filePath = $existing['submitted_file_path'] ?? null;
        $fileName = $existing['submitted_file_name'] ?? null;
        $newFile = false;
        if ($file !== null && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ((int)$file['error'] === UPLOAD_ERR_INI_SIZE || (int)$file['error'] === UPLOAD_ERR_FORM_SIZE) {
                return ['ok' => false, 'error' => 'too_big'];
            }
            if ((int)$file['error'] !== UPLOAD_ERR_OK) {
                return ['ok' => false, 'error' => 'upload'];
            }
            $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            $allowed = self::allowedExtensions($lesson['allowed_file_types'] ?? null);
            if (!in_array($ext, $allowed, true)) {
                return ['ok' => false, 'error' => 'bad_type'];
            }
            if ((int)$file['size'] > self::MAX_BYTES) {
                return ['ok' => false, 'error' => 'too_big'];
            }
            if (!self::contentMatches((string)$file['tmp_name'], $ext)) {
                return ['ok' => false, 'error' => 'bad_content'];
            }
            $saved = MediaStore::saveDocument($pdo, $file, 'assignment', $allowed, self::MAX_BYTES, 'sub_' . $lessonId . '_' . (int)$student['id'] . '_');
            if (!$saved['ok']) {
                return ['ok' => false, 'error' => 'server'];
            }
            $filePath = $saved['file'];
            $fileName = mb_substr(basename((string)$file['name']), 0, 250);
            $newFile = true;
        }
        $hasFile = !empty($filePath);
        $hasLink = $link !== '';
        if ($type === 'file' && !$hasFile) { return ['ok' => false, 'error' => 'need_file']; }
        if ($type === 'link' && !$hasLink) { return ['ok' => false, 'error' => 'need_link']; }
        if (!$hasFile && !$hasLink) { return ['ok' => false, 'error' => 'need_one']; }
        $subType = $hasFile && $hasLink ? 'both' : ($hasLink ? 'link' : 'file');

        $pdo->beginTransaction();
        try {
            if ($existing !== null) {
                // The previous version stays on record with its mark and feedback
                $pdo->prepare(
                    "INSERT INTO lesson_assignment_history (submission_id, attempt, file_path, file_name, link, comment, submitted_at, score, feedback, graded_at)
                     VALUES (:s, :a, :fp, :fn, :l, :c, :t, :sc, :fb, :g)"
                )->execute([
                    's' => $existing['id'], 'a' => (int)$existing['attempt_count'], 'fp' => $existing['submitted_file_path'], 'fn' => $existing['submitted_file_name'],
                    'l' => $existing['submitted_link'], 'c' => $existing['student_comment'], 't' => $existing['submitted_at'],
                    'sc' => $existing['score'], 'fb' => $existing['feedback'], 'g' => $existing['graded_at'],
                ]);
                $pdo->prepare(
                    "UPDATE lesson_assignment_submissions SET submission_type = :st, submitted_file_path = :fp, submitted_file_name = :fn, submitted_link = :l,
                            student_comment = :c, submitted_at = NOW(), student_name = :n, student_matricule = :m, is_late = :late, attempt_count = attempt_count + 1,
                            score = NULL, feedback = NULL, graded_at = NULL, graded_by = NULL, revision_requested_at = NULL, revision_note = NULL
                     WHERE id = :id"
                )->execute(['st' => $subType, 'fp' => $filePath, 'fn' => $fileName, 'l' => $hasLink ? $link : null, 'c' => $comment !== '' ? $comment : null,
                            'n' => $student['name'], 'm' => $matricule, 'late' => $state['late'] ? 1 : 0, 'id' => $existing['id']]);
            } else {
                $pdo->prepare(
                    "INSERT INTO lesson_assignment_submissions (lesson_id, student_id, student_name, student_matricule, submission_type, submitted_file_path,
                            submitted_file_name, submitted_link, student_comment, submitted_at, is_late, attempt_count)
                     VALUES (:lid, :sid, :n, :m, :st, :fp, :fn, :l, :c, NOW(), :late, 1)"
                )->execute(['lid' => $lessonId, 'sid' => $student['id'], 'n' => $student['name'], 'm' => $matricule, 'st' => $subType, 'fp' => $filePath,
                            'fn' => $fileName, 'l' => $hasLink ? $link : null, 'c' => $comment !== '' ? $comment : null, 'late' => $state['late'] ? 1 : 0]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            if ($newFile) {
                MediaStore::delete($pdo, 'assignment', (string)$filePath);
            }
            throw $e;
        }
        return ['ok' => true, 'late' => $state['late'], 'submission' => self::submissionOf($pdo, $lessonId, (int)$student['id'])];
    }

    public static function submissionOf(PDO $pdo, int $lessonId, int $studentId): ?array
    {
        $st = $pdo->prepare("SELECT * FROM lesson_assignment_submissions WHERE lesson_id = :l AND student_id = :s");
        $st->execute(['l' => $lessonId, 's' => $studentId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Marking
    // ---------------------------------------------------------------------------------------------------------------

    /** A submission with its lesson, only when the teacher owns the course. */
    public static function ownedSubmission(PDO $pdo, int $submissionId, int $teacherId): ?array
    {
        $st = $pdo->prepare(
            "SELECT s.*, l.title AS lesson_title, l.assignment_title, l.assignment_max_score, l.assignment_deadline, c.id AS course_id, c.title AS course_title,
                    u.name AS account_name, u.email AS student_email, u.lang AS student_lang, t.name AS teacher_name
             FROM lesson_assignment_submissions s
             JOIN lessons l ON l.id = s.lesson_id
             JOIN chapters ch ON ch.id = l.chapter_id
             JOIN courses c ON c.id = ch.course_id
             JOIN users u ON u.id = s.student_id
             LEFT JOIN users t ON t.id = c.teacher_id
             WHERE s.id = :id AND c.teacher_id = :t"
        );
        $st->execute(['id' => $submissionId, 't' => $teacherId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Accepts "14", "14.5" and "14,5". Returns null when it is not a number in [0, max]. */
    public static function parseScore(string $raw, float $max): ?float
    {
        $raw = trim(str_replace(',', '.', $raw));
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        $v = round((float)$raw, 2);
        return ($v < 0 || $v > $max) ? null : $v;
    }

    /** @return array{ok:bool,error?:string} errors: not_found, bad_score */
    public static function grade(PDO $pdo, int $submissionId, int $teacherId, string $scoreRaw, string $feedback): array
    {
        $sub = self::ownedSubmission($pdo, $submissionId, $teacherId);
        if ($sub === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $score = self::parseScore($scoreRaw, (float)$sub['assignment_max_score']);
        if ($score === null) {
            return ['ok' => false, 'error' => 'bad_score'];
        }
        $feedback = trim(mb_substr($feedback, 0, 5000));
        $pdo->prepare(
            "UPDATE lesson_assignment_submissions SET score = :sc, feedback = :fb, graded_at = NOW(), graded_by = :t, revision_requested_at = NULL, revision_note = NULL WHERE id = :id"
        )->execute(['sc' => $score, 'fb' => $feedback !== '' ? $feedback : null, 't' => $teacherId, 'id' => $submissionId]);
        self::tell($pdo, $sub, 'assignment_graded', ['score' => $score, 'max' => (float)$sub['assignment_max_score'], 'feedback' => $feedback]);
        return ['ok' => true];
    }

    /** Asks the student for a new version (allowed even after the deadline). */
    public static function requestRevision(PDO $pdo, int $submissionId, int $teacherId, string $note): array
    {
        $sub = self::ownedSubmission($pdo, $submissionId, $teacherId);
        if ($sub === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $note = trim(mb_substr($note, 0, 500));
        if (mb_strlen($note) < 5) {
            return ['ok' => false, 'error' => 'note_needed'];
        }
        // The mark is cleared only when the new version arrives, so the student keeps seeing the old one meanwhile
        $pdo->prepare("UPDATE lesson_assignment_submissions SET revision_requested_at = NOW(), revision_note = :n WHERE id = :id")->execute(['n' => $note, 'id' => $submissionId]);
        self::tell($pdo, $sub, 'assignment_revision', ['note' => $note]);
        return ['ok' => true];
    }

    private static function tell(PDO $pdo, array $sub, string $template, array $extra): void
    {
        require_once __DIR__ . '/EmailQueue.php';
        require_once __DIR__ . '/Notifications.php';
        $lang = ($sub['student_lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
        $title = (string)($sub['assignment_title'] ?: $sub['lesson_title']);
        $url = rtrim((string)APP_URL, '/') . '/student/dashboard.php?course_id=' . (int)$sub['course_id'] . '&lesson_id=' . (int)$sub['lesson_id'];
        EmailQueue::enqueue($pdo, (int)$sub['student_id'], (string)$sub['student_email'], $template, $extra + [
            'name' => (string)$sub['account_name'], 'title' => $title, 'lesson_title' => (string)$sub['lesson_title'], 'course_title' => (string)$sub['course_title'],
            'teacher' => (string)($sub['teacher_name'] ?? ''), 'lang' => $lang, 'url' => $url,
        ], $template . ':' . $sub['id'] . ':' . bin2hex(random_bytes(3)));
        Notifications::send(
            $pdo, (int)$sub['student_id'], $template,
            $template === 'assignment_graded' ? ($lang === 'en' ? 'Assignment marked' : 'Devoir noté') : ($lang === 'en' ? 'New version requested' : 'Nouvelle version demandée'),
            $title, '/student/dashboard.php'
        );
        EmailQueue::kick();
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Reports
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * One row per enrolled student, one column per assignment of the course: the marks sheet a teacher exports.
     *
     * @return array{headers:string[],rows:array<int,array<int,mixed>>,assignments:array<int,array<string,mixed>>}
     */
    public static function marksSheet(PDO $pdo, int $courseId, ?int $lessonId = null): array
    {
        $q = "SELECT l.id, l.title, l.assignment_title, l.assignment_max_score, ch.sort_order AS chs, l.sort_order AS ls
              FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id
              WHERE ch.course_id = :c AND l.has_assignment = 1" . ($lessonId ? ' AND l.id = :l' : '') . " ORDER BY ch.sort_order, ch.id, l.sort_order, l.id";
        $st = $pdo->prepare($q);
        $st->execute($lessonId ? ['c' => $courseId, 'l' => $lessonId] : ['c' => $courseId]);
        $assignments = $st->fetchAll(PDO::FETCH_ASSOC);

        $students = $pdo->prepare(
            "SELECT u.id, u.name, u.email, u.matricule FROM enrollments e JOIN users u ON u.id = e.student_id
             WHERE e.course_id = :c AND u.role = 'student' ORDER BY u.name ASC"
        );
        $students->execute(['c' => $courseId]);
        $students = $students->fetchAll(PDO::FETCH_ASSOC);

        $subs = [];
        if ($assignments) {
            $ids = implode(',', array_map(fn($a) => (int)$a['id'], $assignments));
            foreach ($pdo->query("SELECT lesson_id, student_id, score, graded_at, is_late FROM lesson_assignment_submissions WHERE lesson_id IN ($ids)")->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $subs[(int)$s['student_id']][(int)$s['lesson_id']] = $s;
            }
        }

        $headers = ['Matricule', 'Nom', 'E-mail'];
        foreach ($assignments as $a) {
            $headers[] = ($a['assignment_title'] ?: $a['title']) . ' (/' . rtrim(rtrim(number_format((float)$a['assignment_max_score'], 2, '.', ''), '0'), '.') . ')';
        }
        $headers[] = 'Total';
        $headers[] = 'Sur';
        $headers[] = '%';

        $rows = [];
        foreach ($students as $stu) {
            $row = [(string)($stu['matricule'] ?? ''), (string)$stu['name'], (string)$stu['email']];
            $total = 0.0;
            $max = 0.0;
            foreach ($assignments as $a) {
                $s = $subs[(int)$stu['id']][(int)$a['id']] ?? null;
                if ($s === null) {
                    $row[] = 'Non rendu';
                } elseif ($s['score'] === null) {
                    $row[] = 'À noter';
                } else {
                    $row[] = (float)$s['score'];
                    $total += (float)$s['score'];
                    $max += (float)$a['assignment_max_score'];
                }
            }
            $row[] = $max > 0 ? round($total, 2) : '';
            $row[] = $max > 0 ? round($max, 2) : '';
            $row[] = $max > 0 ? round($total / $max * 100, 1) : '';
            $rows[] = $row;
        }
        return ['headers' => $headers, 'rows' => $rows, 'assignments' => $assignments];
    }
}
