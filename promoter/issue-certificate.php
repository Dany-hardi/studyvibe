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


// requireCsrf();
$studentId = (int)($_POST['student_id'] ?? 0);
$moduleId  = (int)($_POST['module_id'] ?? 0);

if ($studentId <= 0 || $moduleId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
    exit;
}

try {
    $pdo = Database::getInstance();

    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = :id AND role = 'student'");
    $stmt->execute(['id' => $studentId]);
    $student = $stmt->fetch();
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Étudiant introuvable.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, title FROM modules WHERE id = :id");
    $stmt->execute(['id' => $moduleId]);
    $module = $stmt->fetch();
    if (!$module) {
        echo json_encode(['success' => false, 'message' => 'Module introuvable.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, certificate_code FROM certificates WHERE student_id = :sid AND module_id = :mid");
    $stmt->execute(['sid' => $studentId, 'mid' => $moduleId]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Un certificat existe déjà pour cet étudiant et ce module.']);
        exit;
    }

    $certCode = 'SV-' . $moduleId . '-' . strtoupper(substr(md5(uniqid((string)$studentId, true)), 0, 8));
    $promoterId = (int)$_SESSION['user_id'];

    $stmt = $pdo->prepare("
        INSERT INTO certificates (student_id, module_id, certificate_code, manual_issue, issued_by)
        VALUES (:sid, :mid, :code, 1, :by)
    ");
    $stmt->execute(['sid' => $studentId, 'mid' => $moduleId, 'code' => $certCode, 'by' => $promoterId]);

    auditLog('certificate_manual', "Student #{$studentId}, Module #{$moduleId}, Code: {$certCode}");
    Mailer::certification($student['email'], $student['name'], $module['title'], $certCode);

    $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_type) VALUES (:sid, 'certified')")
        ->execute(['sid' => $studentId]);

    echo json_encode([
        'success'          => true,
        'certificate_code' => $certCode,
        'student_name'     => $student['name'],
        'module_title'     => $module['title'],
    ]);
} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'issue-certificate.php');
}
