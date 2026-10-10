<?php
declare(strict_types=1);

require_once __DIR__ . '/PasswordPolicy.php';
require_once __DIR__ . '/Matricule.php';
require_once __DIR__ . '/AuthTokens.php';
require_once __DIR__ . '/MediaStore.php';

/**
 * Accounts as the administrator (role "promoter") manages them: creating any account, and deleting any account. Deleting a user is the
 * administrator's right alone: no other endpoint of the application does it (see tests/integration/admin_users.php).
 *
 * Creating: the account is ready at once (email confirmed, active, approved when it is a teacher). The password is either typed by the
 * administrator, generated (shown once), or left to the person: an invitation email carries a link, valid 72 hours, to choose it.
 * A student can be given a matricule and enrolled in courses right away.
 *
 * Deleting is destructive, so it is guarded:
 *   - never yourself, never the last administrator;
 *   - a teacher who owns courses, live sessions or announcements cannot be deleted by accident: the courses (with all their students'
 *     progress and certificates) and the live sessions (with every result) would go with them. The administrator must either hand
 *     everything to another teacher (reassign_to) or explicitly accept the deletion (cascade);
 *   - what a deleted administrator created (API keys, newsletter campaigns) passes to the administrator who deletes;
 *   - uploaded files (avatar, hand-ins, annotated copies, and, with cascade, covers, PDFs, library files, question pictures) are removed;
 *   - queued emails and texts for that person are dropped; the live-evaluation results of a deleted student stay in the teacher's
 *     records without an account (the database keeps the name and email written on the registration).
 */
final class AdminUsers
{
    public const ROLES = ['student', 'teacher', 'promoter'];
    public const INVITE_HOURS = 72;

    // ---------------------------------------------------------------------------------------------------------------------------
    // Creating
    // ---------------------------------------------------------------------------------------------------------------------------

    /** A password that satisfies the policy and is easy to dictate: no look-alike characters. */
    public static function generatePassword(int $length = 14): string
    {
        $sets = ['abcdefghjkmnpqrstuvwxyz', 'ABCDEFGHJKLMNPQRSTUVWXYZ', '23456789', '-_#!$'];
        $chars = [];
        foreach ($sets as $s) {
            $chars[] = $s[random_int(0, strlen($s) - 1)];
        }
        $all = implode('', $sets);
        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        return implode('', $chars);
    }

    /**
     * @param array $in name, email, role, mode (password | generate | invite), password, matricule, lang, phone, courses (ids), welcome (bool)
     * @return array{ok:bool,error?:string,message?:string,user?:array,password?:string,invited?:bool,mail_sent?:?bool,enrolled?:int}
     *         errors: name, email, email_taken, role, password, matricule, mode
     */
    public static function create(PDO $pdo, int $adminId, array $in): array
    {
        $fail = static fn(string $e, string $m): array => ['ok' => false, 'error' => $e, 'message' => $m];
        $name = trim(preg_replace('/\s+/', ' ', (string)($in['name'] ?? '')) ?? '');
        $email = trim((string)($in['email'] ?? ''));
        $role = (string)($in['role'] ?? '');
        $mode = (string)($in['mode'] ?? 'password');
        $lang = ($in['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            return $fail('name', 'Le nom doit contenir entre 2 et 120 caractères.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            return $fail('email', 'Adresse électronique invalide.');
        }
        if (!in_array($role, self::ROLES, true)) {
            return $fail('role', 'Rôle invalide.');
        }
        if (!in_array($mode, ['password', 'generate', 'invite'], true)) {
            return $fail('mode', 'Mode de mot de passe invalide.');
        }
        $dup = $pdo->prepare('SELECT 1 FROM users WHERE LOWER(email) = LOWER(:e) LIMIT 1');
        $dup->execute(['e' => $email]);
        if ($dup->fetchColumn()) {
            return $fail('email_taken', 'Cette adresse électronique est déjà utilisée.');
        }

        $matricule = null;
        if ($role === 'student' && trim((string)($in['matricule'] ?? '')) !== '') {
            $matricule = Matricule::normalize((string)$in['matricule']);
            $err = Matricule::check($matricule);
            if ($err !== null) {
                return $fail('matricule', Matricule::message($err));
            }
            if (Matricule::isTaken($pdo, $matricule, 0)) {
                return $fail('matricule', Matricule::message('taken'));
            }
        }

        $shown = null;
        if ($mode === 'password') {
            $password = (string)($in['password'] ?? '');
            if (($pw = PasswordPolicy::check($password, $email)) !== null) {
                return $fail('password', $pw);
            }
        } elseif ($mode === 'generate') {
            $password = self::generatePassword();
            $shown = $password;
        } else {
            $password = bin2hex(random_bytes(24));   // nobody knows it: the person chooses theirs from the invitation link
        }

        $phone = trim((string)($in['phone'] ?? ''));
        $phone = $phone !== '' ? mb_substr($phone, 0, 40) : null;
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO users (name, email, password, role, email_verified_at, is_active, is_approved, plan_id, lang, matricule, phone, signup_source)
                 VALUES (:n, :e, :p, :r, NOW(), 1, 1, :plan, :l, :m, :ph, 'admin')"
            )->execute([
                'n' => $name, 'e' => $email, 'p' => password_hash($password, PASSWORD_DEFAULT), 'r' => $role,
                'plan' => self::defaultPlan($pdo, $role), 'l' => $lang, 'm' => $matricule, 'ph' => $phone,
            ]);
            $id = (int)$pdo->lastInsertId();
            $enrolled = 0;
            if ($role === 'student' && !empty($in['courses']) && is_array($in['courses'])) {
                $ins = $pdo->prepare('INSERT IGNORE INTO enrollments (student_id, course_id) SELECT :s, id FROM courses WHERE id = :c');
                foreach (array_unique(array_map('intval', $in['courses'])) as $c) {
                    if ($c > 0) {
                        $ins->execute(['s' => $id, 'c' => $c]);
                        $enrolled += $ins->rowCount();
                    }
                }
            }
            self::audit($pdo, $adminId, 'user_created_by_admin', "User #{$id} ({$role}): {$email}, password mode: {$mode}" . ($enrolled ? ", enrolled in {$enrolled} course(s)" : ''));
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                return $fail('email_taken', 'Cette adresse électronique ou ce matricule est déjà utilisé.');
            }
            throw $e;
        }

        // The message to the person: an invitation with a link to choose the password, or a plain "your account is ready"
        $mailSent = null;
        $send = $mode === 'invite' || !empty($in['welcome']);
        if ($send) {
            require_once __DIR__ . '/../Mailer.php';
            $token = $mode === 'invite' ? AuthTokens::createPasswordReset($pdo, $id, self::INVITE_HOURS) : null;
            $titles = [];
            if ($enrolled) {
                $q = $pdo->prepare('SELECT c.title FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.student_id = :s ORDER BY c.title');
                $q->execute(['s' => $id]);
                $titles = $q->fetchAll(PDO::FETCH_COLUMN);
            }
            $mailSent = Mailer::accountCreatedByAdmin($email, $name, $role, $lang, $token, $titles, $mode === 'invite' ? self::INVITE_HOURS : 0);
        }
        return [
            'ok' => true, 'user' => ['id' => $id, 'name' => $name, 'email' => $email, 'role' => $role, 'matricule' => $matricule],
            'password' => $shown, 'invited' => $mode === 'invite', 'mail_sent' => $mailSent, 'enrolled' => $enrolled,
        ];
    }

    private static function defaultPlan(PDO $pdo, string $role): ?int
    {
        if (!function_exists('defaultPlanId')) {
            return null;
        }
        return defaultPlanId($pdo, $role);
    }

    // ---------------------------------------------------------------------------------------------------------------------------
    // Deleting
    // ---------------------------------------------------------------------------------------------------------------------------

    /**
     * What deleting a user would take with it, for the confirmation window.
     *
     * @return array<string,mixed>|null null when the user does not exist
     */
    public static function impact(PDO $pdo, int $userId): ?array
    {
        $st = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = :id');
        $st->execute(['id' => $userId]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            return null;
        }
        $one = fn(string $sql) => (int)($pdo->query(str_replace(':id', (string)$userId, $sql))->fetchColumn() ?: 0);
        $c = [];
        if ($u['role'] === 'teacher') {
            $c['courses'] = $one("SELECT COUNT(*) FROM courses WHERE teacher_id = :id");
            $c['students_in_courses'] = $one("SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE c.teacher_id = :id");
            $c['certificates'] = $one("SELECT COUNT(*) FROM certificates ce JOIN courses c ON c.id = ce.course_id WHERE c.teacher_id = :id");
            $c['live_sessions'] = $one("SELECT COUNT(*) FROM live_eval_sessions WHERE teacher_id = :id");
            $c['live_results'] = $one("SELECT COUNT(*) FROM live_eval_registrations r JOIN live_eval_sessions s ON s.id = r.session_id WHERE s.teacher_id = :id");
            $c['announcements'] = $one("SELECT COUNT(*) FROM announcements WHERE teacher_id = :id");
        } elseif ($u['role'] === 'student') {
            $c['enrollments'] = $one("SELECT COUNT(*) FROM enrollments WHERE student_id = :id");
            $c['certificates'] = $one("SELECT COUNT(*) FROM certificates WHERE student_id = :id");
            $c['assignments'] = $one("SELECT COUNT(*) FROM lesson_assignment_submissions WHERE student_id = :id");
            $c['comments'] = $one("SELECT COUNT(*) FROM lesson_comments WHERE user_id = :id");
            $c['live_results_kept'] = $one("SELECT COUNT(*) FROM live_eval_registrations WHERE student_id = :id");
        } else {
            $c['api_keys'] = $one("SELECT COUNT(*) FROM api_keys WHERE created_by = :id");
            $c['campaigns'] = $one("SELECT COUNT(*) FROM newsletter_campaigns WHERE sent_by = :id");
            $c['other_promoters'] = $one("SELECT COUNT(*) FROM users WHERE role = 'promoter' AND is_active = 1 AND id <> :id");
        }
        $teachers = [];
        if ($u['role'] === 'teacher') {
            $t = $pdo->prepare("SELECT id, name, email FROM users WHERE role = 'teacher' AND is_active = 1 AND is_approved = 1 AND id <> :id ORDER BY name");
            $t->execute(['id' => $userId]);
            $teachers = $t->fetchAll(PDO::FETCH_ASSOC);
        }
        $needsDecision = $u['role'] === 'teacher' && (($c['courses'] ?? 0) + ($c['live_sessions'] ?? 0) + ($c['announcements'] ?? 0)) > 0;
        return ['user' => $u, 'counts' => $c, 'needs_decision' => $needsDecision, 'teachers' => $teachers];
    }

    /**
     * Deletes an account.
     *
     * @param array $opts reassign_to (teacher id) | cascade (bool)
     * @return array{ok:bool,error?:string,message?:string,impact?:array,reassigned?:array,removed_files?:int}
     *         errors: not_found, self, last_admin, needs_decision, bad_reassign
     */
    public static function delete(PDO $pdo, int $adminId, int $userId, array $opts = []): array
    {
        $fail = static fn(string $e, string $m, array $x = []): array => ['ok' => false, 'error' => $e, 'message' => $m] + $x;
        $imp = self::impact($pdo, $userId);
        if ($imp === null) {
            return $fail('not_found', 'Utilisateur introuvable.');
        }
        if ($userId === $adminId) {
            return $fail('self', 'Vous ne pouvez pas supprimer votre propre compte.');
        }
        $u = $imp['user'];
        if ($u['role'] === 'promoter') {
            $others = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'promoter' AND is_active = 1 AND id <> " . $userId)->fetchColumn();
            if ($others < 1) {
                return $fail('last_admin', 'C’est le dernier administrateur actif : il ne peut pas être supprimé.');
            }
        }
        $reassign = (int)($opts['reassign_to'] ?? 0);
        $cascade = !empty($opts['cascade']);
        if ($imp['needs_decision']) {
            if ($reassign > 0) {
                $ok = array_filter($imp['teachers'], fn($t) => (int)$t['id'] === $reassign);
                if (!$ok) {
                    return $fail('bad_reassign', 'L’enseignant choisi pour reprendre les cours est introuvable, inactif ou non validé.');
                }
            } elseif (!$cascade) {
                return $fail('needs_decision', 'Cet enseignant a des cours ou des évaluations : confiez-les à un autre enseignant ou confirmez leur suppression.', ['impact' => $imp]);
            }
        }

        // Files to remove, listed before the rows disappear
        $files = [];
        $add = static function (string $kind, ?string $f) use (&$files): void { if ($f !== null && $f !== '') { $files[] = [$kind, $f]; } };
        $row = $pdo->query('SELECT avatar_path FROM users WHERE id = ' . $userId)->fetch(PDO::FETCH_ASSOC);
        $add('avatar', $row['avatar_path'] ?? null);
        $q = $pdo->query("SELECT submitted_file_path, feedback_file_path, id FROM lesson_assignment_submissions WHERE student_id = $userId");
        $subIds = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $add('assignment', $s['submitted_file_path']);
            $add('assignment_feedback', $s['feedback_file_path']);
            $subIds[] = (int)$s['id'];
        }
        if ($subIds) {
            foreach ($pdo->query('SELECT file_path, feedback_file_path FROM lesson_assignment_history WHERE submission_id IN (' . implode(',', $subIds) . ')')->fetchAll(PDO::FETCH_ASSOC) as $h) {
                $add('assignment', $h['file_path']);
                $add('assignment_feedback', $h['feedback_file_path']);
            }
        }
        if ($u['role'] === 'teacher' && $imp['needs_decision'] && $reassign === 0) {
            foreach (self::teacherFiles($pdo, $userId) as [$k, $f]) { $add($k, $f); }
        }

        $reassigned = [];
        $pdo->beginTransaction();
        try {
            if ($u['role'] === 'teacher' && $reassign > 0) {
                foreach (['courses' => 'teacher_id', 'live_eval_sessions' => 'teacher_id', 'announcements' => 'teacher_id'] as $table => $col) {
                    $st = $pdo->prepare("UPDATE `$table` SET `$col` = :new WHERE `$col` = :old");
                    $st->execute(['new' => $reassign, 'old' => $userId]);
                    $reassigned[$table] = $st->rowCount();
                }
            }
            // What an administrator created passes to the one who deletes: API keys keep working, campaigns keep their history
            $pdo->prepare('UPDATE api_keys SET created_by = :a WHERE created_by = :u')->execute(['a' => $adminId, 'u' => $userId]);
            $pdo->prepare('UPDATE newsletter_campaigns SET sent_by = :a WHERE sent_by = :u')->execute(['a' => $adminId, 'u' => $userId]);
            // Nothing queued may reach a deleted person
            $pdo->prepare("DELETE FROM email_outbox WHERE user_id = :u AND status IN ('queued','sending')")->execute(['u' => $userId]);
            $pdo->prepare("DELETE FROM sms_outbox WHERE user_id = :u AND status IN ('queued','sending')")->execute(['u' => $userId]);
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
            self::audit($pdo, $adminId, 'user_deleted', "User #{$userId} ({$u['role']}): {$u['email']}"
                . ($reassigned ? ' · courses handed to teacher #' . $reassign : '') . ($cascade && $imp['needs_decision'] && !$reassign ? ' · courses and sessions deleted with it' : ''));
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $removed = 0;
        foreach ($files as [$kind, $f]) {
            try {
                MediaStore::delete($pdo, $kind, $f);
                $removed++;
            } catch (Throwable $e) {
                error_log('AdminUsers::delete file ' . $kind . '/' . $f . ': ' . $e->getMessage());
            }
        }
        return ['ok' => true, 'message' => 'Le compte a été supprimé définitivement.', 'reassigned' => $reassigned, 'removed_files' => $removed];
    }

    /** Files of a teacher's courses and live sessions (only removed when those are deleted with the teacher). @return array<int,array{0:string,1:string}> */
    private static function teacherFiles(PDO $pdo, int $teacherId): array
    {
        $out = [];
        $push = function (string $kind, $f) use (&$out): void { if ($f !== null && $f !== '') { $out[] = [$kind, (string)$f]; } };
        foreach ($pdo->query("SELECT id, cover_image FROM courses WHERE teacher_id = $teacherId")->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $push('cover', $c['cover_image'] ?? null);
            $cid = (int)$c['id'];
            foreach ($pdo->query("SELECT l.pdf_path FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id WHERE ch.course_id = $cid")->fetchAll(PDO::FETCH_COLUMN) as $f) { $push('pdf', $f); }
            foreach ($pdo->query("SELECT file_path FROM course_library_items WHERE course_id = $cid")->fetchAll(PDO::FETCH_COLUMN) as $f) { $push('library', $f); }
            foreach ($pdo->query("SELECT s.submitted_file_path, s.feedback_file_path FROM lesson_assignment_submissions s JOIN lessons l ON l.id = s.lesson_id JOIN chapters ch ON ch.id = l.chapter_id WHERE ch.course_id = $cid")->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $push('assignment', $s['submitted_file_path']);
                $push('assignment_feedback', $s['feedback_file_path']);
            }
        }
        foreach ($pdo->query("SELECT q.image_path FROM live_eval_questions q JOIN live_eval_sessions s ON s.id = q.session_id WHERE s.teacher_id = $teacherId AND q.image_path IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $f) {
            $push('live_question', $f);
        }
        return $out;
    }

    private static function audit(PDO $pdo, int $adminId, string $action, string $details): void
    {
        try {
            $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (:u, :a, :d, :ip)')
                ->execute(['u' => $adminId ?: null, 'a' => $action, 'd' => mb_substr($details, 0, 1000), 'ip' => function_exists('getClientIp') ? getClientIp() : '']);
        } catch (Throwable $e) {
            error_log('AdminUsers::audit ' . $e->getMessage());
        }
    }
}
