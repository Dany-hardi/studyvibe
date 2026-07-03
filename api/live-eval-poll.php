<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Live Evaluation Polling API Endpoint
 * 
 * Supports real-time state synchronization for student tele-evaluations.
 * Implements a high-performance local file-caching system (2-second validation windows) 
 * to mitigate database bottlenecks during simultaneous queries from multiple students.
 * 
 * Handled Operations:
 * 1. `poll_lobby`   - Synchronizes lobby connection counts, connected participants, and countdown status.
 * 2. `poll_quiz`    - Fetches the active question details, remaining seconds, pause status, or final leaderboard.
 * 3. `submit_answer`- Records and updates student responses (MCQs vs open written text).
 * 
 * @package    StudyVibe
 * @subpackage API
 * @author     Advanced Engineering Team
 */

require_once __DIR__ . '/../Database.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$code   = trim((string)($_GET['code'] ?? $_POST['code'] ?? ''));

// Stop request if session code parameter is missing
if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'Code de session manquant.']);
    exit;
}

// =========================================================================
// CACHE MECHANISM: LOAD AND WRITE TEMPORARY SYSTEM RECORDS
// =========================================================================

/**
 * Loads session metadata and associated questions via local JSON caches to reduce DB load.
 * Cache is validated for 2 seconds to support multiple concurrent users.
 * 
 * @param PDO    $pdo  Database connection instance.
 * @param string $code Evaluation room access code.
 * @return array|null Session and question data array, or null if session not found.
 */
function getCachedSessionData(PDO $pdo, string $code): ?array
{
    $cacheDir = __DIR__ . '/../uploads/live_cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $cacheFile = $cacheDir . '/session_' . md5($code) . '.json';
    
    // Validate if cache exists and is newer than 2 seconds
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 2) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if ($cached) {
            return $cached;
        }
    }
    
    // Fetch live session details
    $stmt = $pdo->prepare("SELECT * FROM live_eval_sessions WHERE session_code = :code");
    $stmt->execute(['code' => $code]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        return null;
    }
    
    // Fetch related questions in correct sorted order
    $stmt = $pdo->prepare("SELECT * FROM live_eval_questions WHERE session_id = :sid ORDER BY sort_order ASC, id ASC");
    $stmt->execute(['sid' => $session['id']]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [
        'session' => $session,
        'questions' => $questions
    ];
    
    // Save generated array to local cache
    @file_put_contents($cacheFile, json_encode($data));
    return $data;
}

// =========================================================================
// MAIN PROCESSING CONTROL
// =========================================================================

try {
    $pdo = Database::getInstance();

    // Load active session using caching function
    $cachedData = getCachedSessionData($pdo, $code);
    if (!$cachedData) {
        echo json_encode(['success' => false, 'message' => 'Séance d\'évaluation introuvable.']);
        exit;
    }

    $session = $cachedData['session'];
    $questions = $cachedData['questions'];

    // Check asynchrone evaluation dates and status
    $isAsync = isset($session['is_async']) && (int)$session['is_async'] === 1;
    $asyncDeadlinePassed = false;
    if ($isAsync && !empty($session['async_deadline'])) {
        $asyncDeadlinePassed = (time() > strtotime($session['async_deadline']));
    }

    // Gating check: Active session status check
    if ((int)$session['status'] === 0) {
        echo json_encode(['success' => false, 'message' => 'Cette séance est actuellement désactivée par l\'enseignant.', 'status' => 'inactive']);
        exit;
    }

    // Gating check: Asynchrone deadline enforcement
    if ($isAsync && $asyncDeadlinePassed) {
        echo json_encode(['success' => false, 'message' => 'La date limite de cette évaluation asynchrone est dépassée.', 'status' => 'inactive']);
        exit;
    }

    $now = time();
    $startTime = strtotime($session['start_time']);

    // =========================================================================
    // ACTION 1: LOBBY STATUS UPDATES (POLL LOBBY)
    // =========================================================================
    if ($action === 'poll_lobby') {
        // Cache the registration count for 2 seconds to reduce DB pressure
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

        // Count connected active participants (activity in the last 10 seconds)
        $onlineCount = 0;
        $onlineCacheFile = __DIR__ . '/../uploads/live_cache/online_count_' . $session['id'] . '.json';
        if (file_exists($onlineCacheFile) && (time() - filemtime($onlineCacheFile)) < 2) {
            $onlineCount = (int)@file_get_contents($onlineCacheFile);
        } else {
            $onlineStmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM live_eval_registrations 
                WHERE session_id = :sid AND last_activity >= NOW() - INTERVAL 10 SECOND
            ");
            $onlineStmt->execute(['sid' => $session['id']]);
            $onlineCount = (int)$onlineStmt->fetchColumn();
            @file_put_contents($onlineCacheFile, (string)$onlineCount);
        }

        // Determine if evaluation room is active or waiting for start
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
            'online_count'     => $onlineCount,
            'seconds_to_start' => max(0, $secondsToStart),
            'start_time'       => $session['start_time'],
        ]);
        exit;
    }

    // =========================================================================
    // AUTHORIZATION VALIDATION FOR EVALUATION ACTIONS
    // =========================================================================
    $regId = (int)($_SESSION['live_registrations'][$code] ?? 0);
    if ($regId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas inscrit à cette session.', 'not_registered' => true]);
        exit;
    }

    // Validate registration directly in DB to capture reset / removal immediately
    $regStmt = $pdo->prepare("SELECT * FROM live_eval_registrations WHERE id = :id AND session_id = :sid");
    $regStmt->execute(['id' => $regId, 'sid' => $session['id']]);
    $registration = $regStmt->fetch(PDO::FETCH_ASSOC);
    if (!$registration) {
        echo json_encode(['success' => false, 'message' => 'Votre inscription a été réinitialisée ou annulée par l\'enseignant.', 'not_registered' => true]);
        exit;
    }
    $_SESSION['verified_registrations'][$code] = $registration;

    // Track active connection timestamp
    try {
        $updateActStmt = $pdo->prepare("UPDATE live_eval_registrations SET last_activity = NOW() WHERE id = :id");
        $updateActStmt->execute(['id' => $regId]);
    } catch (PDOException $e) {
        // Fail silently
    }

    // Calculate virtual session time offsets for pause states (synchronous mode only)
    $pauseDuration = (int)($session['pause_duration'] ?? 0);
    $isPaused = isset($session['is_paused']) && (int)$session['is_paused'] === 1;

    if ($isPaused && !empty($session['paused_at'])) {
        $virtualNow = strtotime($session['paused_at']) - $pauseDuration;
    } else {
        $virtualNow = time() - $pauseDuration;
    }

    if (empty($questions)) {
        echo json_encode(['success' => false, 'message' => 'Aucune question configurée pour cette évaluation.']);
        exit;
    }

    // Sum total evaluation duration
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

    // =========================================================================
    // QUEUE COMPUTATION: ASYNCHRONOUS WORKFLOWS
    // =========================================================================
    if ($isAsync) {
        // Find how many questions this user has answered
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
            
            // Auto-submit empty response if time limit is reached
            if ($secondsLeft <= 0) {
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
    } 
    // =========================================================================
    // QUEUE COMPUTATION: SYNCHRONOUS WORKFLOWS (REAL-TIME TIMELINE)
    // =========================================================================
    else {
        if (!$isFinished) {
            foreach ($questions as $idx => $q) {
                $limit = $q['time_limit'] !== null ? (int)$q['time_limit'] : (int)$session['default_time_limit'];
                $qStart = $currentTime;
                $qEnd = $currentTime + $limit;

                // Find if virtual time fits this question window
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

    // =========================================================================
    // ACTION 2: RETRIEVE ACTIVE STATE OR RESULTS (POLL QUIZ)
    // =========================================================================
    if ($action === 'poll_quiz') {
        // Return results and leaderboard if session has completed
        if ($isFinished) {
            if ($registration['score'] === null) {
                // Calculate score and trigger automatic results email
                $scorePercent = calculateAndSaveScore($pdo, $session, $registration, $questions);
                $registration['score'] = $scorePercent;
                $_SESSION['verified_registrations'][$code]['score'] = $scorePercent;
            }
            
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

            // Fetch Top-10 leaderboard data
            $leaderStmt = $pdo->prepare("
                SELECT name, score 
                FROM live_eval_registrations 
                WHERE session_id = :sid AND score IS NOT NULL 
                ORDER BY score DESC, name ASC 
                LIMIT 10
            ");
            $leaderStmt->execute(['sid' => $session['id']]);
            $leaderboard = $leaderStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'          => true,
                'status'           => 'finished',
                'is_finished'      => true,
                'total_registered' => $totalRegistered,
                'leaderboard'      => $leaderboard
            ]);
            exit;
        }

        // Return waiting status if active question window is empty
        if (!$activeQ) {
            echo json_encode([
                'success'     => true,
                'status'      => 'waiting',
                'is_finished' => false
            ]);
            exit;
        }

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

        // Check if student has already answered the active question
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

        // Count answers submitted for the active question
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
                'question_type' => $activeQ['question_type'] ?? 'mcq',
            ],
            'answers_received' => $answersReceived,
            'total_registered' => $totalRegistered,
            'already_answered' => $alreadyAnswered,
            'is_finished'      => false,
            'is_paused'        => $isPaused,
        ]);
        exit;
    }

    // =========================================================================
    // ACTION 3: RECORD STUDENT RESPONSE (SUBMIT ANSWER)
    // =========================================================================
    if ($action === 'submit_answer') {
        if ($isPaused) {
            echo json_encode(['success' => false, 'message' => 'L\'évaluation est en pause par l\'enseignant.']);
            exit;
        }

        $questionId = (int)($_POST['question_id'] ?? 0);
        
        $activeQForSubmission = null;
        foreach ($questions as $q) {
            if ((int)$q['id'] === $questionId) {
                $activeQForSubmission = $q;
                break;
            }
        }
        
        if (!$activeQForSubmission) {
            echo json_encode(['success' => false, 'message' => 'Question introuvable.']);
            exit;
        }
        
        // Parse input value according to question type (MCQ options vs written text)
        $qType = $activeQForSubmission['question_type'] ?? 'mcq';
        if ($qType === 'mcq') {
            $selectedOption = strtoupper(trim((string)($_POST['selected_option'] ?? '')));
            if ($questionId <= 0 || !in_array($selectedOption, ['', 'A', 'B', 'C', 'D'], true)) {
                echo json_encode(['success' => false, 'message' => 'Données de réponse invalides.']);
                exit;
            }
        } else {
            $selectedOption = trim((string)($_POST['selected_option'] ?? ''));
        }

        // Enforce time window validation
        if (!$activeQ || (int)$activeQ['id'] !== $questionId) {
            echo json_encode(['success' => false, 'message' => 'Le temps imparti pour cette question est écoulé.']);
            exit;
        }

        // Insert response, handles updates if student overrides answer within time window
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

        $_SESSION['answered_questions'][$questionId] = true;

        echo json_encode(['success' => true, 'message' => 'Réponse enregistrée.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action inconnue.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données.']);
}

// =========================================================================
// UTILITY FUNCTIONS: SCORING & EMAIL DISPATCH
// =========================================================================

/**
 * Calculates correct answers, saves final grade, and dispatches correction report email.
 * 
 * @param PDO   $pdo          Database connection instance.
 * @param array $session      Live evaluation session metadata.
 * @param array $registration Registration database row.
 * @param array $questions    Evaluation questions array list.
 * @return float Calculated score percentage.
 */
function calculateAndSaveScore(PDO $pdo, array $session, array $registration, array $questions): float
{
    $regId = (int)$registration['id'];
    
    // Fetch submitted answers
    $stmt = $pdo->prepare("
        SELECT question_id, selected_option FROM live_eval_answers
        WHERE registration_id = :rid
    ");
    $stmt->execute(['rid' => $regId]);
    $submittedAnswers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $correctCount = 0;
    $totalQuestions = count($questions);
    $qasDetails = [];

    // Evaluate answers
    foreach ($questions as $q) {
        $selected = $submittedAnswers[$q['id']] ?? '';
        $isCorrect = false;
        
        if (($q['question_type'] ?? 'mcq') === 'written') {
            // Normalize spaces and commas to resolve typos in mathematical entries
            $normalizedSelected = str_replace([',', ' '], ['.', ''], strtolower(trim($selected)));
            $normalizedCorrect = str_replace([',', ' '], ['.', ''], strtolower(trim($q['correct_option'])));
            $isCorrect = ($normalizedSelected === $normalizedCorrect);
        } else {
            $isCorrect = ($selected === $q['correct_option']);
        }
        
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
            'question_type'  => $q['question_type'] ?? 'mcq',
        ];
    }

    $scorePercent = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 100.00;

    // Save final calculated score in database
    $updateStmt = $pdo->prepare("UPDATE live_eval_registrations SET score = :score WHERE id = :id");
    $updateStmt->execute(['score' => $scorePercent, 'id' => $regId]);

    // Dispatch results via Email using the Mailer utility
    require_once __DIR__ . '/../Mailer.php';
    @Mailer::sendLiveEvalResults(
        $registration['email'],
        $registration['name'],
        $session['title'],
        $correctCount,
        $totalQuestions,
        $qasDetails,
        (int)$regId
    );

    return (float)$scorePercent;
}
