<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
requireRole('student');

$code = trim((string)($_GET['code'] ?? ''));
if (empty($code)) {
    $errorCode = 404;
    $errorTitle = "Certificat Introuvable";
    $errorMessage = "Le code de certificat demandé est manquant ou vide.";
    $badgeText = "Code manquant";
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
    $errorTitle = "Certificat Introuvable";
    $errorMessage = "Aucun certificat correspondant au code fourni n'a été trouvé pour votre compte.";
    $badgeText = "Non Trouvé";
    include __DIR__ . '/error.php';
    exit;
}

$verifyUrl = APP_URL . '/verify.php?code=' . urlencode($code);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificat — <?= htmlspecialchars($cert['course_title'] ?? 'Cours') ?> — StudyVibe</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #EAE6DF; color: #111111; }

        /* ── Zone d'impression uniquement ── */
        .no-print { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 3rem; background: #FFFFFF; border-bottom: 1px solid #E5E5E7; }
        .no-print .back  { text-decoration: none; font-size: 0.75rem; color: #555; font-weight: 500; text-transform: uppercase; letter-spacing: 0.08em; }
        .no-print .back:hover { color: #111; }
        .no-print .btn-print {
            padding: 0.6rem 1.5rem; background: #111111; color: #FFFFFF;
            border: none; cursor: pointer; font-family: inherit;
            font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.1em; transition: background 0.2s;
        }
        .no-print .btn-print:hover { background: #004B23; }

        /* ── Certificat ── */
        .cert-wrapper {
            display: flex; align-items: center; justify-content: center;
            min-height: calc(100vh - 61px); padding: 3rem;
        }
        .cert-page {
            width: 210mm; min-height: 148mm;
            background: #FFFFFF;
            border: 2px solid #004B23;
            padding: 4rem 5rem;
            position: relative;
            display: flex; flex-direction: column; justify-content: space-between;
        }

        /* Coins décoratifs */
        .cert-page::before,
        .cert-page::after {
            content: '';
            position: absolute;
            width: 2.5rem; height: 2.5rem;
            border-color: #004B23;
            border-style: solid;
        }
        .cert-page::before { top: 1rem; left: 1rem; border-width: 2px 0 0 2px; }
        .cert-page::after  { bottom: 1rem; right: 1rem; border-width: 0 2px 2px 0; }

        .cert-brand { display: flex; align-items: center; gap: 0.75rem; position: relative; z-index: 2; }
        .cert-brand-name { font-family: 'Playfair Display', serif; font-size: 1.25rem; font-weight: 600; }
        .cert-brand-tag  { font-size: 0.625rem; text-transform: uppercase; letter-spacing: 0.15em; color: #888; }

        .cert-body { text-align: center; padding: 2.5rem 0; position: relative; z-index: 2; }
        .cert-label { font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.15em; color: #888; margin-bottom: 0.5rem; }
        .cert-recipient { font-family: 'Playfair Display', serif; font-size: 2.5rem; font-weight: 300; margin-bottom: 0.75rem; line-height: 1.2; }
        .cert-module-label { font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.12em; color: #888; margin-bottom: 0.4rem; }
        .cert-module { font-family: 'Playfair Display', serif; font-size: 1.375rem; font-weight: 500; color: #004B23; }
        .cert-desc   { font-size: 0.8125rem; font-weight: 300; color: #555; margin-top: 0.75rem; max-width: 30rem; margin-inline: auto; line-height: 1.6; }

        .cert-footer { display: flex; justify-content: space-between; align-items: flex-end; position: relative; z-index: 2; }
        .cert-meta { font-size: 0.6875rem; color: #888; }
        .cert-meta strong { color: #111; font-weight: 600; display: block; font-size: 0.75rem; margin-top: 0.2rem; }
        .cert-code { font-size: 0.6875rem; font-family: monospace; color: #004B23; font-weight: 700; }
        .cert-qr img { width: 70px; height: 70px; display: block; }
        .cert-qr-label { font-size: 0.5625rem; color: #888; text-align: center; margin-top: 0.25rem; }

        /* Filigrane central transparent */
        .cert-watermark {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 260px; height: 260px;
            opacity: 0.035;
            pointer-events: none;
            z-index: 1;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #FFFFFF; }
            .cert-wrapper { min-height: 100vh; padding: 0; }
            .cert-page { width: 210mm; min-height: 148mm; border: 2px solid #004B23; box-shadow: none; }
            .cert-watermark { opacity: 0.035; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { size: A4 landscape; margin: 0; }
        }
    </style>
</head>
<body>

<!-- Barre d'actions (masquée à l'impression) -->
<div class="no-print">
    <a href="/student/dashboard.php" class="back">← Retour à mes certifications</a>
    <div style="display:flex;gap:0.75rem;align-items:center">
        <a href="/verify.php?code=<?= urlencode($code) ?>" target="_blank"
           style="font-size:0.75rem;color:#004B23;text-decoration:none;font-weight:500;text-transform:uppercase;letter-spacing:0.08em;">
            🔗 Page de vérification
        </a>
        <button class="btn-print" onclick="window.print()">🖨 Imprimer / Sauvegarder PDF</button>
        <button class="btn-print" id="btn-download-pdf" style="background:#004B23">⬇ Télécharger PDF</button>
    </div>
</div>

<!-- Certificat imprimable -->
<div class="cert-wrapper">
    <div class="cert-page">

        <!-- Filigrane Sceau d'Esprit Pensant Épuré (Monochrome Académique) -->
        <svg class="cert-watermark" viewBox="0 0 120 120" fill="none" stroke="#111111" stroke-width="0.8" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.03;">
            <!-- Doubles cercles extérieurs académiques -->
            <circle cx="60" cy="60" r="54" stroke-dasharray="2 2" />
            <circle cx="60" cy="60" r="50" />
            <circle cx="60" cy="60" r="40" stroke-dasharray="1 3" />
            
            <!-- Constellation (liaisons fines et nœuds noirs) -->
            <line x1="43" y1="41" x2="72" y2="35" stroke="#111111" stroke-width="0.6" />
            <line x1="43" y1="41" x2="59" y2="63" stroke="#111111" stroke-width="0.6" />
            <line x1="43" y1="41" x2="24" y2="23" stroke="#111111" stroke-width="0.6" />
            <line x1="43" y1="41" x2="52" y2="21" stroke="#111111" stroke-width="0.6" />
            <line x1="43" y1="41" x2="30" y2="63" stroke="#111111" stroke-width="0.6" />
            <line x1="59" y1="63" x2="72" y2="35" stroke="#111111" stroke-width="0.6" />

            <circle cx="72" cy="35" r="2" fill="#111111" />
            <circle cx="59" cy="63" r="2" fill="#111111" />
            <circle cx="43" cy="41" r="2" fill="#111111" />
            <circle cx="24" cy="23" r="2" fill="#111111" />
            <circle cx="52" cy="21" r="2" fill="#111111" />
            <circle cx="30" cy="63" r="2" fill="#111111" />

            <!-- Profil du visage -->
            <path d="M66 20 C62 24, 62 34, 62 39 C62 41, 60 43, 59 43 L55 43 L59 45 C60 47, 61 48, 60 50 C59 51, 57 52, 59 54 C61 55, 64 56, 66 56 C69 56, 75 48, 76 51 C78 56, 70 62, 66 70 C61 78, 60 88, 63 98" stroke="#111111" stroke-width="0.8" stroke-linecap="round" stroke-linejoin="round" />

            <!-- Main du penseur -->
            <path d="M43 65 C42 62, 42 58, 43 56 C44 54, 46 54, 47 57 C47 60, 47 63, 47 65 C47 61, 48 56, 49 54 C50 52, 52 52, 53 55 C53 58, 53 61, 53 64 C53 61, 54 57, 55 55 C56 53, 58 53, 59 56 C60 59, 61 67, 61 78 C61 85, 59 91, 57 95" stroke="#111111" stroke-width="0.8" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M43 65 C44 71, 45 78, 47 85 C48 91, 49 94, 50 96" stroke="#111111" stroke-width="0.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>

        <!-- En-tête -->
        <div class="cert-brand">
            <svg class="w-8 h-8" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 32px; height: 32px;">
                <circle cx="50" cy="50" r="46" stroke="#111111" stroke-width="3.5" />
                <line x1="33" y1="31" x2="62" y2="25" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="49" y2="53" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="14" y2="13" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="42" y2="11" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="20" y2="53" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                <line x1="49" y1="53" x2="62" y2="25" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
                <circle cx="62" cy="25" r="6" fill="#111111" />
                <circle cx="49" cy="53" r="6" fill="#111111" />
                <circle cx="33" cy="31" r="6" fill="#111111" />
                <circle cx="14" cy="13" r="6" fill="#111111" />
                <circle cx="42" cy="11" r="6" fill="#111111" />
                <circle cx="20" cy="53" r="6" fill="#111111" />
                <path d="M56 10 C52 14, 52 24, 52 29 C52 31, 50 33, 49 33 L45 33 L49 35 C50 37, 51 38, 50 40 C49 41, 47 42, 49 44 C51 45, 54 46, 56 46 C59 46, 65 38, 66 41 C68 46, 60 52, 56 60 C51 68, 50 78, 53 88" stroke="#111111" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M33 55 C32 52, 32 48, 33 46 C34 44, 36 44, 37 47 C37 50, 37 53, 37 55 C37 51, 38 46, 39 44 C40 42, 42 42, 43 45 C43 48, 43 51, 43 54 C43 51, 44 47, 45 45 C46 43, 48 43, 49 46 C50 49, 51 57, 51 68 C51 75, 49 81, 47 85" stroke="#111111" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M33 55 C34 61, 35 68, 37 75 C38 81, 39 84, 40 86" stroke="#111111" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <div>
                <div class="cert-brand-name">StudyVibe</div>
                <div class="cert-brand-tag">Plateforme Académique</div>
            </div>
        </div>

        <!-- Corps -->
        <div class="cert-body">
            <p class="cert-label">Ce certificat est décerné à</p>
            <h1 class="cert-recipient"><?= htmlspecialchars($cert['student_name']) ?></h1>
            <p class="cert-module-label">Pour la validation complète du cours</p>
            <h2 class="cert-module"><?= htmlspecialchars($cert['course_title'] ?? 'Cours Inconnu') ?></h2>
            <p class="cert-desc">
                En reconnaissance de la réussite à l'ensemble des évaluations du cours, avec un score supérieur ou égal au seuil réglementaire de 80%.
            </p>
        </div>

        <!-- Pied -->
        <div class="cert-footer">
            <div class="cert-meta">
                Date de délivrance
                <strong><?= date('d/m/Y', strtotime($cert['issued_at'])) ?></strong>
            </div>
            <div class="cert-meta" style="text-align:center">
                Code de validation
                <span class="cert-code" style="display:block;margin-top:0.2rem"><?= htmlspecialchars($cert['certificate_code']) ?></span>
            </div>
            <div class="cert-qr">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=70x70&data=<?= urlencode($verifyUrl) ?>"
                     alt="QR Code de vérification">
                <div class="cert-qr-label">Vérifier l'authenticité</div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
document.getElementById('btn-download-pdf')?.addEventListener('click', () => {
    const el = document.querySelector('.cert-page');
    html2pdf().set({
        margin: 0,
        filename: 'certificat-<?= htmlspecialchars($cert['certificate_code']) ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
    }).from(el).save();
});
</script>
</body>
</html>
