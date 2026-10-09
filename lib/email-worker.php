<?php
declare(strict_types=1);

/**
 * Background sender for the email queue. Started by EmailQueue::kick(); can also be run by hand or by cron:
 *   php lib/email-worker.php       (sends everything that is due, then exits)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/EmailQueue.php';

// One worker at a time (Gmail limits parallel connections anyway); a second one exits at once
$lock = fopen(sys_get_temp_dir() . '/studyvibe_email_worker.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
echo 'sent: ' . EmailQueue::work(Database::getInstance()) . "\n";
