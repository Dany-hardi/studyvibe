<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
requireRole('student');

$user = getCurrentUser();
$pdo  = Database::getInstance();

$courses = [];
$lessonScores = [];
$failedAttempts = [];

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
} catch (PDOException) {
    header('Location: /student/dashboard.php');
    exit;
}

$genDate = date('d/m/Y à H:i');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relevé de notes — StudyVibe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #EAE6DF; color: #1A1A1A; }
        .no-print { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; background: #fff; border-bottom: 1px solid #D5D0C8; flex-wrap: wrap; gap: 0.75rem; }
        .no-print a { font-size: 0.75rem; color: #555; text-decoration: none; text-transform: uppercase; letter-spacing: 0.08em; }
        .no-print button { padding: 0.6rem 1.25rem; background: #1A1A1A; color: #fff; border: none; cursor: pointer; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .no-print button.primary { background: #004B23; }
        .wrap { display: flex; justify-content: center; padding: 2rem 1rem; }
        .page { width: 210mm; background: #fff; border: 1px solid #8A857C; padding: 2.5rem 3rem; position: relative; overflow: hidden; }
        .brand { font-family: 'Playfair Display', serif; font-size: 1.35rem; color: #004B23; display: flex; align-items: center; gap: 0.5rem; position: relative; z-index: 2; }
        .sub { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.12em; color: #8A8A8A; margin: 0.25rem 0 2rem; position: relative; z-index: 2; }
        h1 { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 400; margin-bottom: 0.35rem; position: relative; z-index: 2; }
        .meta { font-size: 0.8125rem; color: #5C5C5C; margin-bottom: 2rem; line-height: 1.6; position: relative; z-index: 2; }
        h2 { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.12em; color: #8A8A8A; margin: 1.75rem 0 0.75rem; border-bottom: 1px solid #EAE6DF; padding-bottom: 0.5rem; position: relative; z-index: 2; }
        table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; position: relative; z-index: 2; }
        th, td { padding: 0.5rem 0.35rem; text-align: left; border-bottom: 1px solid #EAE6DF; }
        th { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; color: #8A8A8A; font-weight: 600; }
        .fail { color: #C62828; font-weight: 600; }
        .footer { margin-top: 2.5rem; font-size: 0.65rem; color: #8A8A8A; text-align: center; position: relative; z-index: 2; }
        
        /* Filigrane transparent */
        .releve-watermark {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 320px; height: 320px;
            opacity: 0.025;
            pointer-events: none;
            z-index: 1;
        }

        @media print {
            .no-print { display: none; }
            body { background: #fff; }
            .wrap { padding: 0; }
            .releve-watermark { opacity: 0.025; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <a href="/student/dashboard.php">← Tableau de bord</a>
        <div style="display:flex;gap:0.5rem;">
            <button type="button" onclick="window.print()">Imprimer</button>
            <button type="button" class="primary" id="btn-pdf">Télécharger PDF</button>
        </div>
    </div>
    <div class="wrap">
        <div class="page" id="releve-content">
            <!-- Filigrane Sceau d'Esprit Pensant Épuré (Monochrome Académique) -->
            <svg class="releve-watermark" viewBox="0 0 120 120" fill="none" stroke="#111111" stroke-width="0.8" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.03;">
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

            <div class="brand">
                <svg class="w-6 h-6" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 24px; height: 24px;">
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
                StudyVibe
            </div>
            <div class="sub">Relevé de notes académique</div>
            <h1><?= htmlspecialchars($user['name']); ?></h1>
            <p class="meta">Document généré le <?= $genDate; ?><br>Ce relevé récapitule votre progression et vos tentatives de certification.</p>

            <h2>Progression par cours</h2>
            <?php if (empty($courses)): ?>
                <p style="font-size:0.875rem;color:#8A8A8A;">Aucune inscription enregistrée.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>Cours</th><th>Module</th><th>Progression</th><th>Meilleur score certif.</th><th>Tentatives</th></tr></thead>
                <tbody>
                <?php foreach ($courses as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['course_title']); ?></td>
                        <td><?= htmlspecialchars($c['module_title']); ?></td>
                        <td><?= (int)$c['progress_percent']; ?> %</td>
                        <td><?= $c['best_score'] !== null ? number_format((float)$c['best_score'], 1) . ' %' : '—'; ?></td>
                        <td><?= (int)$c['attempts']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if (!empty($lessonScores)): ?>
            <h2>Leçons complétées</h2>
            <table>
                <thead><tr><th>Leçon</th><th>Cours</th><th>Score</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($lessonScores as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['lesson_title']); ?></td>
                        <td><?= htmlspecialchars($l['course_title']); ?></td>
                        <td><?= (int)$l['score']; ?> %</td>
                        <td><?= date('d/m/Y', strtotime((string)$l['completed_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if (!empty($failedAttempts)): ?>
            <h2>Tentatives de certification non validées</h2>
            <table>
                <thead><tr><th>Cours</th><th>Date</th><th>Score</th><th>Résultat</th></tr></thead>
                <tbody>
                <?php foreach ($failedAttempts as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['course_title']); ?></td>
                        <td><?= date('d/m/Y H:i', strtotime((string)$a['attempted_at'])); ?></td>
                        <td class="fail"><?= number_format((float)$a['score'], 1); ?> %</td>
                        <td class="fail">Échec (seuil 80 %)</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size:0.75rem;color:#8A8A8A;margin-top:0.75rem;">Les relevés détaillés par tentative sont disponibles dans votre espace apprenant.</p>
            <?php endif; ?>

            <div class="footer">StudyVibe Academic LMS — Document non contractuel</div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        document.getElementById('btn-pdf')?.addEventListener('click', () => {
            html2pdf().set({
                margin: 8,
                filename: 'releve-notes-studyvibe.pdf',
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            }).from(document.getElementById('releve-content')).save();
        });
    </script>
</body>
</html>
