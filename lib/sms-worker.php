<?php
declare(strict_types=1);

/**
 * Background sender for the SMS queue. Started by SmsQueue::kick(); can also be run by hand or by cron:
 *   php lib/sms-worker.php       (sends everything that is due, then exits)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/SmsQueue.php';

// One worker at a time is enough for SMS volumes; a second one exits at once
$lock = fopen(sys_get_temp_dir() . '/studyvibe_sms_worker.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
$sent = SmsQueue::work(Database::getInstance());
echo "sent: $sent\n";
