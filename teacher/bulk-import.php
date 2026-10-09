<?php
declare(strict_types=1);

/**
 * Bulk import of questions (and pictures) from a ZIP or CSV, for the teacher who owns the target.
 *
 *   POST mode=preview  file=<zip|csv>  type=live|lesson|course  course_id=..  [session_id=..]  [lesson_id=..]
 *        checks the package and describes it line by line; writes nothing
 *   POST mode=commit   (same fields)   imports it, all or nothing
 *   GET  ?template=1                   downloads an empty package to fill in
 *
 * The format and the rules are described in lib/BulkPackage.php and docs/BULK_IMPORT.md.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/BulkPackage.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/RateLimit.php';

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'teacher') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['template'])) {
        $zip = BulkPackage::template();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="studyvibe_pack_modele.zip"');
        header('Content-Length: ' . filesize($zip));
        readfile($zip);
        @unlink($zip);
        exit;
    }
    http_response_code(400);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-store');
ini_set('display_errors', '0');   // a stray PHP notice must never get into the JSON
Security::requirePostFromSameSite();

$fail = static function (string $msg, int $http = 200, array $extra = []): never {
    http_response_code($http);
    echo json_encode(['success' => false, 'message' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
};

$teacherId = (int)$_SESSION['user_id'];
$type = (string)($_POST['type'] ?? '');
$mode = ($_POST['mode'] ?? 'preview') === 'commit' ? 'commit' : 'preview';
$courseId = (int)($_POST['course_id'] ?? 0);
$sessionId = (int)($_POST['session_id'] ?? 0);
$lessonId = (int)($_POST['lesson_id'] ?? 0);
if (!in_array($type, ['live', 'lesson', 'course'], true)) {
    $fail('Type d’import invalide.');
}

$tmpDir = '';
try {
    $pdo = Database::getInstance();

    // Whose is the target?
    $own = $pdo->prepare('SELECT 1 FROM courses WHERE id = :id AND teacher_id = :t');
    $own->execute(['id' => $courseId, 't' => $teacherId]);
    if (!$own->fetchColumn()) {
        $fail('Cours non autorisé.', 403);
    }
    if ($type === 'live') {
        $q = $pdo->prepare('SELECT 1 FROM live_eval_sessions WHERE id = :s AND course_id = :c AND teacher_id = :t');
        $q->execute(['s' => $sessionId, 'c' => $courseId, 't' => $teacherId]);
        if (!$q->fetchColumn()) {
            $fail('Séance introuvable ou non autorisée.', 403);
        }
    } elseif ($type === 'lesson') {
        $q = $pdo->prepare('SELECT 1 FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id WHERE l.id = :l AND ch.course_id = :c');
        $q->execute(['l' => $lessonId, 'c' => $courseId]);
        if (!$q->fetchColumn()) {
            $fail('Leçon introuvable dans ce cours.', 403);
        }
    }

    if (!RateLimit::allow($pdo, 'bulk:' . $teacherId, 40, 3600)) {
        $fail('Trop d’imports en peu de temps. Réessayez dans un moment.', 429);
    }

    $file = $_FILES['file'] ?? null;
    if (!$file || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        $fail('Aucun fichier reçu.');
    }
    if ((int)$file['error'] === UPLOAD_ERR_INI_SIZE || (int)$file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $fail('Le fichier dépasse la taille maximale acceptée par le serveur (upload_max_filesize).');
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
        $fail('Le transfert du fichier a échoué. Réessayez.');
    }

    $res = BulkPackage::check((string)$file['tmp_name'], (string)$file['name'], $type);
    $tmpDir = $res['tmp'];
    register_shutdown_function(static fn() => BulkPackage::cleanup($tmpDir));   // exit() skips finally blocks, shutdown functions still run
    $view = [
        'summary' => $res['summary'], 'rows' => $res['rows'], 'errors' => $res['errors'], 'warnings' => $res['warnings'], 'can_import' => $res['ok'],
    ];

    if ($mode === 'preview') {
        echo json_encode(['success' => true, 'mode' => 'preview'] + $view, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // commit: the package is checked again here (the browser's earlier preview is not trusted), and refused if anything is wrong
    if (!$res['ok']) {
        $fail('Le pack contient des erreurs : rien n’a été importé. Corrigez-les et renvoyez le pack.', 200, ['mode' => 'commit'] + $view);
    }
    $count = match ($type) {
        'live'   => BulkPackage::importLive($pdo, $sessionId, $res['questions']),
        'lesson' => QuestionImporter::importLessonQuestions($pdo, $lessonId, $res['questions']),
        default  => QuestionImporter::importCourseQuestions($pdo, $courseId, $res['questions']),
    };
    if ($type === 'lesson' && $count > 0) {
        require_once __DIR__ . '/../lib/LessonProgressionHelper.php';
        LessonProgressionHelper::handleLessonUpdate($pdo, $lessonId);
    }
    if ($type === 'live') {
        foreach (glob(dirname(__DIR__) . '/uploads/live_cache/*.json') ?: [] as $f) {
            @unlink($f);
        }
    }
    auditLog('questions_bulk_imported', "{$type}: {$count} questions, {$res['summary']['with_image']} images, course #{$courseId}");
    echo json_encode(['success' => true, 'mode' => 'commit', 'imported' => $count, 'with_image' => $res['summary']['with_image'], 'warnings' => $res['warnings'],
        'message' => "{$count} question(s) importée(s)."], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    logServerError($e, 'teacher/bulk-import');
    $fail('L’import a échoué : rien n’a été enregistré. Réessayez.', 500);
}
