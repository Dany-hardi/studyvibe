<?php
declare(strict_types=1);

require_once __DIR__ . '/LiveScoring.php';
require_once __DIR__ . '/LiveCodeReview.php';

/**
 * Free-pace live evaluations: everybody starts together, then each student moves through the questions in any order, skips what
 * they want, and hands everything in with "Submit all my answers". Nobody has to finish at the same time.
 *
 * - Answers wait in a bucket (live_eval_drafts) and become real answers only at the final submission (or when the session ends).
 * - A timed question (QCM, short written answer, written-answer question) has a time budget that only runs while the student has it open:
 *   the server counts it, the browser only displays it. When it is used up the question locks (the draft stays).
 * - A code question has no time limit at all: it only ends with the session (or the student's submission).
 * - The correct answers, the reference solutions and the marking notes never leave the server.
 */
final class LivePaced
{
    /** A silent gap longer than this is not charged to the open question (closed laptop, network outage). */
    public const GAP_CAP = 90;
    /** Seconds of tolerance after the budget of a question is used up, so that the last keystrokes of the autosave still land. */
    public const GRACE = 4;

    public static function isTimed(array $q): bool
    {
        return ($q['question_type'] ?? 'mcq') !== 'code';
    }

    /** Seconds allowed for a question, or null when it has no limit (code). */
    public static function limitOf(array $q, array $session): ?int
    {
        if (!self::isTimed($q)) {
            return null;
        }
        $own = $q['time_limit'] ?? null;
        $lim = $own !== null && (int)$own > 0 ? (int)$own : (int)($session['default_time_limit'] ?? 30);
        return max(5, $lim);
    }

    /** 'waiting' (not started), 'open', or 'closed' (the session is over). */
    public static function window(PDO $pdo, array $session): string
    {
        $row = $pdo->prepare("SELECT UNIX_TIMESTAMP(NOW()) AS now, UNIX_TIMESTAMP(start_time) AS st, UNIX_TIMESTAMP(end_time) AS en, UNIX_TIMESTAMP(async_deadline) AS dl FROM live_eval_sessions WHERE id = :i");
        $row->execute(['i' => $session['id']]);
        $t = $row->fetch(PDO::FETCH_ASSOC);
        if (!$t || (int)$session['status'] !== 1) {
            return 'closed';
        }
        if (!empty($session['is_async'])) {
            return ($t['dl'] !== null && (int)$t['now'] > (int)$t['dl']) ? 'closed' : 'open';
        }
        if ((int)$t['now'] < (int)$t['st']) {
            return 'waiting';
        }
        return (int)$t['now'] > (int)$t['en'] ? 'closed' : 'open';
    }

    /** @return array{seconds_to_start:int,seconds_to_end:?int,server_time:int} */
    public static function clock(PDO $pdo, array $session): array
    {
        $row = $pdo->prepare("SELECT UNIX_TIMESTAMP(NOW()) AS now, UNIX_TIMESTAMP(start_time) AS st, UNIX_TIMESTAMP(end_time) AS en, UNIX_TIMESTAMP(async_deadline) AS dl FROM live_eval_sessions WHERE id = :i");
        $row->execute(['i' => $session['id']]);
        $t = $row->fetch(PDO::FETCH_ASSOC);
        $now = (int)$t['now'];
        if (!empty($session['is_async'])) {
            return ['seconds_to_start' => 0, 'seconds_to_end' => $t['dl'] !== null ? max(0, (int)$t['dl'] - $now) : null, 'server_time' => $now];
        }
        return ['seconds_to_start' => max(0, (int)$t['st'] - $now), 'seconds_to_end' => max(0, (int)$t['en'] - $now), 'server_time' => $now];
    }

    public static function session(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare("SELECT s.*, c.title AS course_title FROM live_eval_sessions s JOIN courses c ON c.id = s.course_id WHERE s.id = :i");
        $st->execute(['i' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function questions(PDO $pdo, int $sessionId): array
    {
        $st = $pdo->prepare("SELECT * FROM live_eval_questions WHERE session_id = :s ORDER BY sort_order, id");
        $st->execute(['s' => $sessionId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function registration(PDO $pdo, int $regId): ?array
    {
        $st = $pdo->prepare("SELECT * FROM live_eval_registrations WHERE id = :i");
        $st->execute(['i' => $regId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Charges the time spent since the last call to the question that is open (timed questions only), then restarts the clock. */
    public static function settle(PDO $pdo, int $regId): void
    {
        $st = $pdo->prepare("SELECT current_q_id, TIMESTAMPDIFF(SECOND, current_since, NOW()) AS gap FROM live_eval_registrations WHERE id = :i");
        $st->execute(['i' => $regId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r || empty($r['current_q_id'])) {
            return;
        }
        $delta = max(0, min(self::GAP_CAP, (int)$r['gap']));
        if ($delta > 0) {
            $qt = $pdo->prepare("SELECT question_type FROM live_eval_questions WHERE id = :q");
            $qt->execute(['q' => $r['current_q_id']]);
            if (self::isTimed(['question_type' => $qt->fetchColumn()])) {
                $pdo->prepare("INSERT INTO live_eval_drafts (registration_id, question_id, answer, seconds_used) VALUES (:r, :q, '', :d)
                               ON DUPLICATE KEY UPDATE seconds_used = seconds_used + :d2")
                    ->execute(['r' => $regId, 'q' => $r['current_q_id'], 'd' => $delta, 'd2' => $delta]);
            }
        }
        $pdo->prepare("UPDATE live_eval_registrations SET current_since = NOW() WHERE id = :i")->execute(['i' => $regId]);
    }

    /** @return array<int,array{answer:string,seconds_used:int}> by question id */
    private static function drafts(PDO $pdo, int $regId): array
    {
        $st = $pdo->prepare("SELECT question_id, answer, seconds_used FROM live_eval_drafts WHERE registration_id = :r");
        $st->execute(['r' => $regId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $out[(int)$d['question_id']] = ['answer' => (string)$d['answer'], 'seconds_used' => (int)$d['seconds_used']];
        }
        return $out;
    }

    /** What the browser needs to draw the exam. Nothing that gives an answer away. */
    public static function state(PDO $pdo, array $session, int $regId): array
    {
        $reg = self::registration($pdo, $regId);
        $submitted = $reg && $reg['submitted_at'] !== null;
        $window = self::window($pdo, $session);
        if ($window === 'open' && !$submitted) {
            self::settle($pdo, $regId);
        }
        $clock = self::clock($pdo, $session);
        $out = ['window' => $window, 'submitted' => (bool)$submitted, 'clock' => $clock, 'title' => (string)$session['title'], 'course' => (string)($session['course_title'] ?? ''), 'questions' => [], 'current' => $reg['current_q_id'] ?? null];
        if ($window === 'waiting') {
            return $out;
        }
        $drafts = self::drafts($pdo, $regId);
        $n = 0;
        foreach (self::questions($pdo, (int)$session['id']) as $q) {
            $n++;
            $qid = (int)$q['id'];
            $type = (string)$q['question_type'];
            $limit = self::limitOf($q, $session);
            $used = $drafts[$qid]['seconds_used'] ?? 0;
            $answer = $drafts[$qid]['answer'] ?? '';
            $keys = ['A', 'B', 'C', 'D'];
            if ($type === 'mcq' && !empty($session['shuffle_options']) && LiveScoring::canShuffle($q)) {
                $keys = LiveScoring::optionOrder($regId, $qid);
            }
            $item = [
                'id' => $qid, 'n' => $n, 'type' => $type, 'text' => (string)$q['question_text'],
                'image' => !empty($q['image_path']) ? '/download.php?type=live_question&file=' . urlencode(basename((string)$q['image_path'])) : null,
                'limit' => $limit, 'used' => min($used, $limit ?? $used), 'locked' => $limit !== null && $used >= $limit,
                'answer' => $answer, 'answered' => trim($answer) !== '',
            ];
            if ($type === 'mcq') {
                $item['option_keys'] = $keys;
                $item['options'] = array_map(fn(string $k) => (string)$q['option_' . strtolower($k)], $keys);
            }
            if ($type === 'code') {
                $item['code_lang'] = LiveCodeReview::langOf($q['code_lang'] ?? null);
                $item['profile'] = LiveCodeReview::toolProfile($item['code_lang']);
            }
            $out['questions'][] = $item;
        }
        return $out;
    }

    /** The student looks at a question: from now on its clock (if it has one) runs. */
    public static function open(PDO $pdo, array $session, int $regId, int $qid): array
    {
        $guard = self::guard($pdo, $session, $regId);
        if ($guard !== null) {
            return $guard;
        }
        $q = self::questionOf($pdo, (int)$session['id'], $qid);
        if (!$q) {
            return ['ok' => false, 'error' => 'question'];
        }
        self::settle($pdo, $regId);
        $pdo->prepare("UPDATE live_eval_registrations SET current_q_id = :q, current_since = NOW() WHERE id = :i")->execute(['q' => $qid, 'i' => $regId]);
        return ['ok' => true] + self::timing($pdo, $session, $regId, $q);
    }

    /** Stores the draft of a question (autosave). */
    public static function save(PDO $pdo, array $session, int $regId, int $qid, string $answer): array
    {
        $guard = self::guard($pdo, $session, $regId);
        if ($guard !== null) {
            return $guard;
        }
        $q = self::questionOf($pdo, (int)$session['id'], $qid);
        if (!$q) {
            return ['ok' => false, 'error' => 'question'];
        }
        self::settle($pdo, $regId);
        $t = self::timing($pdo, $session, $regId, $q);
        if ($t['locked'] && $t['used'] > (int)$t['limit'] + self::GRACE) {
            return ['ok' => false, 'error' => 'locked'] + $t;
        }
        $clean = self::cleanAnswer($q, $answer);
        if ($clean === null) {
            return ['ok' => false, 'error' => 'invalid'];
        }
        $pdo->prepare("INSERT INTO live_eval_drafts (registration_id, question_id, answer) VALUES (:r, :q, :a)
                       ON DUPLICATE KEY UPDATE answer = :a2")->execute(['r' => $regId, 'q' => $qid, 'a' => $clean, 'a2' => $clean]);
        return ['ok' => true, 'answered' => trim($clean) !== ''] + $t;
    }

    /** Heartbeat: charges the open question and tells the browser the truth about the clocks. */
    public static function ping(PDO $pdo, array $session, int $regId): array
    {
        $reg = self::registration($pdo, $regId);
        if (!$reg || $reg['submitted_at'] !== null) {
            return ['ok' => true, 'submitted' => (bool)$reg, 'window' => self::window($pdo, $session)];
        }
        $window = self::window($pdo, $session);
        if ($window === 'open') {
            self::settle($pdo, $regId);
        } elseif ($window === 'closed') {
            self::submit($pdo, $session, $regId, true);   // the session is over: what is in the bucket is handed in
            return ['ok' => true, 'submitted' => true, 'window' => 'closed'];
        }
        $cur = $reg['current_q_id'] ? self::questionOf($pdo, (int)$session['id'], (int)$reg['current_q_id']) : null;
        return ['ok' => true, 'submitted' => false, 'window' => $window, 'clock' => self::clock($pdo, $session), 'timing' => $cur ? self::timing($pdo, $session, $regId, $cur) : null];
    }

    /**
     * Hands everything in. Without $confirm, a bucket that holds unanswered questions is not submitted: the list comes back instead.
     * @return array{ok:bool,error?:string,unanswered?:int[],answered?:int,total?:int,pending_ai?:int,score?:?float}
     */
    public static function submit(PDO $pdo, array $session, int $regId, bool $confirm): array
    {
        $reg = self::registration($pdo, $regId);
        if (!$reg) {
            return ['ok' => false, 'error' => 'registration'];
        }
        if ($reg['submitted_at'] !== null) {
            return ['ok' => true, 'already' => true, 'pending_ai' => LiveCodeReview::pendingCount($pdo, $regId)];
        }
        self::settle($pdo, $regId);
        $questions = self::questions($pdo, (int)$session['id']);
        $drafts = self::drafts($pdo, $regId);
        $unanswered = [];
        foreach ($questions as $q) {
            if (trim($drafts[(int)$q['id']]['answer'] ?? '') === '') {
                $unanswered[] = (int)$q['id'];
            }
        }
        if ($unanswered && !$confirm) {
            return ['ok' => false, 'error' => 'unanswered', 'unanswered' => $unanswered, 'answered' => count($questions) - count($unanswered), 'total' => count($questions)];
        }
        // claim the submission: two clicks or a click and the end of the session hand in once
        $claim = $pdo->prepare("UPDATE live_eval_registrations SET submitted_at = NOW(), current_q_id = NULL, current_since = NULL WHERE id = :i AND submitted_at IS NULL");
        $claim->execute(['i' => $regId]);
        if ($claim->rowCount() !== 1) {
            return ['ok' => true, 'already' => true];
        }
        foreach ($questions as $q) {
            $qid = (int)$q['id'];
            $a = $drafts[$qid]['answer'] ?? '';
            if (trim($a) === '') {
                continue;
            }
            if (LiveCodeReview::handles($q['question_type'])) {
                LiveCodeReview::submit($pdo, $regId, $qid, $a);
            } else {
                $pdo->prepare("INSERT INTO live_eval_answers (registration_id, question_id, selected_option) VALUES (:r, :q, :s)
                               ON DUPLICATE KEY UPDATE selected_option = :s2, answered_at = CURRENT_TIMESTAMP")
                    ->execute(['r' => $regId, 'q' => $qid, 's' => mb_substr($a, 0, 255), 's2' => mb_substr($a, 0, 255)]);
            }
        }
        $fresh = self::registration($pdo, $regId);
        $score = LiveScoring::finalize($pdo, $session, (array)$fresh, $questions);
        return ['ok' => true, 'answered' => count($questions) - count($unanswered), 'total' => count($questions), 'pending_ai' => LiveCodeReview::pendingCount($pdo, $regId), 'score' => $score];
    }

    /** Throttled sweep for the places that run all the time (rooms polling, workers). */
    public static function tick(PDO $pdo): void
    {
        $flag = dirname(__DIR__) . '/uploads/live_cache/paced_sweep.flag';
        if (!is_dir(dirname($flag))) {
            @mkdir(dirname($flag), 0755, true);
        }
        if ((time() - (int)@filemtime($flag)) < 15) {
            return;
        }
        @touch($flag);
        self::sweep($pdo);
    }

    /** Hands in the work of students whose session is over (they closed the tab, or time ran out). @return int registrations handled */
    public static function sweep(PDO $pdo, int $limit = 20): int
    {
        try {
            $st = $pdo->prepare("
                SELECT r.id, r.session_id FROM live_eval_registrations r JOIN live_eval_sessions s ON s.id = r.session_id
                WHERE s.free_pace = 1 AND r.submitted_at IS NULL AND r.cancelled_at IS NULL
                  AND ((s.is_async = 0 AND s.end_time < NOW()) OR (s.is_async = 1 AND s.async_deadline IS NOT NULL AND s.async_deadline < NOW()))
                  AND EXISTS (SELECT 1 FROM live_eval_drafts d WHERE d.registration_id = r.id AND d.answer <> '')
                ORDER BY r.id LIMIT :n");
            $st->bindValue('n', $limit, PDO::PARAM_INT);
            $st->execute();
            $n = 0;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $session = self::session($pdo, (int)$r['session_id']);
                if ($session) {
                    self::submit($pdo, $session, (int)$r['id'], true);
                    $n++;
                }
            }
            return $n;
        } catch (Throwable $e) {
            error_log('LivePaced::sweep ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Real syntax check of PHP code with the interpreter's own linter (php -l: it compiles, it never runs the code). Used only when the
     * language of a code question is PHP; the other languages are checked in the browser. Silent when the server cannot do it.
     * @return array{checked:bool,errors:array<int,array{line:int,message:string}>}
     */
    public static function lintPhp(string $code): array
    {
        $code = LiveCodeReview::cleanCode($code);
        if (trim($code) === '' || !function_exists('proc_open')) {
            return ['checked' => false, 'errors' => []];
        }
        $bin = null;
        foreach ([PHP_BINDIR . '/php', '/usr/local/bin/php', '/usr/bin/php'] as $c) {
            if (@is_executable($c)) { $bin = $c; break; }
        }
        if ($bin === null) {
            return ['checked' => false, 'errors' => []];
        }
        $offset = 0;
        if (!preg_match('/^\s*<\?(php|=)/', $code)) {
            $code = "<?php\n" . $code;      // a snippet without the opening tag
            $offset = 1;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'svlint');
        file_put_contents($tmp, $code);
        $proc = @proc_open([$bin, '-n', '-l', '-d', 'display_errors=1', '-d', 'error_reporting=-1', $tmp], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            @unlink($tmp);
            return ['checked' => false, 'errors' => []];
        }
        stream_set_blocking($pipes[1], false);
        $text = '';
        $deadline = microtime(true) + 3.0;
        while (microtime(true) < $deadline) {
            $text .= (string)stream_get_contents($pipes[1]);
            $status = proc_get_status($proc);
            if (!$status['running']) {
                $text .= (string)stream_get_contents($pipes[1]);
                break;
            }
            usleep(20000);
        }
        $status = proc_get_status($proc);
        if ($status['running']) { proc_terminate($proc, 9); }
        foreach ($pipes as $p) { @fclose($p); }
        proc_close($proc);
        @unlink($tmp);
        $errors = [];
        if (preg_match_all('/(?:Parse|Fatal) error:\s*(.+?) in .+? on line (\d+)/', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $e) {
                $errors[] = ['line' => max(1, (int)$e[2] - $offset), 'message' => trim(preg_replace('/^syntax error, /', '', $e[1]) ?? $e[1])];
            }
        }
        return ['checked' => true, 'errors' => array_slice($errors, 0, 5)];
    }

    // ---------------------------------------------------------------------------------------------------------------

    private static function guard(PDO $pdo, array $session, int $regId): ?array
    {
        $reg = self::registration($pdo, $regId);
        if (!$reg) {
            return ['ok' => false, 'error' => 'registration'];
        }
        if ($reg['submitted_at'] !== null) {
            return ['ok' => false, 'error' => 'submitted'];
        }
        $w = self::window($pdo, $session);
        if ($w !== 'open') {
            return ['ok' => false, 'error' => $w === 'waiting' ? 'not_started' : 'closed'];
        }
        return null;
    }

    private static function questionOf(PDO $pdo, int $sessionId, int $qid): ?array
    {
        $st = $pdo->prepare("SELECT * FROM live_eval_questions WHERE id = :q AND session_id = :s");
        $st->execute(['q' => $qid, 's' => $sessionId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array{limit:?int,used:int,left:?int,locked:bool} */
    private static function timing(PDO $pdo, array $session, int $regId, array $q): array
    {
        $limit = self::limitOf($q, $session);
        $st = $pdo->prepare("SELECT seconds_used FROM live_eval_drafts WHERE registration_id = :r AND question_id = :q");
        $st->execute(['r' => $regId, 'q' => $q['id']]);
        $used = (int)($st->fetchColumn() ?: 0);
        return ['limit' => $limit, 'used' => $used, 'left' => $limit === null ? null : max(0, $limit - $used), 'locked' => $limit !== null && $used >= $limit];
    }

    /** The answer as it is stored for the type of question; null when it is not acceptable. */
    private static function cleanAnswer(array $q, string $answer): ?string
    {
        switch ((string)$q['question_type']) {
            case 'mcq':
                $a = strtoupper(trim($answer));
                return in_array($a, ['', 'A', 'B', 'C', 'D'], true) ? $a : null;
            case 'written':
                return mb_substr(trim((string)preg_replace('/[\x00-\x1F\x7F]/u', ' ', $answer)), 0, 255);
            default:   // code and open
                return LiveCodeReview::cleanCode($answer);
        }
    }
}
