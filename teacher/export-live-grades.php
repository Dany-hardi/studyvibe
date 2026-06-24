<?php
declare(strict_types=1);

/**
 * Export Excel des notes participants pour une séance de téléévaluation (enseignant).
 */

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

    // Récupérer les inscrits et leurs notes
    $stmt = $pdo->prepare("
        SELECT name, email, score, registered_at
        FROM live_eval_registrations
        WHERE session_id = :sid
        ORDER BY name ASC
    ");
    $stmt->execute(['sid' => $sessionId]);
    $registrations = $stmt->fetchAll();

    $rows = [];
    foreach ($registrations as $r) {
        $rows[] = [
            $r['name'],
            $r['email'],
            $r['score'] !== null ? (float)$r['score'] . '%' : 'Non finalisé',
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

    auditLog('export_live_grades', "Session #{$sessionId}");
    SpreadsheetExporter::sendDownload($filename, $sheets);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Erreur serveur lors de la génération du rapport.');
}
