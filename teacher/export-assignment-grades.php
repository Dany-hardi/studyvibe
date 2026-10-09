<?php
declare(strict_types=1);

/**
 * The marks of the assignments of a course, one row per enrolled student (matricule, name, email) and one column per assignment,
 * then the total and the percentage. For the teacher of the course.
 *
 *   /teacher/export-assignment-grades.php?course_id=3&format=xlsx      Excel
 *   /teacher/export-assignment-grades.php?course_id=3&format=pdf       PDF (LaTeX, landscape)
 *   add &lesson_id=12 for one assignment only
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Assignments.php';
require_once __DIR__ . '/../lib/SpreadsheetExporter.php';
require_once __DIR__ . '/../lib/ExportDocs.php';

if (!isLoggedIn() || !in_array($_SESSION['user_role'] ?? '', ['teacher', 'promoter'], true)) {
    http_response_code(403);
    exit('Accès non autorisé.');
}

$courseId = (int)($_GET['course_id'] ?? 0);
$lessonId = (int)($_GET['lesson_id'] ?? 0) ?: null;
$format = ($_GET['format'] ?? 'xlsx') === 'pdf' ? 'pdf' : 'xlsx';
$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';

try {
    $pdo = Database::getInstance();
    $st = $pdo->prepare("SELECT id, title, teacher_id FROM courses WHERE id = :id");
    $st->execute(['id' => $courseId]);
    $course = $st->fetch(PDO::FETCH_ASSOC);
    if (!$course || ($_SESSION['user_role'] === 'teacher' && (int)$course['teacher_id'] !== (int)$_SESSION['user_id'])) {
        http_response_code(403);
        exit('Cours introuvable ou non autorisé.');
    }

    $sheet = Assignments::marksSheet($pdo, $courseId, $lessonId);
    if (!$sheet['assignments']) {
        http_response_code(404);
        exit($lang === 'en' ? 'No assignment to report.' : 'Aucun devoir à exporter.');
    }
    $slug = 'notes_devoirs_' . ExportTheme::slug((string)$course['title'], 'cours') . '_' . date('Y-m-d');
    $nAssign = count($sheet['assignments']);
    $title = ($lang === 'en' ? 'Assignment marks' : 'Notes des devoirs') . ' — ' . $course['title'];

    if ($format === 'pdf') {
        $display = array_map(fn($r) => array_map(fn($c) => is_float($c) || is_int($c) ? str_replace('.', ',', (string)$c) : $c, $r), $sheet['rows']);
        $doc = ExportDocs::sheetReport([
            'name' => 'Notes', 'title' => $title, 'headers' => $sheet['headers'], 'rows' => $display,
            'meta' => [[$lang === 'en' ? 'Course' : 'Cours', (string)$course['title']], [$lang === 'en' ? 'Assignments' : 'Devoirs', (string)$nAssign]],
        ], $lang);
        $pdf = LatexCompiler::compile($doc['tex']);
        if ($pdf) {
            auditLog('export_assignment_marks_pdf', "Course #{$courseId}");
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $slug . '.pdf"');
            header('Content-Length: ' . strlen($pdf));
            echo $pdf;
            exit;
        }
        // no LaTeX on this server: the Excel file carries the same table
    }

    $formats = [];
    for ($i = 0; $i < $nAssign; $i++) {
        $formats[3 + $i] = 'dec';
    }
    $formats[3 + $nAssign] = 'dec';
    $formats[4 + $nAssign] = 'dec';
    $formats[5 + $nAssign] = 'pct';
    auditLog('export_assignment_marks_xlsx', "Course #{$courseId}");
    SpreadsheetExporter::sendDownload($slug, [[
        'name' => 'Notes devoirs', 'title' => $title,
        'meta' => [['Cours', (string)$course['title']], ['Devoirs', (string)$nAssign], ['Édité le', date('d/m/Y H:i')]],
        'headers' => $sheet['headers'], 'formats' => $formats, 'rows' => $sheet['rows'],
    ]]);
} catch (Throwable $e) {
    logServerError($e, 'export-assignment-grades');
    http_response_code(500);
    exit('Erreur lors de la génération du rapport.');
}
