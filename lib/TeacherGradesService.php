<?php
declare(strict_types=1);

/**
 * TeacherGradesService — collecte des notes apprenants pour un cours enseignant.
 *
 * Agrège les évaluations de leçons (mini-quiz), les tentatives de certification
 * du cours et les certificats délivrés au niveau du module parent.
 */
class TeacherGradesService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Vérifie que le cours appartient à l'enseignant connecté.
     */
    public function assertCourseOwnership(int $courseId, int $teacherId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, m.title AS module_title
            FROM courses c
            JOIN modules m ON m.id = c.module_id
            WHERE c.id = :id AND c.teacher_id = :tid
        ");
        $stmt->execute(['id' => $courseId, 'tid' => $teacherId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Notes des évaluations de leçon : TOUS les inscrits × TOUTES les leçons avec quiz.
     * Les apprenants n'ayant pas passé le quiz ont score = 0 et statut = "Non terminé".
     * Seules les leçons ayant au moins une question sont incluses.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchLessonGrades(int $courseId): array
    {
        // Récupère toutes les combinaisons (inscrit × leçon-avec-quiz) + données de réponse si existantes
        $stmt = $this->pdo->prepare("
            SELECT
                u.id          AS student_id,
                u.name        AS student_name,
                u.email       AS student_email,
                ch.title      AS chapter_title,
                ch.sort_order AS chapter_sort,
                l.id          AS lesson_id,
                l.title       AS lesson_title,
                l.sort_order  AS lesson_sort,
                lq_stats.total_questions,
                COALESCE(ans_stats.correct_count, 0) AS correct_count,
                lp.completed,
                lp.score      AS progress_score,
                lp.completed_at,
                CASE WHEN ans_stats.correct_count IS NULL THEN 0 ELSE 1 END AS has_attempted
            FROM enrollments e
            JOIN users u  ON u.id  = e.student_id
            JOIN courses c ON c.id = e.course_id
            JOIN chapters ch ON ch.course_id = c.id
            -- Seules les leçons ayant au moins une question
            JOIN (
                SELECT lesson_id, COUNT(*) AS total_questions
                FROM lesson_questions
                GROUP BY lesson_id
                HAVING COUNT(*) > 0
            ) lq_stats ON 1=1
            JOIN lessons l ON l.id = lq_stats.lesson_id AND l.chapter_id = ch.id
            -- Réponses existantes (NULL si l'apprenant n'a pas tenté)
            LEFT JOIN (
                SELECT lq2.lesson_id, lqa.student_id,
                       SUM(lqa.answered_correctly) AS correct_count
                FROM lesson_question_answers lqa
                JOIN lesson_questions lq2 ON lq2.id = lqa.question_id
                GROUP BY lq2.lesson_id, lqa.student_id
            ) ans_stats ON ans_stats.lesson_id = l.id AND ans_stats.student_id = u.id
            LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.student_id = u.id
            WHERE c.id = :cid
            ORDER BY u.name ASC, ch.sort_order ASC, l.sort_order ASC
        ");
        $stmt->execute(['cid' => $courseId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $total    = (int)$row['total_questions'];
            $correct  = (int)$row['correct_count'];
            $attempted = (int)($row['has_attempted'] ?? 0) === 1;

            if (!$attempted) {
                // L'apprenant n'a pas encore passé le quiz
                $row['score_percent'] = 0;
                $row['status']        = 'Non terminé';
            } elseif ($total > 0) {
                $row['score_percent'] = round(($correct / $total) * 100, 1);
                $row['status']        = 'Terminé';
            } else {
                $row['score_percent'] = 0;
                $row['status']        = 'Non terminé';
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Tentatives de certification finale (QCM du cours) pour tous les inscrits.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchCertificationAttempts(int $courseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.name AS student_name, u.email AS student_email,
                   ca.score, ca.passed, ca.total_questions, ca.attempted_at
            FROM certification_attempts ca
            JOIN users u ON u.id = ca.student_id
            WHERE ca.course_id = :cid
            ORDER BY ca.attempted_at DESC
        ");
        $stmt->execute(['cid' => $courseId]);
        return $stmt->fetchAll();
    }

    /**
     * Certificats officiels délivrés au niveau du module (certification module).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchModuleCertificates(int $courseId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.name AS student_name, u.email AS student_email,
                   m.title AS module_title, cert.certificate_code,
                   cert.issued_at, cert.manual_issue
            FROM certificates cert
            JOIN users u ON u.id = cert.student_id
            JOIN modules m ON m.id = cert.module_id
            JOIN courses c ON c.module_id = m.id
            WHERE c.id = :cid
            ORDER BY cert.issued_at DESC
        ");
        $stmt->execute(['cid' => $courseId]);
        return $stmt->fetchAll();
    }


    /**
     * Suivi des apprenants inscrits : où chacun en est dans le cours.
     * Calculé à partir des leçons réellement terminées (pas du pourcentage mis en cache dans enrollments).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchEnrolledStudents(int $courseId): array
    {
        require_once __DIR__ . '/LessonFlow.php';
        $lessons = LessonFlow::orderedLessons($this->pdo, $courseId);
        $total   = count($lessons);

        $stmt = $this->pdo->prepare("
            SELECT u.id AS student_id, u.name AS student_name, u.email AS student_email, u.matricule,
                   e.progress_percent, e.enrolled_at
            FROM enrollments e
            JOIN users u ON u.id = e.student_id
            WHERE e.course_id = :cid
            ORDER BY u.name ASC
        ");
        $stmt->execute(['cid' => $courseId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $done = [];      // student => [lesson_id => completed_at]
        $q = $this->pdo->prepare("
            SELECT lp.student_id, lp.lesson_id, lp.completed_at
            FROM lesson_progress lp
            JOIN lessons l ON l.id = lp.lesson_id
            JOIN chapters ch ON ch.id = l.chapter_id
            WHERE ch.course_id = :cid AND lp.completed = 1
        ");
        $q->execute(['cid' => $courseId]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $done[(int)$r['student_id']][(int)$r['lesson_id']] = $r['completed_at'];
        }

        $quiz = [];      // student => [correct, answered]
        $q = $this->pdo->prepare("
            SELECT a.student_id, SUM(a.answered_correctly) AS ok, COUNT(*) AS n
            FROM lesson_question_answers a
            JOIN lesson_questions lq ON lq.id = a.question_id
            JOIN lessons l ON l.id = lq.lesson_id
            JOIN chapters ch ON ch.id = l.chapter_id
            WHERE ch.course_id = :cid
            GROUP BY a.student_id
        ");
        $q->execute(['cid' => $courseId]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $quiz[(int)$r['student_id']] = [(int)$r['ok'], (int)$r['n']];
        }

        $q = $this->pdo->prepare("SELECT COUNT(*) FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id WHERE ch.course_id = :cid AND l.has_assignment = 1");
        $q->execute(['cid' => $courseId]);
        $assignTotal = (int)$q->fetchColumn();

        $subs = [];      // student => number of lessons with a submission
        $q = $this->pdo->prepare("
            SELECT s.student_id, COUNT(DISTINCT s.lesson_id) AS n
            FROM lesson_assignment_submissions s
            JOIN lessons l ON l.id = s.lesson_id
            JOIN chapters ch ON ch.id = l.chapter_id
            WHERE ch.course_id = :cid
            GROUP BY s.student_id
        ");
        $q->execute(['cid' => $courseId]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $subs[(int)$r['student_id']] = (int)$r['n'];
        }

        foreach ($students as &$s) {
            $sid     = (int)$s['student_id'];
            $mine    = $done[$sid] ?? [];
            $count   = 0;
            $current = null;
            $last    = null;
            foreach ($lessons as $l) {
                if (isset($mine[$l['id']])) {
                    $count++;
                    if ($mine[$l['id']] && ($last === null || $mine[$l['id']] > $last)) {
                        $last = $mine[$l['id']];
                    }
                } elseif ($current === null && !$l['expired']) {
                    $current = $l['title'];
                }
            }
            $s['lessons_done']      = $count;
            $s['lessons_total']     = $total;
            $s['progress_percent']  = $total > 0 ? (int)round($count / $total * 100) : 0;
            $s['quiz_percent']      = isset($quiz[$sid]) && $quiz[$sid][1] > 0 ? (int)round($quiz[$sid][0] / $quiz[$sid][1] * 100) : null;
            $s['assignments_done']  = min($subs[$sid] ?? 0, $assignTotal);
            $s['assignments_total'] = $assignTotal;
            $s['current_lesson']    = $count >= $total && $total > 0 ? null : $current;
            $s['last_activity']     = $last;
        }
        unset($s);

        return $students;
    }

    /**
     * Construit les feuilles Excel des notes pour un cours.
     *
     * @return array<int, array{name: string, headers: string[], rows: array<int, array<int, string|int|float|null>>}>
     */
    public function buildGradeSheets(int $courseId, string $courseTitle): array
    {
        $enrolledRows = [];
        foreach ($this->fetchEnrolledStudents($courseId) as $s) {
            $enrolledRows[] = [
                $s['matricule'] ?? '',
                $s['student_name'],
                $s['lessons_done'] . '/' . $s['lessons_total'],
                $s['progress_percent'] . '%',
                $s['quiz_percent'] !== null ? $s['quiz_percent'] . '%' : '—',
                $s['assignments_total'] > 0 ? $s['assignments_done'] . '/' . $s['assignments_total'] : '—',
                $s['current_lesson'] ?? '—',
            ];
        }

        $lessonRows = [];
        foreach ($this->fetchLessonGrades($courseId) as $g) {
            $lessonRows[] = [
                $g['student_name'],
                $g['student_email'],
                $g['chapter_title'],
                $g['lesson_title'],
                $g['total_questions'],
                $g['correct_count'],
                $g['score_percent'] !== null ? $g['score_percent'] . '%' : '—',
                $g['status'],
                !empty($g['completed_at']) ? date('d/m/Y H:i', strtotime((string)$g['completed_at'])) : '—',
            ];
        }

        $certRows = [];
        foreach ($this->fetchCertificationAttempts($courseId) as $a) {
            $certRows[] = [
                $a['student_name'],
                $a['student_email'],
                $a['score'] . '%',
                (int)$a['passed'] === 1 ? 'Reussi' : 'Echoue',
                $a['total_questions'] ?? '—',
                date('d/m/Y H:i', strtotime((string)$a['attempted_at'])),
            ];
        }

        $moduleRows = [];
        foreach ($this->fetchModuleCertificates($courseId) as $c) {
            $moduleRows[] = [
                $c['student_name'],
                $c['student_email'],
                $c['module_title'],
                $c['certificate_code'],
                date('d/m/Y H:i', strtotime((string)$c['issued_at'])),
                (int)$c['manual_issue'] === 1 ? 'Oui' : 'Non',
            ];
        }

        return [
            [
                'name'    => 'Eleves inscrits',
                'headers' => ['Matricule', 'Nom complet', 'Lecons terminees', 'Progression', 'Moyenne quiz', 'Devoirs rendus', 'Lecon en cours'],
                'rows'    => $enrolledRows,
            ],
            [
                'name'    => 'Notes lecons',
                'headers' => ['Apprenant', 'Email', 'Chapitre', 'Lecon', 'Questions', 'Bonnes rep.', 'Score', 'Statut', 'Date'],
                'rows'    => $lessonRows,
            ],
            [
                'name'    => 'Certif cours',
                'headers' => ['Apprenant', 'Email', 'Score', 'Resultat', 'Questions', 'Date tentative'],
                'rows'    => $certRows,
            ],
            [
                'name'    => 'Certif module',
                'headers' => ['Apprenant', 'Email', 'Module', 'Code certificat', 'Date', 'Manuel'],
                'rows'    => $moduleRows,
            ],
        ];
    }
}
