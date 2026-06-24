<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Mailer.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

$studentId = (int)($_POST['student_id'] ?? 0);
$subject   = trim((string)($_POST['subject'] ?? ''));
$message   = trim((string)($_POST['message'] ?? ''));

if ($studentId <= 0 || empty($subject) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis.']);
    exit;
}

try {
    $pdo = Database::getInstance();

    // Vérifier l'apprenant
    $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = :id AND role = 'student'");
    $stmt->execute(['id' => $studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Apprenant introuvable.']);
        exit;
    }

    $mailSent = Mailer::directMessage($student['email'], $student['name'], $subject, $message);

    if ($mailSent) {
        auditLog('direct_message_sent', "To student #{$studentId}: {$student['email']} (Subject: {$subject})");
        echo json_encode([
            'success' => true,
            'message' => "Le message direct a été envoyé avec succès à l'apprenant."
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "Échec de l'envoi de l'email. Veuillez vérifier la configuration de messagerie."
        ]);
    }
    exit;

} catch (PDOException $e) {
    jsonError('Erreur lors de l\'envoi du message.', $e, 'send-direct-message.php');
}
