<?php
declare(strict_types=1);

/**
 * PromoterExportService — collecte et formate les données exportables du tableau de bord promoteur.
 *
 * Centralise les requêtes SQL et la construction des feuilles Excel / sections PDF
 * afin de garder les scripts d'export légers et testables.
 */
class PromoterExportService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Retourne les indicateurs clés affichés sur le tableau de bord promoteur.
     *
     * @return array<string, string|int|float>
     */
    public function fetchMetrics(): array
    {
        $totalStudents = (int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
        $totalTeachers = (int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
        $totalPromoters = (int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'promoter'")->fetchColumn();
        $totalCourses  = (int)$this->pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
        $totalModules  = (int)$this->pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
        $totalCerts    = (int)$this->pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
        $enrollments   = (int)$this->pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();
        $avgProgress   = (float)$this->pdo->query("SELECT COALESCE(AVG(progress_percent),0) FROM enrollments")->fetchColumn();
        $passRate      = (float)$this->pdo->query("
            SELECT COALESCE(ROUND(SUM(passed)/COUNT(*)*100,1), 0) FROM certification_attempts
        ")->fetchColumn();

        return [
            'date_export'        => date('d/m/Y H:i'),
            'apprenants'         => $totalStudents,
            'enseignants'        => $totalTeachers,
            'promoteurs'         => $totalPromoters,
            'modules'            => $totalModules,
            'cours'              => $totalCourses,
            'inscriptions'       => $enrollments,
            'certifications'     => $totalCerts,
            'progression_moyenne'=> round($avgProgress, 1) . '%',
            'taux_reussite_qcm'  => $passRate . '%',
        ];
    }

    /**
     * Liste des apprenants avec statistiques d'inscription et de progression.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchStudents(): array
    {
        return $this->pdo->query("
            SELECT u.id, u.name, u.email, u.created_at,
                   COUNT(DISTINCT e.id) AS enrollments_count,
                   COALESCE(ROUND(AVG(e.progress_percent), 1), 0) AS avg_progress,
                   (SELECT COUNT(*) FROM certificates c WHERE c.student_id = u.id) AS certificates_count
            FROM users u
            LEFT JOIN enrollments e ON e.student_id = u.id
            WHERE u.role = 'student'
            GROUP BY u.id, u.name, u.email, u.created_at
            ORDER BY u.name ASC
        ")->fetchAll();
    }

    /**
     * Liste des enseignants avec le nombre de cours dont ils sont titulaires.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchTeachers(): array
    {
        return $this->pdo->query("
            SELECT u.id, u.name, u.email, u.created_at,
                   COUNT(c.id) AS courses_assigned
            FROM users u
            LEFT JOIN courses c ON c.teacher_id = u.id
            WHERE u.role = 'teacher'
            GROUP BY u.id, u.name, u.email, u.created_at
            ORDER BY u.name ASC
        ")->fetchAll();
    }

    /**
     * Catalogue des modules de formation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchModules(): array
    {
        return $this->pdo->query("
            SELECT m.id, m.title, m.description, m.created_at,
                   COUNT(c.id) AS courses_count
            FROM modules m
            LEFT JOIN courses c ON c.module_id = m.id
            GROUP BY m.id, m.title, m.description, m.created_at
            ORDER BY m.id DESC
        ")->fetchAll();
    }

    /**
     * Catalogue des cours avec module, enseignant et créateur.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchCourses(): array
    {
        return $this->pdo->query("
            SELECT c.id, c.title, c.description, m.title AS module_title,
                   COALESCE(u.name, 'Non assigne') AS teacher_name,
                   COALESCE(cr.name, '—') AS creator_name,
                   c.enrollment_key, c.start_date, c.end_date, c.eval_deadline,
                   c.exam_duration_minutes, c.created_at
            FROM courses c
            JOIN modules m ON m.id = c.module_id
            LEFT JOIN users u ON u.id = c.teacher_id
            LEFT JOIN users cr ON cr.id = c.created_by
            ORDER BY c.id DESC
        ")->fetchAll();
    }

    /**
     * Détail des inscriptions apprenant ↔ cours.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchEnrollments(): array
    {
        return $this->pdo->query("
            SELECT u.name AS student_name, u.email AS student_email,
                   c.title AS course_title, m.title AS module_title,
                   e.progress_percent, e.enrolled_at
            FROM enrollments e
            JOIN users u ON u.id = e.student_id
            JOIN courses c ON c.id = e.course_id
            JOIN modules m ON m.id = c.module_id
            ORDER BY e.enrolled_at DESC
        ")->fetchAll();
    }

    /**
     * Registre des certifications délivrées.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchCertificates(): array
    {
        return $this->pdo->query("
            SELECT u.name AS student_name, u.email AS student_email,
                   co.title AS course_title, cert.certificate_code,
                   cert.issued_at, cert.manual_issue
            FROM certificates cert
            JOIN users u ON u.id = cert.student_id
            LEFT JOIN courses co ON co.id = cert.course_id
            ORDER BY cert.issued_at DESC
        ")->fetchAll();
    }

    /**
     * Journal d'audit complet (toutes les entrées, du plus récent au plus ancien).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAuditLogs(): array
    {
        return $this->pdo->query("
            SELECT al.id, al.created_at, al.action, al.details, al.ip_address,
                   COALESCE(u.name, 'Systeme') AS user_name,
                   COALESCE(u.email, '—') AS user_email
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.id DESC
        ")->fetchAll();
    }

    /**
     * Transforme les métriques en feuille Excel (indicateur / valeur).
     *
     * @return array{name: string, headers: string[], rows: array<int, array<int, string|int|float>>}
     */
    public function buildMetricsSheet(): array
    {
        $metrics = $this->fetchMetrics();
        $rows    = [];
        foreach ($metrics as $label => $value) {
            $rows[] = [str_replace('_', ' ', ucfirst((string)$label)), $value];
        }

        return [
            'name'    => 'Metriques',
            'headers' => ['Indicateur', 'Valeur'],
            'rows'    => $rows,
        ];
    }

    /**
     * Construit la feuille Excel des apprenants.
     */
    public function buildStudentsSheet(): array
    {
        $rows = [];
        foreach ($this->fetchStudents() as $s) {
            $rows[] = [
                $s['id'],
                $s['name'],
                $s['email'],
                date('d/m/Y', strtotime((string)$s['created_at'])),
                $s['enrollments_count'],
                $s['avg_progress'] . '%',
                $s['certificates_count'],
            ];
        }

        return [
            'name'    => 'Apprenants',
            'headers' => ['ID', 'Nom', 'Email', 'Inscrit le', 'Cours suivis', 'Progression moy.', 'Certifications'],
            'rows'    => $rows,
        ];
    }

    /**
     * Construit la feuille Excel des enseignants.
     */
    public function buildTeachersSheet(): array
    {
        $rows = [];
        foreach ($this->fetchTeachers() as $t) {
            $rows[] = [
                $t['id'],
                $t['name'],
                $t['email'],
                date('d/m/Y', strtotime((string)$t['created_at'])),
                $t['courses_assigned'],
            ];
        }

        return [
            'name'    => 'Enseignants',
            'headers' => ['ID', 'Nom', 'Email', 'Inscrit le', 'Cours assignes'],
            'rows'    => $rows,
        ];
    }

    /**
     * Construit la feuille Excel des modules.
     */
    public function buildModulesSheet(): array
    {
        $rows = [];
        foreach ($this->fetchModules() as $m) {
            $rows[] = [
                $m['id'],
                $m['title'],
                $m['description'] ?? '',
                date('d/m/Y', strtotime((string)$m['created_at'])),
                $m['courses_count'],
            ];
        }

        return [
            'name'    => 'Modules',
            'headers' => ['ID', 'Titre', 'Description', 'Cree le', 'Nb cours'],
            'rows'    => $rows,
        ];
    }

    /**
     * Construit la feuille Excel des cours.
     */
    public function buildCoursesSheet(): array
    {
        $rows = [];
        foreach ($this->fetchCourses() as $c) {
            $rows[] = [
                $c['id'],
                $c['title'],
                $c['module_title'],
                $c['teacher_name'],
                $c['creator_name'],
                $c['enrollment_key'] ?? 'Libre',
                $c['start_date'] ?? '',
                $c['end_date'] ?? '',
                $c['eval_deadline'] ?? '',
                $c['exam_duration_minutes'],
                date('d/m/Y', strtotime((string)$c['created_at'])),
            ];
        }

        return [
            'name'    => 'Cours',
            'headers' => ['ID', 'Titre', 'Module', 'Enseignant', 'Cree par', 'Cle', 'Debut', 'Fin', 'Deadline eval.', 'QCM min', 'Cree le'],
            'rows'    => $rows,
        ];
    }

    /**
     * Construit la feuille Excel des inscriptions.
     */
    public function buildEnrollmentsSheet(): array
    {
        $rows = [];
        foreach ($this->fetchEnrollments() as $e) {
            $rows[] = [
                $e['student_name'],
                $e['student_email'],
                $e['course_title'],
                $e['module_title'],
                $e['progress_percent'] . '%',
                date('d/m/Y H:i', strtotime((string)$e['enrolled_at'])),
            ];
        }

        return [
            'name'    => 'Inscriptions',
            'headers' => ['Apprenant', 'Email', 'Cours', 'Module', 'Progression', 'Date inscription'],
            'rows'    => $rows,
        ];
    }

    /**
     * Construit la feuille Excel des certifications.
     */
    public function buildCertificatesSheet(): array
    {
        $rows = [];
        foreach ($this->fetchCertificates() as $c) {
            $rows[] = [
                $c['student_name'],
                $c['student_email'],
                $c['course_title'] ?? 'Inconnu',
                $c['certificate_code'],
                date('d/m/Y H:i', strtotime((string)$c['issued_at'])),
                $c['manual_issue'] ? 'Oui' : 'Non',
            ];
        }

        return [
            'name'    => 'Certifications',
            'headers' => ['Apprenant', 'Email', 'Cours', 'Code', 'Date', 'Manuel'],
            'rows'    => $rows,
        ];
    }

    /**
     * Construit la feuille Excel du journal d'audit.
     */
    public function buildAuditLogsSheet(): array
    {
        $rows = [];
        foreach ($this->fetchAuditLogs() as $log) {
            $rows[] = [
                $log['id'],
                date('d/m/Y H:i', strtotime((string)$log['created_at'])),
                $log['user_name'],
                $log['user_email'] ?? '—',
                $log['action'],
                $log['details'] ?? '',
                $log['ip_address'] ?? '—',
            ];
        }

        return [
            'name'    => 'Journal audit',
            'headers' => ['ID', 'Date', 'Utilisateur', 'Email', 'Action', 'Details', 'IP'],
            'rows'    => $rows,
        ];
    }

    /**
     * Construit la feuille Excel des tentatives de certification (QCM finaux).
     */
    public function buildCertificationAttemptsSheet(): array
    {
        $rows = $this->pdo->query("
            SELECT u.name AS student_name, u.email AS student_email,
                   c.title AS course_title, m.title AS module_title,
                   ca.score, ca.passed, ca.total_questions, ca.attempted_at
            FROM certification_attempts ca
            JOIN users u ON u.id = ca.student_id
            JOIN courses c ON c.id = ca.course_id
            JOIN modules m ON m.id = c.module_id
            ORDER BY ca.attempted_at DESC
        ")->fetchAll();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['student_name'],
                $r['student_email'],
                $r['course_title'],
                $r['module_title'],
                $r['score'] . '%',
                $r['passed'] ? 'Reussi' : 'Echoue',
                $r['total_questions'] ?? '—',
                date('d/m/Y H:i', strtotime((string)$r['attempted_at'])),
            ];
        }

        return [
            'name'    => 'Tentatives QCM',
            'headers' => ['Apprenant', 'Email', 'Cours', 'Module', 'Score', 'Resultat', 'Questions', 'Date'],
            'rows'    => $data,
        ];
    }

    /**
     * Retourne toutes les feuilles pour un export Excel global.
     *
     * @return array<int, array{name: string, headers: string[], rows: array<int, array<int, string|int|float|null>>}>
     */
    public function buildAllSheets(): array
    {
        return [
            $this->buildMetricsSheet(),
            $this->buildStudentsSheet(),
            $this->buildTeachersSheet(),
            $this->buildModulesSheet(),
            $this->buildCoursesSheet(),
            $this->buildEnrollmentsSheet(),
            $this->buildCertificatesSheet(),
            $this->buildCertificationAttemptsSheet(),
            $this->buildAuditLogsSheet(),
        ];
    }

    /**
     * Retourne une feuille unique selon le type demandé par l'URL d'export.
     *
     * @return array{name: string, headers: string[], rows: array<int, array<int, string|int|float|null>>}|null
     */
    public function buildSheetByType(string $type): ?array
    {
        return match ($type) {
            'metrics'                => $this->buildMetricsSheet(),
            'students'               => $this->buildStudentsSheet(),
            'teachers'               => $this->buildTeachersSheet(),
            'modules'                => $this->buildModulesSheet(),
            'courses'                => $this->buildCoursesSheet(),
            'enrollments'            => $this->buildEnrollmentsSheet(),
            'certifications'         => $this->buildCertificatesSheet(),
            'certification_attempts' => $this->buildCertificationAttemptsSheet(),
            'audit_logs'             => $this->buildAuditLogsSheet(),
            default                  => null,
        };
    }
}
