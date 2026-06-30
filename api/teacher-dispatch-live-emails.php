<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Mailer.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$sessionId = (int)($_REQUEST['session_id'] ?? 0);
if ($sessionId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Identifiant de séance manquant.']);
    exit;
}

try {
    $pdo = Database::getInstance();
    $teacherId = (int)$_SESSION['user_id'];

    // Vérifier l'accès à la session
    $stmt = $pdo->prepare("
        SELECT s.*, c.title AS course_title
        FROM live_eval_sessions s
        JOIN courses c ON s.course_id = c.id
        WHERE s.id = :sid AND s.teacher_id = :tid
    ");
    $stmt->execute(['sid' => $sessionId, 'tid' => $teacherId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        echo json_encode(['success' => false, 'message' => 'Séance introuvable ou non autorisée.']);
        exit;
    }

    // Récupérer les questions
    $stmt = $pdo->prepare("SELECT * FROM live_eval_questions WHERE session_id = :sid ORDER BY sort_order ASC, id ASC");
    $stmt->execute(['sid' => $sessionId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalQuestions = count($questions);

    // Récupérer les inscrits
    $stmt = $pdo->prepare("
        SELECT r.id, r.name, r.email, r.score,
               (SELECT COUNT(*) FROM live_eval_answers WHERE registration_id = r.id) AS answered_count
        FROM live_eval_registrations r
        WHERE r.session_id = :sid
        ORDER BY r.name ASC
    ");
    $stmt->execute(['sid' => $sessionId]);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $action = (string)($_POST['action'] ?? $_GET['action'] ?? 'get_draft');

    if ($action === 'get_draft') {
        $drafts = [];
        foreach ($registrations as $r) {
            // Récupérer les réponses soumises
            $ansStmt = $pdo->prepare("
                SELECT question_id, selected_option FROM live_eval_answers
                WHERE registration_id = :rid
            ");
            $ansStmt->execute(['rid' => $r['id']]);
            $submittedAnswers = $ansStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $correctCount = 0;
            foreach ($questions as $q) {
                $selected = $submittedAnswers[$q['id']] ?? '';
                if ($selected === $q['correct_option']) {
                    $correctCount++;
                }
            }

            $drafts[] = [
                'id' => $r['id'],
                'name' => $r['name'],
                'email' => $r['email'],
                'correct_count' => $correctCount,
                'total_questions' => $totalQuestions,
                'answered_count' => (int)$r['answered_count'],
            ];
        }

        echo json_encode([
            'success' => true,
            'session_title' => $session['title'],
            'total_questions' => $totalQuestions,
            'drafts' => $drafts
        ]);
        exit;
    }

    if ($action === 'dispatch') {
        $rawIds = $_POST['registration_ids'] ?? '[]';
        $targetIds = json_decode((string)$rawIds, true);
        if (!is_array($targetIds)) {
            $targetIds = [];
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($registrations as $r) {
            // Seulement traiter les IDs demandés
            if (!in_array($r['id'], $targetIds)) {
                continue;
            }

            // Récupérer les réponses soumises
            $ansStmt = $pdo->prepare("
                SELECT question_id, selected_option FROM live_eval_answers
                WHERE registration_id = :rid
            ");
            $ansStmt->execute(['rid' => $r['id']]);
            $submittedAnswers = $ansStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $correctCount = 0;
            $qasDetails = [];
            foreach ($questions as $q) {
                $selected = $submittedAnswers[$q['id']] ?? '';
                $isCorrect = $selected === $q['correct_option'];
                if ($isCorrect) {
                    $correctCount++;
                }
                $qasDetails[] = [
                    'question_text'  => $q['question_text'],
                    'option_a'       => $q['option_a'],
                    'option_b'       => $q['option_b'],
                    'option_c'       => $q['option_c'],
                    'option_d'       => $q['option_d'],
                    'correct_option' => $q['correct_option'],
                    'selected_option'=> $selected,
                ];
            }

            // Mettre à jour le score final dans la DB
            $scorePercent = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 100.00;
            $updateStmt = $pdo->prepare("UPDATE live_eval_registrations SET score = :score WHERE id = :id");
            $updateStmt->execute(['score' => $scorePercent, 'id' => $r['id']]);

            // Envoyer l'email
            $sent = Mailer::sendLiveEvalResults($r['email'], $r['name'], $session['title'], $correctCount, $totalQuestions, $qasDetails, (int)$r['id']);
            if ($sent) {
                $successCount++;
            } else {
                $failCount++;
                $lastError = Mailer::getLastError() ?? 'Erreur inconnue';
            }
        }

        echo json_encode([
            'success' => true,
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'last_error' => $lastError ?? null,
            'message' => "E-mails envoyés avec succès à {$successCount} étudiant(s)." . ($failCount > 0 ? " Échec pour {$failCount} étudiant(s)." : "")
        ]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
