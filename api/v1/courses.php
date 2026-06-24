<?php
declare(strict_types=1);
require_once __DIR__ . '/../../auth.php';

header('Content-Type: application/json');

// Vérification clé API
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (empty($apiKey)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Clé API manquante.']);
    exit;
}

try {
    $pdo = Database::getInstance();
    
    // Vérifie que la clé existe en base
    $hash = hash('sha256', $apiKey);
    $stmt = $pdo->prepare("SELECT id FROM api_keys WHERE key_hash = :hash");
    $stmt->execute(['hash' => $hash]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Clé API invalide.']);
        exit;
    }
    
    $courses = $pdo->query("
        SELECT c.id, c.title, c.description, m.title AS module, u.name AS teacher
        FROM courses c
        JOIN modules m ON c.module_id = m.id
        LEFT JOIN users u ON c.teacher_id = u.id
        ORDER BY c.id DESC
    ")->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $courses]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}