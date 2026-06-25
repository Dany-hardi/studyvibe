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

/**
 * Récupère les métadonnées de la session et de ses questions de manière optimisée via un cache local.
 */
function getCachedSessionData(PDO $pdo, string $code): ?array
{
    $cacheDir = __DIR__ . '/../uploads/live_cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $cacheFile = $cacheDir . '/session_' . md5($code) . '.json';
    
    // Le cache est valide pendant 2 secondes (permet de limiter les accès DB pour 100 étudiants simultanés)
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 2) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if ($cached) {
            return $cached;
        }
    }
    
    // Requête principale
    $stmt = $pdo->prepare("SELECT * FROM live_eval_sessions WHERE session_code = :code");
    $stmt->execute(['code' => $code]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM live_eval_questions WHERE session_id = :sid ORDER BY sort_order ASC, id ASC");
    $stmt->execute(['sid' => $session['id']]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [
        'session' => $session,
        'questions' => $questions
    ];
    
    @file_put_contents($cacheFile, json_encode($data));
    return $data;
}

try {
    $pdo = Database::getInstance();

    // Récupérer la session via le cache
    $cachedData = getCachedSessionData($pdo, $code);
    if (!$cachedData) {
        echo json_encode(['success' => false, 'message' => 'Séance introuvable.']);
        exit;
    }

    $session = $cachedData['session'];
    $questions = $cachedData['questions'];

    $isAsync = isset($session['is_async']) && (int)$session['is_async'] === 1;
    $asyncDeadlinePassed = false;
    if ($isAsync && !empty($session['async_deadline'])) {
        $asyncDeadlinePassed = (time() > strtotime($session['async_deadline']));
    }

    if ((int)$session['status'] === 0) {
        echo json_encode(['success' => false, 'message' => 'Cette séance est actuellement désactivée par l\'enseignant.', 'status' => 'inactive']);
        exit;
    }

    if ($isAsync && $asyncDeadlinePassed) {
        echo json_encode(['success' => false, 'message' => 'La date limite de cette évaluation asynchrone est dépassée.', 'status' => 'inactive']);
        exit;
    }

    $now = time();
    $startTime = strtotime($session['start_time']);

    // ── ACTION 1 : Poll Lobby (Salle d'attente) ──────────────────────
    if ($action === 'poll_lobby') {
        // Mettre en cache le nombre d'inscrits pendant 2 secondes pour éviter d'inonder la base de données
        $lobbyCacheFile = __DIR__ . '/../uploads/live_cache/lobby_count_' . $session['id'] . '.json';
        $registeredCount = 0;
        
        if (file_exists($lobbyCacheFile) && (time() - filemtime($lobbyCacheFile)) < 2) {
            $registeredCount = (int)@file_get_contents($lobbyCacheFile);
        } else {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_registrations WHERE session_id = :sid");
            $countStmt->execute(['sid' => $session['id']]);
            $registeredCount = (int)$countStmt->fetchColumn();
            @file_put_contents($lobbyCacheFile, (string)$registeredCount);
        }

        $secondsToStart = $startTime - $now;
        $status = $secondsToStart > 0 ? 'waiting' : 'active';
        if ($isAsync && !$asyncDeadlinePassed) {
            $status = 'active';
            $secondsToStart = 0;
        }

        echo json_encode([
            'success'          => true,
            'status'           => $status,
            'registered_count' => $registeredCount,
            'seconds_to_start' => max(0, $secondsToStart),
            'start_time'       => $session['start_time'],
        ]);
        exit;
    }

    // Pour les autres actions, il faut être enregistré
    $regId = (int)($_SESSION['live_registrations'][$code] ?? 0);
    if ($regId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas inscrit à cette session.', 'not_registered' => true]);
        exit;
    }

    // Toujours vérifier en base de données pour détecter instantanément la réinitialisation ou l'exclusion
    $regStmt = $pdo->prepare("SELECT * FROM live_eval_registrations WHERE id = :id AND session_id = :sid");
    $regStmt->execute(['id' => $regId, 'sid' => $session['id']]);
    $registration = $regStmt->fetch(PDO::FETCH_ASSOC);
    if (!$registration) {
        unset($_SESSION['live_registrations'][$code]);
        unset($_SESSION['verified_registrations'][$code]);
        echo json_encode(['success' => false, 'message' => 'Votre inscription a été réinitialisée ou annulée par l\'enseignant.', 'not_registered' => true]);
        exit;
    }
    $_SESSION['verified_registrations'][$code] = $registration;

    // Mettre à jour la date de dernière activité du participant pour le suivi "En ligne"
    try {
        $updateActStmt = $pdo->prepare("UPDATE live_eval_registrations SET last_activity = NOW() WHERE id = :id");
        $updateActStmt->execute(['id' => $regId]);
    } catch (PDOException $e) {
        // Silencieusement ignorer
    }

    // Calculer le temps virtuel ajusté en cas de pause (uniquement pour les sessions synchronisées)
    $pauseDuration = (int)($session['pause_duration'] ?? 0);
    $isPaused = isset($session['is_paused']) && (int)$session['is_paused'] === 1;

    if ($isPaused && !empty($session['paused_at'])) {
        $virtualNow = strtotime($session['paused_at']) - $pauseDuration;
    } else {
        $virtualNow = time() - $pauseDuration;
    }

    // Déterminer le planning dynamique de la question active
    if (empty($questions)) {
        echo json_encode(['success' => false, 'message' => 'Aucune question configurée pour cette évaluation.']);
        exit;
    }

    // Calculer la durée totale
    $totalDuration = 0;
    foreach ($questions as $q) {
        $limit = $q['time_limit'] !== null ? (int)$q['time_limit'] : (int)$session['default_time_limit'];
        $totalDuration += $limit;
    }

    $isFinished = $virtualNow >= ($startTime + $totalDuration);

    $currentTime = $startTime;
    $activeQ = null;
    $activeIndex = -1;
    $secondsLeft = 0;

    if ($isAsync) {
        // Compter les réponses déjà données par cet étudiant
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_answers WHERE registration_id = :rid");
        $stmt->execute(['rid' => $regId]);
        $submittedCount = (int)$stmt->fetchColumn();

        if ($submittedCount >= count($questions)) {
            $isFinished = true;
            $activeQ = null;
            $activeIndex = -1;
            $secondsLeft = 0;
        } else {
            $isFinished = false;
            $activeIndex = $submittedCount;
            $activeQ = $questions[$activeIndex];
            
            $limit = $activeQ['time_limit'] !== null ? (int)$activeQ['time_limit'] : (int)$session['default_time_limit'];
            
            if (!isset($_SESSION['async_q_start'][$session['id']][$activeQ['id']])) {
                $_SESSION['async_q_start'][$session['id']][$activeQ['id']] = $now;
            }
            
            $startTimeQ = $_SESSION['async_q_start'][$session['id']][$activeQ['id']];
            $elapsed = $now - $startTimeQ;
            $secondsLeft = max(0, $limit - $elapsed);
            
            if ($secondsLeft <= 0) {
                // Soumettre automatiquement une réponse vide pour avancer
                try {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO live_eval_answers (registration_id, question_id, selected_option)
                        VALUES (:rid, :qid, '')
                        ON DUPLICATE KEY UPDATE selected_option = '', answered_at = CURRENT_TIMESTAMP
                    ");
                    $insertStmt->execute([
                        'rid' => $regId,
                        'qid' => $activeQ['id']
                    ]);
                    $_SESSION['answered_questions'][$activeQ['id']] = true;
                } catch (PDOException $ex) {}
                
                $submittedCount++;
                if ($submittedCount >= count($questions)) {
                    $isFinished = true;
                    $activeQ = null;
                    $activeIndex = -1;
                    $secondsLeft = 0;
                } else {
                    $activeIndex = $submittedCount;
                    $activeQ = $questions[$activeIndex];
                    $limit = $activeQ['time_limit'] !== null ? (int)$activeQ['time_limit'] : (int)$session['default_time_limit'];
                    $_SESSION['async_q_start'][$session['id']][$activeQ['id']] = $now;
                    $secondsLeft = $limit;
                }
            }
        }
    } else {
        if (!$isFinished) {
            foreach ($questions as $idx => $q) {
                $limit = $q['time_limit'] !== null ? (int)$q['time_limit'] : (int)$session['default_time_limit'];
                $qStart = $currentTime;
                $qEnd = $currentTime + $limit;

                if ($virtualNow >= $qStart && $virtualNow < $qEnd) {
                    $activeQ = $q;
                    $activeIndex = $idx;
                    $secondsLeft = $qEnd - $virtualNow;
                    break;
                }
                $currentTime = $qEnd;
            }
        }
    }

    // ── ACTION 2 : Poll Quiz (État en direct du QCM) ──────────────────
    if ($action === 'poll_quiz') {
        if ($isFinished) {
            // Traiter la notation si ce n'est pas déjà fait
            if ($registration['score'] === null) {
                // Charger le score récent de la session PHP pour éviter des calculs doublons
                $scorePercent = calculateAndSaveScore($pdo, $session, $registration, $questions);
                $registration['score'] = $scorePercent;
                $_SESSION['verified_registrations'][$code]['score'] = $scorePercent;
            }
            
            // Total inscrits
            $regCountCacheFile = __DIR__ . '/../uploads/live_cache/lobby_count_' . $session['id'] . '.json';
            $totalRegistered = 0;
            if (file_exists($regCountCacheFile) && (time() - filemtime($regCountCacheFile)) < 5) {
                $totalRegistered = (int)@file_get_contents($regCountCacheFile);
            } else {
                $totalRegStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_registrations WHERE session_id = :sid");
                $totalRegStmt->execute(['sid' => $session['id']]);
                $totalRegistered = (int)$totalRegStmt->fetchColumn();
                @file_put_contents($regCountCacheFile, (string)$totalRegistered);
            }

            echo json_encode([
                'success'          => true,
                'status'           => 'finished',
                'is_finished'      => true,
                'total_registered' => $totalRegistered
            ]);
            exit;
        }

        if (!$activeQ) {
            // Dans une transition ou avant le début
            echo json_encode([
                'success'     => true,
                'status'      => 'waiting',
                'is_finished' => false
            ]);
            exit;
        }

        // Total inscrits
        $regCountCacheFile = __DIR__ . '/../uploads/live_cache/lobby_count_' . $session['id'] . '.json';
        $totalRegistered = 0;
        if (file_exists($regCountCacheFile) && (time() - filemtime($regCountCacheFile)) < 3) {
            $totalRegistered = (int)@file_get_contents($regCountCacheFile);
        } else {
            $totalRegStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_registrations WHERE session_id = :sid");
            $totalRegStmt->execute(['sid' => $session['id']]);
            $totalRegistered = (int)$totalRegStmt->fetchColumn();
            @file_put_contents($regCountCacheFile, (string)$totalRegistered);
        }

        // Vérifier si l'étudiant courant a déjà répondu (utilisant le cache de session)
        $alreadyAnswered = false;
        $qid = (int)$activeQ['id'];
        if (isset($_SESSION['answered_questions'][$qid])) {
            $alreadyAnswered = true;
        } else {
            $alreadyAnsweredStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_answers WHERE registration_id = :rid AND question_id = :qid");
            $alreadyAnsweredStmt->execute(['rid' => $regId, 'qid' => $qid]);
            $alreadyAnswered = (int)$alreadyAnsweredStmt->fetchColumn() > 0;
            if ($alreadyAnswered) {
                $_SESSION['answered_questions'][$qid] = true;
            }
        }

        // Compter les réponses reçues pour la question active (avec cache de 1 seconde pour soulager MySQL)
        $answersCacheFile = __DIR__ . '/../uploads/live_cache/answers_count_' . $qid . '.json';
        $answersReceived = 0;
        if (file_exists($answersCacheFile) && (time() - filemtime($answersCacheFile)) < 1) {
            $answersReceived = (int)@file_get_contents($answersCacheFile);
        } else {
            $answersStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_answers WHERE question_id = :qid");
            $answersStmt->execute(['qid' => $qid]);
            $answersReceived = (int)$answersStmt->fetchColumn();
            @file_put_contents($answersCacheFile, (string)$answersReceived);
        }

        echo json_encode([
            'success' => true,
            'status'  => 'active',
            'current_question_index' => $activeIndex,
            'total_questions'        => count($questions),
            'question' => [
                'id'            => $qid,
                'question_text' => $activeQ['question_text'],
                'option_a'      => $activeQ['option_a'],
                'option_b'      => $activeQ['option_b'],
                'option_c'      => $activeQ['option_c'],
                'option_d'      => $activeQ['option_d'],
                'image_path'    => $activeQ['image_path'] ? '/uploads/live_questions/' . $activeQ['image_path'] : null,
                'seconds_left'  => $secondsLeft,
            ],
            'answers_received' => $answersReceived,
            'total_registered' => $totalRegistered,
            'already_answered' => $alreadyAnswered,
            'is_finished'      => false,
            'is_paused'        => $isPaused,
        ]);
        exit;
    }

    // ── ACTION 3 : Submit Answer (Soumission de réponse) ─────────────
    if ($action === 'submit_answer') {
        if ($isPaused) {
            echo json_encode(['success' => false, 'message' => 'L\'évaluation est en pause par l\'enseignant.']);
            exit;
        }

        $questionId     = (int)($_POST['question_id'] ?? 0);
        $selectedOption = strtoupper(trim((string)($_POST['selected_option'] ?? '')));

        if ($questionId <= 0 || !in_array($selectedOption, ['', 'A', 'B', 'C', 'D'], true)) {
            echo json_encode(['success' => false, 'message' => 'Données de réponse invalides.']);
            exit;
        }

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

        // Mettre en cache la soumission dans la session de l'étudiant
        $_SESSION['answered_questions'][$questionId] = true;

        echo json_encode(['success' => true, 'message' => 'Réponse enregistrée.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action inconnue.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur : ' . $e->getMessage()]);
}

/**
 * Calcule le score final de l'utilisateur et envoie ses résultats par e-mail
 */
function calculateAndSaveScore(PDO $pdo, array $session, array $registration, array $questions): float
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

    // Envoyer les résultats par e-mail immédiatement au participant (pour les modes asynchrone et synchrone)
    require_once __DIR__ . '/../Mailer.php';
    @Mailer::sendLiveEvalResults(
        $registration['email'],
        $registration['name'],
        $session['title'],
        $correctCount,
        $totalQuestions,
        $qasDetails
    );

    return (float)$scorePercent;
}
