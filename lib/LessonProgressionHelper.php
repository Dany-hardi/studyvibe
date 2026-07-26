<?php
declare(strict_types=1);

require_once __DIR__ . '/Notifications.php';
require_once __DIR__ . '/../Mailer.php';

class LessonProgressionHelper
{
    public static function handleLessonUpdate(PDO $pdo, int $lessonId, bool $isNewLesson = false): void
    {
        // 1. Récupérer les informations sur la leçon et le cours
        $stmt = $pdo->prepare("
            SELECT l.title AS lesson_title, c.id AS course_id, c.title AS course_title
            FROM lessons l
            JOIN chapters ch ON l.chapter_id = ch.id
            JOIN courses c ON ch.course_id = c.id
            WHERE l.id = :lid
        ");
        $stmt->execute(['lid' => $lessonId]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$info) {
            return;
        }

        $courseId    = (int)$info['course_id'];
        $lessonTitle = (string)$info['lesson_title'];
        $courseTitle = (string)$info['course_title'];

        // 2. Récupérer tous les étudiants inscrits à ce cours
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id AS student_id, u.name, u.email
            FROM enrollments e
            JOIN users u ON e.student_id = u.id
            WHERE e.course_id = :cid AND u.role = 'student'
        ");
        $stmt->execute(['cid' => $courseId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($students)) {
            return;
        }

        // 3. Préparer les requêtes de réinitialisation et de mise à jour
        $updateProgStmt = $pdo->prepare("
            UPDATE lesson_progress
            SET completed = 0, content_consumed = 0, score = NULL, completed_at = NULL
            WHERE lesson_id = :lid AND student_id = :sid
        ");

        $updateEnrollmentStmt = $pdo->prepare("
            UPDATE enrollments
            SET progress_percent = :progress_percent
            WHERE student_id = :student_id AND course_id = :course_id
        ");

        foreach ($students as $s) {
            $sid    = (int)$s['student_id'];
            $sname  = (string)$s['name'];
            $semail = (string)$s['email'];

            // Réinitialiser la progression sur la leçon modifiée si l'étudiant l'avait faite
            $updateProgStmt->execute(['lid' => $lessonId, 'sid' => $sid]);

            // Recalculer le pourcentage global de progression de l'étudiant dans ce cours
            $progressPercent = self::getCourseProgress($pdo, $sid, $courseId);

            // Mettre à jour la table d'inscriptions
            $updateEnrollmentStmt->execute([
                'progress_percent' => $progressPercent,
                'student_id'       => $sid,
                'course_id'        => $courseId,
            ]);

            // Notification In-App
            $notifTitle = $isNewLesson ? 'Nouvelle leçon ajoutée !' : 'Leçon mise à jour !';
            $notifMsg   = $isNewLesson
                ? "Une nouvelle leçon \"{$lessonTitle}\" a été ajoutée au cours \"{$courseTitle}\". Consultez-la pour maintenir votre progression."
                : "L'enseignant a mis à jour la leçon \"{$lessonTitle}\" du cours \"{$courseTitle}\". Votre progression a été ajustée.";

            Notifications::send(
                $pdo,
                $sid,
                'lesson_update',
                $notifTitle,
                $notifMsg,
                "/student/dashboard.php?course_id={$courseId}"
            );

            // Envoi de l'email de notification aux apprenants
            if (!empty($semail)) {
                try {
                    Mailer::sendLessonContentUpdated($semail, $sname, $courseTitle, $lessonTitle, $courseId, $isNewLesson);
                } catch (\Throwable $e) {
                    error_log("Erreur lors de l'envoi de l'email de mise à jour de leçon à {$semail} : " . $e->getMessage());
                }
            }
        }
    }

    public static function getCourseProgress(PDO $pdo, int $studentId, int $courseId): int
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM lessons l
            JOIN chapters ch ON l.chapter_id = ch.id
            WHERE ch.course_id = :course_id
        ");
        $stmt->execute(['course_id' => $courseId]);
        $totalLessons = (int)$stmt->fetchColumn();

        if ($totalLessons === 0) {
            return 100;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT lp.lesson_id)
            FROM lesson_progress lp
            JOIN lessons l ON lp.lesson_id = l.id
            JOIN chapters ch ON l.chapter_id = ch.id
            WHERE lp.student_id = :student_id AND ch.course_id = :course_id AND lp.completed = 1
        ");
        $stmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
        $completedLessons = (int)$stmt->fetchColumn();

        return (int)round(($completedLessons / $totalLessons) * 100);
    }
}

