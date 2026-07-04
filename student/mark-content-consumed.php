<?php
/**
 * StudyVibe Academic LMS - Lesson Content Consumption Handler
 *
 * This controller processes requests by students to mark a lesson's learning material
 * (text reader, PDF view, or video finished) as consumed.
 *
 * PHP version 8.2
 *
 * @category  Controller
 * @package   StudyVibe\Student
 * @author    StudyVibe Team <development@studyvibe.academic>
 * @copyright 2026 StudyVibe
 * @license   Proprietary
 * @link      https://studyvibe.academic
 */

declare(strict_types=1);

// =========================================================================
// SECTION 1: AUTHENTICATION & INPUT PARAMETERS SECURITY
// =========================================================================

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode de requête non autorisée.']);
    exit;
}

$lessonId  = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
$studentId = $_SESSION['user_id'];

if ($lessonId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Identifiant de leçon non valide.']);
    exit;
}

// =========================================================================
// SECTION 2: CONTENT CONSUMED STATUS TRANSACTION
// =========================================================================

try {
    $pdo = Database::getInstance();

    $stmt = $pdo->prepare("
        SELECT l.id, c.id AS course_id
        FROM lessons l
        JOIN chapters ch ON l.chapter_id = ch.id
        JOIN courses c ON ch.course_id = c.id
        WHERE l.id = :id
    ");
    $stmt->execute(['id' => $lessonId]);
    $lesson = $stmt->fetch();

    if (!$lesson) {
        echo json_encode(['success' => false, 'message' => 'Leçon introuvable.']);
        exit;
    }

    $enrollStmt = $pdo->prepare(
        "SELECT id FROM enrollments WHERE student_id = :student_id AND course_id = :course_id"
    );
    $enrollStmt->execute(['student_id' => $studentId, 'course_id' => $lesson['course_id']]);
    if (!$enrollStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas inscrit au cours correspondant.']);
        exit;
    }

    $pdo->prepare("
        INSERT INTO lesson_progress (student_id, lesson_id, content_consumed)
        VALUES (:student_id, :lesson_id, 1)
        ON DUPLICATE KEY UPDATE content_consumed = 1
    ")->execute(['student_id' => $studentId, 'lesson_id' => $lessonId]);

    echo json_encode(['success' => true, 'content_consumed' => true]);

} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'mark-content-consumed.php');
}
