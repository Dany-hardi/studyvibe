<?php
/**
 * StudyVibe Academic LMS - Certification Attempt Report Controller
 *
 * This controller processes and displays the detailed report page for a student's
 * certification attempt. It supports printing and PDF export via html2pdf.js.
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
// SECTION 1: AUTHENTICATION, GATEKEEPING & INITIALIZATION
// =========================================================================

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/student_i18n.php';
requireRole('student');

$attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
$user      = getCurrentUser();
$attempt   = null;

if ($attemptId <= 0) {
    $errorCode = 404;
    $errorTitle = "Relevé Introuvable";
    $errorMessage = "L'identifiant du relevé de tentative demandé est invalide ou absent.";
    $badgeText = "Paramètre incorrect";
    include __DIR__ . '/../error.php';
    exit;
}

// =========================================================================
// SECTION 2: DATA FETCHING & QUERY PROCESSING
// =========================================================================

try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare("
        SELECT ca.*, c.title AS course_title, u.name AS student_name
        FROM certification_attempts ca
        JOIN courses c ON c.id = ca.course_id
        JOIN users u ON u.id = ca.student_id
        WHERE ca.id = :id AND ca.student_id = :sid AND ca.passed = 0
    ");
    $stmt->execute(['id' => $attemptId, 'sid' => $user['id']]);
    $attempt = $stmt->fetch();
} catch (PDOException $e) {
    $attempt = null;
}

if (!$attempt) {
    $errorCode = 404;
    $errorTitle = "Relevé Introuvable";
    $errorMessage = "Le relevé de tentative demandé est introuvable ou vous n'êtes pas autorisé à y accéder.";
    $badgeText = "Non Trouvé";
    include __DIR__ . '/../error.php';
    exit;
}

$totalQ     = (int)($attempt['total_questions'] ?? 0);
$score      = (float)$attempt['score'];
$threshold  = 80;
$dateStr    = sdDate($attempt['attempted_at'], 'year') . ' · ' . sdDate($attempt['attempted_at'], 'time');
$approxOk   = $totalQ > 0 ? (int)round(($score / 100) * $totalQ) : null;

// =========================================================================
// SECTION 3: HTML INTERFACE LAYOUT & TEMPLATE
// =========================================================================
?>
<!DOCTYPE html>
<html lang="<?= sdLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= sdH(sd('doc_attempt_title')) ?> — <?= sdH($attempt['course_title']) ?></title>
    <?= sdFontsLink() ?>
    <link rel="stylesheet" href="/assets/css/sv2.css">
    <?= Brand::headLinks() ?>
    <link rel="stylesheet" href="/assets/css/student-doc.css">
    <?= sdThemeBoot() ?>
</head>
<body class="v2 sdoc">
<div class="doc-bar no-print">
    <a class="doc-back" href="/student/dashboard.php#results"><?= sdIcon('arrow', 16) ?><?= sdH(sd('doc_back')) ?></a>
    <div class="doc-actions">
        <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()"><?= sdIcon('print', 16) ?><?= sdH(sd('doc_print')) ?></button>
        <button type="button" class="btn btn-primary btn-sm" id="btn-pdf"><?= sdIcon('down', 16) ?><?= sdH(sd('doc_pdf')) ?></button>
    </div>
</div>
<main class="doc-wrap">
<article class="doc-sheet" id="report-content">
    <header class="doc-head">
        <div>
            <p class="doc-kicker"><?= sdH(sd('doc_attempt_title')) ?></p>
            <h1 class="tr-name"><?= sdH($attempt['course_title']) ?></h1>
            <p class="doc-meta"><?= sdH(sd('doc_learner')) ?> : <strong><?= sdH($attempt['student_name']) ?></strong><br><?= sdH(sd('doc_attempt_date')) ?> : <strong class="num"><?= sdH($dateStr) ?></strong></p>
        </div>
        <?= Brand::logo('md') ?>
    </header>

    <div class="res-score">
        <div class="res-big num"><?= sdH(sdScore($score)) ?><small>%</small></div>
        <div class="res-side">
            <span class="doc-verdict is-fail"><?= sdIcon('x', 14) ?><?= sdH(sd('not_passed')) ?></span>
            <p class="res-note"><?= sdH(sd('doc_required')) ?> : <span class="num"><?= $threshold ?> %</span></p>
        </div>
    </div>

    <h2 class="tr-h"><?= sdH(sd('doc_attempt_summary')) ?></h2>
    <dl class="tr-stats">
        <div class="tr-stat"><dt><?= sdH(sd('doc_result')) ?></dt><dd class="tr-fail"><?= sdH(sd('not_passed')) ?></dd></div>
        <div class="tr-stat"><dt><?= sdH(sd('doc_score')) ?></dt><dd class="num"><?= sdH(sdScore($score)) ?> %</dd></div>
        <div class="tr-stat"><dt><?= sdH(sd('doc_required')) ?></dt><dd class="num"><?= $threshold ?> %</dd></div>
        <?php if ($totalQ > 0): ?><div class="tr-stat"><dt><?= sdH(sd('doc_exam_questions')) ?></dt><dd class="num"><?= $totalQ ?></dd></div><?php endif; ?>
    </dl>

    <p class="tr-note"><?= sdH(sd('doc_attempt_notice')) ?></p>
    <p class="tr-foot"><?= sdH(sd('doc_footer', ['date' => sdDate(time(), 'date')])) ?></p>
</article>
</main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
document.getElementById('btn-pdf')?.addEventListener('click', () => {
    const root = document.documentElement, wasDark = root.classList.contains('dark');
    root.classList.remove('dark');
    html2pdf().set({ margin: 10, filename: 'releve-tentative-<?= (int)$attemptId; ?>.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } })
        .from(document.getElementById('report-content')).save().then(() => { if (wasDark) root.classList.add('dark'); });
});
</script>
</body>
</html>
