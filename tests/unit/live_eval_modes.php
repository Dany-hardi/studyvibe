<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/LiveEvalModes.php';
$pass = 0; $fail = 0;
$check = function (string $n, bool $ok) use (&$pass, &$fail): void { echo ($ok ? 'ok   ' : 'FAIL ') . $n . "\n"; $ok ? $pass++ : $fail++; };
$now = strtotime('2026-10-14 10:00:00');

$m = LiveEvalModes::fromForm(['eval_mode' => 'live', 'live_start_time' => '2026-10-15T09:00', 'live_end_time' => '2026-10-15T11:00'], $now);
$check('live: window kept, not async, no deadline', $m['ok'] && $m['is_async'] === 0 && $m['after'] === 0 && $m['deadline'] === null && $m['start'] === '2026-10-15 09:00:00');
$check('live: an end before the start is refused', !LiveEvalModes::fromForm(['eval_mode' => 'live', 'live_start_time' => '2026-10-15T11:00', 'live_end_time' => '2026-10-15T09:00'], $now)['ok']);
$check('live: dates are required', !LiveEvalModes::fromForm(['eval_mode' => 'live'], $now)['ok']);

$m = LiveEvalModes::fromForm(['eval_mode' => 'async'], $now);
$check('async: no dates at all is fine, opens now, no limit', $m['ok'] && $m['is_async'] === 1 && $m['start'] === '2026-10-14 10:00:00' && $m['deadline'] === null);
$m = LiveEvalModes::fromForm(['eval_mode' => 'async', 'async_deadline' => '2026-10-30T23:00'], $now);
$check('async: a deadline is kept and used as the end', $m['ok'] && $m['deadline'] === '2026-10-30 23:00:00' && $m['end'] === $m['deadline']);
$check('async: a deadline before it opens is refused', !LiveEvalModes::fromForm(['eval_mode' => 'async', 'live_start_time' => '2026-10-20T08:00', 'async_deadline' => '2026-10-19T08:00'], $now)['ok']);

$f = ['eval_mode' => 'live_async', 'live_start_time' => '2026-10-15T09:00', 'live_end_time' => '2026-10-15T11:00', 'async_deadline' => '2026-10-22T23:00'];
$m = LiveEvalModes::fromForm($f, $now);
$check('live then async: starts live and will switch', $m['ok'] && $m['is_async'] === 0 && $m['after'] === 1 && $m['deadline'] === '2026-10-22 23:00:00');
$check('live then async: the deadline must follow the live end', !LiveEvalModes::fromForm(['async_deadline' => '2026-10-15T10:00'] + $f, $now)['ok']);
$check('live then async: a deadline is required', !LiveEvalModes::fromForm(['async_deadline' => ''] + $f, $now)['ok']);
$check('older forms (checkbox only) still mean async', LiveEvalModes::fromForm(['is_async' => '1'], $now)['is_async'] === 1);
$check('form mode of a stored session', LiveEvalModes::formMode(['async_after_live' => 1, 'is_async' => 1]) === 'live_async' && LiveEvalModes::formMode(['is_async' => 1]) === 'async' && LiveEvalModes::formMode([]) === 'live');

echo "$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
