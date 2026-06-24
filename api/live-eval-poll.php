<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$code   = trim((string)($_GET['code'] ?? $_POST['code'] ?? ''));

if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'Code de session manquant.']);
    exit;
}

try {
    $pdo = Database::getInstance();

    // Récupérer la session de téléévaluation
    $stmt = $pdo->prepare("SELECT * FROM live_eval_sessions WHERE session_code = :code");
    $stmt->execute(['code' => $code]);
    $session = $stmt->fetch();

    if (!$session) {
        echo json_encode(['success' => false, 'message' => 'Séance introuvable.']);
        exit;
    }

    if ((int)$session['status'] === 0) {
        echo json_encode(['success' => false, 'message' => 'Cette séance est actuellement désactivée par l\'enseignant.', 'status' => 'inactive']);
        exit;
    }

    $now = time();
    $startTime = strtotime($session['start_time']);

    // ── ACTION 1 : Poll Lobby (Salle d'attente) ──────────────────────
    if ($action === 'poll_lobby') {
        // Compter les personnes inscrites à cette session
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_registrations WHERE session_id = :sid");
        $countStmt->execute(['sid' => $session['id']]);
        $registeredCount = (int)$countStmt->fetchColumn();

        $secondsToStart = $startTime - $now;

        echo json_encode([
            'success'          => true,
            'status'           => $secondsToStart > 0 ? 'waiting' : 'active',
            'registered_count' => $registeredCount,
            'seconds_to_start' => max(0, $secondsToStart),
            'start_time'       => $session['start_time'],
        ]);
        exit;
    }

    // Pour les autres actions, il faut être enregistré (avoir un registration_id)
    $regId = (int)($_SESSION['live_registrations'][$code] ?? 0);
    if ($regId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas inscrit à cette session.', 'not_registered' => true]);
        exit;
    }

    // Vérifier que l'inscription correspond
    $regStmt = $pdo->prepare("SELECT * FROM live_eval_registrations WHERE id = :id AND session_id = :sid");
    $regStmt->execute(['id' => $regId, 'sid' => $session['id']]);
    $registration = $regStmt->fetch();
    if (!$registration) {
        echo json_encode(['success' => false, 'message' => 'Inscription invalide.', 'not_registered' => true]);
        exit;
    }

    // Récupérer le planning dynamique des questions
    $schedule = getActiveQuestionInfo($pdo, $session, $now);
    if (!$schedule) {
        echo json_encode(['success' => false, 'message' => 'Aucune question configurée pour cette évaluation.']);
        exit;
    }

    // ── ACTION 2 : Poll Quiz (État en direct du QCM) ──────────────────
    if ($action === 'poll_quiz') {
        // Si l'évaluation est finie
        if ($schedule['is_finished']) {
            // Traiter la notation et l'envoi de l'e-mail si ce n'est pas déjà fait
            if ($registration['score'] === null) {
                calculateAndEmailScore($pdo, $session, $registration, $schedule['questions']);
            }
            // Compter le total des personnes inscrites
            $totalRegStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_registrations WHERE session_id = :sid");
            $totalRegStmt->execute(['sid' => $session['id']]);
            $totalRegistered = (int)$totalRegStmt->fetchColumn();

            echo json_encode([
                'success'          => true,
                'status'           => 'finished',
                'is_finished'      => true,
                'total_registered' => $totalRegistered
            ]);
            exit;
        }

        $activeQ = $schedule['active_question'];
        if (!$activeQ) {
            // Dans une transition ou avant le début
            echo json_encode([
                'success'     => true,
                'status'      => 'waiting',
                'is_finished' => false
            ]);
            exit;
        }

        // Compter combien d'inscrits ont répondu à la question active
        $ansCountStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_answers WHERE question_id = :qid");
        $ansCountStmt->execute(['qid' => $activeQ['id']]);
        $answersReceived = (int)$ansCountStmt->fetchColumn();

        // Compter le total des personnes inscrites
        $totalRegStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_registrations WHERE session_id = :sid");
        $totalRegStmt->execute(['sid' => $session['id']]);
        $totalRegistered = (int)$totalRegStmt->fetchColumn();

        // Vérifier si l'étudiant courant a déjà répondu
        $alreadyAnsweredStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_answers WHERE registration_id = :rid AND question_id = :qid");
        $alreadyAnsweredStmt->execute(['rid' => $regId, 'qid' => $activeQ['id']]);
        $alreadyAnswered = (int)$alreadyAnsweredStmt->fetchColumn() > 0;

        echo json_encode([
            'success' => true,
            'status'  => 'active',
            'current_question_index' => $schedule['active_index'],
            'total_questions'        => count($schedule['questions']),
            'question' => [
                'id'            => $activeQ['id'],
                'question_text' => $activeQ['question_text'],
                'option_a'      => $activeQ['option_a'],
                'option_b'      => $activeQ['option_b'],
                'option_c'      => $activeQ['option_c'],
                'option_d'      => $activeQ['option_d'],
                'image_path'    => $activeQ['image_path'] ? '/uploads/live_questions/' . $activeQ['image_path'] : null,
                'seconds_left'  => $schedule['seconds_left'],
            ],
            'answers_received' => $answersReceived,
            'total_registered' => $totalRegistered,
            'already_answered' => $alreadyAnswered,
            'is_finished'      => false,
        ]);
        exit;
    }

    // ── ACTION 3 : Submit Answer (Soumission de réponse) ─────────────
    if ($action === 'submit_answer') {
        $questionId     = (int)($_POST['question_id'] ?? 0);
        $selectedOption = strtoupper(trim((string)($_POST['selected_option'] ?? '')));

        if ($questionId <= 0 || !in_array($selectedOption, ['A', 'B', 'C', 'D'], true)) {
            echo json_encode(['success' => false, 'message' => 'Données de réponse invalides.']);
            exit;
        }

        // Vérifier que la question soumise est bien la question active en cours
        $activeQ = $schedule['active_question'];
        if (!$activeQ || (int)$activeQ['id'] !== $questionId) {
            echo json_encode(['success' => false, 'message' => 'Le temps imparti pour cette question est écoulé.']);
            exit;
        }

        // Insérer ou mettre à jour la réponse du participant
        $insertStmt = $pdo->prepare("
            INSERT INTO live_eval_answers (registration_id, question_id, selected_option)
            VALUES (:rid, :qid, :sel)
            ON DUPLICATE KEY UPDATE selected_option = :sel2, answered_at = CURRENT_TIMESTAMP
        ");
        $insertStmt->execute([
            'rid'  => $regId,
            'qid'  => $questionId,
            'sel'  => $selectedOption,
            'sel2' => $selectedOption,
        ]);

        echo json_encode(['success' => true, 'message' => 'Réponse enregistrée.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action inconnue.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur : ' . $e->getMessage()]);
}

/**
 * Calcule la question actuellement active par rapport au temps écoulé
 */
function getActiveQuestionInfo(PDO $pdo, array $session, int $nowTime): ?array
{
    $stmt = $pdo->prepare("
        SELECT * FROM live_eval_questions
        WHERE session_id = :sid
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute(['sid' => $session['id']]);
    $questions = $stmt->fetchAll();

    if (empty($questions)) {
        return null;
    }

    $startTime = strtotime($session['start_time']);

    // Calculate total duration of all questions first
    $totalDuration = 0;
    foreach ($questions as $q) {
        $limit = $q['time_limit'] !== null ? (int)$q['time_limit'] : (int)$session['default_time_limit'];
        $totalDuration += $limit;
    }

    $isFinished = $nowTime >= ($startTime + $totalDuration);

    $currentTime = $startTime;
    $activeQuestion = null;
    $activeIndex = -1;
    $secondsLeft = 0;

    if (!$isFinished) {
        foreach ($questions as $idx => $q) {
            $limit = $q['time_limit'] !== null ? (int)$q['time_limit'] : (int)$session['default_time_limit'];
            $qStart = $currentTime;
            $qEnd = $currentTime + $limit;

            if ($nowTime >= $qStart && $nowTime < $qEnd) {
                $activeQuestion = $q;
                $activeIndex = $idx;
                $secondsLeft = $qEnd - $nowTime;
                break;
            }

            $currentTime = $qEnd;
        }
    }

    return [
        'questions'       => $questions,
        'active_question' => $activeQuestion,
        'active_index'    => $activeIndex,
        'seconds_left'    => $secondsLeft,
        'is_finished'     => $isFinished,
        'end_time'        => $startTime + $totalDuration,
    ];
}

/**
 * Calcule le score final de l'utilisateur et envoie ses résultats par e-mail
 */
function calculateAndEmailScore(PDO $pdo, array $session, array $registration, array $questions): void
{
    $regId = (int)$registration['id'];
    
    // Récupérer les réponses soumises
    $stmt = $pdo->prepare("
        SELECT question_id, selected_option FROM live_eval_answers
        WHERE registration_id = :rid
    ");
    $stmt->execute(['rid' => $regId]);
    $submittedAnswers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [question_id => selected_option]

    $correctCount = 0;
    $totalQuestions = count($questions);
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

    $scorePercent = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 100.00;

    // Enregistrer le score final en base de données
    $updateStmt = $pdo->prepare("UPDATE live_eval_registrations SET score = :score WHERE id = :id");
    $updateStmt->execute(['score' => $scorePercent, 'id' => $regId]);

    // Envoyer l'email
    try {
        require_once __DIR__ . '/../Mailer.php';
        Mailer::sendLiveEvalResults($registration['email'], $registration['name'], $session['title'], (float)$scorePercent, $qasDetails);
    } catch (Exception $e) {
        // Ignorer l'erreur d'envoi d'e-mail pour ne pas bloquer
    }
}
