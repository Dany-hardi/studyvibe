<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Export Assignment Submissions to Excel
 * 
 * Exports student assignment submissions for a selected course or lesson into an Excel-compatible CSV file.
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

    // Base Query
    $sql = "
        SELECT 
            u.name AS student_name,
            las.student_name AS declared_student_name,
            las.student_matricule,
            u.email AS student_email,
            c.title AS course_title,
            l.title AS lesson_title,
            las.submitted_at,
            las.submission_type,
            las.submitted_link,
            las.submitted_file_name,
            las.submitted_file_path,
            las.student_comment
        FROM lesson_assignment_submissions las
        JOIN users u ON u.id = las.student_id
        JOIN lessons l ON l.id = las.lesson_id
        JOIN chapters ch ON ch.id = l.chapter_id
        JOIN courses c ON c.id = ch.course_id
        WHERE 1=1
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

    $sql .= " ORDER BY las.submitted_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $appUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $filename = "devoirs_etudiants_" . date('Y-m-d_H-i') . ".csv";

    // Set download headers
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');
    // Write UTF-8 BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers
    fputcsv($output, [
        'Nom & Prénom',
        'Matricule',
        'Email Compte',
        'Cours',
        'Leçon',
        'Date & Heure de Dépôt',
        'Type de Rendu',
        'Lien Partagé / Projet',
        'Fichier Déposé',
        'Lien Téléchargement Fichier',
        'Commentaire Élève'
    ]);

    // Data rows
    foreach ($submissions as $s) {
        $fileDownloadUrl = !empty($s['submitted_file_path']) ? $appUrl . '/download.php?type=assignment&file=' . rawurlencode($s['submitted_file_path']) : '';
        $typeLabel = match ($s['submission_type']) {
            'both' => 'Fichier & Lien',
            'link' => 'Lien uniquement',
            default => 'Fichier uniquement',
        };

        fputcsv($output, [
            !empty($s['declared_student_name']) ? $s['declared_student_name'] : ($s['student_name'] ?? 'Élève inconnu'),
            $s['student_matricule'] ?? '',
            $s['student_email'] ?? '',
            $s['course_title'] ?? '',
            $s['lesson_title'] ?? '',
            $s['submitted_at'] ?? '',
            $typeLabel,
            $s['submitted_link'] ?? '',
            $s['submitted_file_name'] ?? '',
            $fileDownloadUrl,
            $s['student_comment'] ?? ''
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die('Erreur lors de l\'exportation Excel : ' . $e->getMessage());
}
