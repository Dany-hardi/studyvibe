<?php
declare(strict_types=1);

/**
 * Background sender for the email queue. Started by EmailQueue::kick(); also run by cron EVERY MINUTE (see DEPLOYMENT.md), which is what
 * guarantees the reminder 15 minutes before a live evaluation:
 *   php lib/email-worker.php       (queues the reminders that are due, sends everything that is due, then exits)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/EmailQueue.php';
require_once __DIR__ . '/LiveReminders.php';
require_once __DIR__ . '/PushQueue.php';
require_once __DIR__ . '/CareersWorker.php';

// One worker at a time (Gmail limits parallel connections anyway); a second one exits at once
$lock = fopen(sys_get_temp_dir() . '/studyvibe_email_worker.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
$pdo = Database::getInstance();
// Live evaluations about to start get their reminder first, so it leaves in the same run
require_once __DIR__ . '/LiveEvalModes.php';
LiveEvalModes::advance($pdo);
$r = LiveReminders::dispatch($pdo);
echo 'reminders: ' . $r['queued'] . ' for ' . $r['sessions'] . " session(s)\n";
require_once __DIR__ . '/LiveCodeReview.php';
try {
    // side by side when the host lets us start processes, otherwise one after the other for this run
    if (!LiveCodeReview::kick($pdo, 0, 4)) { echo 'code reviews: ' . LiveCodeReview::processFor($pdo, 45) . "\n"; } else { echo "code reviews: workers started\n"; }
} catch (Throwable $e) { error_log('code reviews: ' . $e->getMessage()); }
// results emails of live evaluations (also sent by workers started when they are queued; this is the safety net)
require_once __DIR__ . '/LiveMailQueue.php';
try { LiveMailQueue::work($pdo, 0, 40); } catch (Throwable $e) { error_log('live mails: ' . $e->getMessage()); }
echo 'sent: ' . EmailQueue::work($pdo) . "\n";
echo 'push: ' . PushQueue::work($pdo) . "\n";
// Careers: screening, interview reminders, daily digest (own lock, runs only what is due)
echo 'careers: ' . json_encode(CareersWorker::run($pdo, 15)) . "\n";
