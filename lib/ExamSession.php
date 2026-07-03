<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Course Exam Session Service
 * 
 * Manages the database tracking state for formal student course evaluations.
 * Handles the creation of unique exam sessions, validation check gates, time 
 * expiration logic, and final submission flags.
 * 
 * @package    StudyVibe
 * @subpackage Lib
 * @author     Advanced Engineering Team
 */
class ExamSession
{
    /**
     * Initializes a new exam session record, or resumes an active, unsubmitted one.
     * 
     * @param PDO $pdo             Database connection instance.
     * @param int $studentId       Target student primary key.
     * @param int $courseId        Target course primary key.
     * @param int $durationMinutes Allocated duration in minutes.
     * @return array{id: int, started_at: string, expires_at: string, submitted: int} Active session details.
     */
    public static function startOrResume(PDO $pdo, int $studentId, int $courseId, int $durationMinutes): array
    {
        // Query for an existing active, unsubmitted and non-expired session
        $stmt = $pdo->prepare("
            SELECT * FROM exam_sessions
            WHERE student_id = :sid AND course_id = :cid AND submitted = 0 AND expires_at > NOW()
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(['sid' => $studentId, 'cid' => $courseId]);
        $existing = $stmt->fetch();
        if ($existing) {
            return $existing;
        }

        // Initialize a new session window
        $started = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + $durationMinutes * 60);
        
        $ins = $pdo->prepare("
            INSERT INTO exam_sessions (student_id, course_id, started_at, expires_at)
            VALUES (:sid, :cid, :start, :exp)
        ");
        $ins->execute(['sid' => $studentId, 'cid' => $courseId, 'start' => $started, 'exp' => $expires]);

        return [
            'id'         => (int)$pdo->lastInsertId(),
            'started_at' => $started,
            'expires_at' => $expires,
            'submitted'  => 0,
        ];
    }

    /**
     * Verifies that the student has a valid, active, and unexpired exam session.
     * 
     * @param PDO $pdo       Database connection instance.
     * @param int $studentId Target student primary key.
     * @param int $courseId  Target course primary key.
     * @return string|null Error string if session is inactive or expired, null if OK.
     */
    public static function assertActive(PDO $pdo, int $studentId, int $courseId): ?string
    {
        $stmt = $pdo->prepare("
            SELECT expires_at FROM exam_sessions
            WHERE student_id = :sid AND course_id = :cid AND submitted = 0
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(['sid' => $studentId, 'cid' => $courseId]);
        $row = $stmt->fetch();
        
        if (!$row) {
            return 'Aucune session d\'examen active. Relancez l\'évaluation.';
        }
        
        if (strtotime($row['expires_at']) < time()) {
            return 'Le temps imparti pour cet examen est écoulé.';
        }
        
        return null;
    }

    /**
     * Flags the active exam session as submitted, completing the assessment.
     * 
     * @param PDO $pdo       Database connection instance.
     * @param int $studentId Target student primary key.
     * @param int $courseId  Target course primary key.
     * @return void
     */
    public static function markSubmitted(PDO $pdo, int $studentId, int $courseId): void
    {
        $pdo->prepare("
            UPDATE exam_sessions SET submitted = 1
            WHERE student_id = :sid AND course_id = :cid AND submitted = 0
        ")->execute(['sid' => $studentId, 'cid' => $courseId]);
    }
}
