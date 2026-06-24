<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../QuestionImporter.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}


// requireCsrf();
$type     = trim((string)($_POST['type'] ?? ''));
$courseId = (int)($_POST['course_id'] ?? 0);
$lessonId = (int)($_POST['lesson_id'] ?? 0);
$user     = getCurrentUser();

if (!in_array($type, ['lesson', 'course', 'live'], true)) {
    echo json_encode(['success' => false, 'message' => 'Type d\'import invalide.']);
    exit;
}

try {
    $pdo = Database::getInstance();

    $stmt = $pdo->prepare('SELECT id FROM courses WHERE id = :id AND teacher_id = :tid');
    $stmt->execute(['id' => $courseId, 'tid' => $user['id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cours non autorisé.']);
        exit;
    }

    if ($type === 'lesson') {
        if ($lessonId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Leçon non spécifiée.']);
            exit;
        }
        $chk = $pdo->prepare('
            SELECT l.id FROM lessons l
            JOIN chapters ch ON ch.id = l.chapter_id
            WHERE l.id = :lid AND ch.course_id = :cid
        ');
        $chk->execute(['lid' => $lessonId, 'cid' => $courseId]);
        if (!$chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Leçon introuvable dans ce cours.']);
            exit;
        }
    }

    if ($type === 'live') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if ($sessionId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Séance non spécifiée.']);
            exit;
        }
        $chk = $pdo->prepare('
            SELECT id FROM live_eval_sessions
            WHERE id = :sid AND course_id = :cid AND teacher_id = :tid
        ');
        $chk->execute(['sid' => $sessionId, 'cid' => $courseId, 'tid' => $user['id']]);
        if (!$chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Séance introuvable ou non autorisée.']);
            exit;
        }
    }

    $parsed = ['questions' => [], 'errors' => []];

    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $content = (string)file_get_contents($_FILES['file']['tmp_name']);
        $ext     = strtolower(pathinfo((string)$_FILES['file']['name'], PATHINFO_EXTENSION));
        if ($ext === 'csv' || $ext === 'txt') {
            $parsed = QuestionImporter::parseCsv($content);
        } else {
            echo json_encode(['success' => false, 'message' => 'Pour Excel (.xlsx), utilisez l\'import depuis le tableau de bord (conversion automatique).']);
            exit;
        }
    } elseif (!empty($_POST['rows_json'])) {
        $rows = json_decode((string)$_POST['rows_json'], true);
        if (!is_array($rows)) {
            echo json_encode(['success' => false, 'message' => 'Données JSON invalides.']);
            exit;
        }
        $parsed = QuestionImporter::parseJsonRows($rows);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aucun fichier fourni.']);
        exit;
    }

    if (empty($parsed['questions'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Aucune question valide trouvée.',
            'errors'  => $parsed['errors'],
        ]);
        exit;
    }

    if ($type === 'lesson') {
        $count = QuestionImporter::importLessonQuestions($pdo, $lessonId, $parsed['questions']);
    } elseif ($type === 'live') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $count = QuestionImporter::importLiveQuestions($pdo, $sessionId, $parsed['questions']);
    } else {
        $count = QuestionImporter::importCourseQuestions($pdo, $courseId, $parsed['questions']);
    }

    if ($type === 'lesson' && $count > 0) {
        require_once __DIR__ . '/../lib/LessonProgressionHelper.php';
        LessonProgressionHelper::handleLessonUpdate($pdo, $lessonId);
    }

    auditLog('questions_imported', "{$type}: {$count} questions, course #{$courseId}");

    echo json_encode([
        'success'  => true,
        'imported' => $count,
        'errors'   => $parsed['errors'],
        'message'  => "{$count} question(s) importée(s) avec succès.",
    ]);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'import-questions.php');
}
