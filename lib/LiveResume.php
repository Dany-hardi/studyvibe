<?php
declare(strict_types=1);

/**
 * Coming back to an evaluation after leaving or closing the tab.
 *
 *  - Live (synchronous): nothing to rebuild. The registration, the answers already given and the timeline are on the server, so a student
 *    who comes back lands on the question running now, with the questions already answered kept. (The Quit button only forgets the
 *    registration when no answer was given.)
 *  - Asynchronous: the student comes back to the very question they left, within 24 hours of their last activity. The timer of that question
 *    does not run while they are away (CHARGE_AWAY_TIME = false). After 24 hours of silence the unfinished attempt is dropped and the next
 *    entry starts again from question 1. A finished attempt is never wiped by merely opening the link: only "Retake" (restart=1) or entering
 *    again through the form starts a new one.
 */
final class LiveResume
{
    /** An unfinished asynchronous attempt can be resumed for this long after the last sign of life. */
    public const WINDOW = 86400;
    /** Polls arrive every 2 s; a gap longer than this means the student was away. */
    public const AWAY_GRACE = 20;
    /** false: time spent away is not taken off the running question. true: it is (the question may then close by itself). */
    public const CHARGE_AWAY_TIME = false;

    /** Select this next to the registration's columns: seconds since its last sign of life, computed by the database (its clock wrote last_activity). */
    public const IDLE_SQL = 'TIMESTAMPDIFF(SECOND, COALESCE(last_activity, registered_at), NOW()) AS idle_seconds';

    /** Seconds since the last sign of life (last poll, else the moment the registration was created). */
    public static function idle(array $reg, int $now): int
    {
        if (isset($reg['idle_seconds']) && $reg['idle_seconds'] !== null) {
            return max(0, (int)$reg['idle_seconds']);
        }
        $t = !empty($reg['last_activity']) ? strtotime((string)$reg['last_activity']) : (!empty($reg['registered_at']) ? strtotime((string)$reg['registered_at']) : 0);
        return $t ? max(0, $now - (int)$t) : 0;
    }

    public static function isStale(array $reg, int $now): bool
    {
        return self::idle($reg, $now) > self::WINDOW;
    }

    /** Started but not finished: some answer given, or a question already running, and no score yet. */
    public static function inProgress(array $reg, int $answers): bool
    {
        return $reg['score'] === null && empty($reg['cancelled_at']) && ($answers > 0 || !empty($reg['async_q_id']));
    }

    /**
     * Should an asynchronous registration be wiped and started over?
     * @param bool $explicit  the student asked to retake (restart=1)
     * @param bool $viaForm   the student entered through the entry form again (as opposed to opening the link or reloading)
     */
    public static function shouldRestart(array $reg, int $answers, bool $explicit, bool $viaForm, int $now): bool
    {
        if (!empty($reg['cancelled_at'])) {
            return false;                                      // a result the teacher cancelled is not wiped by a new attempt
        }
        $completed = $reg['score'] !== null || !empty($reg['submitted_at']);
        if ($explicit) {
            return true;
        }
        if ($completed) {
            return $viaForm;                                   // entering again after finishing means "take it again"
        }
        return self::inProgress($reg, $answers) && self::isStale($reg, $now);
    }

    /** Seconds to push the question's start forward so that the time away is not charged (0 when the student was not away). */
    public static function awayShift(array $reg, int $now): int
    {
        if (self::CHARGE_AWAY_TIME) {
            return 0;
        }
        $gap = self::idle($reg, $now);
        return $gap > self::AWAY_GRACE ? $gap : 0;
    }

    public static function answerCount(PDO $pdo, int $regId): int
    {
        $st = $pdo->prepare('SELECT COUNT(*) FROM live_eval_answers WHERE registration_id = :r');
        $st->execute(['r' => $regId]);
        return (int)$st->fetchColumn();
    }

    /** Wipes an asynchronous attempt so the next entry starts at question 1 (the previous attempt is archived first). */
    public static function reset(PDO $pdo, int $regId): void
    {
        $sid = 0;
        try {
            $q = $pdo->prepare('SELECT session_id, score FROM live_eval_registrations WHERE id = :id');
            $q->execute(['id' => $regId]);
            $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];
            $sid = (int)($row['session_id'] ?? 0);
            if (($row['score'] ?? null) !== null) {
                require_once __DIR__ . '/Archive.php';
                Archive::snapshotRegistration($pdo, $regId, 'before_retake');
            }
        } catch (Throwable $e) { error_log('retake archive: ' . $e->getMessage()); }
        // everything the previous attempt left, including what the free-pace room keeps (hand-in date, drafts, timers, AI markings)
        $pdo->prepare('UPDATE live_eval_registrations SET score = NULL, async_q_id = NULL, async_q_started_at = NULL WHERE id = :id')->execute(['id' => $regId]);
        $pdo->prepare('DELETE FROM live_eval_answers WHERE registration_id = :id')->execute(['id' => $regId]);
        foreach ([
            'UPDATE live_eval_registrations SET submitted_at = NULL, submit_mode = NULL, current_q_id = NULL, current_since = NULL, correct_count = NULL, resend_results = 0 WHERE id = :id',
            'DELETE FROM live_eval_drafts WHERE registration_id = :id',
            'DELETE FROM live_eval_code_reviews WHERE registration_id = :id',
        ] as $sql) {
            try { $pdo->prepare($sql)->execute(['id' => $regId]); } catch (PDOException $e) { /* a schema that has not migrated that part yet */ }
        }
        try {
            require_once __DIR__ . '/LiveEvents.php';
            LiveEvents::log($pdo, $sid, $regId, 'retake', 'new attempt');
        } catch (Throwable $e) { /* the timeline is a bonus */ }
    }
}
