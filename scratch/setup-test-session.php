<?php
declare(strict_types=1);
date_default_timezone_set('Africa/Douala');

$pdo = new PDO("mysql:host=127.0.0.1;dbname=studyvibe", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Nettoyer les anciennes données de test
$pdo->exec("DELETE FROM live_eval_answers WHERE registration_id IN (SELECT id FROM live_eval_registrations WHERE email = 'test@example.com')");
$pdo->exec("DELETE FROM live_eval_registrations WHERE email = 'test@example.com'");
$pdo->exec("DELETE FROM live_eval_questions WHERE session_id = 99");
$pdo->exec("DELETE FROM live_eval_sessions WHERE id = 99");

// 2. Créer la session
$startTime = date('Y-m-d H:i:s', time() + 10); // commence dans 10 secondes
$endTime = date('Y-m-d H:i:s', time() + 3600);

$stmt = $pdo->prepare("
    INSERT INTO live_eval_sessions (id, course_id, teacher_id, title, session_code, start_time, end_time, status, default_time_limit)
    VALUES (99, 1, 24, 'Test Live Sync', 'testcode99', :start, :end, 1, 10)
");
$stmt->execute(['start' => $startTime, 'end' => $endTime]);

// 3. Ajouter 2 questions (10 secondes par question)
$q1 = $pdo->prepare("
    INSERT INTO live_eval_questions (session_id, question_text, option_a, option_b, option_c, option_d, correct_option, time_limit, sort_order)
    VALUES (99, 'Quelle est la capitale de la France ?', 'Londres', 'Paris', 'Berlin', 'Madrid', 'B', 10, 1)
");
$q1->execute();

$q2 = $pdo->prepare("
    INSERT INTO live_eval_questions (session_id, question_text, option_a, option_b, option_c, option_d, correct_option, time_limit, sort_order)
    VALUES (99, 'Combien font 2 + 2 ?', '3', '4', '5', '6', 'B', 10, 2)
");
$q2->execute();

// 4. Inscrire l'étudiant
$reg = $pdo->prepare("
    INSERT INTO live_eval_registrations (session_id, name, email, registered_at)
    VALUES (99, 'Test Student', 'test@example.com', NOW())
");
$reg->execute();
$regId = $pdo->lastInsertId();

// Créer le script de session pour le navigateur
$sessionHelperContent = <<<PHP
<?php
session_start();
\$_SESSION['live_registrations']['testcode99'] = {$regId};
echo "Session student OK - RegID: {$regId}";
PHP;

file_put_contents(__DIR__ . '/../test-login-student.php', $sessionHelperContent);

echo "Test session 99 created successfully! Starts at: " . $startTime . "\n";
