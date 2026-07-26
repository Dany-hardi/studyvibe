<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Download PDF Assignments Packaged in ZIP
 * 
 * Collects all submitted PDF documents for a selected course/lesson and packages them into a downloadable ZIP archive.
 */

require_once __DIR__ . '/../auth.php';

if (!isLoggedIn() || ($_SESSION['user_role'] !== 'teacher' && $_SESSION['user_role'] !== 'promoter')) {
    http_response_code(403);
    die('Accès non autorisé.');
}

$teacherId = (int)$_SESSION['user_id'];
$courseId  = (int)($_GET['course_id'] ?? 0);
$lessonId  = (int)($_GET['lesson_id'] ?? 0);

try {
    $pdo = Database::getInstance();

    $sql = "
        SELECT 
            u.name AS student_name,
            l.title AS lesson_title,
            las.submitted_file_path,
            las.submitted_file_name
        FROM lesson_assignment_submissions las
        JOIN users u ON u.id = las.student_id
        JOIN lessons l ON l.id = las.lesson_id
        JOIN chapters ch ON ch.id = l.chapter_id
        JOIN courses c ON c.id = ch.course_id
        WHERE las.submitted_file_path IS NOT NULL 
          AND las.submitted_file_path != ''
    ";

    $params = [];

    if ($_SESSION['user_role'] === 'teacher') {
        $sql .= " AND c.teacher_id = :tid";
        $params['tid'] = $teacherId;
    }

    if ($courseId > 0) {
        $sql .= " AND c.id = :cid";
        $params['cid'] = $courseId;
    }

    if ($lessonId > 0) {
        $sql .= " AND l.id = :lid";
        $params['lid'] = $lessonId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter all submitted files
    $fileSubmissions = array_filter($submissions, function($s) {
        $ext = strtolower(pathinfo($s['submitted_file_path'] ?? '', PATHINFO_EXTENSION));
        return !empty($ext);
    });

    if (empty($fileSubmissions)) {
        die('Aucun fichier déposé n\'a été trouvé pour le filtre sélectionné.');
    }

    if (!class_exists('ZipArchive')) {
        die('L\'extension PHP ZipArchive n\'est pas activée sur le serveur.');
    }

    $zip = new ZipArchive();
    $tempZipFile = sys_get_temp_dir() . '/devoirs_pdf_' . time() . '_' . uniqid() . '.zip';

    if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die('Impossible de créer le fichier ZIP temporaire.');
    }

    $assignmentsDir = __DIR__ . '/../uploads/assignments/';
    $addedCount = 0;

    foreach ($fileSubmissions as $s) {
        $filePath = $assignmentsDir . $s['submitted_file_path'];
        if (file_exists($filePath) && is_file($filePath)) {
            $studentSlug = preg_replace('/[^a-zA-Z0-9_-]/', '_', $s['student_name'] ?? 'Etudiant');
            $lessonSlug  = preg_replace('/[^a-zA-Z0-9_-]/', '_', $s['lesson_title'] ?? 'Lecon');
            $origName    = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $s['submitted_file_name'] ?? 'document');
            
            $zipEntryName = "{$studentSlug}_{$lessonSlug}_{$origName}";
            $zip->addFile($filePath, $zipEntryName);
            $addedCount++;
        }
    }

    $zip->close();

    if ($addedCount === 0 || !file_exists($tempZipFile)) {
        die('Les fichiers physiques spécifiés sont introuvables sur le serveur.');
    }

    $downloadFilename = "devoirs_fichiers_" . date('Y-m-d_H-i') . ".zip";

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
    header('Content-Length: ' . (string)filesize($tempZipFile));
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($tempZipFile);
    @unlink($tempZipFile);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die('Erreur lors de la création de l\'archive ZIP : ' . $e->getMessage());
}
