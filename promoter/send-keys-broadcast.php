<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Mailer.php';

header('Content-Type: application/json');
set_time_limit(300); // 5 minutes
ini_set('max_execution_time', '300');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'promoter') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

// requireCsrf();
if (!Mailer::isConfigured()) {
    echo json_encode([
        'success' => false,
        'message' => 'SMTP non configuré. Renseignez SMTP_HOST, SMTP_USER et SMTP_PASS dans .env avant d\'envoyer.',
    ]);
    exit;
}

try {
    $pdo = Database::getInstance();

    // 1. Get all students
    $stmt = $pdo->query("SELECT id, name, email FROM users WHERE role = 'student'");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        echo json_encode(['success' => false, 'message' => 'Aucun étudiant enregistré sur la plateforme.']);
        exit;
    }

    // 2. Get all courses with enrollment keys
    $stmtCourses = $pdo->query("
        SELECT id, title, enrollment_key 
        FROM courses 
        WHERE is_published = 1 AND enrollment_key IS NOT NULL AND enrollment_key != '' 
        ORDER BY title ASC
    ");
    $courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

    if (empty($courses)) {
        echo json_encode(['success' => false, 'message' => 'Aucun cours avec clé d\'inscription n\'est actuellement disponible.']);
        exit;
    }

    $sent = 0;
    $failed = 0;

    foreach ($students as $student) {
        $ok = Mailer::sendEnrollmentKeys($student['email'], $student['name'], $courses);
        if ($ok) {
            $sent++;
        } else {
            $failed++;
        }
    }

    if ($sent > 0) {
        auditLog('keys_broadcast', "Sent enrollment keys to {$sent} students, failed: {$failed}");
    }

    echo json_encode([
        'success' => $sent > 0,
        'sent' => $sent,
        'failed' => $failed,
        'message' => $sent > 0
            ? "Clés d'inscriptions diffusées avec succès à {$sent} étudiant(s)." . ($failed ? " ({$failed} échec(s))" : '')
            : "Aucun email n'a pu être envoyé. Veuillez vérifier vos configurations SMTP.",
    ]);
    exit;

} catch (PDOException $e) {
    jsonError('Erreur serveur lors de la diffusion.', $e, 'send-keys-broadcast.php');
}
