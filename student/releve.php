<?php
/**
 * StudyVibe Academic LMS - Student Academic Transcript Controller
 *
 * This controller retrieves, processes, and formats the student's complete academic
 * transcript, including course progress, lesson quiz scores, and certification attempts.
 * Supports printable CSS and PDF export via html2pdf.js.
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
// SECTION 1: AUTHENTICATION & GLOBAL INITIALIZATION
// =========================================================================

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/student_i18n.php';
requireRole('student');

$user = getCurrentUser();
$pdo  = Database::getInstance();

$courses = [];
$lessonScores = [];
$failedAttempts = [];

// =========================================================================
// SECTION 2: REPORT METRICS DATA RETRIEVAL
// =========================================================================

try {
    $stmt = $pdo->prepare("
        SELECT c.title AS course_title, m.title AS module_title,
               e.progress_percent,
               (SELECT MAX(ca.score) FROM certification_attempts ca
                WHERE ca.student_id = e.student_id AND ca.course_id = e.course_id) AS best_score,
               (SELECT COUNT(*) FROM certification_attempts ca
                WHERE ca.student_id = e.student_id AND ca.course_id = e.course_id) AS attempts
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        JOIN modules m ON m.id = c.module_id
        WHERE e.student_id = :sid
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute(['sid' => $user['id']]);
    $courses = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT l.title AS lesson_title, c.title AS course_title, lp.score, lp.completed_at
        FROM lesson_progress lp
        JOIN lessons l ON l.id = lp.lesson_id
        JOIN chapters ch ON ch.id = l.chapter_id
        JOIN courses c ON c.id = ch.course_id
        WHERE lp.student_id = :sid AND lp.completed = 1 AND lp.score IS NOT NULL
        ORDER BY lp.completed_at DESC
        LIMIT 100
    ");
    $stmt->execute(['sid' => $user['id']]);
    $lessonScores = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT ca.id, ca.score, ca.attempted_at, ca.total_questions,
               c.title AS course_title, m.title AS module_title
        FROM certification_attempts ca
        JOIN courses c ON c.id = ca.course_id
        JOIN modules m ON m.id = c.module_id
        WHERE ca.student_id = :sid AND ca.passed = 0
        ORDER BY ca.attempted_at DESC
    ");
    $stmt->execute(['sid' => $user['id']]);
    $failedAttempts = $stmt->fetchAll();
} catch (PDOException $e) {
    $errorCode = 500;
    $errorTitle = "Erreur de base de données";
    $errorMessage = "Une erreur est survenue lors de la récupération de votre relevé de notes.";
    $badgeText = "Erreur SQL";
    include __DIR__ . '/../error.php';
    exit;
}

$genDate = sdDate(time(), 'year') . ' · ' . sdDate(time(), 'time');

// =========================================================================
// SECTION 3: HTML REPORT PAGE LAYOUT
// =========================================================================
?>
<!DOCTYPE html>
<html lang="<?= sdLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= sdH(sd('doc_transcript')) ?> — StudyVibe</title>
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
<article class="doc-sheet" id="releve-content">
    <header class="doc-head">
        <div>
            <p class="doc-kicker"><?= sdH(sd('doc_transcript')) ?></p>
            <h1 class="tr-name"><?= sdH($user['name']) ?></h1>
            <p class="doc-meta"><?php if (!empty($user['matricule'])): ?><?= sdH(sd('matricule')) ?> : <strong class="num"><?= sdH($user['matricule']) ?></strong><br><?php endif; ?><?= sdH(sd('doc_generated', ['date' => $genDate])) ?><br><?= sdH(sd('doc_transcript_text')) ?></p>
        </div>
        <?= Brand::logo('md') ?>
    </header>

    <h2 class="tr-h"><?= sdH(sd('by_course')) ?></h2>
    <?php if (empty($courses)): ?>
        <p class="doc-meta"><?= sdH(sd('doc_no_enrolment')) ?></p>
    <?php else: ?>
    <table class="tr-table">
        <thead><tr><th><?= sdH(sd('th_course')) ?></th><th class="r"><?= sdH(sd('th_progress')) ?></th><th class="r"><?= sdH(sd('th_best')) ?></th><th class="r"><?= sdH(sd('th_attempts')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($courses as $c): ?>
            <tr>
                <td><?= sdH($c['course_title']) ?><small><?= sdH($c['module_title']) ?></small></td>
                <td class="r num"><?= (int)$c['progress_percent'] ?> %</td>
                <td class="r num"><?= $c['best_score'] !== null ? sdH(sdScore($c['best_score'])) . ' %' : '—' ?></td>
                <td class="r num"><?= (int)$c['attempts'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($lessonScores)): ?>
    <h2 class="tr-h"><?= sdH(sd('doc_completed_lessons')) ?></h2>
    <table class="tr-table">
        <thead><tr><th><?= sdH(sd('th_lesson')) ?></th><th class="r"><?= sdH(sd('th_date')) ?></th><th class="r"><?= sdH(sd('th_score')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($lessonScores as $l): ?>
            <tr>
                <td><?= sdH($l['lesson_title']) ?><small><?= sdH($l['course_title']) ?></small></td>
                <td class="r num"><?= sdH(sdDate($l['completed_at'], 'date')) ?></td>
                <td class="r num"><?= (int)$l['score'] ?> %</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($failedAttempts)): ?>
    <h2 class="tr-h"><?= sdH(sd('doc_failed_certs')) ?></h2>
    <table class="tr-table">
        <thead><tr><th><?= sdH(sd('th_course')) ?></th><th class="r"><?= sdH(sd('th_date')) ?></th><th class="r"><?= sdH(sd('th_score')) ?></th><th class="r"><?= sdH(sd('doc_th_result')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($failedAttempts as $a): ?>
            <tr>
                <td><?= sdH($a['course_title']) ?></td>
                <td class="r num"><?= sdH(sdDate($a['attempted_at'], 'date')) ?></td>
                <td class="r num tr-fail"><?= sdH(sdScore($a['score'])) ?> %</td>
                <td class="r tr-fail">✕ <?= sdH(sd('doc_not_validated')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="doc-meta" style="margin-top:12px"><?= sdH(sd('doc_failed_note')) ?></p>
    <?php endif; ?>

    <p class="tr-foot"><?= sdH(sd('doc_footer', ['date' => sdDate(time(), 'date')])) ?></p>
</article>
</main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
document.getElementById('btn-pdf')?.addEventListener('click', () => {
    const root = document.documentElement, wasDark = root.classList.contains('dark');
    root.classList.remove('dark');
    html2pdf().set({ margin: 8, filename: 'releve-notes-studyvibe.pdf', html2canvas: { scale: 2 }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } })
        .from(document.getElementById('releve-content')).save().then(() => { if (wasDark) root.classList.add('dark'); });
});
</script>
</body>
</html>
