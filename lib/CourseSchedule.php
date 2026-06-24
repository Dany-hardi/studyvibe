<?php
declare(strict_types=1);

/**
 * Validation des fenêtres académiques (inscription, évaluation).
 */
class CourseSchedule
{
    public static function fetchCourse(PDO $pdo, int $courseId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT id, title, start_date, end_date, eval_deadline, is_published, exam_duration_minutes
            FROM courses WHERE id = :id
        ");
        $stmt->execute(['id' => $courseId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function canEnroll(array $course): ?string
    {
        if (!(int)($course['is_published'] ?? 1)) {
            return 'Ce cours n\'est pas encore publié.';
        }
        $today = date('Y-m-d');
        if (!empty($course['start_date']) && $today < $course['start_date']) {
            return 'Les inscriptions ouvrent le ' . date('d/m/Y', strtotime($course['start_date'])) . '.';
        }
        if (!empty($course['end_date']) && $today > $course['end_date']) {
            return 'La période d\'inscription à ce cours est terminée.';
        }
        return null;
    }

    public static function canTakeExam(array $course): ?string
    {
        if (!(int)($course['is_published'] ?? 1)) {
            return 'Ce cours n\'est pas disponible pour l\'évaluation.';
        }
        $today = date('Y-m-d');
        if (!empty($course['eval_deadline']) && $today > $course['eval_deadline']) {
            return 'La date limite d\'évaluation (' . date('d/m/Y', strtotime($course['eval_deadline'])) . ') est dépassée.';
        }
        if (!empty($course['end_date']) && $today > $course['end_date']) {
            return 'La période du cours est terminée.';
        }
        return null;
    }

    public static function deadlineAlerts(PDO $pdo, int $studentId): array
    {
        $stmt = $pdo->prepare("
            SELECT c.id, c.title, c.eval_deadline
            FROM enrollments e
            JOIN courses c ON c.id = e.course_id
            WHERE e.student_id = :sid
              AND c.eval_deadline IS NOT NULL
              AND c.eval_deadline >= CURDATE()
              AND c.eval_deadline <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
              AND e.progress_percent < 100
            ORDER BY c.eval_deadline ASC
        ");
        $stmt->execute(['sid' => $studentId]);
        return $stmt->fetchAll();
    }
}
