<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Student Assignment Submission Handler
 * 
 * Handles deposit and updating of student assignments (DOCX/PDF files <= 20Mo, links, comments).
 */

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé. Session étudiant requise.']);
    exit;
}

$studentId = (int)$_SESSION['user_id'];
$lessonId  = (int)($_POST['lesson_id'] ?? 0);
$studentName = trim((string)($_POST['student_name'] ?? ''));
$studentMatricule = trim((string)($_POST['student_matricule'] ?? ''));
$link      = trim((string)($_POST['assignment_link'] ?? $_POST['submitted_link'] ?? ''));
$comment   = trim((string)($_POST['student_comment'] ?? ''));

if ($lessonId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Identifiant de leçon invalide.']);
    exit;
}

if (empty($studentName) || empty($studentMatricule)) {
    echo json_encode(['success' => false, 'message' => 'Veuillez préciser votre Nom complet et votre Matricule étudiant.']);
    exit;
}

try {
    $pdo = Database::getInstance();

    // Verify lesson exists and has assignments enabled
    $stmt = $pdo->prepare("
        SELECT l.id, l.has_assignment, l.assignment_type, l.allowed_file_types, l.assignment_deadline, ch.course_id 
        FROM lessons l
        JOIN chapters ch ON l.chapter_id = ch.id
        WHERE l.id = :id
    ");
    $stmt->execute(['id' => $lessonId]);
    $lesson = $stmt->fetch();

    if (!$lesson || empty($lesson['has_assignment'])) {
        echo json_encode(['success' => false, 'message' => 'Le dépôt de devoirs n\'est pas activé pour cette leçon.']);
        exit;
    }

    $asgType = $lesson['assignment_type'] ?? 'both';
    $rawAllowed = $lesson['allowed_file_types'] ?? 'pdf,docx';
    $allowedExts = array_map('trim', array_map('strtolower', explode(',', $rawAllowed)));

    // Verify student enrollment
    $enrollStmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = :sid AND course_id = :cid");
    $enrollStmt->execute(['sid' => $studentId, 'cid' => $lesson['course_id']]);
    if (!$enrollStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas inscrit au cours correspondant.']);
        exit;
    }

    // Fetch existing submission if any
    $existingStmt = $pdo->prepare("SELECT * FROM lesson_assignment_submissions WHERE lesson_id = :lid AND student_id = :sid");
    $existingStmt->execute(['lid' => $lessonId, 'sid' => $studentId]);
    $existingSub = $existingStmt->fetch();

    $filePath = $existingSub['submitted_file_path'] ?? null;
    $fileName = $existingSub['submitted_file_name'] ?? null;

    // Process file upload if provided
    if (!empty($_FILES['assignment_file']['name']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['assignment_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Allowed extensions verification
        if (!in_array($ext, $allowedExts, true)) {
            $allowedDisplay = implode(', ', array_map('strtoupper', $allowedExts));
            echo json_encode(['success' => false, 'message' => "Format de fichier non supporté. Formats acceptés par l'enseignant : {$allowedDisplay}."]);
            exit;
        }

        // Limit size: 20 MB
        if ($file['size'] > 20 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Taille de fichier trop grande. Le fichier ne doit pas dépasser 20 Mo.']);
            exit;
        }

        $targetDir = __DIR__ . '/../uploads/assignments/';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $newFileName = 'sub_' . $lessonId . '_' . $studentId . '_' . md5(uniqid('', true)) . '.' . $ext;
        $targetPath = $targetDir . $newFileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Échec du transfert du fichier sur le serveur.']);
            exit;
        }

        // Delete old file if updating
        if (!empty($filePath) && file_exists($targetDir . $filePath)) {
            @unlink($targetDir . $filePath);
        }

        $filePath = $newFileName;
        $fileName = $file['name'];
    }

    // Format link if provided
    if (!empty($link)) {
        if (!preg_match('~^https?://~i', $link)) {
            $link = 'https://' . $link;
        }
    }

    if ($asgType === 'file' && empty($filePath)) {
        echo json_encode(['success' => false, 'message' => 'L\'enseignant exige la soumission d\'un document/fichier.']);
        exit;
    }

    if ($asgType === 'link' && empty($link)) {
        echo json_encode(['success' => false, 'message' => 'L\'enseignant exige la soumission d\'un lien web.']);
        exit;
    }

    if (empty($filePath) && empty($link)) {
        echo json_encode(['success' => false, 'message' => 'Veuillez déposer un document ou renseigner un lien de projet.']);
        exit;
    }

    // Determine submission_type
    $subType = 'file';
    if (!empty($filePath) && !empty($link)) {
        $subType = 'both';
    } elseif (!empty($link)) {
        $subType = 'link';
    }

    // Save to database
    $upsertStmt = $pdo->prepare("
        INSERT INTO lesson_assignment_submissions 
            (lesson_id, student_id, student_name, student_matricule, submission_type, submitted_file_path, submitted_file_name, submitted_link, student_comment, submitted_at)
        VALUES 
            (:lid, :sid, :sname, :smat, :stype, :fpath, :fname, :slink, :scomm, NOW())
        ON DUPLICATE KEY UPDATE
            student_name = VALUES(student_name),
            student_matricule = VALUES(student_matricule),
            submission_type = VALUES(submission_type),
            submitted_file_path = VALUES(submitted_file_path),
            submitted_file_name = VALUES(submitted_file_name),
            submitted_link = VALUES(submitted_link),
            student_comment = VALUES(student_comment),
            submitted_at = NOW()
    ");

    $upsertStmt->execute([
        'lid'   => $lessonId,
        'sid'   => $studentId,
        'sname' => $studentName,
        'smat'  => $studentMatricule,
        'stype' => $subType,
        'fpath' => $filePath,
        'fname' => $fileName,
        'slink' => $link,
        'scomm' => $comment,
    ]);

    // Fetch refreshed record
    $refStmt = $pdo->prepare("SELECT * FROM lesson_assignment_submissions WHERE lesson_id = :lid AND student_id = :sid");
    $refStmt->execute(['lid' => $lessonId, 'sid' => $studentId]);
    $sub = $refStmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Votre travail a été déposé avec succès !',
        'submission' => $sub
    ]);

} catch (PDOException $e) {
    jsonError('Erreur lors du dépôt du devoir.', $e, 'submit-assignment');
}
