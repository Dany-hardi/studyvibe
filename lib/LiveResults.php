<?php
declare(strict_types=1);

require_once __DIR__ . '/LiveScoring.php';
require_once __DIR__ . '/EmailQueue.php';

/**
 * What a teacher sees when reviewing a finished live evaluation: one row per student with matricule, name, email, mark and an
 * integrity assessment. The assessment is a set of indications, never a verdict: the teacher decides.
 *
 * Signals used:
 *   - tab exits counted during the exam (only when the session had "count tab exits" turned on)
 *   - the same wrong answers as another student, on several questions: wrong answers are the telling ones, because everybody can
 *     agree on the right answer by knowing it, but two students picking the same wrong option again and again is unusual
 * Levels: 0 nothing to report, 1 worth a look, 2 suspicious.
 */
final class LiveResults
{
    private const MAX_STUDENTS_COMPARED = 400;

    /**
     * @param array $session row of live_eval_sessions (needs id, integrity_watch)
     * @return array{rows:array<int,array<string,mixed>>,total_questions:int,summary:array<string,int>}
     */
    public static function review(PDO $pdo, array $session): array
    {
        $sid = (int)$session['id'];
        $watch = !empty($session['integrity_watch']);

        $qs = $pdo->prepare("SELECT id, question_type, correct_option FROM live_eval_questions WHERE session_id = :s ORDER BY sort_order, id");
        $qs->execute(['s' => $sid]);
        $questions = $qs->fetchAll(PDO::FETCH_ASSOC);
        $total = count($questions);

        $rs = $pdo->prepare(
            "SELECT r.id, r.name, r.email, r.score, r.focus_losses, r.cancelled_at, r.cancelled_reason, r.cancelled_score, u.matricule
             FROM live_eval_registrations r
             LEFT JOIN users u ON u.id = r.student_id OR (r.student_id IS NULL AND u.email = r.email)
             WHERE r.session_id = :s
             GROUP BY r.id
             ORDER BY r.name ASC"
        );
        $rs->execute(['s' => $sid]);
        $regs = $rs->fetchAll(PDO::FETCH_ASSOC);

        $as = $pdo->prepare(
            "SELECT a.registration_id, a.question_id, a.selected_option FROM live_eval_answers a
             JOIN live_eval_registrations r ON r.id = a.registration_id WHERE r.session_id = :s"
        );
        $as->execute(['s' => $sid]);
        $answers = [];
        foreach ($as->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $answers[(int)$a['registration_id']][(int)$a['question_id']] = (string)$a['selected_option'];
        }

        // Mark and wrong answers per student
        $info = [];
        foreach ($regs as $r) {
            $rid = (int)$r['id'];
            $mine = $answers[$rid] ?? [];
            $right = 0;
            $wrong = [];   // question id => wrong option chosen (multiple choice only)
            foreach ($questions as $q) {
                $sel = $mine[(int)$q['id']] ?? '';
                if (LiveScoring::isCorrect((string)$q['question_type'], $sel, (string)$q['correct_option'])) {
                    $right++;
                } elseif ($q['question_type'] === 'mcq' && $sel !== '') {
                    $wrong[(int)$q['id']] = $sel;
                }
            }
            $info[$rid] = ['right' => $right, 'wrong' => $wrong, 'answered' => count(array_filter($mine, fn($v) => $v !== ''))];
        }

        // Same wrong answers as somebody else
        $similar = [];   // rid => list of [other name, shared, of]
        if ($total >= 6 && count($regs) <= self::MAX_STUDENTS_COMPARED) {
            $ids = array_keys($info);
            foreach ($ids as $i => $a) {
                if (count($info[$a]['wrong']) < 3) {
                    continue;
                }
                foreach (array_slice($ids, $i + 1) as $b) {
                    if (count($info[$b]['wrong']) < 3) {
                        continue;
                    }
                    $shared = count(array_filter($info[$a]['wrong'], fn($opt, $qid) => ($info[$b]['wrong'][$qid] ?? null) === $opt, ARRAY_FILTER_USE_BOTH));
                    $minWrong = min(count($info[$a]['wrong']), count($info[$b]['wrong']));
                    if ($shared >= 3 && $shared >= (int)ceil(0.6 * $minWrong)) {
                        $similar[$a][] = ['rid' => $b, 'shared' => $shared, 'of' => $minWrong];
                        $similar[$b][] = ['rid' => $a, 'shared' => $shared, 'of' => $minWrong];
                    }
                }
            }
        }
        $names = array_column($regs, 'name', 'id');

        $rows = [];
        $summary = ['total' => count($regs), 'cancelled' => 0, 'attention' => 0, 'suspect' => 0, 'not_submitted' => 0];
        foreach ($regs as $r) {
            $rid = (int)$r['id'];
            $cancelled = $r['cancelled_at'] !== null;
            $submitted = $r['score'] !== null || $cancelled;
            $level = 0;
            $notes = [];
            if ($watch) {
                $exits = (int)$r['focus_losses'];
                if ($exits >= 3) {
                    $level = 2;
                } elseif ($exits >= 1) {
                    $level = max($level, 1);
                }
                if ($exits > 0) {
                    $notes[] = ['code' => 'exits', 'n' => $exits];
                }
            }
            foreach (($similar[$rid] ?? []) as $m) {
                $level = max($level, ($m['shared'] >= 5 && $m['shared'] >= (int)ceil(0.8 * $m['of'])) ? 2 : 1);
                $notes[] = ['code' => 'similar', 'with' => (string)($names[$m['rid']] ?? ''), 'shared' => $m['shared'], 'of' => $m['of']];
            }
            if (!$submitted) {
                $summary['not_submitted']++;
            }
            if ($cancelled) {
                $summary['cancelled']++;
            } elseif ($level === 2) {
                $summary['suspect']++;
            } elseif ($level === 1) {
                $summary['attention']++;
            }
            $rows[] = [
                'id'         => $rid,
                'matricule'  => (string)($r['matricule'] ?? ''),
                'name'       => (string)$r['name'],
                'email'      => (string)$r['email'],
                'mark'       => $submitted ? $info[$rid]['right'] : null,
                'total'      => $total,
                'answered'   => $info[$rid]['answered'],
                'submitted'  => $submitted,
                'integrity'  => ['watched' => $watch, 'level' => $level, 'notes' => $notes],
                'cancelled'  => $cancelled,
                'cancel_reason' => (string)($r['cancelled_reason'] ?? ''),
            ];
        }
        return ['rows' => $rows, 'total_questions' => $total, 'summary' => $summary];
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Cancelling and restoring a result
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * Cancels one student's result. The mark is kept aside (cancelled_score) so it can be restored; while cancelled the student
     * has no score (leaderboard, exports and certificates ignore the result) and is told by email.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function cancel(PDO $pdo, int $registrationId, int $teacherId, string $reason): array
    {
        $reason = trim(mb_substr($reason, 0, 255));
        $row = self::ownedRegistration($pdo, $registrationId, $teacherId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if ($row['cancelled_at'] !== null) {
            return ['ok' => false, 'error' => 'already'];
        }
        $pdo->prepare(
            "UPDATE live_eval_registrations SET cancelled_score = score, score = NULL, cancelled_at = NOW(), cancelled_reason = :r
             WHERE id = :id AND cancelled_at IS NULL"
        )->execute(['r' => $reason !== '' ? $reason : null, 'id' => $registrationId]);
        self::forgetCaches((int)$row['session_id']);
        self::tell($pdo, $row, $reason, false);
        return ['ok' => true];
    }

    /** Puts a cancelled result back. A student who never finished gets their score computed again when the exam is viewed. */
    public static function restore(PDO $pdo, int $registrationId, int $teacherId): array
    {
        $row = self::ownedRegistration($pdo, $registrationId, $teacherId);
        if ($row === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        if ($row['cancelled_at'] === null) {
            return ['ok' => false, 'error' => 'not_cancelled'];
        }
        $pdo->prepare(
            "UPDATE live_eval_registrations SET score = cancelled_score, cancelled_score = NULL, cancelled_at = NULL, cancelled_reason = NULL
             WHERE id = :id AND cancelled_at IS NOT NULL"
        )->execute(['id' => $registrationId]);
        self::forgetCaches((int)$row['session_id']);
        self::tell($pdo, $row, '', true);
        return ['ok' => true];
    }

    private static function ownedRegistration(PDO $pdo, int $registrationId, int $teacherId): ?array
    {
        $st = $pdo->prepare(
            "SELECT r.id, r.session_id, r.name, r.email, r.student_id, r.cancelled_at, s.title AS session_title, c.title AS course_title,
                    t.name AS teacher_name, u.lang
             FROM live_eval_registrations r
             JOIN live_eval_sessions s ON s.id = r.session_id
             JOIN courses c ON c.id = s.course_id
             LEFT JOIN users t ON t.id = s.teacher_id
             LEFT JOIN users u ON u.id = r.student_id OR (r.student_id IS NULL AND u.email = r.email)
             WHERE r.id = :id AND s.teacher_id = :t
             LIMIT 1"
        );
        $st->execute(['id' => $registrationId, 't' => $teacherId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function tell(PDO $pdo, array $row, string $reason, bool $restored): void
    {
        EmailQueue::enqueue($pdo, $row['student_id'] !== null ? (int)$row['student_id'] : null, (string)$row['email'], 'result_cancelled', [
            'name' => (string)$row['name'], 'session_title' => (string)$row['session_title'], 'course_title' => (string)$row['course_title'],
            'reason' => $reason, 'teacher' => (string)($row['teacher_name'] ?? ''), 'lang' => ($row['lang'] ?? 'fr') === 'en' ? 'en' : 'fr', 'restored' => $restored,
        ], 'result:' . $row['id'] . ':' . ($restored ? 'restored' : 'cancelled') . ':' . bin2hex(random_bytes(3)));
        EmailQueue::kick();
    }

    /** The exam room caches the leaderboard for a few seconds; a cancelled result must leave it at once. */
    private static function forgetCaches(int $sessionId): void
    {
        foreach (glob(dirname(__DIR__) . '/uploads/live_cache/leaderboard_' . $sessionId . '.json*') ?: [] as $f) {
            @unlink($f);
        }
    }
}
