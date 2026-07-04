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
        SELECT name, email, score, registered_at
        FROM live_eval_registrations
        WHERE session_id = :sid
        ORDER BY CASE WHEN score IS NULL THEN 1 ELSE 0 END, score DESC, name ASC
    ");
    $stmt->execute(['sid' => $sessionId]);
    $registrations = $stmt->fetchAll();

    $rows = [];
    foreach ($registrations as $r) {
        if ($r['score'] !== null) {
            $rawScore = round(((float)$r['score'] / 100) * $totalQuestions);
            $scoreDisplay = "{$rawScore} / {$totalQuestions}";
        } else {
            $scoreDisplay = 'Non finalisé';
        }
        $rows[] = [
            $r['name'],
            $r['email'],
            $scoreDisplay,
            $r['registered_at']
        ];
    }

    $sheets = [
        [
            'name' => 'Résultats',
            'headers' => ['Nom complet', 'Adresse e-mail', 'Score final', 'Date d\'inscription'],
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
