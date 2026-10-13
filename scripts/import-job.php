<?php
declare(strict_types=1);

/**
 * Creates job postings from Markdown files, from the command line (the same engine and rules as the careers workspace):
 *
 *   php scripts/import-job.php docs/careers/postings/engineering-senior-full-stack-developer.md [more.md ...] [--draft] [--dry-run]
 *
 * --dry-run  only parses and prints the problems; --draft saves the postings privately instead of publishing them.
 * The postings are created in the name of the first active administrator. Exit code 0 when every file was imported (or is valid for --dry-run).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../lib/CareersMarkdown.php';

$files = array_values(array_filter(array_slice($argv, 1), static fn($a) => !str_starts_with($a, '--')));
$draft = in_array('--draft', $argv, true);
$dry = in_array('--dry-run', $argv, true);
if (!$files) {
    fwrite(STDERR, "Usage: php scripts/import-job.php file.md [file.md ...] [--draft] [--dry-run]\n");
    exit(2);
}
$pdo = Database::getInstance();
$admin = (int)$pdo->query("SELECT id FROM users WHERE role = 'promoter' AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
if ($admin <= 0 && !$dry) {
    fwrite(STDERR, "No active administrator account found.\n");
    exit(1);
}
$bad = 0;
foreach ($files as $f) {
    echo basename($f) . ': ';
    if (!is_file($f)) {
        echo "file not found\n";
        $bad++;
        continue;
    }
    $md = (string)file_get_contents($f);
    if ($dry) {
        $p = CareersMarkdown::parse($md, $pdo);
        echo $p['ok'] ? "valid" : "INVALID";
        echo ' (' . count($p['errors']) . ' error(s), ' . count($p['warnings']) . " warning(s))\n";
        foreach (array_merge($p['errors'], $p['warnings']) as $m) {
            echo '  - ' . $m['code'] . ($m['field'] ? ' [' . $m['field'] . ']' : '') . ($m['line'] ? ' line ' . $m['line'] : '') . ' ' . json_encode($m['params'], JSON_UNESCAPED_UNICODE) . "\n";
        }
        $bad += $p['ok'] ? 0 : 1;
        continue;
    }
    $r = CareersMarkdown::import($pdo, $admin, $md, $draft ? 'draft' : null);
    if ($r['ok']) {
        echo 'created #' . $r['id'] . ' ' . $r['job']['code'] . ' /career.php?slug=' . $r['slug'] . ' (' . $r['job']['status'] . ")\n";
    } else {
        echo "refused\n";
        foreach ($r['errors'] as $m) {
            echo '  - ' . $m['code'] . ($m['field'] ? ' [' . $m['field'] . ']' : '') . ($m['line'] ? ' line ' . $m['line'] : '') . ' ' . json_encode($m['params'], JSON_UNESCAPED_UNICODE) . "\n";
        }
        $bad++;
    }
}
exit($bad ? 1 : 0);
