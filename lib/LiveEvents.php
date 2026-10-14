<?php
declare(strict_types=1);

/**
 * Timeline of a live evaluation: who registered, entered the room, handed in (by themselves, at the end of the time, by the system), was marked
 * again, excluded. One row per event, never rewritten: it is what the teacher reads back when a student says "I did hand in".
 */
final class LiveEvents
{
    public static function log(PDO $pdo, int $sessionId, ?int $registrationId, string $event, string $detail = ''): void
    {
        try {
            $pdo->prepare("INSERT INTO live_eval_events (session_id, registration_id, event, detail) VALUES (:s, :r, :e, :d)")
                ->execute(['s' => $sessionId, 'r' => $registrationId, 'e' => mb_substr($event, 0, 24), 'd' => mb_substr($detail, 0, 255)]);
        } catch (Throwable $e) {
            error_log('live events: ' . $e->getMessage());
        }
    }

    /** First sight of a student in the room: recorded once, and the last activity is kept fresh. */
    public static function seen(PDO $pdo, int $registrationId): void
    {
        $pdo->prepare("UPDATE live_eval_registrations SET last_activity = NOW(), first_seen = COALESCE(first_seen, NOW()) WHERE id = :r")->execute(['r' => $registrationId]);
    }
}
