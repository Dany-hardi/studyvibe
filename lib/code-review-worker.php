<?php
declare(strict_types=1);

/**
 * Background marker of code answers. Started by LiveCodeReview::kick() (several side by side, each claims one answer at a time); can also be
 * run by hand:   php lib/code-review-worker.php [registration_id]
 * It marks what is due, then exits.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
@set_time_limit(0);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/LiveCodeReview.php';

try {
    LiveCodeReview::processFor(Database::getInstance(), 900, (int)($argv[1] ?? 0));
} catch (Throwable $e) {
    error_log('code review worker: ' . $e->getMessage());
}
