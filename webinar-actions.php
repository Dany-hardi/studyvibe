<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$user = getCurrentUser();
$action = $_GET['action'] ?? '';
$pdo = Database::getInstance();

if ($action === 'get_qa') {
    $webinarId = (int)($_GET['webinar_id'] ?? 0);
    if ($webinarId <= 0) {
        echo json_encode([]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT q.*, u.name AS student_name
            FROM webinar_qa q
            JOIN users u ON q.student_id = u.id
            WHERE q.webinar_id = :wid
            ORDER BY q.votes DESC, q.created_at DESC
        ");
        $stmt->execute(['wid' => $webinarId]);
        $rows = $stmt->fetchAll();
        
        // Formater les dates de manière humaine
        foreach ($rows as &$r) {
            $diff = time() - strtotime($r['created_at']);
            if ($diff < 60) {
                $r['time_ago'] = "À l'instant";
            } elseif ($diff < 3600) {
                $r['time_ago'] = "Il y a " . floor($diff / 60) . " min";
            } else {
                $r['time_ago'] = "Il y a " . floor($diff / 3600) . " h";
            }
        }
        
        echo json_encode($rows);
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

if ($action === 'add_qa') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'POST requis']);
        exit;
    }
    
    $webinarId = (int)($_POST['webinar_id'] ?? 0);
    $text = trim((string)($_POST['question_text'] ?? ''));
    
    if ($webinarId <= 0 || empty($text)) {
        echo json_encode(['success' => false, 'error' => 'Champs manquants']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO webinar_qa (webinar_id, student_id, question_text, votes, is_answered)
            VALUES (:wid, :sid, :txt, 0, 0)
        ");
        $stmt->execute([
            'wid' => $webinarId,
            'sid' => $user['id'],
            'txt' => $text
        ]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'vote_qa') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'POST requis']);
        exit;
    }
    
    $qaId = (int)($_GET['id'] ?? 0);
    if ($qaId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID question invalide']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE webinar_qa SET votes = votes + 1 WHERE id = :id");
        $stmt->execute(['id' => $qaId]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Action inconnue']);
