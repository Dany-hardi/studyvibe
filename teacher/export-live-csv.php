<?php
/**
 * StudyVibe Academic LMS - CSV Evaluation Exporter & Score Converter
 * 
 * Generates standardized 3-column CSV reports (matricule, nom_prenom, note) 
 * for live tele-evaluations with live scale conversion support.
 */

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    http_response_code(403);
    die('Accès non autorisé.');
}

$teacherId   = (int)$_SESSION['user_id'];
$sessionId   = (int)($_REQUEST['session_id'] ?? 0);
$targetScale = isset($_REQUEST['target_scale']) && is_numeric($_REQUEST['target_scale']) ? (float)$_REQUEST['target_scale'] : null;
$isPreview   = (int)($_REQUEST['preview'] ?? 0) === 1;

if ($sessionId <= 0) {
    if ($isPreview) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Identifiant de séance invalide.']);
        exit;
    }
    http_response_code(400);
    die('Identifiant de séance non valide.');
}

try {
    $pdo = Database::getInstance();

    // Verify ownership
    $stmt = $pdo->prepare("
        SELECT s.*, c.title AS course_title
        FROM live_eval_sessions s
        JOIN courses c ON s.course_id = c.id
        WHERE s.id = :sid AND s.teacher_id = :tid
    ");
    $stmt->execute(['sid' => $sessionId, 'tid' => $teacherId]);
    $session = $stmt->fetch();

    if (!$session) {
        if ($isPreview) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Séance introuvable ou non autorisée.']);
            exit;
        }
        http_response_code(403);
        die('Séance introuvable ou non autorisée.');
    }

    // Count total questions in session
    $qStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_questions WHERE session_id = :sid");
    $qStmt->execute(['sid' => $sessionId]);
    $totalQuestions = (int)$qStmt->fetchColumn();
    if ($totalQuestions <= 0) {
        $totalQuestions = 20; // Default fallback scale if no questions present
    }

    // Original max scale is total questions count
    $originalScale = (float)$totalQuestions;
    if ($targetScale === null || $targetScale <= 0) {
        $targetScale = $originalScale;
    }

    // Query registrations and student information
    $stmt = $pdo->prepare("
        SELECT 
            r.id,
            r.name AS reg_name,
            r.email AS reg_email,
            r.score AS score_percent,
            r.registered_at,
            u.matricule AS user_matricule,
            u.name AS user_name
        FROM live_eval_registrations r
        LEFT JOIN users u ON (r.student_id = u.id OR r.email = u.email)
        WHERE r.session_id = :sid
        ORDER BY CASE WHEN r.score IS NULL THEN 1 ELSE 0 END, r.score DESC, r.name ASC
    ");
    $stmt->execute(['sid' => $sessionId]);
    $registrations = $stmt->fetchAll();

    $studentList = [];
    foreach ($registrations as $r) {
        $matricule = !empty($r['user_matricule']) ? trim($r['user_matricule']) : 'N/A';
        $fullName  = !empty($r['user_name']) ? trim($r['user_name']) : trim($r['reg_name']);

        if ($r['score_percent'] !== null) {
            $rawScore = round(((float)$r['score_percent'] / 100) * $originalScale, 2);
            $convertedScore = round(((float)$r['score_percent'] / 100) * $targetScale, 2);
        } else {
            $rawScore = 0.0;
            $convertedScore = 0.0;
        }

        // Format score cleanly (e.g. 15 instead of 15.00 if whole integer)
        $formattedNote = (fmod($convertedScore, 1.0) == 0.0) ? (string)(int)$convertedScore : (string)$convertedScore;

        $studentList[] = [
            'matricule'       => $matricule,
            'nom_prenom'      => $fullName,
            'raw_score'       => $rawScore,
            'converted_score' => $convertedScore,
            'note'            => $formattedNote
        ];
    }

    // If API Preview request, return JSON
    if ($isPreview) {
        header('Content-Type: application/json');
        echo json_encode([
            'success'        => true,
            'session_title'  => $session['title'],
            'original_scale' => $originalScale,
            'target_scale'   => $targetScale,
            'students'       => $studentList
        ]);
        exit;
    }

    // Direct CSV File Download
    $slug = preg_replace('/[^a-z0-9_-]+/i', '_', (string)$session['title']) ?: 'evaluation';
    $filename = "rapport_notes_" . mb_strtolower($slug) . "_" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');
    // Write UTF-8 BOM for Excel / UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Mandatory CSV headers: matricule, nom_prenom, note
    fputcsv($output, ['matricule', 'nom_prenom', 'note'], ';');

    foreach ($studentList as $s) {
        fputcsv($output, [
            $s['matricule'],
            $s['nom_prenom'],
            $s['note']
        ], ';');
    }

    fclose($output);
    auditLog('export_live_csv_converted', "Session #{$sessionId} — Scale {$originalScale} -> {$targetScale}");
    exit;

} catch (Exception $e) {
    if ($isPreview) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erreur serveur : ' . $e->getMessage()]);
        exit;
    }
    http_response_code(500);
    die('Erreur serveur lors de la génération du rapport CSV : ' . $e->getMessage());
}
