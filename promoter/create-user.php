<?php
declare(strict_types=1);

/**
 * The administrator (promoter) creates an account: student, teacher or another administrator.
 *
 *   POST name, email, role (student|teacher|promoter), mode (password | generate | invite), password (mode=password),
 *        matricule (student), lang (fr|en), phone, courses[] (student: enrol at once), welcome=1 (send "your account is ready")
 *
 * The account is ready at once: email confirmed, active, approved. mode=generate returns the password once; mode=invite emails a link
 * (72 hours) to choose the password. Logic and rules: lib/AdminUsers.php.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/RateLimit.php';
require_once __DIR__ . '/../lib/AdminUsers.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'promoter') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}
Security::requirePostFromSameSite();

try {
    $pdo = Database::getInstance();
    $adminId = (int)$_SESSION['user_id'];
    if (!RateLimit::allow($pdo, 'adminusers:create:' . $adminId, 120, 3600)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Trop de créations en peu de temps. Réessayez dans un moment.']);
        exit;
    }
    $res = AdminUsers::create($pdo, $adminId, [
        'name' => $_POST['name'] ?? '', 'email' => $_POST['email'] ?? '', 'role' => $_POST['role'] ?? '', 'mode' => $_POST['mode'] ?? 'password',
        'password' => $_POST['password'] ?? '', 'matricule' => $_POST['matricule'] ?? '', 'lang' => $_POST['lang'] ?? 'fr', 'phone' => $_POST['phone'] ?? '',
        'courses' => (array)($_POST['courses'] ?? []), 'welcome' => !empty($_POST['welcome']),
    ]);
    if (!$res['ok']) {
        echo json_encode(['success' => false, 'error' => $res['error'], 'message' => $res['message']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $msg = 'Compte créé avec succès.';
    if ($res['invited']) {
        $msg = $res['mail_sent'] ? 'Compte créé : une invitation a été envoyée pour choisir le mot de passe.' : 'Compte créé, mais l’invitation n’a pas pu être envoyée. Utilisez « Mot de passe oublié » ou recréez le compte avec un mot de passe.';
    }
    echo json_encode([
        'success' => true, 'message' => $msg, 'user' => $res['user'], 'password' => $res['password'], 'invited' => $res['invited'],
        'mail_sent' => $res['mail_sent'], 'enrolled' => $res['enrolled'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    logServerError($e, 'promoter/create-user');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur. Veuillez réessayer.']);
}
