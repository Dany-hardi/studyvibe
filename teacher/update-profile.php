<?php
declare(strict_types=1);

/**
 * Teacher profile: display name and profile picture.
 *
 *   POST name=...            changes the display name
 *   POST avatar=<file>       replaces the picture
 *   POST remove_avatar=1     goes back to the initial letter
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Avatar.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !in_array($_SESSION['user_role'] ?? '', ['teacher', 'promoter'], true) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$pdo = Database::getInstance();

try {
    if (isset($_FILES['avatar'])) {
        echo json_encode(Avatar::replace($pdo, $userId, $_FILES['avatar']));
        exit;
    }
    if (!empty($_POST['remove_avatar'])) {
        echo json_encode(Avatar::remove($pdo, $userId));
        exit;
    }
    if (isset($_POST['name'])) {
        $name = trim((string)$_POST['name']);
        if ($name === '' || mb_strlen($name) > 100) {
            echo json_encode(['success' => false, 'message' => 'Le nom doit contenir entre 1 et 100 caractères.']);
            exit;
        }
        $pdo->prepare('UPDATE users SET name = :n WHERE id = :id')->execute(['n' => $name, 'id' => $userId]);
        $_SESSION['user_name'] = $name;
        echo json_encode(['success' => true, 'message' => 'Profil mis à jour.', 'name' => $name]);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Aucune donnée valide reçue.']);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'teacher/update-profile.php');
}
