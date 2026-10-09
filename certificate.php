<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Brand.php';
require_once __DIR__ . '/lib/student_i18n.php';
requireRole('student');

$code = trim((string)($_GET['code'] ?? ''));
if (empty($code)) {
    $errorCode = 404;
    $errorTitle = sd("doc_err_missing_t");
    $errorMessage = sd("doc_err_missing_m");
    $badgeText = sd("doc_err_missing_b");
    include __DIR__ . '/error.php';
    exit;
}

$user = getCurrentUser();
$cert = null;

try {
    $pdo  = Database::getInstance();
    $stmt = $pdo->prepare("
        SELECT cert.*, u.name AS student_name, c.title AS course_title
        FROM certificates cert
        JOIN users u ON cert.student_id = u.id
        LEFT JOIN courses c ON cert.course_id = c.id
        WHERE cert.certificate_code = :code AND cert.student_id = :student_id
    ");
    $stmt->execute(['code' => $code, 'student_id' => $user['id']]);
    $cert = $stmt->fetch();
} catch (PDOException $e) {}

if (!$cert) {
    $errorCode = 404;
    $errorTitle = sd("doc_err_nf_t");
    $errorMessage = sd("doc_err_nf_m");
    $badgeText = sd("doc_err_nf_b");
    include __DIR__ . '/error.php';
    exit;
}

$verifyUrl = APP_URL . '/verify.php?code=' . urlencode($code);
$verifyShort = preg_replace('#^https?://#', '', rtrim((string)APP_URL, '/')) . '/verify.php';
$issued = strtotime((string)($cert['issued_at'] ?? 'now')) ?: time();
$linkedinUrl = 'https://www.linkedin.com/profile/add?' . http_build_query([
    'startTask'        => 'CERTIFICATION_NAME',
    'name'             => (string)($cert['course_title'] ?? 'StudyVibe'),
    'organizationName' => 'StudyVibe',
    'issueYear'        => (int)date('Y', $issued),
    'issueMonth'       => (int)date('n', $issued),
    'certUrl'          => $verifyUrl,
    'certId'           => $code,
]);
$sealText = strtoupper(sd('doc_cert_word')) . ' · STUDYVIBE · ' . strtoupper(sd('doc_cert_word')) . ' · STUDYVIBE · ';
?>
<!DOCTYPE html>
<html lang="<?= sdLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= sdH(sd('doc_cert_word')) ?> — <?= sdH($cert['course_title'] ?? sd('unknown_course')) ?> — StudyVibe</title>
    <?= sdFontsLink() ?>
    <link rel="stylesheet" href="/assets/css/sv2.css">
    <?= Brand::headLinks() ?>
    <link rel="stylesheet" href="/assets/css/student-doc.css">
    <style>@page { size: A4 landscape; margin: 0; }</style>
    <?= sdThemeBoot() ?>
</head>
<body class="v2 sdoc sdoc-cert">
<div class="doc-bar no-print">
    <a class="doc-back" href="/student/dashboard.php#certs"><?= sdIcon('arrow', 16) ?><?= sdH(sd('doc_back')) ?></a>
    <div class="doc-actions">
        <a class="btn btn-text" href="/verify.php?code=<?= urlencode($code) ?>" target="_blank" rel="noopener"><?= sdIcon('link', 16) ?>&nbsp;<?= sdH(sd('doc_verify_page')) ?></a>
        <a class="btn btn-text" href="<?= sdH($linkedinUrl) ?>" target="_blank" rel="noopener"><?= sdH(sdLang() === 'en' ? 'Add to LinkedIn' : 'Ajouter à LinkedIn') ?></a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()"><?= sdIcon('print', 16) ?><?= sdH(sd('doc_print')) ?></button>
        <button type="button" class="btn btn-primary btn-sm" id="btn-download-pdf"><?= sdIcon('down', 16) ?><?= sdH(sd('doc_pdf')) ?></button>
    </div>
</div>

<div class="cert-stage">
  <div class="cert-fit" id="cert-fit">
    <div class="cert-page" id="cert-page">
        <div class="cert-top">
            <?= Brand::logo('md') ?>
            <span class="cert-word"><?= sdH(sd('doc_platform')) ?></span>
        </div>

        <div class="cert-body">
            <p class="cert-kicker"><?= sdH(sd('cert_kicker')) ?></p>
            <p class="cert-label"><?= sdH(sd('doc_cert_awarded')) ?></p>
            <h1 class="cert-name"><?= sdH($cert['student_name']) ?></h1>
            <div class="cert-rule"></div>
            <p class="cert-label" style="margin-bottom:2mm"><?= sdH(sd('doc_cert_for')) ?></p>
            <h2 class="cert-course"><?= sdH($cert['course_title'] ?? sd('unknown_course')) ?></h2>
            <p class="cert-text"><?= sdH(sd('doc_cert_body')) ?></p>
        </div>

        <div class="cert-foot">
            <div class="cert-f">
                <b><?= sdH(sd('doc_issued')) ?></b>
                <strong><?= sdH(sdDate($cert['issued_at'], 'year')) ?></strong>
                <span><?= sdH(sd('doc_signed')) ?></span>
            </div>
            <div class="cert-seal" aria-hidden="true">
                <svg viewBox="0 0 100 100">
                    <defs><path id="sealpath" d="M50,50 m-39,0 a39,39 0 1,1 78,0 a39,39 0 1,1 -78,0"/></defs>
                    <circle cx="50" cy="50" r="48" fill="none" stroke="#B5482A" stroke-width="1.4"/>
                    <circle cx="50" cy="50" r="30" fill="none" stroke="#B5482A" stroke-width=".6"/>
                    <circle cx="50" cy="50" r="46" fill="none" stroke="#B5482A" stroke-width=".4" stroke-dasharray="1 2.2"/>
                    <text><textPath href="#sealpath" startOffset="0"><?= sdH($sealText) ?></textPath></text>
                </svg>
                <?= Brand::mark(40) ?>
            </div>
            <div class="cert-f r">
                <div>
                    <b><?= sdH(sd('doc_validation_code')) ?></b>
                    <span class="cert-code"><?= sdH($cert['certificate_code']) ?></span>
                    <span class="cert-url"><?= sdH(sd('doc_verify_at')) ?> <?= sdH($verifyShort) ?></span>
                </div>
                <div class="cert-qr"><img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&amp;margin=0&amp;data=<?= urlencode($verifyUrl) ?>" alt="<?= sdH(sd('doc_scan')) ?>" crossorigin="anonymous"></div>
            </div>
        </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
(function () {
    const fit = document.getElementById('cert-fit');
    function scale() {
        const mm = 96 / 25.4, w = 297 * mm;
        fit.style.setProperty('--s', Math.min(1, (window.innerWidth - 32) / w).toFixed(4));
    }
    scale(); window.addEventListener('resize', scale);
    document.getElementById('btn-download-pdf')?.addEventListener('click', () => {
        const root = document.documentElement, wasDark = root.classList.contains('dark');
        root.classList.remove('dark');
        const s = fit.style.getPropertyValue('--s'); fit.style.setProperty('--s', '1');
        html2pdf().set({
            margin: 0, filename: 'certificat-<?= htmlspecialchars($cert['certificate_code']) ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
        }).from(document.getElementById('cert-page')).save().then(() => { fit.style.setProperty('--s', s); if (wasDark) root.classList.add('dark'); });
    });
})();
</script>
</body>
</html>
