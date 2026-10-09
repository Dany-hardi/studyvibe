<?php
declare(strict_types=1);

/**
 * Background sender for live-evaluation result emails. Started by LiveMailQueue::kick(); can also be run by hand or by cron:
 *   php lib/live-mail-worker.php        (drains the queue, then exits)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
@set_time_limit(0);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/LiveMailQueue.php';

LiveMailQueue::work(Database::getInstance(), (int)($argv[1] ?? 1));
