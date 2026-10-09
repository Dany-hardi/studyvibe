<?php
/**
 * StudyVibe Academic LMS - Live Evaluation PDF Export Controller
 *
 * This controller generates and compiles a LaTeX document representing the
 * correction report of a student's live evaluation, outputting a high-quality PDF.
 *
 * PHP version 8.2
 *
 * @category  Controller
 * @package   StudyVibe\Student
 * @author    StudyVibe Team <development@studyvibe.academic>
 * @copyright 2026 StudyVibe
 * @license   Proprietary
 * @link      https://studyvibe.academic
 */

declare(strict_types=1);

// =========================================================================
// SECTION 1: AUTHENTICATION, JETON SECURITY & QUERY VALIDATION
// =========================================================================

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/LiveScoring.php';
require_once __DIR__ . '/../lib/ExportDocs.php';

// Exiger que l'utilisateur soit connecté
if (!isLoggedIn()) {
    http_response_code(401);
    exit('Veuillez vous connecter pour accéder à ce document.');
}

$regId = (int)($_GET['registration_id'] ?? 0);
$token = trim((string)($_GET['token'] ?? ''));

if ($regId <= 0 || empty($token)) {
    http_response_code(400);
    exit('Paramètres de requête manquants ou invalides.');
}

// Validation du jeton sécurisé
$expectedToken = hash_hmac('sha256', (string)$regId, APP_SECRET);
if (!hash_equals($expectedToken, $token)) {
    http_response_code(403);
    exit('Jeton de sécurité invalide ou expiré.');
}

$pdo = Database::getInstance();
$currentUser = getCurrentUser();

// =========================================================================
// SECTION 2: REGISTRATION & SESSION DATA RESOLUTION
// =========================================================================

// Charger l'inscription avec les détails de la séance
try {
    $stmt = $pdo->prepare("
        SELECT r.*, s.title AS session_title, s.course_id, s.shuffle_options, c.title AS course_title
        FROM live_eval_registrations r
        JOIN live_eval_sessions s ON r.session_id = s.id
        JOIN courses c ON s.course_id = c.id
        WHERE r.id = :id
    ");
    $stmt->execute(['id' => $regId]);
    $registration = $stmt->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    exit('Erreur lors du chargement des données de la séance.');
}

if (!$registration) {
    http_response_code(404);
    exit('Inscription ou séance d\'évaluation introuvable.');
}

// Vérification de propriété : seul l'étudiant concerné, l'enseignant ou le promoteur peut voir le rapport
if ($currentUser['role'] === 'student' && (int)$registration['student_id'] !== $currentUser['id'] && $registration['email'] !== $currentUser['email']) {
    http_response_code(403);
    exit('Vous n\'êtes pas autorisé à accéder aux résultats de cette personne.');
}

// =========================================================================
// SECTION 3: ANSWERS DATA FETCHING & SCORE CALCULATION
// =========================================================================

// A result the teacher cancelled has no correction report
if (!empty($registration['cancelled_at'])) {
    http_response_code(403);
    exit('Ce résultat a été annulé par l\'enseignant.');
}

// Charger les réponses soumises et les questions associées
try {
    $stmt = $pdo->prepare("
        SELECT q.id AS question_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option, q.explanation, q.question_type, q.image_path, a.selected_option
        FROM live_eval_answers a
        JOIN live_eval_questions q ON a.question_id = q.id
        WHERE a.registration_id = :reg_id
        ORDER BY q.id ASC
    ");
    $stmt->execute(['reg_id' => $regId]);
    $answers = $stmt->fetchAll();
    // Options in the order this student saw them (only when the session shuffled them)
    foreach ($answers as $i => $row) {
        $answers[$i] = LiveScoring::asSeen($row, (string)$row['selected_option'], (int)$regId, !empty($registration['shuffle_options']));
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Erreur lors du chargement des réponses.');
}

$totalQuestions = count($answers);
$correctCount = 0;
foreach ($answers as $ans) {
    $isCorrect = false;
    $isCorrect = LiveScoring::isCorrect((string)($ans['question_type'] ?? 'mcq'), (string)$ans['selected_option'], (string)$ans['correct_option']);
    if ($isCorrect) {
        $correctCount++;
    }
}
$scorePercent = $totalQuestions > 0 ? ($correctCount / $totalQuestions) * 100 : 0.0;

// =========================================================================
// SECTION 4: THE DOCUMENT
// =========================================================================

// Built in lib/ExportDocs.php with the look from lib/ExportTheme.php (traditional LaTeX typeface). The options are already in the
// order this student saw them (see above), and the question pictures are included.
$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$doc  = ExportDocs::studentReport($pdo, $registration, $answers, $lang);
$slug = 'rapport_correction_' . ExportTheme::slug((string)$registration['session_title'], 'evaluation');

$pdfData = LatexCompiler::compile($doc['tex'], $doc['assets']);
if (!$pdfData) {
    // No technical details for the student: the log stays on the server
    http_response_code(503);
    exit($lang === 'en' ? 'The PDF could not be produced right now. Please try again later.' : 'Le PDF n’a pas pu être produit pour le moment. Réessayez plus tard.');
}

auditLog('export_evaluation_pdf_latex', "Registration #{$regId}");
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $slug . '.pdf"');
header('Content-Length: ' . strlen($pdfData));
header('Cache-Control: private, max-age=0, must-revalidate');
echo $pdfData;
exit;
