<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$courseId = (int)($_GET['course_id'] ?? 0);
if ($courseId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Identifiant du cours manquant.']);
    exit;
}

try {
    $pdo = Database::getInstance();
    $teacherId = (int)$_SESSION['user_id'];

    // Vérifier que le cours appartient bien à cet enseignant
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = :id AND teacher_id = :tid");
    $stmt->execute(['id' => $courseId, 'tid' => $teacherId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cours non autorisé.']);
        exit;
    }

    // Récupérer toutes les sessions de téléévaluation pour ce cours
    $stmt = $pdo->prepare("
        SELECT id, title, start_time, end_time, default_time_limit, status, session_code 
        FROM live_eval_sessions 
        WHERE course_id = :cid AND teacher_id = :tid
    ");
    $stmt->execute(['cid' => $courseId, 'tid' => $teacherId]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];

    $now = time();

    foreach ($sessions as $session) {
        $sid = (int)$session['id'];

        // 1. Nombre total d'inscrits
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_registrations WHERE session_id = :sid");
        $countStmt->execute(['sid' => $sid]);
        $participantCount = (int)$countStmt->fetchColumn();

        // 1b. Nombre d'utilisateurs connectés (dernières 10 secondes d'activité)
        $onlineStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM live_eval_registrations 
            WHERE session_id = :sid AND last_activity >= NOW() - INTERVAL 10 SECOND
        ");
        $onlineStmt->execute(['sid' => $sid]);
        $onlineCount = (int)$onlineStmt->fetchColumn();

        // 2. Récupérer les questions pour déterminer la question en cours
        $qStmt = $pdo->prepare("SELECT id, time_limit FROM live_eval_questions WHERE session_id = :sid ORDER BY sort_order ASC, id ASC");
        $qStmt->execute(['sid' => $sid]);
        $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalQuestions = count($questions);
        $activeQuestionIndex = -1;
        $activeQuestionId = null;
        $answersReceived = 0;
        $status = 'waiting'; // waiting, active, finished

        if ($totalQuestions > 0 && (int)$session['status'] === 1) {
            $startTime = strtotime($session['start_time']);
            
            // Calculer la durée totale
            $totalDuration = 0;
            foreach ($questions as $q) {
                $limit = $q['time_limit'] !== null ? (int)$q['time_limit'] : (int)$session['default_time_limit'];
                $totalDuration += $limit;
            }

            if ($now >= ($startTime + $totalDuration)) {
                $status = 'finished';
            } elseif ($now >= $startTime) {
                $status = 'active';
                $currentTime = $startTime;
                
                foreach ($questions as $idx => $q) {
                    $limit = $q['time_limit'] !== null ? (int)$q['time_limit'] : (int)$session['default_time_limit'];
                    $qStart = $currentTime;
                    $qEnd = $currentTime + $limit;

                    if ($now >= $qStart && $now < $qEnd) {
                        $activeQuestionIndex = $idx;
                        $activeQuestionId = (int)$q['id'];
                        break;
                    }
                    $currentTime = $qEnd;
                }

                // Compter les réponses reçues pour la question active
                if ($activeQuestionId !== null) {
                    $ansStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_answers WHERE question_id = :qid");
                    $ansStmt->execute(['qid' => $activeQuestionId]);
                    $answersReceived = (int)$ansStmt->fetchColumn();
                }
            }
        }

        $results[] = [
            'id'                     => $sid,
            'title'                  => $session['title'],
            'session_code'           => $session['session_code'],
            'participant_count'      => $participantCount,
            'online_count'           => $onlineCount,
            'status'                 => $status,
            'total_questions'        => $totalQuestions,
            'active_question_index'  => $activeQuestionIndex,
            'active_question_votes'  => $answersReceived
        ];
    }

    echo json_encode([
        'success'  => true,
        'sessions' => $results
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
