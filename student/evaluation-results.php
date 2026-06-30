<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

// Exiger que l'utilisateur soit connecté
if (!isLoggedIn()) {
    header('Location: /index.php?error=auth_required');
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

// Charger l'inscription avec les détails de la séance
try {
    $stmt = $pdo->prepare("
        SELECT r.*, s.title AS session_title, s.course_id, c.title AS course_title
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

// Charger les réponses soumises et les questions associées
try {
    $stmt = $pdo->prepare("
        SELECT q.id AS question_id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option, q.explanation, a.selected_option
        FROM live_eval_answers a
        JOIN live_eval_questions q ON a.question_id = q.id
        WHERE a.registration_id = :reg_id
        ORDER BY q.id ASC
    ");
    $stmt->execute(['reg_id' => $regId]);
    $answers = $stmt->fetchAll();
} catch (PDOException $e) {
    dieSafe("Erreur serveur lors du chargement des réponses.");
}

// Calculer les métriques locales
$totalQuestions = count($answers);
$correctCount = 0;
foreach ($answers as $ans) {
    if ($ans['selected_option'] === $ans['correct_option']) {
        $correctCount++;
    }
}
$scorePercent = $totalQuestions > 0 ? ($correctCount / $totalQuestions) * 100 : 0;
$hasPassed = $scorePercent >= 50;

?>
<!DOCTYPE html>
<html lang="fr" class="sv-cream">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport d'Évaluation — StudyVibe</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#004B23',
                        brandHover: '#003d1c',
                        ink: '#111111',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        serif: ['Plus Jakarta Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Bibliothèques KaTeX pour le rendu des formules mathématiques et caractères spéciaux en LaTeX -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js" onload="renderMath()"></script>

    <style>
        .latex-container {
            font-size: 1.05rem;
        }
    </style>
</head>
<body class="bg-[#FAFAFA] text-[#111111] font-sans antialiased min-h-screen pb-16">
    <!-- Navbar -->
    <nav class="bg-white border-b border-[#E5E5E7] px-8 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="font-serif text-xl font-bold tracking-tight text-brand">StudyVibe</span>
            <span class="text-xs text-gray-300 font-light">|</span>
            <span class="text-xs text-gray-500 font-medium">Rapport d'évaluation</span>
        </div>
        <div>
            <?php if ($currentUser['role'] === 'student'): ?>
                <a href="/student/dashboard.php" class="text-xs font-semibold uppercase tracking-wider text-brand hover:text-brandHover flex items-center gap-1.5 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Mon Dashboard</span>
                </a>
            <?php else: ?>
                <button onclick="window.close();" class="text-xs font-semibold uppercase tracking-wider text-gray-600 hover:text-gray-900 transition-colors">Fermer l'onglet</button>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="max-w-4xl mx-auto px-4 mt-12 space-y-8">
        <!-- Hero card / Overview -->
        <div class="bg-white border border-[#E5E5E7] rounded-sm p-8 flex flex-col md:flex-row items-center justify-between gap-6 shadow-sm">
            <div class="space-y-2 text-center md:text-left">
                <span class="text-[10px] uppercase font-bold text-brand tracking-widest bg-green-50 border border-green-200/50 px-2.5 py-1 rounded-full">Résultats Officiels</span>
                <h1 class="font-serif text-3xl font-light text-gray-900 mt-2"><?= htmlspecialchars($registration['session_title']) ?></h1>
                <p class="text-xs text-gray-500">
                    Cours : <strong class="text-gray-800 font-medium"><?= htmlspecialchars($registration['course_title']) ?></strong> | Candidat : <strong class="text-gray-800 font-medium"><?= htmlspecialchars($registration['name']) ?></strong>
                </p>
            </div>

            <!-- Score badge -->
            <div class="flex flex-col items-center justify-center bg-[#FAFAFA] border border-[#E5E5E7] rounded-sm p-6 min-w-[200px]">
                <span class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold mb-1">Score final</span>
                <span class="text-4xl font-light <?= $hasPassed ? 'text-brand' : 'text-red-600' ?>"><?= $correctCount ?> / <?= $totalQuestions ?></span>
                <span class="text-xs font-semibold mt-1 <?= $hasPassed ? 'text-brand' : 'text-red-600' ?>"><?= round($scorePercent, 1) ?> %</span>
                
                <span class="mt-4 px-3 py-1 text-[10px] uppercase tracking-wider font-bold rounded-sm border <?= $hasPassed ? 'bg-green-50 text-brand border-green-200/50' : 'bg-red-50 text-red-600 border-red-200/50' ?>">
                    <?= $hasPassed ? 'Validé' : 'Non validé' ?>
                </span>
            </div>
        </div>

        <!-- Section header -->
        <div class="flex items-center justify-between border-b border-[#E5E5E7] pb-3">
            <h2 class="font-serif text-xl font-light text-gray-900">Analyse détaillée des réponses</h2>
            <span class="text-xs text-gray-400 font-medium"><?= $totalQuestions ?> Questions</span>
        </div>

        <!-- Questions loop -->
        <div class="space-y-6">
            <?php foreach ($answers as $index => $qa): 
                $num = $index + 1;
                $isCorrect = $qa['selected_option'] === $qa['correct_option'];
            ?>
                <div class="bg-white border border-[#E5E5E7] rounded-sm p-6 space-y-4 shadow-sm relative overflow-hidden">
                    <!-- Status indicator line on the left border -->
                    <div class="absolute left-0 top-0 bottom-0 w-1.5 <?= $isCorrect ? 'bg-brand' : 'bg-red-500' ?>"></div>
                    
                    <!-- Question header -->
                    <div class="flex items-start justify-between gap-4">
                        <h3 class="font-serif text-lg font-light text-gray-900 latex-container">
                            <span class="font-mono text-xs font-bold text-gray-400 mr-2">Q<?= $num ?></span>
                            <?= htmlspecialchars($qa['question_text']) ?>
                        </h3>
                        <span class="flex-shrink-0 px-2 py-0.5 text-[10px] uppercase font-bold rounded-sm border <?= $isCorrect ? 'bg-green-50 text-brand border-green-200/50' : 'bg-red-50 text-red-600 border-red-200/50' ?>">
                            <?= $isCorrect ? 'Correct' : 'Incorrect' ?>
                        </span>
                    </div>

                    <!-- Options list -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                        <?php foreach (['A', 'B', 'C', 'D'] as $opt): 
                            $optText = $qa['option_' . strtolower($opt)] ?? '';
                            if (empty($optText)) continue;
                            
                            $isCorrectOpt = $opt === $qa['correct_option'];
                            $isSelectedOpt = $opt === $qa['selected_option'];
                            
                            $bgClass = 'bg-white border-[#E5E5E7] text-gray-700';
                            $icon = '';
                            if ($isCorrectOpt) {
                                $bgClass = 'bg-green-50/50 border-[#A2D190] text-[#385723] font-medium';
                                $icon = '<svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>';
                            } elseif ($isSelectedOpt) {
                                $bgClass = 'bg-red-50/50 border-[#F8CBAD] text-red-700';
                                $icon = '<svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>';
                            }
                        ?>
                            <div class="border rounded-sm p-3.5 flex items-center justify-between text-xs transition-all <?= $bgClass ?>">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold uppercase text-[10px] px-1.5 py-0.5 bg-black/5 rounded-sm"><?= $opt ?></span>
                                    <span class="latex-container"><?= htmlspecialchars($optText) ?></span>
                                </div>
                                <?php if ($icon): ?>
                                    <span class="flex-shrink-0"><?= $icon ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Explanation/Justification block -->
                    <?php if (!empty($qa['explanation'])): ?>
                        <div class="bg-blue-50/40 border border-blue-200/50 rounded-sm p-4 mt-4 flex items-start gap-3">
                            <div class="w-8 h-8 bg-blue-100/80 rounded-full flex items-center justify-center text-blue-700 flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                            <div class="space-y-1">
                                <span class="text-[10px] uppercase font-bold text-blue-700 tracking-wider">Justification de l'enseignant</span>
                                <div class="text-xs text-gray-700 leading-relaxed latex-container">
                                    <?= nl2br(htmlspecialchars($qa['explanation'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Footer script -->
    <script>
        function renderMath() {
            if (typeof renderMathInElement === 'function') {
                renderMathInElement(document.body, {
                    delimiters: [
                        {left: '$$', right: '$$', display: true},
                        {left: '$', right: '$', display: false},
                        {left: '\\(', right: '\\)', display: false},
                        {left: '\\[', right: '\\]', display: true}
                    ],
                    throwOnError: false
                });
            }
        }
    </script>
</body>
</html>
