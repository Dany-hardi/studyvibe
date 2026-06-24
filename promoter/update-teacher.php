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
    echo json_encode(['success' => false, 'message' => 'Méthode de requête non autorisée.']);
    exit;
}


// requireCsrf();
$courseId  = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
$teacherId = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : -1;

if ($courseId <= 0 || $teacherId < 0) {
    echo json_encode(['success' => false, 'message' => 'Identifiants de cours ou d\'enseignant non valides.']);
    exit;
}

try {
    $pdo = Database::getInstance();

    $courseStmt = $pdo->prepare("
        SELECT c.id, c.title, c.teacher_id, u.name AS teacher_name, u.email AS teacher_email
        FROM courses c
        LEFT JOIN users u ON u.id = c.teacher_id
        WHERE c.id = :id
    ");
    $courseStmt->execute(['id' => $courseId]);
    $course = $courseStmt->fetch();

    if (!$course) {
        echo json_encode(['success' => false, 'message' => 'Cours introuvable.']);
        exit;
    }

    $previousTeacherId    = $course['teacher_id'] ? (int)$course['teacher_id'] : null;
    $previousTeacherName  = $course['teacher_name'] ?? '';
    $previousTeacherEmail = $course['teacher_email'] ?? '';

    // Révocation : teacher_id = 0
    if ($teacherId === 0) {
        $pdo->prepare("UPDATE courses SET teacher_id = NULL WHERE id = :id")->execute(['id' => $courseId]);
        auditLog('teacher_revoked', "Cours #{$courseId} « {$course['title']} » — enseignant révoqué");

        if ($previousTeacherEmail) {
            Mailer::courseAssignmentChanged(
                $previousTeacherEmail,
                $previousTeacherName,
                (string)$course['title'],
                'assignation révoquée'
            );
        }

        echo json_encode(['success' => true, 'message' => 'L\'enseignant a été révoqué de ce cours.']);
        exit;
    }

    $checkTeacher = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = :id");
    $checkTeacher->execute(['id' => $teacherId]);
    $newTeacher = $checkTeacher->fetch();

    if (!$newTeacher || $newTeacher['role'] !== 'teacher') {
        echo json_encode(['success' => false, 'message' => 'L\'utilisateur sélectionné n\'est pas un enseignant.']);
        exit;
    }

    $pdo->prepare("UPDATE courses SET teacher_id = :teacher_id WHERE id = :id")->execute([
        'teacher_id' => $teacherId,
        'id'         => $courseId,
    ]);

    $actionLabel = $previousTeacherId ? 'réassigné à un autre enseignant' : 'assigné';
    auditLog('teacher_assigned', "Cours #{$courseId} « {$course['title']} » → {$newTeacher['name']}");

    if ($previousTeacherEmail && $previousTeacherId !== $teacherId) {
        Mailer::courseAssignmentChanged(
            $previousTeacherEmail,
            $previousTeacherName,
            (string)$course['title'],
            'réassigné à un autre enseignant'
        );
    }

    if (!empty($newTeacher['email'])) {
        Mailer::courseAssignmentChanged(
            (string)$newTeacher['email'],
            (string)$newTeacher['name'],
            (string)$course['title'],
            'vous avez été assigné à ce cours'
        );
    }

    echo json_encode(['success' => true, 'message' => 'L\'enseignant a été assigné avec succès.']);
    exit;

} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'update-teacher.php');
}
