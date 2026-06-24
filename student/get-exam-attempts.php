<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$courseId = (int)($_GET['course_id'] ?? 0);
if ($courseId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Cours invalide.']);
    exit;
}

try {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("
        SELECT score, passed, total_questions, attempted_at
        FROM certification_attempts
        WHERE student_id = :sid AND course_id = :cid
        ORDER BY attempted_at DESC
        LIMIT 10
    ");
    $stmt->execute(['sid' => $_SESSION['user_id'], 'cid' => $courseId]);
    $attempts = $stmt->fetchAll();

    $recent = $pdo->prepare("
        SELECT COUNT(*) FROM certification_attempts
        WHERE student_id = :sid AND course_id = :cid
          AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $recent->execute(['sid' => $_SESSION['user_id'], 'cid' => $courseId]);
    $used24h = (int)$recent->fetchColumn();

    echo json_encode([
        'success'  => true,
        'attempts' => $attempts,
        'used_24h' => $used24h,
        'left_24h' => max(0, 3 - $used24h),
    ]);
} catch (PDOException $e) {
    jsonError('Erreur serveur.', $e, 'get-exam-attempts');
}
