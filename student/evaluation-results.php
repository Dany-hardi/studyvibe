<?php
/**
 * StudyVibe Academic LMS - Live Evaluation Results View
 *
 * This controller processes and displays the detailed results page for a student's
 * completed live evaluation, including a breakdown of correct/incorrect responses
 * and teacher justifications rendered with KaTeX.
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
// SECTION 1: AUTHENTICATION & SECURITY PARAMETERS VALIDATION
// =========================================================================

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/LiveScoring.php';
require_once __DIR__ . '/../lib/student_i18n.php';

// Exiger que l'utilisateur soit connecté
if (!isLoggedIn()) {
    $redirectPath = $_SERVER['REQUEST_URI'] ?? '/student/dashboard.php';
    header('Location: /index.php?error=auth_required&redirect=' . urlencode($redirectPath));
    exit;
}

$regId = (int)($_GET['registration_id'] ?? 0);
$token = trim((string)($_GET['token'] ?? ''));

if ($regId <= 0 || empty($token)) {
    $errorCode = 403;
    $errorTitle = "Accès Interdit";
    $errorMessage = "Accès interdit : les paramètres d'accès requis sont manquants ou corrompus.";
    $badgeText = "Paramètres manquants";
    include __DIR__ . '/../error.php';
    exit;
}

// Validation du jeton sécurisé
$expectedToken = hash_hmac('sha256', (string)$regId, APP_SECRET);
if (!hash_equals($expectedToken, $token)) {
    $errorCode = 403;
    $errorTitle = "Signature Invalide";
    $errorMessage = "Le jeton d'authentification fourni est invalide ou expiré.";
    $badgeText = "Jeton incorrect";
    include __DIR__ . '/../error.php';
    exit;
}

$pdo = Database::getInstance();
$currentUser = getCurrentUser();

// =========================================================================
// SECTION 2: LIVE EVALUATION SESSION REGISTRATION RESOLUTION
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
    dieSafe("Erreur serveur lors du chargement des données.");
}

if (!$registration) {
    $errorCode = 404;
    $errorTitle = "Rapport Introuvable";
    $errorMessage = "Le rapport d'évaluation demandé n'existe pas ou a été archivé.";
    $badgeText = "Non Trouvé";
    include __DIR__ . '/../error.php';
    exit;
}

// Vérification de propriété : seul l'étudiant concerné, l'enseignant ou le promoteur peut voir le rapport
if ($currentUser['role'] === 'student' && (int)$registration['student_id'] !== $currentUser['id'] && $registration['email'] !== $currentUser['email']) {
    $errorCode = 403;
    $errorTitle = "Accès Refusé";
    $errorMessage = "Accès refusé : vous n'avez pas l'autorisation de consulter ce rapport d'évaluation.";
    $badgeText = "Propriétaire différent";
    include __DIR__ . '/../error.php';
    exit;
}

// =========================================================================
// SECTION 3: USER ANSWERS FETCHING & METRIC COMPUTATION
// =========================================================================

// Charger les réponses soumises et les questions associées
try {
    $stmt = $pdo->prepare("
        SELECT q.id AS question_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option, q.explanation, q.question_type, a.selected_option
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
    dieSafe("Erreur serveur lors du chargement des réponses.");
}

// Calculer les métriques locales
$totalQuestions = count($answers);
$correctCount = 0;
foreach ($answers as $ans) {
    $isCorrect = false;
    $isCorrect = LiveScoring::isCorrect((string)($ans['question_type'] ?? 'mcq'), (string)$ans['selected_option'], (string)$ans['correct_option']);
    if ($isCorrect) {
        $correctCount++;
    }
}
$scorePercent = $totalQuestions > 0 ? ($correctCount / $totalQuestions) * 100 : 0;
$hasPassed = $scorePercent >= 50;

// =========================================================================
// SECTION 4: HTML INTERFACE LAYOUT & KATEX INTEGRATION
// =========================================================================
?>
<!DOCTYPE html>
<html lang="<?= sdLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= sdH(sd('doc_report_title')) ?> — <?= sdH($registration['session_title']) ?> — StudyVibe</title>
    <?= sdFontsLink() ?>
    <link rel="stylesheet" href="/assets/css/sv2.css">
    <?= Brand::headLinks() ?>
    <link rel="stylesheet" href="/assets/css/student-doc.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js" onload="renderMath()"></script>
    <?= sdThemeBoot() ?>
</head>
<body class="v2 sdoc">
<div class="doc-bar no-print">
    <?php if ($currentUser['role'] === 'student'): ?>
        <a class="doc-back" href="/student/dashboard.php#evals"><?= sdIcon('arrow', 16) ?><?= sdH(sd('doc_back')) ?></a>
    <?php else: ?>
        <button type="button" class="doc-back" style="background:none;border:0;cursor:pointer;font-family:inherit" onclick="window.close();"><?= sdIcon('x', 16) ?><?= sdH(sd('doc_close_tab')) ?></button>
    <?php endif; ?>
    <div class="doc-actions">
        <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()"><?= sdIcon('print', 16) ?><?= sdH(sd('doc_print')) ?></button>
        <a class="btn btn-primary btn-sm" href="/student/export-evaluation-pdf.php?registration_id=<?= $regId ?>&amp;token=<?= urlencode($token) ?>"><?= sdIcon('down', 16) ?><?= sdH(sd('doc_pdf_latex')) ?></a>
    </div>
</div>

<main class="doc-wrap">
<article class="doc-sheet">
    <header class="res-top">
        <p class="doc-kicker"><?= sdH(sd('doc_report_title')) ?></p>
        <h1><?= sdH($registration['session_title']) ?></h1>
        <p class="doc-meta"><?= sdH(sd('doc_course')) ?> : <strong><?= sdH($registration['course_title']) ?></strong><br><?= sdH(sd('doc_candidate')) ?> : <strong><?= sdH($registration['name']) ?></strong></p>
    </header>

    <section class="res-score" aria-label="<?= sdH(sd('doc_final_score')) ?>">
        <div class="res-big num"><?= sdH(sdScore($scorePercent)) ?><small>%</small></div>
        <div class="res-side">
            <span class="doc-verdict <?= $hasPassed ? 'is-pass' : 'is-fail' ?>"><?= sdIcon($hasPassed ? 'check' : 'x', 14) ?><?= sdH($hasPassed ? sd('passed') : sd('not_passed')) ?></span>
            <p class="res-frac num"><?= sdH(sd('doc_summary', ['ok' => $correctCount, 'total' => $totalQuestions])) ?></p>
            <p class="res-note"><?= sdH(sd('doc_threshold_live')) ?></p>
        </div>
    </section>

    <h2 class="res-h2"><?= sdH(sd('doc_review')) ?> <span><?= sdH(sd('doc_questions', ['n' => $totalQuestions])) ?></span></h2>

    <?php foreach ($answers as $index => $qa):
        $num = $index + 1;
        $isWritten = ($qa['question_type'] ?? 'mcq') === 'written';
        $isCorrect = LiveScoring::isCorrect($isWritten ? 'written' : 'mcq', (string)$qa['selected_option'], (string)$qa['correct_option']);
    ?>
    <section class="res-q">
        <div class="res-q-head">
            <h3 class="res-q-title latex-container"><span class="n num"><?= $num ?>.</span><span><?= sdH($qa['question_text']) ?></span></h3>
            <span class="res-mark <?= $isCorrect ? 'is-ok' : 'is-bad' ?>"><?= sdIcon($isCorrect ? 'check' : 'x', 13) ?><?= sdH($isCorrect ? sd('doc_correct') : sd('doc_incorrect')) ?></span>
        </div>

        <?php if ($isWritten): ?>
            <div class="res-written">
                <div class="res-box <?= $isCorrect ? 'is-right' : 'is-wrong' ?>"><b><?= sdH(sd('doc_your_answer')) ?></b><span class="latex-container"><?= $qa['selected_option'] === '' || $qa['selected_option'] === null ? '<em>' . sdH(sd('doc_none')) . '</em>' : sdH($qa['selected_option']) ?></span></div>
                <?php if (!$isCorrect): ?>
                <div class="res-box is-right"><b><?= sdH(sd('doc_right_answer')) ?></b><span class="latex-container"><?= sdH($qa['correct_option']) ?></span></div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <ul class="res-opts">
                <?php foreach (['A', 'B', 'C', 'D'] as $opt):
                    $optText = $qa['option_' . strtolower($opt)] ?? '';
                    if ($optText === '' || $optText === null) { continue; }
                    $isRight = $opt === $qa['correct_option'];
                    $isPicked = $opt === $qa['selected_option'];
                    $cls = $isRight ? 'is-right' : ($isPicked ? 'is-picked' : '');
                ?>
                <li class="res-opt <?= $cls ?>">
                    <span class="l"><?= $opt ?></span>
                    <span class="t latex-container"><?= sdH($optText) ?></span>
                    <?php if ($isRight): ?><span class="tag"><?= sdIcon('check', 13) ?><?= sdH($isPicked ? sd('doc_correct_opt') : sd('doc_correct_opt')) ?><?= $isPicked ? ' · ' . sdH(sd('doc_yours')) : '' ?></span>
                    <?php elseif ($isPicked): ?><span class="tag"><?= sdIcon('x', 13) ?><?= sdH(sd('doc_yours')) ?></span><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($qa['explanation'])): ?>
        <div class="res-why"><b><?= sdH(sd('doc_explanation')) ?></b><span class="latex-container"><?= nl2br(sdH($qa['explanation'])) ?></span></div>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>
</article>
</main>

<script>
function renderMath() {
    if (typeof renderMathInElement === 'function') {
        renderMathInElement(document.body, {
            delimiters: [{left: '$$', right: '$$', display: true}, {left: '$', right: '$', display: false}, {left: '\\(', right: '\\)', display: false}, {left: '\\[', right: '\\]', display: true}],
            throwOnError: false
        });
    }
}
</script>
</body>
</html>
