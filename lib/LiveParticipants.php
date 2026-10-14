<?php
declare(strict_types=1);

/**
 * What the teacher sees for every participant of a live evaluation: where the student is (registered, online, offline without handing in),
 * whether the work is handed in for good, and whether the marking of the code is still running. One definition for the page and for its live refresh.
 */
final class LiveParticipants
{
    /** A student whose browser pinged within this many seconds counts as online (the exam room pings every 10 s). */
    public const ONLINE_SECONDS = 25;

    /**
     * @return array<int,array<string,mixed>> the participants of a session, newest first, each with its status, its progress and its mark.
     * The age of the last sign of life is computed by the database clock (the same one that wrote it), never by comparing it with the web server's clock.
     */
    public static function rows(PDO $pdo, int $sessionId): array
    {
        $st = $pdo->prepare("
            SELECT r.id, r.name, r.email, r.score, r.correct_count, r.question_count, r.last_activity, r.registered_at, r.first_seen, r.submitted_at, r.submit_mode, r.cancelled_at,
                   TIMESTAMPDIFF(SECOND, r.last_activity, NOW()) AS idle_seconds,
                   (SELECT COUNT(*) FROM live_eval_questions q WHERE q.session_id = r.session_id) AS total_questions,
                   (SELECT COUNT(*) FROM live_eval_code_reviews c WHERE c.registration_id = r.id AND c.status IN ('pending', 'working')) AS ai_pending,
                   (SELECT COUNT(*) FROM live_eval_code_reviews c WHERE c.registration_id = r.id AND c.status = 'review') AS ai_review,
                   (SELECT COUNT(*) FROM (SELECT a.question_id FROM live_eval_answers a WHERE a.registration_id = r.id
                                          UNION SELECT d.question_id FROM live_eval_drafts d WHERE d.registration_id = r.id AND d.answer <> '') u) AS n_answered
            FROM live_eval_registrations r WHERE r.session_id = :s ORDER BY r.registered_at DESC, r.id DESC
        ");
        $st->execute(['s' => $sessionId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['n_started'] = (int)$r['n_answered'];
            $r['submitted'] = self::isSubmitted($r);
            $r['status'] = self::statusOf($r);
            $total = (int)($r['question_count'] ?? 0) ?: (int)$r['total_questions'];
            $r['total'] = $total;
            $r['mark'] = $r['score'] === null ? null : ($r['correct_count'] !== null ? (int)$r['correct_count'] : (int)round((float)$r['score'] / 100 * $total));
            $r['percent'] = $r['score'] === null ? null : round((float)$r['score'], 1);
        }
        return $rows;
    }

    /** Handed in for good: the "submit all" of a free-pace exam, or the final score of any other mode. A cancelled result is not one. */
    public static function isSubmitted(array $r): bool
    {
        return empty($r['cancelled_at']) && ($r['submitted_at'] !== null || $r['score'] !== null);
    }

    /** cancelled | marking | needs_review | submitted | online | offline | registered */
    public static function statusOf(array $r): string
    {
        if (!empty($r['cancelled_at'])) {
            return 'cancelled';
        }
        if (self::isSubmitted($r)) {
            if ((int)($r['ai_pending'] ?? 0) > 0) {
                return 'marking';
            }
            return (int)($r['ai_review'] ?? 0) > 0 ? 'needs_review' : 'submitted';
        }
        $online = isset($r['idle_seconds']) && $r['idle_seconds'] !== null && (int)$r['idle_seconds'] >= -2 && (int)$r['idle_seconds'] <= self::ONLINE_SECONDS;
        if ($online) {
            return 'online';
        }
        return (int)($r['n_started'] ?? 0) > 0 ? 'offline' : 'registered';
    }
}
