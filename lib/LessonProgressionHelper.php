<?php
declare(strict_types=1);

require_once __DIR__ . '/Notifications.php';

class LessonProgressionHelper
{
    public static function handleLessonUpdate(PDO $pdo, int $lessonId): void
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
        $info = $stmt->fetch();
        if (!$info) {
            return;
        }

        $courseId    = (int)$info['course_id'];
        $lessonTitle = $info['lesson_title'];
        $courseTitle = $info['course_title'];

        // 2. Trouver tous les étudiants qui ont déjà complété cette leçon
        $stmt = $pdo->prepare("
            SELECT student_id FROM lesson_progress
            WHERE lesson_id = :lid AND completed = 1
        ");
        $stmt->execute(['lid' => $lessonId]);
        $studentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($studentIds)) {
            return;
        }

        // 3. Réinitialiser la progression de la leçon pour ces étudiants
        $updateProgStmt = $pdo->prepare("
            UPDATE lesson_progress
            SET completed = 0, content_consumed = 0
            WHERE lesson_id = :lid AND student_id = :sid
        ");

        foreach ($studentIds as $sid) {
            $sid = (int)$sid;
            // Réinitialiser la progression
            $updateProgStmt->execute(['lid' => $lessonId, 'sid' => $sid]);

            // Recalculer le pourcentage global du cours
            $progressPercent = self::getCourseProgress($pdo, $sid, $courseId);

            // Mettre à jour l'inscription de l'apprenant
            $pdo->prepare("
                UPDATE enrollments SET progress_percent = :progress_percent
                WHERE student_id = :student_id AND course_id = :course_id
            ")->execute([
                'progress_percent' => $progressPercent,
                'student_id'       => $sid,
                'course_id'        => $courseId,
            ]);

            // Envoyer une notification à l'apprenant
            Notifications::send(
                $pdo,
                $sid,
                'lesson_update',
                'Leçon mise à jour !',
                "L'enseignant a mis à jour la leçon \"{$lessonTitle}\" du cours \"{$courseTitle}\". Veuillez la consulter de nouveau pour valider votre progression.",
                "/student/dashboard.php?course_id={$courseId}"
            );
        }
    }

    private static function getCourseProgress(PDO $pdo, int $studentId, int $courseId): int
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM lessons l
            JOIN chapters ch ON l.chapter_id = ch.id
            WHERE ch.course_id = :course_id
        ");
        $stmt->execute(['course_id' => $courseId]);
        $totalLessons = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT lp.lesson_id)
            FROM lesson_progress lp
            JOIN lessons l ON lp.lesson_id = l.id
            JOIN chapters ch ON l.chapter_id = ch.id
            WHERE lp.student_id = :student_id AND ch.course_id = :course_id AND lp.completed = 1
        ");
        $stmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
        $completedLessons = (int)$stmt->fetchColumn();

        return $totalLessons > 0 ? (int)round(($completedLessons / $totalLessons) * 100) : 100;
    }
}
