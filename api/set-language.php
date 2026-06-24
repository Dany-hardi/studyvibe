<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Database.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$lang = strtolower(trim((string)($data['lang'] ?? '')));

if (!in_array($lang, ['fr', 'en'], true)) {
    echo json_encode(['success' => false, 'message' => 'Langue non supportée.']);
    exit;
}

$_SESSION['user_lang'] = $lang;
setcookie('studyvibe_lang', $lang, [
    'expires' => time() + (365 * 24 * 60 * 60), // 1 an
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (isset($_SESSION['user_id'])) {
    try {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("UPDATE users SET lang = :lang WHERE id = :id");
        $stmt->execute([
            'lang' => $lang,
            'id' => $_SESSION['user_id']
        ]);
    } catch (Exception $e) {
        // Enregistrer l'erreur mais ne pas bloquer le client
    }
}

echo json_encode(['success' => true, 'lang' => $lang]);
exit;
