<?php
declare(strict_types=1);

/**
 * The careers engine, from the command line (cron, every minute):
 *   php lib/careers-worker.php
 * Screens new applications, sends interview reminders, declines held applications when asked, erases applications past their retention,
 * and sends the daily digest. lib/email-worker.php (scheduled by the Docker image) calls it too, so no extra cron entry is needed there.
 * See lib/CareersWorker.php for what each task does.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/CareersWorker.php';

$r = CareersWorker::run(Database::getInstance(), 40);
echo 'careers: ' . json_encode($r) . "\n";
