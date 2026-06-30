<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Database.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    http_response_code(403);
    die('Accès non autorisé.');
}

$type = trim((string)($_GET['type'] ?? ''));
$id   = (int)($_GET['id'] ?? 0);

if ($id <= 0 || !in_array($type, ['lesson', 'course', 'live'], true)) {
    http_response_code(400);
    die('Requête invalide.');
}

try {
    $pdo = Database::getInstance();
    $teacherId = (int)$_SESSION['user_id'];
    $questions = [];
    $filename = "questions_{$type}_{$id}.csv";

    if ($type === 'lesson') {
        // Verify lesson ownership
        $stmt = $pdo->prepare("
            SELECT l.id FROM lessons l
            JOIN chapters ch ON ch.id = l.chapter_id
            JOIN courses c ON c.id = ch.course_id
            WHERE l.id = :lid AND c.teacher_id = :tid
        ");
        $stmt->execute(['lid' => $id, 'tid' => $teacherId]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            die('Non autorisé.');
        }

        $stmt = $pdo->prepare("SELECT * FROM lesson_questions WHERE lesson_id = :id ORDER BY id ASC");
        $stmt->execute(['id' => $id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($type === 'course') {
        // Verify course ownership
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = :cid AND teacher_id = :tid");
        $stmt->execute(['cid' => $id, 'tid' => $teacherId]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            die('Non autorisé.');
        }

        $stmt = $pdo->prepare("SELECT * FROM course_questions WHERE course_id = :id ORDER BY id ASC");
        $stmt->execute(['id' => $id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($type === 'live') {
        // Verify live session ownership
        $stmt = $pdo->prepare("SELECT id FROM live_eval_sessions WHERE id = :sid AND teacher_id = :tid");
        $stmt->execute(['sid' => $id, 'tid' => $teacherId]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            die('Non autorisé.');
        }

        $stmt = $pdo->prepare("SELECT * FROM live_eval_questions WHERE session_id = :id ORDER BY sort_order ASC, id ASC");
        $stmt->execute(['id' => $id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Set download headers
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");

    $output = fopen('php://output', 'w');
    // Write UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers
    fputcsv($output, ['question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct', 'explanation']);

    // Data
    foreach ($questions as $q) {
        fputcsv($output, [
            $q['question_text'] ?? '',
            $q['option_a'] ?? '',
            $q['option_b'] ?? '',
            $q['option_c'] ?? '',
            $q['option_d'] ?? '',
            $q['correct_option'] ?? '',
            $q['explanation'] ?? ''
        ]);
    }
    fclose($output);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    die('Erreur : ' . $e->getMessage());
}
