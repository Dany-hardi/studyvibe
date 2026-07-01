<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$user = getCurrentUser();
$webinarId = (int)($_GET['webinar_id'] ?? $_POST['webinar_id'] ?? 0);
if ($webinarId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Webinaire invalide']);
    exit;
}

$pdo = Database::getInstance();

try {
    // Récupérer la fiche de présence
    $stmt = $pdo->prepare("SELECT id, total_minutes_present, last_seen_at FROM webinar_attendance WHERE webinar_id = :wid AND student_id = :sid");
    $stmt->execute(['wid' => $webinarId, 'sid' => $user['id']]);
    $att = $stmt->fetch();

    if (!$att) {
        $stmt = $pdo->prepare("INSERT INTO webinar_attendance (webinar_id, student_id, joined_at, last_seen_at, total_minutes_present) VALUES (:wid, :sid, NOW(), NOW(), 0)");
        $stmt->execute(['wid' => $webinarId, 'sid' => $user['id']]);
        $totalMinutes = 0;
    } else {
        $lastSeen = strtotime($att['last_seen_at']);
        $diff = time() - $lastSeen;
        
        // Si la dernière activité remonte à moins de 5 minutes, on calcule précisément la présence acquise
        $increment = 0.5; // Heartbeat à 30s par défaut
        if ($diff > 0 && $diff < 300) {
            $increment = round($diff / 60, 2);
        }
        
        $stmt = $pdo->prepare("
            UPDATE webinar_attendance 
            SET last_seen_at = NOW(), total_minutes_present = total_minutes_present + :inc
            WHERE id = :id
        ");
        $stmt->execute(['inc' => $increment, 'id' => $att['id']]);
        
        // Récupérer le score actualisé
        $stmt = $pdo->prepare("SELECT total_minutes_present FROM webinar_attendance WHERE id = :id");
        $stmt->execute(['id' => $att['id']]);
        $totalMinutes = round((float)$stmt->fetchColumn(), 1);
    }

    echo json_encode(['success' => true, 'total_minutes' => $totalMinutes]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
