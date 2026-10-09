<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}


// requireCsrf();
$name     = trim((string)($_POST['name']     ?? ''));
$email    = trim((string)($_POST['email']    ?? ''));
$password = (string)($_POST['password']      ?? '');
$role     = trim((string)($_POST['role']     ?? ''));

// Validation
if (empty($name) || empty($email) || empty($password) || empty($role)) {
    echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Adresse électronique invalide.']);
    exit;
}
if (!in_array($role, ['teacher', 'promoter', 'student'], true)) {
    echo json_encode(['success' => false, 'message' => 'Rôle invalide.']);
    exit;
}
require_once __DIR__ . '/../lib/PasswordPolicy.php';
if (($pwError = PasswordPolicy::check($password, $email ?? '')) !== null) {
    echo json_encode(['success' => false, 'message' => $pwError]);
    exit;
}

try {
    $pdo = Database::getInstance();

    // Check for duplicate email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cette adresse électronique est déjà utilisée.']);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, email_verified_at, plan_id) VALUES (:name, :email, :password, :role, NOW(), :plan_id)");
    $stmt->execute(['name' => $name, 'email' => $email, 'password' => $hashed, 'role' => $role, 'plan_id' => defaultPlanId($pdo, $role)]);

    $newId = (int)$pdo->lastInsertId();
    echo json_encode([
        'success' => true,
        'message' => "Compte créé avec succès.",
        'user' => ['id' => $newId, 'name' => $name, 'email' => $email, 'role' => $role]
    ]);
    exit;

} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'create-user.php');
}
