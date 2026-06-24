<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$code  = trim((string)($_GET['code'] ?? ''));
$cert  = null;
$error = '';

if (empty($code)) {
    $error = 'Aucun code de certificat fourni.';
} else {
    try {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT cert.*, u.name AS student_name,
                   m.title AS module_title, m.description AS module_description
            FROM certificates cert
            JOIN users u ON cert.student_id = u.id
            JOIN modules m ON cert.module_id = m.id
            WHERE cert.certificate_code = :code
        ");
        $stmt->execute(['code' => $code]);
        $cert = $stmt->fetch();
        if (!$cert) $error = 'Aucun certificat ne correspond à ce code de validation.';
    } catch (PDOException) {
        $error = 'Erreur serveur. Veuillez réessayer.';
    }
}

$verifyUrl = APP_URL . '/verify.php?code=' . urlencode($code);
?>
<!DOCTYPE html>
<html lang="fr" class="sv-cream">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de Certificat — StudyVibe</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif'],serif:['Playfair Display','serif']}}}}</script>
</head>
<body class="font-sans antialiased text-[#111111] sv-page min-h-screen flex flex-col">

<header class="border-b border-[#E5E5E7] py-5 px-12 flex justify-between items-center bg-[#FFFFFF]">
    <a href="/index.php" class="flex items-center gap-3">
        <svg class="w-7 h-7 text-[#004B23]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
        <span class="font-serif text-xl font-semibold">StudyVibe</span>
    </a>
    <span class="text-[10px] font-mono uppercase tracking-widest text-[#888888]">Registre de Vérification</span>
</header>

<main class="flex-grow flex items-center justify-center p-8">
    <div class="max-w-xl w-full space-y-6">

        <?php if ($error): ?>
        <!-- ── Invalide ── -->
        <div class="bg-[#FFFFFF] border border-[#D32F2F] p-12 text-center space-y-4">
            <div class="text-4xl text-[#D32F2F]">✕</div>
            <h1 class="font-serif text-2xl font-light text-[#D32F2F]">Certificat non reconnu</h1>
            <p class="text-sm font-light text-[#555555]"><?= htmlspecialchars($error) ?></p>
            <?php if ($code): ?>
                <p class="text-xs font-mono text-[#888888]">Code vérifié : <strong><?= htmlspecialchars($code) ?></strong></p>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- ── Valide ── -->
        <div class="bg-[#FFFFFF] border-2 border-[#004B23] p-10 space-y-8 text-center">
            <div>
                <div class="text-xs font-mono uppercase tracking-widest text-[#004B23] font-semibold mb-1">✓ Certificat Authentique</div>
                <p class="text-xs text-[#888888] font-light">Vérifié dans le registre officiel StudyVibe</p>
            </div>

            <div class="border-t border-b border-[#E5E5E7] py-8 space-y-3">
                <p class="text-xs font-light text-[#888888] uppercase tracking-wider">Décerné à</p>
                <h1 class="font-serif text-3xl font-light"><?= htmlspecialchars($cert['student_name']) ?></h1>
            </div>

            <div class="space-y-1">
                <p class="text-xs uppercase tracking-wider text-[#888888]">Pour la validation du module</p>
                <h2 class="font-serif text-xl font-medium"><?= htmlspecialchars($cert['module_title']) ?></h2>
                <?php if ($cert['module_description']): ?>
                    <p class="text-xs font-light text-[#555555] max-w-sm mx-auto"><?= htmlspecialchars($cert['module_description']) ?></p>
                <?php endif; ?>
            </div>

            <div class="flex justify-center items-start gap-10 pt-2">
                <div class="text-center">
                    <div class="text-[10px] uppercase tracking-wider text-[#888888] mb-1">Date de délivrance</div>
                    <div class="font-semibold text-sm"><?= date('d/m/Y', strtotime($cert['issued_at'])) ?></div>
                </div>
                <div class="text-center">
                    <div class="text-[10px] uppercase tracking-wider text-[#888888] mb-1">Code de validation</div>
                    <div class="font-mono font-semibold text-sm text-[#004B23]"><?= htmlspecialchars($cert['certificate_code']) ?></div>
                </div>
                <div class="text-center">
                    <div class="text-[10px] uppercase tracking-wider text-[#888888] mb-1">Code QR</div>
                    <canvas id="verify-qr" width="80" height="80" class="border border-[#E5E5E7] p-1 mx-auto" aria-label="QR code de vérification"></canvas>
                    <div class="text-[10px] text-[#888888] mt-1">Scanner pour vérifier</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Formulaire de recherche ── -->
        <div class="bg-[#FFFFFF] border border-[#E5E5E7] p-8 space-y-4">
            <h3 class="font-serif text-lg font-light">Vérifier un autre certificat</h3>
            <form action="/verify.php" method="GET" class="flex gap-3">
                <input type="text" name="code" placeholder="ex: SV-1-A3F7B2C8"
                       value="<?= htmlspecialchars($code) ?>"
                       class="flex-1 px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm font-mono">
                <button type="submit"
                    class="px-5 py-2 bg-[#111111] text-[#FFFFFF] text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] rounded-sm">
                    Vérifier
                </button>
            </form>
        </div>
    </div>
</main>

<footer class="border-t border-[#E5E5E7] py-4 px-12 text-center text-xs text-[#888888] font-light bg-[#FFFFFF]">
    StudyVibe — Registre Public des Certifications
</footer>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1/build/qrcode.min.js"></script>
<script>
if (typeof QRCode !== 'undefined') {
    QRCode.toCanvas(document.getElementById('verify-qr'), <?= json_encode($verifyUrl); ?>, { width: 80, margin: 1 });
}
</script>
<script src="/assets/js/app.js"></script>
</body>
</html>
