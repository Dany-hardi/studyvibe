<?php
/**
 * StudyVibe Academic LMS - Live Grades Export Controller
 *
 * This controller compiles grades from a specific live evaluation session, computes
 * totals, formats them, and dispatches them as an Excel download.
 *
 * PHP version 8.2
 *
 * @category  Controller
 * @package   StudyVibe\Teacher
 * @author    StudyVibe Team <development@studyvibe.academic>
 * @copyright 2026 StudyVibe
 * @license   Proprietary
 * @link      https://studyvibe.academic
 */

declare(strict_types=1);

// =========================================================================
// SECTION 1: AUTHENTICATION & ACCESS GATING CHECKS
// =========================================================================

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/SpreadsheetExporter.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== 'teacher') {
    header('Location: /index.php');
    exit;
}

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$teacherId = (int)$_SESSION['user_id'];

if ($sessionId <= 0) {
    http_response_code(400);
    exit('Identifiant de séance non valide.');
}

// =========================================================================
// SECTION 2: REPORT METRICS COMPILATION
// =========================================================================

try {
    $pdo = Database::getInstance();

    // Vérifier l'accès à la session
    $stmt = $pdo->prepare("
        SELECT s.*, c.title AS course_title
        FROM live_eval_sessions s
        JOIN courses c ON s.course_id = c.id
        WHERE s.id = :sid AND s.teacher_id = :tid
    ");
    $stmt->execute(['sid' => $sessionId, 'tid' => $teacherId]);
    $session = $stmt->fetch();

    if (!$session) {
        http_response_code(403);
        exit('Séance introuvable ou non autorisée.');
    }

    // Récupérer le nombre total de questions
    $qCountStmt = $pdo->prepare("SELECT COUNT(*) FROM live_eval_questions WHERE session_id = :sid");
    $qCountStmt->execute(['sid' => $sessionId]);
    $totalQuestions = (int)$qCountStmt->fetchColumn();

    // Récupérer les inscrits et leurs notes
    $stmt = $pdo->prepare("
        SELECT name, email, score, registered_at, cancelled_at
        FROM live_eval_registrations
        WHERE session_id = :sid
        ORDER BY CASE WHEN score IS NULL THEN 1 ELSE 0 END, score DESC, name ASC
    ");
    $stmt->execute(['sid' => $sessionId]);
    $registrations = $stmt->fetchAll();

    require_once __DIR__ . '/../lib/ExportDocs.php';
    $rows = [];
    foreach ($registrations as $r) {
        if ($r['score'] !== null) {
            $raw = (int)round(((float)$r['score'] / 100) * $totalQuestions);
            $rows[] = [$r['name'], $r['email'], $raw, $totalQuestions, round((float)$r['score'], 1), (float)$r['score'] >= 50 ? 'Admis' : 'Ajourné', $r['registered_at']];
        } else {
            $rows[] = [$r['name'], $r['email'], null, $totalQuestions, null, !empty($r['cancelled_at']) ? 'Annulé' : 'Non rendu', $r['registered_at']];
        }
    }
    $st = ExportDocs::statistics($registrations);

    $sheets = [
        [
            'name' => 'Résultats',
            'title' => (string)$session['title'],
            'meta' => [
                ['Cours', (string)$session['course_title']],
                ['Inscrits / copies rendues', $st['total'] . ' / ' . $st['submitted']],
                ['Moyenne', str_replace('.', ',', (string)round($st['mean'], 1)) . ' %'],
                ['Taux de réussite (seuil 50 %)', str_replace('.', ',', (string)round($st['pass_rate'], 1)) . ' %'],
            ],
            'headers' => ['Nom complet', 'Adresse e-mail', 'Bonnes réponses', 'Sur', 'Note (%)', 'Résultat', 'Date d\'inscription'],
            'formats' => [2 => 'int', 3 => 'int', 4 => 'pct'],
            'rows' => $rows
        ]
    ];

    $slug = preg_replace('/[^a-z0-9_-]+/i', '_', (string)$session['title']) ?: 'live_eval';
    $filename = 'resultats_' . mb_strtolower($slug) . '_' . date('Y-m-d');

    // =========================================================================
    // SECTION 3: SPREADSHEET DISPATCH
    // =========================================================================

    auditLog('export_live_grades', "Session #{$sessionId}");
    SpreadsheetExporter::sendDownload($filename, $sheets);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Erreur serveur lors de la génération du rapport.');
}
