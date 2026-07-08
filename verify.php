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
                   c.title AS course_title, c.description AS course_description,
                   m.title AS module_title,
                   t.name AS teacher_name
            FROM certificates cert
            JOIN users u ON cert.student_id = u.id
            LEFT JOIN courses c ON cert.course_id = c.id
            LEFT JOIN modules m ON cert.module_id = m.id
            LEFT JOIN users t ON c.teacher_id = t.id
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
        <!-- ── Valide (Stunning Certificate Verification Card) ── -->
        <div class="bg-white border border-[#E5E5E7] rounded-sm shadow-xl overflow-hidden relative">
            <!-- Top brand band with verification badge -->
            <div class="bg-[#EAF2EC] border-b border-[#004B23]/10 px-8 py-4 flex justify-between items-center">
                <div class="flex items-center gap-2 text-[#004B23]">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l5-5z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-xs font-mono font-bold uppercase tracking-widest">Statut : Certificat Authentique</span>
                </div>
                <span class="text-[10px] font-mono text-[#555555] uppercase tracking-wider">Vérifié le <?= date('d/m/Y') ?></span>
            </div>

            <!-- Content Area -->
            <div class="p-8 space-y-8 relative">
                <!-- Academic Seal Decorative Watermark -->
                <div class="absolute right-6 top-6 opacity-[0.03] pointer-events-none">
                    <svg class="w-32 h-32 text-black" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H7c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.04-.42 1.99-1.07 2.75z"/>
                    </svg>
                </div>

                <!-- Recipient Info Header -->
                <div class="space-y-2">
                    <span class="text-[10px] font-mono text-gray-400 uppercase tracking-widest block">Récipiendaire académique</span>
                    <h1 class="font-serif text-3xl font-normal text-gray-900 tracking-tight">
                        <?= htmlspecialchars($cert['student_name']) ?>
                    </h1>
                    <p class="text-sm font-light text-gray-500">
                        A complété avec succès le parcours académique et les examens requis par la plateforme d'apprentissage StudyVibe.
                    </p>
                </div>

                <!-- Technical Details Table/Grid -->
                <div class="border-t border-[#E5E5E7] pt-6">
                    <h3 class="text-xs font-mono text-gray-400 uppercase tracking-widest mb-4">Informations Clés de Certification</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-8 text-sm">
                        <!-- Cours Validé -->
                        <div class="space-y-1">
                            <span class="text-[11px] text-[#888888] font-medium block">Cours Validé</span>
                            <span class="font-serif text-base text-[#111111] font-semibold block">
                                <?= htmlspecialchars($cert['course_title'] ?? 'N/A') ?>
                            </span>
                        </div>

                        <!-- Instructeur -->
                        <div class="space-y-1">
                            <span class="text-[11px] text-[#888888] font-medium block">Instructeur Titulaire</span>
                            <span class="text-gray-800 font-medium block">
                                <?= htmlspecialchars($cert['teacher_name'] ?? 'Non assigné') ?>
                            </span>
                        </div>

                        <!-- Date d'émission -->
                        <div class="space-y-1">
                            <span class="text-[11px] text-[#888888] font-medium block">Date de Délivrance</span>
                            <span class="text-gray-800 block">
                                <?= date('d/m/Y \à H:i', strtotime($cert['issued_at'])) ?>
                            </span>
                        </div>

                        <!-- Code Unique -->
                        <div class="space-y-1">
                            <span class="text-[11px] text-[#888888] font-medium block">Code de Validation Unique</span>
                            <span class="font-mono text-xs text-[#004B23] font-bold block bg-green-50 px-2 py-0.5 rounded-sm inline-block border border-green-100">
                                <?= htmlspecialchars($cert['certificate_code']) ?>
                            </span>
                        </div>

                        <!-- Mode de Délivrance -->
                        <div class="space-y-1 md:col-span-2">
                            <span class="text-[11px] text-[#888888] font-medium block">Méthode de Délivrance</span>
                            <span class="text-gray-600 font-light block text-xs">
                                <?php if ($cert['manual_issue']): ?>
                                    Attribution manuelle à titre exceptionnel par l'autorité académique compétente.
                                <?php else: ?>
                                    Validation automatique suite à la réussite aux évaluations requises avec un score d'au moins 80%.
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Footer with QR scan confirmation and Action links -->
                <div class="border-t border-[#E5E5E7] pt-6 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="flex items-center gap-4">
                        <canvas id="verify-qr" width="70" height="70" class="border border-[#E5E5E7] p-1 bg-white" aria-label="QR code de vérification"></canvas>
                        <div class="text-left">
                            <span class="text-[10px] font-mono text-gray-400 block uppercase tracking-wider">Preuve Numérique</span>
                            <span class="text-xs text-gray-500 font-light block">Ce QR code redirige vers cette page de registre officielle.</span>
                        </div>
                    </div>
                    <?php if (isLoggedIn() && getCurrentUser()['role'] === 'student' && (int)getCurrentUser()['id'] === (int)$cert['student_id']): ?>
                        <a href="/certificate.php?code=<?= urlencode($cert['certificate_code']) ?>" target="_blank"
                           class="inline-block px-4 py-2 bg-[#111111] text-white text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] rounded-sm transition-colors shadow-sm">
                            Afficher le diplôme officiel
                        </a>
                    <?php endif; ?>
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
