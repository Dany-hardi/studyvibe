<?php
declare(strict_types=1);

/**
 * The three ways an evaluation can run, chosen when it is created (and changeable later):
 *   live        a supervised session between a start and an end; closes at the end.
 *   async       a free assignment ("devoir libre"): students take it when they want, until an optional deadline.
 *   live_async  starts as a live session and, once the live window is over, goes on as a free assignment until a deadline.
 *
 * Stored in live_eval_sessions as is_async (what the session is right now) and async_after_live (switch to async when the live ends).
 */
final class LiveEvalModes
{
    public const MODES = ['live', 'async', 'live_async'];

    /**
     * Reads the mode fields of the create / edit form.
     * @return array{ok:bool,error?:string,start?:string,end?:string,is_async?:int,after?:int,deadline?:?string,mode?:string}
     */
    public static function fromForm(array $in, ?int $now = null): array
    {
        $now ??= time();
        $mode = (string)($in['eval_mode'] ?? '');
        if (!in_array($mode, self::MODES, true)) {
            $mode = isset($in['is_async']) ? 'async' : 'live';      // older forms
        }
        $start = self::stamp((string)($in['live_start_time'] ?? ''));
        $end = self::stamp((string)($in['live_end_time'] ?? ''));
        $deadline = self::stamp((string)($in['async_deadline'] ?? ''));

        if ($mode === 'async') {
            $start ??= date('Y-m-d H:i:s', $now);               // open as soon as it is launched
            if ($deadline !== null && strtotime($deadline) <= strtotime($start)) {
                return ['ok' => false, 'error' => 'deadline'];
            }
            return ['ok' => true, 'mode' => $mode, 'start' => $start, 'end' => $deadline ?? $start, 'is_async' => 1, 'after' => 0, 'deadline' => $deadline];
        }
        if ($start === null || $end === null || strtotime($end) <= strtotime($start)) {
            return ['ok' => false, 'error' => 'window'];
        }
        if ($mode === 'live') {
            return ['ok' => true, 'mode' => $mode, 'start' => $start, 'end' => $end, 'is_async' => 0, 'after' => 0, 'deadline' => null];
        }
        // live then asynchronous: the free period needs a deadline after the live window
        if ($deadline === null || strtotime($deadline) <= strtotime($end)) {
            return ['ok' => false, 'error' => 'deadline'];
        }
        return ['ok' => true, 'mode' => $mode, 'start' => $start, 'end' => $end, 'is_async' => 0, 'after' => 1, 'deadline' => $deadline];
    }

    /** The mode of a stored session. */
    public static function of(array $session): string
    {
        if (!empty($session['async_after_live'])) {
            return !empty($session['is_async']) ? 'live_async_free' : 'live_async';
        }
        return !empty($session['is_async']) ? 'async' : 'live';
    }

    /** The form value to show for a stored session (a session that already moved on to its free period keeps showing live_async). */
    public static function formMode(array $session): string
    {
        return !empty($session['async_after_live']) ? 'live_async' : (!empty($session['is_async']) ? 'async' : 'live');
    }

    /** Sessions whose live window is over move on to their free period. Cheap: one indexed-free UPDATE, safe to call on any page. @return int sessions switched */
    public static function advance(PDO $pdo): int
    {
        try {
            require_once __DIR__ . '/LivePaced.php';
            LivePaced::tick($pdo);      // students of a free-pace live window that just ended are handed in before the session moves on
            return (int)$pdo->exec("UPDATE live_eval_sessions SET is_async = 1, is_paused = 0 WHERE async_after_live = 1 AND is_async = 0 AND end_time < NOW()");
        } catch (Throwable $e) {
            error_log('LiveEvalModes::advance ' . $e->getMessage());
            return 0;
        }
    }

    /** '2026-10-14T09:30' (datetime-local) or '2026-10-14 09:30[:00]' to 'Y-m-d H:i:s'; null when empty or not a date. */
    private static function stamp(string $v): ?string
    {
        $v = trim(str_replace('T', ' ', $v));
        if ($v === '') {
            return null;
        }
        $t = strtotime($v);
        return $t === false ? null : date('Y-m-d H:i:s', $t);
    }
}
