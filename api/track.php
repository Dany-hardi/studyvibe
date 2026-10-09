<?php
declare(strict_types=1);

/** Front-of-funnel beacon: counts a handful of anonymous events (sign-up dialog opened, second step reached, ...). */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Analytics.php';

header('Content-Type: application/json');
$event = (string)($_POST['event'] ?? '');
$allowed = ['signup_open', 'signup_step2', 'signup_submit', 'login_open', 'login_submit'];

// A browser can report at most 40 events a minute: enough for real use, useless for inflating numbers
$bucket = (int)(time() / 60);
if (($_SESSION['sv_track_min'] ?? 0) !== $bucket) {
    $_SESSION['sv_track_min'] = $bucket;
    $_SESSION['sv_track_n'] = 0;
}
if (in_array($event, $allowed, true) && ++$_SESSION['sv_track_n'] <= 40) {
    Analytics::hit($event);
}
echo json_encode(['ok' => true]);
