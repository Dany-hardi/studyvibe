<?php
declare(strict_types=1);

/** The Markdown template of a job posting (?lang=en|fr), for administrators. */

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../lib/CareersMarkdown.php';

careersAdminOrFail(false);
$lang = ($_GET['lang'] ?? 'en') === 'fr' ? 'fr' : 'en';
header('Content-Type: text/markdown; charset=utf-8');
header('Content-Disposition: attachment; filename="' . ($lang === 'fr' ? 'modele-offre-studyvibe.md' : 'studyvibe-job-template.md') . '"');
header('Cache-Control: private, no-store');
echo CareersMarkdown::template($lang);
