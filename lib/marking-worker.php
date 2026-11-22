<?php
declare(strict_types=1);

/**
 * Runs the marking engine on the copies waiting in grading buckets (lib/MarkingBucket.php). Run by cron every minute; the bucket page
 * also runs it in short slices while the teacher has it open, so a bucket is ready without this worker, only slower.
 *   php lib/marking-worker.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/MarkingBucket.php';

$lock = fopen(sys_get_temp_dir() . '/studyvibe_marking_worker.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
$pdo = Database::getInstance();
$start = microtime(true);
$lessons = $pdo->query(
    "SELECT DISTINCT lesson_id FROM lesson_assignment_submissions WHERE in_marking_at IS NOT NULL AND engine_at IS NULL AND graded_at IS NULL LIMIT 50"
)->fetchAll(PDO::FETCH_COLUMN);
foreach ($lessons as $lessonId) {
    $left = 50 - (microtime(true) - $start);
    if ($left < 3) {
        break;
    }
    try {
        $r = MarkingBucket::run($pdo, (int)$lessonId, $left);
        echo "lesson $lessonId: {$r['done']} done, {$r['pending']} left\n";
    } catch (Throwable $e) {
        error_log('marking worker: ' . $e->getMessage());
    }
}
