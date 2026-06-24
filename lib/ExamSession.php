<?php
declare(strict_types=1);

class ExamSession
{
    public static function startOrResume(PDO $pdo, int $studentId, int $courseId, int $durationMinutes): array
    {
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

    public static function markSubmitted(PDO $pdo, int $studentId, int $courseId): void
    {
        $pdo->prepare("
            UPDATE exam_sessions SET submitted = 1
            WHERE student_id = :sid AND course_id = :cid AND submitted = 0
        ")->execute(['sid' => $studentId, 'cid' => $courseId]);
    }
}
