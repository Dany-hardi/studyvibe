<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Mailer.php';
require_once __DIR__ . '/../lib/Notifications.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/AdminUsers.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

Security::requirePostFromSameSite();   // also refuses anything but POST

$action = trim((string)($_POST['action'] ?? ''));
$userId = (int)($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur invalide.']);
    exit;
}

// Empêcher un promoteur de s'auto-manipuler sur des actions critiques (suppression ou désactivation de son propre compte)
if (($action === 'delete' || $action === 'toggle_active') && $userId === (int)$_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas désactiver ou supprimer votre propre compte promoteur.']);
    exit;
}

try {
    $pdo = Database::getInstance();

    // Récupérer l'utilisateur pour audit et email
    $stmt = $pdo->prepare("SELECT id, name, email, role, is_active, is_approved FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        echo json_encode(['success' => false, 'error' => 'not_found', 'message' => 'Utilisateur introuvable.']);
        exit;
    }

    if ($action === 'approve') {
        if ($targetUser['role'] !== 'teacher') {
            echo json_encode(['success' => false, 'message' => 'Seuls les enseignants peuvent être validés.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE users SET is_approved = 1 WHERE id = :id");
        $stmt->execute(['id' => $userId]);

        auditLog('user_approved', "Teacher approved: {$targetUser['email']}");

        // Envoyer l'email d'approbation
        Mailer::teacherApproved($targetUser['email'], $targetUser['name']);

        // Notification interne
        Notifications::send($pdo, $userId, 'status', 'Compte validé !', 'Bonne nouvelle ! Votre compte d\'enseignant a été validé par l\'administration.');

        echo json_encode([
            'success' => true,
            'message' => "Le compte de l'enseignant a été validé avec succès."
        ]);
        exit;

    } elseif ($action === 'toggle_active') {
        $newActive = $targetUser['is_active'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE users SET is_active = :active WHERE id = :id");
        $stmt->execute(['active' => $newActive, 'id' => $userId]);

        $statusStr = $newActive ? 'activated' : 'deactivated';
        auditLog("user_{$statusStr}", "User #{$userId} ({$targetUser['role']}): {$targetUser['email']}");

        // Notification interne
        $title = $newActive ? 'Compte réactivé' : 'Compte suspendu';
        $msg = $newActive ? 'Votre compte a été réactivé par l\'administration. Vous pouvez à nouveau vous connecter.' : 'Votre compte a été suspendu par l\'administration.';
        Notifications::send($pdo, $userId, 'status', $title, $msg);

        echo json_encode([
            'success' => true,
            'is_active' => $newActive,
            'message' => $newActive ? 'Le compte a été réactivé avec succès.' : 'Le compte a été suspendu avec succès.'
        ]);
        exit;

    } elseif ($action === 'make_super_teacher' || $action === 'make_teacher') {
        // Teacher <-> super teacher. Nothing else changes: courses, quizzes and marks stay with the account.
        $from = $action === 'make_super_teacher' ? 'teacher' : 'super_teacher';
        $to = $action === 'make_super_teacher' ? 'super_teacher' : 'teacher';
        if ($targetUser['role'] !== $from) {
            echo json_encode(['success' => false, 'message' => $to === 'super_teacher' ? 'Seul un enseignant peut devenir super enseignant.' : 'Ce compte n’est pas super enseignant.']);
            exit;
        }
        $pdo->prepare("UPDATE users SET role = :r WHERE id = :id")->execute(['r' => $to, 'id' => $userId]);
        auditLog('user_role_changed', "User #{$userId} {$targetUser['email']}: {$from} -> {$to}");
        try {
            Notifications::send($pdo, $userId, 'account', $to === 'super_teacher' ? 'Vous êtes super enseignant' : 'Votre rôle a changé',
                $to === 'super_teacher' ? 'Vous voyez désormais tous les cours en lecture seule et les contributions.' : 'Vous êtes de nouveau enseignant.', '/teacher/dashboard.php');
        } catch (Throwable $e) { /* the change is made even if the notice fails */ }
        echo json_encode(['success' => true, 'role' => $to, 'message' => $to === 'super_teacher' ? 'Le compte est maintenant super enseignant.' : 'Le compte est de nouveau enseignant.']);
        exit;

    } elseif ($action === 'impact') {
        // What deleting this account would take with it (for the confirmation window)
        echo json_encode(['success' => true, 'impact' => AdminUsers::impact($pdo, $userId)], JSON_UNESCAPED_UNICODE);
        exit;

    } elseif ($action === 'delete') {
        // Only an administrator can delete an account (this file is the only place in the application that does). The rules, the
        // guard rails and the clean-up are in AdminUsers::delete().
        $res = AdminUsers::delete($pdo, (int)$_SESSION['user_id'], $userId, [
            'reassign_to' => (int)($_POST['reassign_to'] ?? 0), 'cascade' => !empty($_POST['cascade']), 'erase_archives' => !empty($_POST['erase_archives']),
        ]);
        echo json_encode(['success' => $res['ok']] + $res, JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
    exit;

} catch (PDOException $e) {
    jsonError('Erreur lors du traitement.', $e, 'manage-user.php');
}
