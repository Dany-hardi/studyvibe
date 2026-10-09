<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Mailer.php';
require_once __DIR__ . '/../Newsletter.php';
require_once __DIR__ . '/../lib/Brand.php';
requireRole('promoter');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // requireCsrf();
}

$user = getCurrentUser();

try {
    $pdo = Database::getInstance();

    // Handle Certificate Revocation / Removal (Form POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_certificate') {
        $certId = (int)($_POST['certificate_id'] ?? 0);
        if ($certId > 0) {
            $stmt = $pdo->prepare("SELECT certificate_code, student_id FROM certificates WHERE id = :id");
            $stmt->execute(['id' => $certId]);
            $certData = $stmt->fetch();
            if ($certData) {
                $stmtDel = $pdo->prepare("DELETE FROM certificates WHERE id = :id");
                $stmtDel->execute(['id' => $certId]);
                auditLog('certificate_removed', "Code: {$certData['certificate_code']}, Student ID: {$certData['student_id']}");
                header("Location: /promoter/dashboard.php?success=certificate_removed#tab-certificates");
                exit;
            }
        }
    }

    // Handle Module Creation (Form POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_module') {
        $title = trim((string)$_POST['module_title']);
        $description = trim((string)$_POST['module_desc']);
        if (!empty($title)) {
            $stmt = $pdo->prepare("INSERT INTO modules (title, description) VALUES (:title, :description)");
            $stmt->execute(['title' => $title, 'description' => $description]);
            auditLog('module_created', "Module: {$title}");
            header("Location: /promoter/dashboard.php?success=module_created#tab-academy");
            exit;
        }
    }

    // Handle Tele-Evaluation Postpone (Form POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'postpone_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $startTime = trim((string)($_POST['start_time'] ?? ''));
        $endTime   = trim((string)($_POST['end_time'] ?? ''));
        
        if ($sessionId > 0 && !empty($startTime) && !empty($endTime)) {
            // Check status isn't active/finished if we want to guard, but let's allow promoter full override
            $stmt = $pdo->prepare("UPDATE live_eval_sessions SET start_time = :start, end_time = :end WHERE id = :id");
            $stmt->execute(['start' => $startTime, 'end' => $endTime, 'id' => $sessionId]);
            auditLog('live_eval_postponed', "Session ID: {$sessionId}");
            header("Location: /promoter/dashboard.php?success=session_postponed#tab-live");
            exit;
        }
    }

    // Handle Tele-Evaluation Cancel (Form POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if ($sessionId > 0) {
            $stmt = $pdo->prepare("DELETE FROM live_eval_sessions WHERE id = :id");
            $stmt->execute(['id' => $sessionId]);
            auditLog('live_eval_cancelled', "Session ID: {$sessionId}");
            header("Location: /promoter/dashboard.php?success=session_cancelled#tab-live");
            exit;
        }
    }

    // Fetch Live Session data and telemetry
    $liveSessions = $pdo->query("
        SELECT s.*, u.name AS teacher_name, c.title AS course_title,
               (SELECT COUNT(*) FROM live_eval_registrations r WHERE r.session_id = s.id) AS registered_count,
               (SELECT COUNT(*) FROM live_eval_registrations r WHERE r.session_id = s.id AND r.score IS NOT NULL) AS evaluated_count,
               (SELECT COALESCE(AVG(r.score), 0) FROM live_eval_registrations r WHERE r.session_id = s.id AND r.score IS NOT NULL) AS avg_score
        FROM live_eval_sessions s
        JOIN courses c ON s.course_id = c.id
        JOIN users u ON s.teacher_id = u.id
        ORDER BY s.start_time DESC
    ")->fetchAll();

    $allRegs = $pdo->query("SELECT score FROM live_eval_registrations WHERE score IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    $totalEvaluatedCount = count($allRegs);
    $overallAvgScore = $totalEvaluatedCount > 0 ? array_sum($allRegs) / $totalEvaluatedCount : 0.0;
    
    $successCount = 0;
    foreach ($allRegs as $sc) {
        if ($sc >= 50.0) {
            $successCount++;
        }
    }
    $overallSuccessRate = $totalEvaluatedCount > 0 ? ($successCount / $totalEvaluatedCount) * 100 : 0.0;

    $buckets = [0, 0, 0, 0, 0];
    foreach ($allRegs as $sc) {
        if ($sc <= 20) $buckets[0]++;
        elseif ($sc <= 40) $buckets[1]++;
        elseif ($sc <= 60) $buckets[2]++;
        elseif ($sc <= 80) $buckets[3]++;
        else $buckets[4]++;
    }
    $maxBucket = max(1, max($buckets));

    // Handle Course Creation (Form POST)
    // (Création de cours déplacée vers l'espace enseignant)

    // Fetch all Modules
    $modules = $pdo->query("SELECT * FROM modules ORDER BY id DESC")->fetchAll();

    // Fetch all Courses with Module, Teacher and Creator names
    $courses = $pdo->query("
        SELECT c.*, m.title AS module_title,
               u.name AS teacher_name,
               cr.name AS creator_name
        FROM courses c
        JOIN modules m ON c.module_id = m.id
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN users cr ON c.created_by = cr.id
        ORDER BY c.id DESC
    ")->fetchAll();

    // Fetch all Teachers
    $teachers = $pdo->query("SELECT id, name, email, is_active, is_approved, created_at FROM users WHERE role = 'teacher' ORDER BY name ASC")->fetchAll();

    // Fetch all Certificates
    $certificates = $pdo->query("
        SELECT cert.*, u.name AS student_name, u.email AS student_email, 
               co.title AS course_title,
               ut.name AS teacher_name,
               issuer.name AS issuer_name
        FROM certificates cert
        JOIN users u ON cert.student_id = u.id
        LEFT JOIN courses co ON cert.course_id = co.id
        LEFT JOIN users ut ON co.teacher_id = ut.id
        LEFT JOIN users issuer ON cert.issued_by = issuer.id
        ORDER BY cert.id DESC
    ")->fetchAll();

    // Fetch all Users for User Management panel
    $allUsers = $pdo->query("
        SELECT id, name, email, role, is_active, is_approved, created_at, email_verified_at FROM users ORDER BY role ASC, name ASC
    ")->fetchAll();

    $students = $pdo->query("SELECT id, name, email, is_active, created_at FROM users WHERE role = 'student' ORDER BY name ASC")->fetchAll();

    $auditLogs = $pdo->query("
        SELECT al.*, u.name AS user_name
        FROM audit_logs al
        LEFT JOIN users u ON u.id = al.user_id
        ORDER BY al.id DESC LIMIT 50
    ")->fetchAll();

    // Newsletter — abonnés et campagnes
    $newsletterSubscribers = [];
    $newsletterCampaigns   = [];
    $kpiNewsletterSubs     = 0;
    try {
        $newsletterSubscribers = Newsletter::getActiveSubscribers($pdo);
        $newsletterCampaigns   = Newsletter::getCampaigns($pdo, 10);
        $kpiNewsletterSubs     = count($newsletterSubscribers);
    } catch (PDOException) {
        // Tables newsletter non migrées
    }
    $smtpConfigured = Mailer::isConfigured();

    // ── KPIs (Stats) ──
    $kpiTotalStudents  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $kpiTotalCourses   = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $kpiTotalCerts     = (int)$pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
    $kpiEnrollments    = (int)$pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();
    $kpiAvgProgress    = (float)($pdo->query("SELECT COALESCE(AVG(progress_percent),0) FROM enrollments")->fetchColumn());
    $kpiPassRate       = $pdo->query("
        SELECT COALESCE(ROUND(SUM(passed)/COUNT(*)*100,1), 0)
        FROM certification_attempts
    ")->fetchColumn();

    $totalTeachersCount = count($teachers);
    $pendingTeachersCount = 0;
    foreach ($teachers as $t) {
        if (!(int)$t['is_approved']) {
            $pendingTeachersCount++;
        }
    }
    $totalStudentsCount = count($students);

    // Certification tests passed with no certificate issued yet (decision queue)
    $awaitingCerts = $pdo->query("
        SELECT a.student_id, a.course_id, MAX(a.score) AS score, MAX(a.attempted_at) AS at,
               u.name, u.email, c.title
        FROM certification_attempts a
        JOIN users u ON u.id = a.student_id
        JOIN courses c ON c.id = a.course_id
        WHERE a.passed = 1
          AND NOT EXISTS (SELECT 1 FROM certificates ce WHERE ce.student_id = a.student_id AND ce.course_id = a.course_id)
        GROUP BY a.student_id, a.course_id, u.name, u.email, c.title
        ORDER BY at DESC
    ")->fetchAll();
    $hasAttempts = (int)$pdo->query("SELECT COUNT(*) FROM certification_attempts")->fetchColumn() > 0;

} catch (PDOException $e) {
    dieSafe('Erreur serveur. Veuillez réessayer.', $e, 'promoter/dashboard');
}


$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$li = $lang === 'en' ? 1 : 0;
$PI = (require __DIR__ . '/../locales/promoter-insights.php')[$lang];
$PI_ICO = [
 'qr'    => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="4.5" height="4.5" rx="1"/><rect x="9.5" y="2" width="4.5" height="4.5" rx="1"/><rect x="2" y="9.5" width="4.5" height="4.5" rx="1"/><path d="M9.5 9.5h2v2h-2zM12.5 12.5h1.5v1.5h-1.5zM12.5 9.5H14"/></svg>',
 'down'  => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2v8m0 0L5 7m3 3 3-3M3 13h10"/></svg>',
 'print' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" aria-hidden="true"><path d="M4 6V2h8v4M4 11H2.5V6h11v5H12M4.5 9.5h7V14h-7z"/></svg>',
];
$TX = [
 // chrome
 'title' => ['Console promoteur — StudyVibe', 'Promoter console — StudyVibe'],
 'role_label' => ['Administrateur', 'Institution admin'],
 'theme' => ['Changer de thème', 'Toggle theme'],
 'logout' => ['Déconnexion', 'Sign out'],
 'notif' => ['Notifications', 'Notifications'],
 'notif_none' => ['Aucune notification.', 'No notifications.'],
 'skip' => ['Aller au contenu', 'Skip to content'],
 'nav_label' => ['Sections', 'Sections'],
 'nav_overview' => ['Aujourd’hui', 'Today'],
 'nav_people' => ['Personnes', 'People'],
 'nav_academy' => ['Cours', 'Courses'],
 'nav_live' => ['Évaluations en direct', 'Live evaluations'],
 'nav_certs' => ['Certificats', 'Certificates'],
 'nav_msgs' => ['Messages', 'Messages'],
 'nav_api' => ['Clés API', 'API keys'],
 'nav_audit' => ['Journal et exports', 'Activity and exports'],
 'rail_foot' => ['Chaque action sensible demande une confirmation avant de s’exécuter.', 'Every sensitive action asks for confirmation before it runs.'],
 'ok_generic' => ['Opération effectuée.', 'Done.'],
 'ok_certificate_removed' => ['Certificat révoqué.', 'Certificate revoked.'],
 'ok_module_created' => ['Module créé.', 'Module created.'],
 'ok_session_postponed' => ['Séance reportée.', 'Session rescheduled.'],
 'ok_session_cancelled' => ['Séance annulée.', 'Session cancelled.'],
 // overview
 'ov_h_todo' => ['Ce qui attend votre <em>décision</em>', 'What needs your <em>decision</em>'],
 'ov_h_clear' => ['Tout est <em>à jour</em>', 'Everything is <em>up to date</em>'],
 'ov_date' => ['Bonjour :name. Nous sommes le :date.', 'Hello :name. Today is :date.'],
 'ov_clear_t' => ['Rien n’attend de décision.', 'Nothing is waiting on you.'],
 'ov_clear_p' => ['Aucun compte enseignant à valider, aucun certificat en attente, aucun e-mail non confirmé. Les totaux ci-dessous restent à jour.', 'No teacher account to approve, no certificate waiting, no unconfirmed email. The totals below stay current.'],
 'q_teachers' => ['comptes enseignants à valider', 'teacher accounts to approve'],
 'q_teachers_p' => ['Ces personnes se sont inscrites comme enseignants. Elles ne peuvent pas créer de cours avant votre validation.', 'These people signed up as teachers. They cannot create courses until you approve them.'],
 'q_teacher1' => ['compte enseignant à valider', 'teacher account to approve'],
 'q_certs' => ['certificats à délivrer', 'certificates to issue'],
 'q_cert1' => ['certificat à délivrer', 'certificate to issue'],
 'q_certs_p' => ['Ces étudiants ont réussi l’évaluation de certification mais n’ont pas encore de certificat pour ce cours.', 'These students passed the certification test but have no certificate for that course yet.'],
 'q_unver' => ['comptes sans e-mail confirmé', 'accounts with an unconfirmed email'],
 'q_unver1' => ['compte sans e-mail confirmé', 'account with an unconfirmed email'],
 'q_unver_p' => ['Ces personnes se sont inscrites mais n’ont jamais ouvert le lien de confirmation. Écrivez-leur ou vérifiez l’adresse.', 'These people signed up but never opened the confirmation link. Write to them or check the address.'],
 'q_see_all' => ['Tout voir', 'See all'],
 'q_more' => ['et :n autres', 'and :n more'],
 'smtp_off_t' => ['L’envoi d’e-mails est désactivé.', 'Email sending is switched off.'],
 'smtp_off_p' => ['Les validations, certificats et newsletters ne partiront pas tant que SMTP n’est pas configuré dans .env.', 'Approvals, certificates and newsletters will not go out until SMTP is configured in .env.'],
 'approve' => ['Valider', 'Approve'],
 'issue' => ['Délivrer', 'Issue'],
 'contact' => ['Contacter', 'Write'],
 'review' => ['Voir', 'Review'],
 'since' => ['inscrit le :d', 'signed up :d'],
 'passed_on' => [':s % le :d', ':s% on :d'],
 'tot_h' => ['En chiffres', 'In numbers'],
 'tot_p' => ['Valeurs lues dans la base à l’instant du chargement.', 'Values read from the database when the page loaded.'],
 't_students' => ['Apprenants', 'Students'],
 't_teachers' => ['Enseignants', 'Teachers'],
 't_courses' => ['Cours', 'Courses'],
 't_enroll' => ['Inscriptions', 'Enrollments'],
 't_certs' => ['Certificats délivrés', 'Certificates issued'],
 't_progress' => ['Progression moyenne', 'Average progress'],
 't_pass' => ['Réussite aux certifications', 'Certification pass rate'],
 't_subs' => ['Abonnés newsletter', 'Newsletter subscribers'],
 't_na' => ['Pas encore de données', 'No data yet'],
 // people
 'pe_h' => ['Les <em>personnes</em>', 'The <em>people</em>'],
 'pe_p' => ['Tous les comptes de la plateforme dans un seul tableau. Filtrez par rôle ou par statut, ouvrez le menu d’une ligne pour agir.', 'Every account on the platform in one table. Filter by role or status, open a row’s menu to act.'],
 'pe_create' => ['Créer un compte', 'Create account'],
 'pe_search' => ['Rechercher un nom ou un e-mail', 'Search a name or an email'],
 'f_all' => ['Tous', 'All'],
 'f_teacher' => ['Enseignants', 'Teachers'],
 'f_student' => ['Apprenants', 'Students'],
 'f_promoter' => ['Promoteurs', 'Promoters'],
 'f_role' => ['Filtrer par rôle', 'Filter by role'],
 'f_status' => ['Filtrer par statut', 'Filter by status'],
 'f_s_all' => ['Tous les statuts', 'Any status'],
 's_pending' => ['En attente', 'Pending'],
 's_active' => ['Actif', 'Active'],
 's_suspended' => ['Suspendu', 'Suspended'],
 's_unver' => ['E-mail non confirmé', 'Email unconfirmed'],
 'th_person' => ['Personne', 'Person'],
 'th_role' => ['Rôle', 'Role'],
 'th_status' => ['Statut', 'Status'],
 'th_joined' => ['Inscrit le', 'Joined'],
 'th_actions' => ['Actions', 'Actions'],
 'th_select_all' => ['Tout sélectionner', 'Select all'],
 'role_teacher' => ['Enseignant', 'Teacher'],
 'role_student' => ['Apprenant', 'Student'],
 'role_promoter' => ['Administrateur', 'Institution admin'],
 'you' => ['vous', 'you'],
 'row_menu' => ['Actions pour :name', 'Actions for :name'],
 'row_select' => ['Sélectionner :name', 'Select :name'],
 'pe_none' => ['Aucune personne ne correspond à ces filtres.', 'No one matches these filters.'],
 'pe_count' => ['<b class="num" id="pe-shown">0</b> sur :n personnes', '<b class="num" id="pe-shown">0</b> of :n people'],
 'pe_more' => ['Afficher plus', 'Show more'],
 'sel_n' => ['sélectionné(s)', 'selected'],
 'b_approve' => ['Valider les enseignants', 'Approve teachers'],
 'b_suspend' => ['Suspendre', 'Suspend'],
 'b_reactivate' => ['Réactiver', 'Reactivate'],
 'b_delete' => ['Supprimer', 'Delete'],
 'b_clear' => ['Désélectionner', 'Clear selection'],
 // academy
 'ac_h' => ['Modules et <em>cours</em>', 'Modules and <em>courses</em>'],
 'ac_p' => ['Un module regroupe des cours et ouvre droit à une certification. Attribuez ici un enseignant titulaire à chaque cours.', 'A module groups courses and leads to a certification. Assign a lead teacher to each course here.'],
 'mod_h' => ['Modules', 'Modules'],
 'mod_new' => ['Nouveau module', 'New module'],
 'mod_title' => ['Titre du module', 'Module title'],
 'mod_title_ph' => ['ex : Algorithmique et structures de données', 'e.g. Algorithms and data structures'],
 'mod_desc' => ['Description', 'Description'],
 'mod_desc_ph' => ['Objectifs du module en deux phrases.', 'What the module covers, in two sentences.'],
 'mod_create' => ['Créer le module', 'Create module'],
 'mod_edit' => ['Modifier', 'Edit'],
 'mod_delete' => ['Supprimer', 'Delete'],
 'mod_courses' => [':n cours', ':n courses'],
 'mod_courses1' => ['1 cours', '1 course'],
 'mod_blocked' => ['Impossible tant que des cours sont liés à ce module.', 'Not possible while courses are linked to this module.'],
 'mod_none' => ['Aucun module pour le moment.', 'No module yet.'],
 'mod_edit_h' => ['Modifier le module', 'Edit module'],
 'cs_h' => ['Cours et enseignants titulaires', 'Courses and lead teachers'],
 'cs_p' => ['Retirer un enseignant garde tout le contenu du cours.', 'Removing a teacher keeps all course content.'],
 'th_course' => ['Cours', 'Course'],
 'th_module' => ['Module', 'Module'],
 'th_creator' => ['Créé par', 'Created by'],
 'th_teacher' => ['Enseignant titulaire', 'Lead teacher'],
 'th_key' => ['Clé d’inscription', 'Enrollment key'],
 'cs_none' => ['Aucun cours.', 'No courses.'],
 'cs_unassigned' => ['Non assigné', 'Unassigned'],
 'cs_assign' => ['Assigner', 'Assign'],
 'cs_revoke' => ['Retirer', 'Remove'],
 'cs_open' => ['Libre', 'Open'],
 'cs_teacher_for' => ['Enseignant pour :c', 'Teacher for :c'],
 // live
 'lv_h' => ['Évaluations <em>en direct</em>', 'Live <em>evaluations</em>'],
 'lv_p' => ['Le calendrier des séances et les résultats de toutes les classes.', 'The session calendar and results across all classes.'],
 'lv_evaluated' => ['Participants évalués', 'Participants evaluated'],
 'lv_avg' => ['Moyenne générale', 'Overall average'],
 'lv_rate' => ['Réussite (50 % et plus)', 'Pass rate (50% and above)'],
 'lv_dist' => ['Répartition des notes', 'Score distribution'],
 'lv_dist_p' => ['Nombre de participants par tranche de note.', 'Participants per score band.'],
 'lv_sessions' => ['Séances', 'Sessions'],
 'th_session' => ['Séance', 'Session'],
 'th_lteacher' => ['Enseignant', 'Teacher'],
 'th_regs' => ['Inscrits / évalués', 'Registered / evaluated'],
 'th_avg' => ['Moyenne', 'Average'],
 'th_when' => ['Horaire', 'Schedule'],
 'lv_none' => ['Aucune séance configurée pour le moment.', 'No session configured yet.'],
 'lv_status_lobby' => ['Salle d’attente', 'Lobby'],
 'lv_status_active' => ['En cours', 'Live'],
 'lv_status_finished' => ['Terminée', 'Finished'],
 'lv_status_draft' => ['Brouillon', 'Draft'],
 'lv_code' => ['Code', 'Code'],
 'lv_start' => ['Début', 'Start'],
 'lv_end' => ['Fin', 'End'],
 'lv_pp_h' => ['Reporter la séance', 'Reschedule the session'],
 'lv_pp_start' => ['Début', 'Start'],
 'lv_pp_end' => ['Fin', 'End'],
 'lv_pp_save' => ['Enregistrer', 'Save'],
 // certificates
 'ce_h' => ['Les <em>certificats</em>', '<em>Certificates</em>'],
 'ce_p' => ['Délivrez ceux qui attendent, accordez une exception, retrouvez un certificat par son code ou son titulaire.', 'Issue those waiting, grant an exception, find a certificate by its code or its holder.'],
 'ce_wait_h' => ['En attente de délivrance', 'Waiting to be issued'],
 'ce_wait_none' => ['Aucun certificat en attente. Les réussites récentes ont toutes leur certificat.', 'No certificate waiting. Every recent pass has its certificate.'],
 'ce_manual_h' => ['Délivrance exceptionnelle', 'Exceptional issue'],
 'ce_manual_p' => ['Valide un cours pour un étudiant sans passer par l’évaluation. L’étudiant reçoit le certificat par e-mail.', 'Validates a course for a student without the test. The student receives the certificate by email.'],
 'ce_student' => ['Étudiant', 'Student'],
 'ce_course' => ['Cours validé', 'Course validated'],
 'ce_choose' => ['Choisir…', 'Choose…'],
 'ce_issue' => ['Délivrer le certificat', 'Issue certificate'],
 'ce_reg_h' => ['Registre', 'Register'],
 'ce_export' => ['Exporter en Excel', 'Export to Excel'],
 'ce_search' => ['Rechercher un étudiant, un cours ou un code', 'Search a student, a course or a code'],
 'th_holder' => ['Titulaire', 'Holder'],
 'th_origin' => ['Origine', 'Origin'],
 'th_code' => ['Code', 'Code'],
 'th_issued' => ['Délivré le', 'Issued'],
 'ce_auto' => ['Automatique', 'Automatic'],
 'ce_manual' => ['Manuel', 'Manual'],
 'ce_manual_by' => ['Manuel, par :n', 'Manual, by :n'],
 'ce_none' => ['Aucun certificat délivré pour le moment.', 'No certificate issued yet.'],
 'ce_unknown' => ['Cours supprimé', 'Deleted course'],
 'm_open' => ['Ouvrir la page publique', 'Open public page'],
 'm_copy' => ['Copier le code', 'Copy code'],
 'm_revoke_cert' => ['Révoquer le certificat', 'Revoke certificate'],
 // messages
 'ms_h' => ['Messages et <em>newsletters</em>', 'Messages and <em>newsletters</em>'],
 'ms_p' => ['Ce qui part vers l’extérieur. Chaque envoi indique à qui il va et demande confirmation.', 'Everything that leaves the platform. Each send states who gets it and asks you to confirm.'],
 'nl_h' => ['Rédiger une newsletter', 'Write a newsletter'],
 'nl_smtp_on' => ['Envoi par SMTP actif', 'SMTP sending active'],
 'nl_smtp_off' => ['SMTP non configuré : renseignez .env pour envoyer', 'SMTP not configured: fill in .env to send'],
 'nl_test' => ['Tester SMTP', 'Test SMTP'],
 'nl_audience' => ['Destinataires', 'Recipients'],
 'nl_a_sub' => ['Abonnés à la newsletter', 'Newsletter subscribers'],
 'nl_a_stu' => ['Tous les apprenants', 'All students'],
 'nl_a_all' => ['Abonnés et apprenants', 'Subscribers and students'],
 'nl_a_sub_d' => ['les abonnés actifs de la newsletter', 'the active newsletter subscribers'],
 'nl_a_stu_d' => ['tous les comptes apprenants, confirmés ou non', 'every student account, confirmed or not'],
 'nl_a_all_d' => ['les abonnés et tous les apprenants (sans doublon)', 'subscribers and all students (no duplicates)'],
 'nl_line' => ['Ce message sera envoyé à :n personnes : :d.', 'This message will be sent to :n people: :d.'],
 'nl_subject' => ['Objet', 'Subject'],
 'nl_subject_ph' => ['ex : Ouverture des inscriptions de la session d’été', 'e.g. Summer session enrollment is open'],
 'nl_body' => ['Message', 'Message'],
 'nl_body_ph' => ['Écrivez le message. Le texte est envoyé tel quel, les sauts de ligne sont conservés.', 'Write the message. The text is sent as is, line breaks are kept.'],
 'nl_send' => ['Relire et envoyer…', 'Review and send…'],
 'nl_preview' => ['Aperçu', 'Preview'],
 'nl_prev_to' => ['À : :a (:n personnes)', 'To: :a (:n people)'],
 'nl_prev_empty' => ['Votre message apparaîtra ici.', 'Your message will appear here.'],
 'nl_prev_nosub' => ['(sans objet)', '(no subject)'],
 'nl_prev_foot' => ['Un lien de désinscription est ajouté automatiquement en bas de chaque e-mail.', 'An unsubscribe link is added automatically at the bottom of every email.'],
 'nl_hist_h' => ['Dernières campagnes', 'Recent campaigns'],
 'nl_hist_none' => ['Aucune campagne envoyée.', 'No campaign sent yet.'],
 'nl_hist_item' => [':n destinataires, envoyée par :u le :d', ':n recipients, sent by :u on :d'],
 'nl_subs_h' => ['Voir les abonnés récents', 'See recent subscribers'],
 'nl_subs_none' => ['Aucun abonné pour le moment.', 'No subscriber yet.'],
 'kb_h' => ['Rappeler les clés d’inscription', 'Remind students of enrollment keys'],
 'kb_p' => ['Envoie à chaque apprenant un e-mail avec les clés des cours protégés. Les cours libres et non publiés ne figurent pas dans le message.', 'Sends every student an email listing the keys of protected courses. Open and unpublished courses are left out.'],
 'kb_line' => ['Sera envoyé à :n apprenants.', 'Will be sent to :n students.'],
 'kb_send' => ['Envoyer les clés…', 'Send the keys…'],
 'kb_list' => ['Cours concernés', 'Courses included'],
 'kb_none' => ['Aucun cours n’a de clé d’inscription.', 'No course has an enrollment key.'],
 'dm_h' => ['Écrire à un apprenant', 'Write to a student'],
 'dm_p' => ['Le message arrive directement dans la boîte mail de la personne.', 'The message lands directly in the person’s inbox.'],
 'dm_to' => ['Destinataire', 'Recipient'],
 'dm_subject' => ['Objet', 'Subject'],
 'dm_subject_ph' => ['ex : À propos de votre certificat', 'e.g. About your certificate'],
 'dm_msg' => ['Message', 'Message'],
 'dm_send' => ['Envoyer le message', 'Send message'],
 // api
 'ak_h' => ['Clés <em>API</em>', '<em>API</em> keys'],
 'ak_p' => ['Une clé permet à un outil externe, une application mobile par exemple, de lire la liste des cours et des modules. Elle ne donne aucun accès en écriture.', 'A key lets an outside tool, a mobile app for example, read the list of courses and modules. It gives no write access.'],
 'ak_label' => ['Nom de la clé', 'Key name'],
 'ak_label_ph' => ['ex : Application mobile', 'e.g. Mobile app'],
 'ak_label_hint' => ['Pour savoir plus tard à quoi elle sert.', 'So you know later what it is for.'],
 'ak_create' => ['Créer la clé', 'Create key'],
 'ak_once_h' => ['Copiez cette clé maintenant', 'Copy this key now'],
 'ak_once_p' => ['Elle ne sera plus jamais affichée. StudyVibe n’en garde qu’une empreinte, impossible à relire. Si vous la perdez, révoquez-la et créez-en une autre.', 'It will never be shown again. StudyVibe only keeps a fingerprint that cannot be read back. If you lose it, revoke it and create another.'],
 'ak_copy' => ['Copier', 'Copy'],
 'ak_copied' => ['Copiée', 'Copied'],
 'ak_done' => ['J’ai copié la clé', 'I have copied the key'],
 'ak_use' => ['Utilisation : envoyez la clé dans l’en-tête X-API-Key.', 'Usage: send the key in the X-API-Key header.'],
 'ak_list_h' => ['Clés existantes', 'Existing keys'],
 'th_name' => ['Nom', 'Name'],
 'th_created' => ['Créée le', 'Created'],
 'th_by' => ['Par', 'By'],
 'ak_active' => ['Active', 'Active'],
 'ak_revoked' => ['Révoquée', 'Revoked'],
 'ak_revoke' => ['Révoquer', 'Revoke'],
 'ak_none' => ['Aucune clé pour le moment.', 'No key yet.'],
 'ak_loading' => ['Chargement…', 'Loading…'],
 'ak_default' => ['Clé du :d', 'Key of :d'],
 'ak_nolast' => ['La date de dernière utilisation n’est pas enregistrée par l’API pour le moment.', 'The API does not record when a key was last used yet.'],
 // audit
 'au_h' => ['Journal et <em>exports</em>', 'Activity and <em>exports</em>'],
 'au_p' => ['Les cinquante dernières actions sensibles, un rapport de synthèse et les exports complets.', 'The last fifty sensitive actions, a summary report and the full exports.'],
 'au_log' => ['Dernières actions', 'Latest actions'],
 'au_export' => ['Exporter le journal', 'Export the log'],
 'au_search' => ['Filtrer le journal', 'Filter the log'],
 'th_date' => ['Date', 'Date'],
 'th_author' => ['Auteur', 'Author'],
 'th_action' => ['Action', 'Action'],
 'th_details' => ['Détails', 'Details'],
 'au_none' => ['Le journal est vide.', 'The log is empty.'],
 'ex_h' => ['Exports', 'Exports'],
 'ex_p' => ['Toutes les tables et mesures en un classeur Excel à plusieurs feuilles.', 'Every table and metric in one multi-sheet Excel workbook.'],
 'ex_btn' => ['Télécharger l’export complet', 'Download the full export'],
 'ai_h' => ['Rapport de synthèse', 'Summary report'],
 'ai_p' => ['Un texte rédigé par l’IA à partir des chiffres de la plateforme. À relire avant d’en tirer une décision.', 'A text written by AI from the platform’s figures. Read it critically before acting on it.'],
 'ai_btn' => ['Générer le rapport', 'Generate the report'],
 'ai_wait' => ['Rédaction en cours…', 'Writing…'],
 'ai_print' => ['Imprimer', 'Print'],
 // dialogs
 'cancel' => ['Annuler', 'Cancel'],
 'close' => ['Fermer', 'Close'],
 'back' => ['Retour', 'Back'],
 'cu_h' => ['Créer un compte', 'Create an account'],
 'cu_p' => ['La personne pourra se connecter tout de suite avec ce mot de passe.', 'The person can sign in right away with this password.'],
 'cu_name' => ['Nom complet', 'Full name'],
 'cu_name_ph' => ['ex : Dr Isabelle Martin', 'e.g. Dr Isabelle Martin'],
 'cu_email' => ['Adresse e-mail', 'Email address'],
 'cu_email_ph' => ['prenom@etablissement.edu', 'name@institution.edu'],
 'cu_pass' => ['Mot de passe initial', 'Initial password'],
 'cu_pass_hint' => ['8 caractères minimum.', 'At least 8 characters.'],
 'cu_role' => ['Rôle', 'Role'],
 'cu_role_promoter' => ['Promoteur (co-administrateur)', 'Promoter (co-administrator)'],
 'cu_submit' => ['Créer le compte', 'Create account'],
 'confirm' => ['Confirmer', 'Confirm'],
 'jserr' => ['Une erreur est survenue.', 'Something went wrong.'],
];
$t = fn(string $k, array $r = []): string => strtr($TX[$k][$li] ?? $k, array_combine(array_map(fn($x) => ':' . $x, array_keys($r)), array_values($r)) ?: []);
$h = fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$fmtD = fn($ts): string => $ts ? ($lang === 'en' ? date('M j, Y', strtotime((string)$ts)) : date('d/m/Y', strtotime((string)$ts))) : '';
$fmtDT = fn($ts): string => $ts ? ($lang === 'en' ? date('M j, Y g:i A', strtotime((string)$ts)) : date('d/m/Y H:i', strtotime((string)$ts))) : '';
$roleName = ['teacher' => $t('role_teacher'), 'student' => $t('role_student'), 'promoter' => $t('role_promoter')];

// JS dictionary (strings used by promoter.js)
$JT = [
 'fr' => [
  'ok' => 'Opération effectuée.', 'err' => 'Une erreur est survenue.', 'net' => 'Erreur réseau : ',
  'm_approve' => 'Valider', 'm_contact' => 'Écrire', 'm_suspend' => 'Suspendre', 'm_reactivate' => 'Réactiver', 'm_delete' => 'Supprimer…',
  'm_open' => 'Ouvrir la page publique', 'm_copy' => 'Copier le code', 'm_revoke_cert' => 'Révoquer le certificat…',
  'm_postpone' => 'Reporter', 'm_cancel_session' => 'Annuler la séance…',
  'copied' => 'Copié.', 'cancel' => 'Annuler', 'confirm' => 'Confirmer', 'sending' => 'Envoi en cours…',
  'approve_t' => 'Valider ce compte enseignant ?', 'approve_p' => '{name} pourra créer des cours. Un e-mail de confirmation lui sera envoyé.', 'approve_b' => 'Valider',
  'suspend_t' => 'Suspendre {name} ?', 'suspend_p' => 'La personne ne pourra plus se connecter tant que le compte est suspendu. Rien n’est supprimé.', 'suspend_b' => 'Suspendre',
  'reactivate_t' => 'Réactiver {name} ?', 'reactivate_p' => 'La personne pourra de nouveau se connecter.', 'reactivate_b' => 'Réactiver',
  'delete_t' => 'Supprimer {name} définitivement ?', 'delete_p' => 'Le compte et toutes ses données (inscriptions, notes, certificats) seront effacés. Cette action ne peut pas être annulée.', 'delete_b' => 'Supprimer définitivement',
  'phrase' => 'supprimer', 'phrase_hint' => 'Pour confirmer, tapez « {w} » ci-dessous.',
  'bulk_approve_t' => 'Valider {n} enseignants ?', 'bulk_approve_p' => 'Seuls les comptes enseignants en attente sont concernés. Chacun recevra un e-mail.',
  'bulk_suspend_t' => 'Suspendre {n} comptes ?', 'bulk_suspend_p' => 'Ces personnes ne pourront plus se connecter. Votre propre compte est ignoré.',
  'bulk_reactivate_t' => 'Réactiver {n} comptes ?', 'bulk_reactivate_p' => 'Seuls les comptes suspendus sont concernés.',
  'bulk_delete_t' => 'Supprimer {n} comptes définitivement ?', 'bulk_delete_p' => 'Ces comptes et toutes leurs données seront effacés. Cette action ne peut pas être annulée. Votre propre compte est ignoré.',
  'bulk_done' => '{ok} sur {n} comptes mis à jour.', 'bulk_none' => 'Aucun compte sélectionné n’est concerné par cette action.',
  'issue_t' => 'Délivrer le certificat ?', 'issue_p' => '{name} recevra le certificat pour « {course} » par e-mail.', 'issue_b' => 'Délivrer',
  'revcert_t' => 'Révoquer ce certificat ?', 'revcert_p' => 'Le certificat {code} de {name} sera supprimé définitivement et son lien de vérification cessera de fonctionner.', 'revcert_b' => 'Révoquer définitivement',
  'cancels_t' => 'Annuler cette séance ?', 'cancels_p' => '« {name} » sera supprimée avec ses inscriptions et ses résultats. Cette action ne peut pas être annulée.', 'cancels_b' => 'Supprimer la séance',
  'revteacher_t' => 'Retirer l’enseignant de ce cours ?', 'revteacher_p' => 'Le contenu pédagogique est conservé. Le cours n’aura plus d’enseignant titulaire.', 'revteacher_b' => 'Retirer',
  'pick_teacher' => 'Choisissez un enseignant à assigner.', 'assigned' => 'Assigné', 'removed' => 'Retiré',
  'moddel_t' => 'Supprimer ce module ?', 'moddel_p' => '« {name} » sera supprimé. Aucun cours n’y est rattaché.', 'moddel_b' => 'Supprimer le module',
  'nl_t' => 'Envoyer cette newsletter à {n} personnes ?', 'nl_p' => 'Objet : « {subject} »<br>Destinataires : {aud}.<br>Les e-mails partent immédiatement et on ne peut pas les rappeler.', 'nl_b' => 'Envoyer à {n} personnes',
  'nl_empty' => 'Renseignez l’objet et le message avant d’envoyer.', 'nl_norecip' => 'Cette audience ne compte aucun destinataire.',
  'kb_t' => 'Envoyer les clés à {n} apprenants ?', 'kb_p' => 'Chaque apprenant reçoit un e-mail listant les clés d’inscription des cours protégés. L’envoi est immédiat.', 'kb_b' => 'Envoyer à {n} apprenants', 'kb_sending' => 'Diffusion en cours…',
  'akrev_t' => 'Révoquer la clé « {name} » ?', 'akrev_p' => 'Tout outil qui utilise cette clé perdra l’accès immédiatement. Une clé révoquée ne peut pas être réactivée.', 'akrev_b' => 'Révoquer la clé',
  'ak_created' => 'Clé créée.', 'ak_default' => 'Clé du {d}', 'ak_active' => 'Active', 'ak_revoked' => 'Révoquée', 'ak_revoke' => 'Révoquer', 'ak_none' => 'Aucune clé pour le moment.',
  'ak_copied' => 'Copiée', 'ak_copy' => 'Copier', 'ak_revoked_ok' => 'Clé révoquée.',
  'cert_ok' => 'Certificat {code} délivré à {name}.', 'ai_ok' => 'Rapport généré.', 'dm_sending' => 'Envoi…', 'dm_send' => 'Envoyer le message', 'creating' => 'Création…', 'cu_submit' => 'Créer le compte',
  'notif_none' => 'Aucune notification.', 'smtp_test' => 'Test SMTP lancé.',
  'prev_nosub' => '(sans objet)', 'a_sub' => 'les abonnés actifs de la newsletter', 'a_stu' => 'tous les comptes apprenants, confirmés ou non', 'a_all' => 'les abonnés et tous les apprenants (sans doublon)',
  'line' => 'Ce message sera envoyé à {n} personnes : {d}.', 'prev_to' => 'À : {a} ({n} personnes)', 'edit' => 'Modifier', 'role_you' => 'vous',
 ],
 'en' => [
  'ok' => 'Done.', 'err' => 'Something went wrong.', 'net' => 'Network error: ',
  'm_approve' => 'Approve', 'm_contact' => 'Write', 'm_suspend' => 'Suspend', 'm_reactivate' => 'Reactivate', 'm_delete' => 'Delete…',
  'm_open' => 'Open public page', 'm_copy' => 'Copy code', 'm_revoke_cert' => 'Revoke certificate…',
  'm_postpone' => 'Reschedule', 'm_cancel_session' => 'Cancel session…',
  'copied' => 'Copied.', 'cancel' => 'Cancel', 'confirm' => 'Confirm', 'sending' => 'Sending…',
  'approve_t' => 'Approve this teacher account?', 'approve_p' => '{name} will be able to create courses. A confirmation email is sent to them.', 'approve_b' => 'Approve',
  'suspend_t' => 'Suspend {name}?', 'suspend_p' => 'They will not be able to sign in while the account is suspended. Nothing is deleted.', 'suspend_b' => 'Suspend',
  'reactivate_t' => 'Reactivate {name}?', 'reactivate_p' => 'They will be able to sign in again.', 'reactivate_b' => 'Reactivate',
  'delete_t' => 'Delete {name} permanently?', 'delete_p' => 'The account and all its data (enrollments, grades, certificates) will be erased. This cannot be undone.', 'delete_b' => 'Delete permanently',
  'phrase' => 'delete', 'phrase_hint' => 'To confirm, type “{w}” below.',
  'bulk_approve_t' => 'Approve {n} teachers?', 'bulk_approve_p' => 'Only pending teacher accounts are affected. Each one receives an email.',
  'bulk_suspend_t' => 'Suspend {n} accounts?', 'bulk_suspend_p' => 'These people will not be able to sign in. Your own account is skipped.',
  'bulk_reactivate_t' => 'Reactivate {n} accounts?', 'bulk_reactivate_p' => 'Only suspended accounts are affected.',
  'bulk_delete_t' => 'Delete {n} accounts permanently?', 'bulk_delete_p' => 'These accounts and all their data will be erased. This cannot be undone. Your own account is skipped.',
  'bulk_done' => '{ok} of {n} accounts updated.', 'bulk_none' => 'None of the selected accounts is affected by this action.',
  'issue_t' => 'Issue the certificate?', 'issue_p' => '{name} will receive the certificate for “{course}” by email.', 'issue_b' => 'Issue',
  'revcert_t' => 'Revoke this certificate?', 'revcert_p' => 'Certificate {code} for {name} will be deleted permanently and its verification link will stop working.', 'revcert_b' => 'Revoke permanently',
  'cancels_t' => 'Cancel this session?', 'cancels_p' => '“{name}” will be deleted with its registrations and results. This cannot be undone.', 'cancels_b' => 'Delete the session',
  'revteacher_t' => 'Remove the teacher from this course?', 'revteacher_p' => 'The course content is kept. The course will have no lead teacher.', 'revteacher_b' => 'Remove',
  'pick_teacher' => 'Choose a teacher to assign.', 'assigned' => 'Assigned', 'removed' => 'Removed',
  'moddel_t' => 'Delete this module?', 'moddel_p' => '“{name}” will be deleted. No course is linked to it.', 'moddel_b' => 'Delete the module',
  'nl_t' => 'Send this newsletter to {n} people?', 'nl_p' => 'Subject: “{subject}”<br>Recipients: {aud}.<br>Emails go out immediately and cannot be recalled.', 'nl_b' => 'Send to {n} people',
  'nl_empty' => 'Fill in the subject and the message before sending.', 'nl_norecip' => 'This audience has no recipient.',
  'kb_t' => 'Send the keys to {n} students?', 'kb_p' => 'Every student receives an email listing the enrollment keys of protected courses. It goes out immediately.', 'kb_b' => 'Send to {n} students', 'kb_sending' => 'Sending…',
  'akrev_t' => 'Revoke the key “{name}”?', 'akrev_p' => 'Any tool using this key loses access immediately. A revoked key cannot be reactivated.', 'akrev_b' => 'Revoke key',
  'ak_created' => 'Key created.', 'ak_default' => 'Key of {d}', 'ak_active' => 'Active', 'ak_revoked' => 'Revoked', 'ak_revoke' => 'Revoke', 'ak_none' => 'No key yet.',
  'ak_copied' => 'Copied', 'ak_copy' => 'Copy', 'ak_revoked_ok' => 'Key revoked.',
  'cert_ok' => 'Certificate {code} issued to {name}.', 'ai_ok' => 'Report generated.', 'dm_sending' => 'Sending…', 'dm_send' => 'Send message', 'creating' => 'Creating…', 'cu_submit' => 'Create account',
  'notif_none' => 'No notifications.', 'smtp_test' => 'SMTP test started.',
  'prev_nosub' => '(no subject)', 'a_sub' => 'the active newsletter subscribers', 'a_stu' => 'every student account, confirmed or not', 'a_all' => 'subscribers and all students (no duplicates)',
  'line' => 'This message will be sent to {n} people: {d}.', 'prev_to' => 'To: {a} ({n} people)', 'edit' => 'Edit', 'role_you' => 'you',
 ],
][$lang];

// Newsletter audience sizes (read-only counts, no side effects)
$subEmails = array_map(fn($s) => strtolower((string)$s['email']), $newsletterSubscribers);
$stuEmails = array_map(fn($s) => strtolower((string)$s['email']), $students);
$audCounts = [
    'subscribers' => count(array_unique($subEmails)),
    'students'    => count(array_unique($stuEmails)),
    'all'         => count(array_unique(array_merge($subEmails, $stuEmails))),
];
$keyedCourses = array_values(array_filter($courses, fn($c) => !empty($c['enrollment_key'])));

// Decision queue
$pendingTeachers = array_values(array_filter($teachers, fn($x) => !(int)$x['is_approved']));
$unverifiedUsers = array_values(array_filter($allUsers, fn($x) => empty($x['email_verified_at'])));
$queueTotal = count($pendingTeachers) + count($awaitingCerts) + count($unverifiedUsers) + ($smtpConfigured ? 0 : 1);

// People counters
$cnt = ['all' => count($allUsers), 'teacher' => 0, 'student' => 0, 'promoter' => 0, 'pending' => 0, 'active' => 0, 'suspended' => 0, 'unverified' => 0];
foreach ($allUsers as $u) {
    $cnt[$u['role']] = ($cnt[$u['role']] ?? 0) + 1;
    $isPending = $u['role'] === 'teacher' && !(int)$u['is_approved'];
    if ($isPending) $cnt['pending']++;
    if (!(int)$u['is_active']) $cnt['suspended']++;
    if (empty($u['email_verified_at'])) $cnt['unverified']++;
    if ((int)$u['is_active'] && !$isPending) $cnt['active']++;
}
$myId = (int)$_SESSION['user_id'];
$firstName = trim(explode(' ', (string)$user['name'])[0] ?? '');
$moduleCourseCount = [];
foreach ($courses as $c) { $moduleCourseCount[(int)$c['module_id']] = ($moduleCourseCount[(int)$c['module_id']] ?? 0) + 1; }
$okKey = isset($_GET['success']) ? (string)$_GET['success'] : '';
$okMsg = $okKey !== '' ? ($TX['ok_' . $okKey][$li] ?? $t('ok_generic')) : '';
$todayStr = $lang === 'en' ? date('l, F j') : (['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'][(int)date('w')] . ' ' . date('j') . ' ' . ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'][(int)date('n') - 1]);

$ico = [
 'search' => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="7" cy="7" r="4.6"/><path d="M10.5 10.5 14 14"/></svg>',
 'dots'   => '<svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><circle cx="3" cy="8" r="1.4"/><circle cx="8" cy="8" r="1.4"/><circle cx="13" cy="8" r="1.4"/></svg>',
 'bell'   => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 11V7a4 4 0 0 1 8 0v4l1 1.5H3z"/><path d="M6.5 14a1.5 1.5 0 0 0 3 0"/></svg>',
];

function pm_chips(array $u, callable $t): string {
    $isPending = $u['role'] === 'teacher' && !(int)$u['is_approved'];
    $o = '<span class="st-row">';
    if ($isPending) $o .= '<span class="st wait">' . $t('s_pending') . '</span>';
    elseif (!(int)$u['is_active']) $o .= '<span class="st off">' . $t('s_suspended') . '</span>';
    else $o .= '<span class="st ok">' . $t('s_active') . '</span>';
    if ($isPending && !(int)$u['is_active']) $o .= '<span class="st off">' . $t('s_suspended') . '</span>';
    if (empty($u['email_verified_at'])) $o .= '<span class="st hollow">' . $t('s_unver') . '</span>';
    return $o . '</span>';
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#F5F0E6">
<title><?= $h($t('title')) ?></title>
<?= Brand::headLinks() ?>
<link rel="icon" type="image/png" href="/assets/img/favicon.png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/sv2.css">
<link rel="stylesheet" href="/assets/css/promoter.css">
<link rel="stylesheet" href="/assets/css/promoter-v3.css">
<?= csrfMetaTag(); ?>
<script>
  document.documentElement.classList.add('js');
  try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
</script>
</head>
<body class="v2 pm">
<a class="sr" href="#pm-main"><?= $t('skip') ?></a>

<header class="pm-top">
  <div class="pm-top-in">
    <?= Brand::logo('md') ?>
    <span class="pm-role"><?= $t('role_label') ?></span>
    <div class="pm-tools">
      <div class="pm-bell" id="notif-wrap">
        <button class="chip" type="button" id="notif-btn" aria-label="<?= $t('notif') ?>" aria-expanded="false" aria-controls="notif-panel"><?= $ico['bell'] ?><span class="dot" id="notif-count" hidden></span></button>
        <div class="pm-notif" id="notif-panel" hidden></div>
      </div>
      <span class="pm-user"><?= $h($user['name']) ?></span>
      <div class="seg" role="group" aria-label="Language">
        <a href="#" data-lang="fr" <?= $lang === 'fr' ? 'aria-current="true"' : '' ?>>FR</a>
        <a href="#" data-lang="en" <?= $lang === 'en' ? 'aria-current="true"' : '' ?>>EN</a>
      </div>
      <button class="chip" type="button" data-dark-toggle aria-label="<?= $t('theme') ?>"><svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 2a6 6 0 0 1 0 12z" fill="currentColor"/></svg></button>
      <a class="chip" href="/account/security.php"><?= TranslationService::getLang() === 'en' ? 'Security' : 'Sécurité' ?></a>
      <a class="chip" href="/logout.php"><?= $t('logout') ?></a>
    </div>
  </div>
</header>

<div class="pm-shell">
  <nav class="pm-rail" aria-label="<?= $t('nav_label') ?>">
    <p class="pm-rail-g"><?= $h($PI['nav_g_steer']) ?></p>
    <ul>
      <li><a href="#tab-overview" data-tab="tab-overview"><span><?= $h($PI['nav_command']) ?></span><?php if ($queueTotal > 0): ?><span class="pm-count num"><?= $queueTotal ?></span><?php endif; ?></a></li>
      <li><a href="#tab-growth" data-tab="tab-growth"><span><?= $h($PI['nav_growth']) ?></span></a></li>
      <li><a href="#tab-insights" data-tab="tab-insights"><span><?= $h($PI['nav_insights']) ?></span></a></li>
    </ul>
    <p class="pm-rail-g"><?= $h($PI['nav_g_manage']) ?></p>
    <ul>
      <li><a href="#tab-people" data-tab="tab-people"><span><?= $t('nav_people') ?></span><?php if ($cnt['pending'] > 0): ?><span class="pm-count num"><?= $cnt['pending'] ?></span><?php endif; ?></a></li>
      <li><a href="#tab-academy" data-tab="tab-academy"><span><?= $t('nav_academy') ?></span></a></li>
      <li><a href="#tab-live" data-tab="tab-live"><span><?= $t('nav_live') ?></span></a></li>
      <li><a href="#tab-certificates" data-tab="tab-certificates"><span><?= $t('nav_certs') ?></span><?php if (count($awaitingCerts) > 0): ?><span class="pm-count num"><?= count($awaitingCerts) ?></span><?php endif; ?></a></li>
      <li><a href="#tab-communications" data-tab="tab-communications"><span><?= $t('nav_msgs') ?></span></a></li>
    </ul>
    <p class="pm-rail-g"><?= $h($PI['nav_g_system']) ?></p>
    <ul>
      <li><a href="#tab-api" data-tab="tab-api"><span><?= $t('nav_api') ?></span></a></li>
      <li><a href="#tab-audit" data-tab="tab-audit"><span><?= $t('nav_audit') ?></span></a></li>
    </ul>
    <p class="pm-rail-foot"><?= $t('rail_foot') ?></p>
  </nav>

  <main class="pm-main" id="pm-main" tabindex="-1">

  <!-- ============ TODAY ============ -->
  <section id="tab-overview" class="tab-content pm-panel" data-panel>
    <?php require __DIR__ . '/panels/overview-top.php'; ?>

    <?php if (!$smtpConfigured): ?>
      <div class="q-warn" role="status">
        <div><b><?= $t('smtp_off_t') ?></b><span><?= $t('smtp_off_p') ?></span></div>
      </div>
    <?php endif; ?>

    <?php if (count($pendingTeachers) + count($awaitingCerts) + count($unverifiedUsers) === 0): ?>
      <div class="q-clear"><b><?= $t('ov_clear_t') ?></b><?= $t('ov_clear_p') ?></div>
    <?php else: ?>
    <div class="queue">
      <?php if ($pendingTeachers): ?>
      <section class="q-group" aria-labelledby="q-t">
        <header>
          <div class="q-title"><span class="q-n"><?= count($pendingTeachers) ?></span><h2 id="q-t"><?= count($pendingTeachers) === 1 ? $t('q_teacher1') : $t('q_teachers') ?></h2></div>
          <button type="button" class="link" data-goto="tab-people" data-fstatus="pending"><?= $t('q_see_all') ?></button>
        </header>
        <p class="q-sub"><?= $t('q_teachers_p') ?></p>
        <div class="q-rows">
          <?php foreach (array_slice($pendingTeachers, 0, 5) as $pt): ?>
            <div class="q-row">
              <div class="q-who"><b><?= $h($pt['name']) ?></b><span><?= $h($pt['email']) ?> · <?= $h($t('since', ['d' => $fmtD($pt['created_at'])])) ?></span></div>
              <div class="q-act"><button type="button" class="btn btn-primary btn-sm" data-user-act="approve" data-id="<?= (int)$pt['id'] ?>" data-name="<?= $h($pt['name']) ?>"><?= $t('approve') ?></button></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($pendingTeachers) > 5): ?><p class="note" style="margin-top:.6rem"><?= $h($t('q_more', ['n' => count($pendingTeachers) - 5])) ?></p><?php endif; ?>
      </section>
      <?php endif; ?>

      <?php if ($awaitingCerts): ?>
      <section class="q-group" aria-labelledby="q-c">
        <header>
          <div class="q-title"><span class="q-n"><?= count($awaitingCerts) ?></span><h2 id="q-c"><?= count($awaitingCerts) === 1 ? $t('q_cert1') : $t('q_certs') ?></h2></div>
          <button type="button" class="link" data-goto="tab-certificates"><?= $t('q_see_all') ?></button>
        </header>
        <p class="q-sub"><?= $t('q_certs_p') ?></p>
        <div class="q-rows">
          <?php foreach (array_slice($awaitingCerts, 0, 5) as $ac): ?>
            <div class="q-row">
              <div class="q-who"><b><?= $h($ac['name']) ?> · <?= $h($ac['title']) ?></b><span><?= $h($t('passed_on', ['s' => rtrim(rtrim(number_format((float)$ac['score'], 1, '.', ''), '0'), '.'), 'd' => $fmtD($ac['at'])])) ?></span></div>
              <div class="q-act"><button type="button" class="btn btn-primary btn-sm" data-issue data-student="<?= (int)$ac['student_id'] ?>" data-course="<?= (int)$ac['course_id'] ?>" data-name="<?= $h($ac['name']) ?>" data-ctitle="<?= $h($ac['title']) ?>"><?= $t('issue') ?></button></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($awaitingCerts) > 5): ?><p class="note" style="margin-top:.6rem"><?= $h($t('q_more', ['n' => count($awaitingCerts) - 5])) ?></p><?php endif; ?>
      </section>
      <?php endif; ?>

      <?php if ($unverifiedUsers): ?>
      <section class="q-group" aria-labelledby="q-u">
        <header>
          <div class="q-title"><span class="q-n"><?= count($unverifiedUsers) ?></span><h2 id="q-u"><?= count($unverifiedUsers) === 1 ? $t('q_unver1') : $t('q_unver') ?></h2></div>
          <button type="button" class="link" data-goto="tab-people" data-fstatus="unverified"><?= $t('q_see_all') ?></button>
        </header>
        <p class="q-sub"><?= $t('q_unver_p') ?></p>
        <div class="q-rows">
          <?php foreach (array_slice($unverifiedUsers, 0, 4) as $uu): ?>
            <div class="q-row">
              <div class="q-who"><b><?= $h($uu['name']) ?></b><span><?= $h($uu['email']) ?> · <?= $h($t('since', ['d' => $fmtD($uu['created_at'])])) ?></span></div>
              <?php if ($uu['role'] === 'student'): ?><div class="q-act"><button type="button" class="btn btn-ghost btn-sm" data-user-act="contact" data-id="<?= (int)$uu['id'] ?>" data-name="<?= $h($uu['name']) ?>"><?= $t('contact') ?></button></div><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($unverifiedUsers) > 4): ?><p class="note" style="margin-top:.6rem"><?= $h($t('q_more', ['n' => count($unverifiedUsers) - 4])) ?></p><?php endif; ?>
      </section>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <section class="pm-sec" aria-labelledby="tot-h">
      <header><h2 id="tot-h"><?= $t('tot_h') ?></h2><p><?= $t('tot_p') ?></p></header>
      <dl class="totals">
        <div><dd class="num"><?= $kpiTotalStudents ?></dd><dt><?= $t('t_students') ?></dt></div>
        <div><dd class="num"><?= $totalTeachersCount ?></dd><dt><?= $t('t_teachers') ?></dt></div>
        <div><dd class="num"><?= $kpiTotalCourses ?></dd><dt><?= $t('t_courses') ?></dt></div>
        <div><dd class="num"><?= $kpiEnrollments ?></dd><dt><?= $t('t_enroll') ?></dt></div>
        <div><dd class="num"><?= $kpiTotalCerts ?></dd><dt><?= $t('t_certs') ?></dt></div>
        <div><?php if ($kpiEnrollments > 0): ?><dd class="num"><?= round($kpiAvgProgress) ?><small>%</small></dd><?php else: ?><dd class="na"><?= $t('t_na') ?></dd><?php endif; ?><dt><?= $t('t_progress') ?></dt></div>
        <div><?php if ($hasAttempts): ?><dd class="num"><?= round((float)$kpiPassRate) ?><small>%</small></dd><?php else: ?><dd class="na"><?= $t('t_na') ?></dd><?php endif; ?><dt><?= $t('t_pass') ?></dt></div>
        <div><dd class="num"><?= $kpiNewsletterSubs ?></dd><dt><?= $t('t_subs') ?></dt></div>
      </dl>
    </section>
  </section>

  <!-- ============ GROWTH + INSIGHTS (v3) ============ -->
  <?php require __DIR__ . '/panels/growth.php'; ?>
  <?php require __DIR__ . '/panels/insights.php'; ?>

  <!-- ============ PEOPLE ============ -->
  <section id="tab-people" class="tab-content pm-panel" data-panel hidden>
    <div class="pm-head">
      <div><h1><?= $t('pe_h') ?></h1><p><?= $t('pe_p') ?></p></div>
      <div class="pm-head-actions"><button type="button" class="btn btn-primary" data-open="user-modal"><?= $t('pe_create') ?></button></div>
    </div>

    <div class="toolbar">
      <div class="search">
        <?= $ico['search'] ?>
        <input class="input" type="search" id="pe-q" placeholder="<?= $h($t('pe_search')) ?>" aria-label="<?= $h($t('pe_search')) ?>" autocomplete="off">
      </div>
      <div class="pills" role="group" aria-label="<?= $h($t('f_role')) ?>" id="pe-roles">
        <button type="button" class="pill" data-role="all" aria-pressed="true"><?= $t('f_all') ?> <small><?= $cnt['all'] ?></small></button>
        <button type="button" class="pill" data-role="teacher" aria-pressed="false"><?= $t('f_teacher') ?> <small><?= $cnt['teacher'] ?></small></button>
        <button type="button" class="pill" data-role="student" aria-pressed="false"><?= $t('f_student') ?> <small><?= $cnt['student'] ?></small></button>
        <button type="button" class="pill" data-role="promoter" aria-pressed="false"><?= $t('f_promoter') ?> <small><?= $cnt['promoter'] ?></small></button>
      </div>
      <select class="input" id="pe-status" aria-label="<?= $h($t('f_status')) ?>" style="width:auto;min-width:200px">
        <option value="all"><?= $t('f_s_all') ?></option>
        <option value="pending"><?= $t('s_pending') ?> (<?= $cnt['pending'] ?>)</option>
        <option value="active"><?= $t('s_active') ?> (<?= $cnt['active'] ?>)</option>
        <option value="suspended"><?= $t('s_suspended') ?> (<?= $cnt['suspended'] ?>)</option>
        <option value="unverified"><?= $t('s_unver') ?> (<?= $cnt['unverified'] ?>)</option>
      </select>
    </div>

    <div class="bulk" id="pe-bulk" hidden role="region" aria-live="polite">
      <span><b id="pe-bulk-n">0</b> <?= $t('sel_n') ?></span>
      <button type="button" class="btn btn-sm btn-ghost" data-bulk="approve"><?= $t('b_approve') ?></button>
      <button type="button" class="btn btn-sm btn-ghost" data-bulk="suspend"><?= $t('b_suspend') ?></button>
      <button type="button" class="btn btn-sm btn-ghost" data-bulk="reactivate"><?= $t('b_reactivate') ?></button>
      <button type="button" class="btn btn-sm btn-ghost" data-bulk="delete" style="color:var(--danger)"><?= $t('b_delete') ?>…</button>
      <span class="spacer"></span>
      <button type="button" class="link" id="pe-bulk-clear"><?= $t('b_clear') ?></button>
    </div>

    <div class="tbl-wrap">
      <table class="tbl" id="pe-table">
        <thead><tr>
          <th class="c-check"><input type="checkbox" id="pe-all" aria-label="<?= $h($t('th_select_all')) ?>"></th>
          <th><?= $t('th_person') ?></th>
          <th class="c-hide-sm"><?= $t('th_role') ?></th>
          <th class="c-hide-sm"><?= $t('th_status') ?></th>
          <th class="c-hide-sm"><?= $t('th_joined') ?></th>
          <th class="c-act"><span class="sr"><?= $t('th_actions') ?></span></th>
        </tr></thead>
        <tbody id="pe-body">
        <?php foreach ($allUsers as $u):
            $isPending = $u['role'] === 'teacher' && !(int)$u['is_approved'];
            $isActive = (int)$u['is_active'];
            $isSelf = (int)$u['id'] === $myId;
            $status = $isPending ? 'pending' : ($isActive ? 'active' : 'suspended');
            $stAll = trim(($isPending ? 'pending ' : '') . (!$isActive ? 'suspended ' : '') . (($isActive && !$isPending) ? 'active ' : '') . (empty($u['email_verified_at']) ? 'unverified' : ''));
            $acts = [];
            if ($isPending) $acts[] = 'approve';
            if ($u['role'] === 'student') $acts[] = 'contact';
            if (!$isSelf) { $acts[] = $isActive ? 'suspend' : 'reactivate'; $acts[] = 'delete'; }
        ?>
          <tr data-user="<?= (int)$u['id'] ?>" data-role="<?= $h($u['role']) ?>" data-st="<?= $h($stAll) ?>" data-active="<?= $isActive ?>" data-pending="<?= $isPending ? 1 : 0 ?>" data-self="<?= $isSelf ? 1 : 0 ?>" data-q="<?= $h(mb_strtolower($u['name'] . ' ' . $u['email'])) ?>">
            <td class="c-check"><?php if (!$isSelf): ?><input type="checkbox" class="pe-chk" value="<?= (int)$u['id'] ?>" aria-label="<?= $h($t('row_select', ['name' => $u['name']])) ?>"><?php endif; ?></td>
            <td><span class="name"><?= $h($u['name']) ?><?= $isSelf ? ' <span class="muted" style="font-weight:500">(' . $t('you') . ')</span>' : '' ?></span><span class="sub"><?= $h($u['email']) ?><span class="role-inline"> · <?= $h($roleName[$u['role']] ?? $u['role']) ?></span></span><span class="st-inline"><?= pm_chips($u, $t) ?></span></td>
            <td class="c-hide-sm"><?= $h($roleName[$u['role']] ?? $u['role']) ?></td>
            <td class="c-hide-sm"><?= pm_chips($u, $t) ?></td>
            <td class="c-hide-sm c-nowrap num"><?= $h($fmtD($u['created_at'])) ?></td>
            <td class="c-act"><?php if ($acts): ?><button type="button" class="kebab" aria-haspopup="menu" aria-expanded="false" aria-label="<?= $h($t('row_menu', ['name' => $u['name']])) ?>" data-menu="user" data-id="<?= (int)$u['id'] ?>" data-name="<?= $h($u['name']) ?>" data-acts="<?= $h(implode(',', $acts)) ?>"><?= $ico['dots'] ?></button><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="tbl-empty" id="pe-empty" hidden><?= $t('pe_none') ?></p>
    </div>
    <div class="tbl-foot"><span><?= $t('pe_count', ['n' => $cnt['all']]) ?></span><button type="button" class="btn btn-ghost btn-sm" id="pe-more" hidden><?= $t('pe_more') ?></button></div>
  </section>

  <!-- ============ ACADEMY ============ -->
  <section id="tab-academy" class="tab-content pm-panel" data-panel hidden>
    <div class="pm-head"><div><h1><?= $t('ac_h') ?></h1><p><?= $t('ac_p') ?></p></div></div>

    <section class="pm-sec">
      <header><h2><?= $t('mod_h') ?></h2></header>
      <div class="compose" style="grid-template-columns:minmax(0,1.2fr) minmax(0,1fr)">
        <div>
          <?php if (empty($modules)): ?><p class="muted"><?= $t('mod_none') ?></p><?php endif; ?>
          <?php foreach ($modules as $m): $n = $moduleCourseCount[(int)$m['id']] ?? 0; ?>
            <div class="mod" data-module="<?= (int)$m['id'] ?>">
              <div><b><?= $h($m['title']) ?></b><p><?= $h($m['description'] ?? '') ?></p><p class="num"><?= $n === 1 ? $t('mod_courses1') : $t('mod_courses', ['n' => $n]) ?></p></div>
              <div class="st-row" style="flex-shrink:0">
                <button type="button" class="btn btn-quiet btn-sm" data-mod-edit data-id="<?= (int)$m['id'] ?>" data-title="<?= $h($m['title']) ?>" data-desc="<?= $h($m['description'] ?? '') ?>"><?= $t('mod_edit') ?></button>
                <button type="button" class="btn btn-quiet btn-sm" style="color:var(--danger)" data-mod-del data-id="<?= (int)$m['id'] ?>" data-name="<?= $h($m['title']) ?>" <?= $n > 0 ? 'disabled title="' . $h($t('mod_blocked')) . '"' : '' ?>><?= $t('mod_delete') ?></button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <form class="block" action="/promoter/dashboard.php" method="POST">
          <h3 style="margin-bottom:1rem"><?= $t('mod_new') ?></h3>
          <input type="hidden" name="action" value="create_module">
          <div class="field"><label for="mod-title"><?= $t('mod_title') ?></label><input class="input" id="mod-title" type="text" name="module_title" required placeholder="<?= $h($t('mod_title_ph')) ?>"></div>
          <div class="field"><label for="mod-desc"><?= $t('mod_desc') ?></label><textarea class="input" id="mod-desc" name="module_desc" rows="3" placeholder="<?= $h($t('mod_desc_ph')) ?>"></textarea></div>
          <button type="submit" class="btn btn-primary"><?= $t('mod_create') ?></button>
        </form>
      </div>
    </section>

    <section class="pm-sec">
      <header><h2><?= $t('cs_h') ?></h2><p><?= $t('cs_p') ?></p></header>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th><?= $t('th_course') ?></th><th class="c-hide-sm"><?= $t('th_module') ?></th><th class="c-hide-sm"><?= $t('th_creator') ?></th><th><?= $t('th_teacher') ?></th><th class="c-hide-sm"><?= $t('th_key') ?></th></tr></thead>
          <tbody>
          <?php if (empty($courses)): ?><tr><td colspan="5" class="tbl-empty"><?= $t('cs_none') ?></td></tr><?php endif; ?>
          <?php foreach ($courses as $c): ?>
            <tr>
              <td><span class="name"><?= $h($c['title']) ?></span><span class="sub role-inline" style="display:none"><?= $h($c['module_title']) ?></span></td>
              <td class="c-hide-sm"><?= $h($c['module_title']) ?></td>
              <td class="c-hide-sm"><?= !empty($c['creator_name']) ? $h($c['creator_name']) : '<span class="muted">—</span>' ?></td>
              <td>
                <div class="st-row" style="flex-wrap:nowrap;align-items:center">
                  <select class="input cell-sel" id="teacher-select-<?= (int)$c['id'] ?>" data-current="<?= (int)($c['teacher_id'] ?? 0) ?>" aria-label="<?= $h($t('cs_teacher_for', ['c' => $c['title']])) ?>">
                    <option value="" <?= empty($c['teacher_id']) ? 'selected' : '' ?>><?= $t('cs_unassigned') ?></option>
                    <?php foreach ($teachers as $tc): ?>
                      <option value="<?= (int)$tc['id'] ?>" <?= (int)($c['teacher_id'] ?? 0) === (int)$tc['id'] ? 'selected' : '' ?>><?= $h($tc['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="button" class="btn btn-ghost btn-sm" data-assign="<?= (int)$c['id'] ?>" disabled><?= $t('cs_assign') ?></button>
                  <?php if (!empty($c['teacher_id'])): ?><button type="button" class="btn btn-quiet btn-sm" data-unassign="<?= (int)$c['id'] ?>"><?= $t('cs_revoke') ?></button><?php endif; ?>
                  <span id="status-<?= (int)$c['id'] ?>" class="note" hidden></span>
                </div>
              </td>
              <td class="c-hide-sm num"><?= $c['enrollment_key'] ? $h($c['enrollment_key']) : '<span class="muted">' . $t('cs_open') . '</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </section>

  <!-- ============ LIVE EVALUATIONS ============ -->
  <section id="tab-live" class="tab-content pm-panel" data-panel hidden>
    <div class="pm-head"><div><h1><?= $t('lv_h') ?></h1><p><?= $t('lv_p') ?></p></div></div>

    <dl class="totals" style="grid-template-columns:repeat(3,minmax(0,1fr))">
      <div><dd class="num"><?= $totalEvaluatedCount ?></dd><dt><?= $t('lv_evaluated') ?></dt></div>
      <div><?php if ($totalEvaluatedCount): ?><dd class="num"><?= round($overallAvgScore, 1) ?><small>%</small></dd><?php else: ?><dd class="na">—</dd><?php endif; ?><dt><?= $t('lv_avg') ?></dt></div>
      <div><?php if ($totalEvaluatedCount): ?><dd class="num"><?= round($overallSuccessRate, 1) ?><small>%</small></dd><?php else: ?><dd class="na">—</dd><?php endif; ?><dt><?= $t('lv_rate') ?></dt></div>
    </dl>

    <?php if ($totalEvaluatedCount): ?>
    <section class="pm-sec">
      <header><h2><?= $t('lv_dist') ?></h2><p><?= $t('lv_dist_p') ?></p></header>
      <div class="bars" role="img" aria-label="<?= $h($t('lv_dist')) ?>">
        <?php $bl = ['0–20', '21–40', '41–60', '61–80', '81–100']; foreach ($buckets as $i => $v): ?>
          <div class="bar"><b><?= $v ?></b><i style="height:<?= max(2, round($v / $maxBucket * 100)) ?>%"></i><span><?= $bl[$i] ?> %</span></div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="pm-sec">
      <header><h2><?= $t('lv_sessions') ?></h2></header>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th><?= $t('th_session') ?></th><th class="c-hide-sm"><?= $t('th_lteacher') ?></th><th><?= $t('th_status') ?></th><th class="c-hide-sm"><?= $t('th_regs') ?></th><th class="c-hide-sm"><?= $t('th_avg') ?></th><th class="c-hide-sm"><?= $t('th_when') ?></th><th class="c-act"><span class="sr"><?= $t('th_actions') ?></span></th></tr></thead>
          <tbody>
          <?php if (empty($liveSessions)): ?><tr><td colspan="7" class="tbl-empty"><?= $t('lv_none') ?></td></tr><?php endif; ?>
          <?php foreach ($liveSessions as $s):
              $stKey = in_array($s['status'], ['lobby', 'active', 'finished'], true) ? $s['status'] : 'draft';
              $stCls = $stKey === 'active' ? 'ok' : ($stKey === 'lobby' ? 'wait' : ($stKey === 'finished' ? '' : 'hollow'));
          ?>
            <tr>
              <td><span class="name"><?= $h($s['title']) ?></span><span class="sub"><?= $h($s['course_title']) ?> · <?= $t('lv_code') ?> <?= $h($s['session_code']) ?></span></td>
              <td class="c-hide-sm"><?= $h($s['teacher_name']) ?></td>
              <td><span class="st <?= $stCls ?>"><?= $t('lv_status_' . $stKey) ?></span></td>
              <td class="c-hide-sm num"><?= (int)$s['registered_count'] ?> / <?= (int)$s['evaluated_count'] ?></td>
              <td class="c-hide-sm num"><?= (int)$s['evaluated_count'] > 0 ? round((float)$s['avg_score'], 1) . ' %' : '—' ?></td>
              <td class="c-hide-sm num" style="font-size:.88rem"><?= $t('lv_start') ?> <?= $h($fmtDT($s['start_time'])) ?><br><?= $t('lv_end') ?> <?= $h($fmtDT($s['end_time'])) ?></td>
              <td class="c-act"><button type="button" class="kebab" aria-haspopup="menu" aria-expanded="false" aria-label="<?= $h($t('row_menu', ['name' => $s['title']])) ?>" data-menu="session" data-id="<?= (int)$s['id'] ?>" data-name="<?= $h($s['title']) ?>" data-start="<?= $h(date('Y-m-d\TH:i', strtotime($s['start_time']))) ?>" data-end="<?= $h(date('Y-m-d\TH:i', strtotime($s['end_time']))) ?>" data-acts="postpone,cancel_session"><?= $ico['dots'] ?></button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </section>

  <!-- ============ CERTIFICATES ============ -->
  <section id="tab-certificates" class="tab-content pm-panel" data-panel hidden>
    <div class="pm-head"><div><h1><?= $t('ce_h') ?></h1><p><?= $t('ce_p') ?></p></div></div>

    <section class="pm-sec">
      <header><h2><?= $t('ce_wait_h') ?></h2></header>
      <?php if (!$awaitingCerts): ?>
        <p class="muted"><?= $t('ce_wait_none') ?></p>
      <?php else: ?>
        <div class="q-rows" style="border-top:1px solid var(--line-2)">
          <?php foreach ($awaitingCerts as $ac): ?>
            <div class="q-row">
              <div class="q-who"><b><?= $h($ac['name']) ?> · <?= $h($ac['title']) ?></b><span><?= $h($ac['email']) ?> · <?= $h($t('passed_on', ['s' => rtrim(rtrim(number_format((float)$ac['score'], 1, '.', ''), '0'), '.'), 'd' => $fmtD($ac['at'])])) ?></span></div>
              <div class="q-act"><button type="button" class="btn btn-primary btn-sm" data-issue data-student="<?= (int)$ac['student_id'] ?>" data-course="<?= (int)$ac['course_id'] ?>" data-name="<?= $h($ac['name']) ?>" data-ctitle="<?= $h($ac['title']) ?>"><?= $t('issue') ?></button></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="pm-sec">
      <header><h2><?= $t('ce_manual_h') ?></h2><p><?= $t('ce_manual_p') ?></p></header>
      <form id="manual-cert-form" class="block">
        <div class="row3">
          <div class="field"><label for="mc-student"><?= $t('ce_student') ?></label>
            <select class="input" id="mc-student" name="student_id" required>
              <option value=""><?= $t('ce_choose') ?></option>
              <?php foreach ($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= $h($s['name']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="field"><label for="mc-course"><?= $t('ce_course') ?></label>
            <select class="input" id="mc-course" name="course_id" required>
              <option value=""><?= $t('ce_choose') ?></option>
              <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= $h($c['title']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="field"><button type="submit" class="btn btn-primary" style="width:100%"><?= $t('ce_issue') ?></button></div>
        </div>
      </form>
    </section>

    <section class="pm-sec">
      <header><h2><?= $t('ce_reg_h') ?> <span class="muted num" style="font-family:var(--font-body);font-size:1rem">(<?= count($certificates) ?>)</span></h2>
        <a class="link" href="/promoter/export-excel.php?type=certifications"><?= $t('ce_export') ?></a></header>
      <div class="toolbar"><div class="search"><?= $ico['search'] ?><input class="input" type="search" id="ce-q" placeholder="<?= $h($t('ce_search')) ?>" aria-label="<?= $h($t('ce_search')) ?>" autocomplete="off"></div></div>
      <div class="tbl-wrap">
        <table class="tbl" id="ce-table">
          <thead><tr><th><?= $t('th_holder') ?></th><th><?= $t('th_course') ?></th><th class="c-hide-sm"><?= $t('th_origin') ?></th><th><?= $t('th_code') ?></th><th class="c-hide-sm"><?= $t('th_issued') ?></th><th class="c-act"><span class="sr"><?= $t('th_actions') ?></span></th></tr></thead>
          <tbody id="ce-body">
          <?php if (empty($certificates)): ?><tr><td colspan="6" class="tbl-empty"><?= $t('ce_none') ?></td></tr><?php endif; ?>
          <?php foreach ($certificates as $cert): ?>
            <tr data-q="<?= $h(mb_strtolower($cert['student_name'] . ' ' . $cert['student_email'] . ' ' . ($cert['course_title'] ?? '') . ' ' . $cert['certificate_code'])) ?>">
              <td><span class="name"><?= $h($cert['student_name']) ?></span><span class="sub"><?= $h($cert['student_email']) ?></span></td>
              <td><?= $h($cert['course_title'] ?? $t('ce_unknown')) ?><?php if (!empty($cert['teacher_name'])): ?><span class="sub"><?= $h($cert['teacher_name']) ?></span><?php endif; ?></td>
              <td class="c-hide-sm"><?php if ($cert['manual_issue']): ?><span class="st wait"><?= $h($t('ce_manual_by', ['n' => $cert['issuer_name'] ?? '?'])) ?></span><?php else: ?><span class="st ok"><?= $t('ce_auto') ?></span><?php endif; ?></td>
              <td class="c-nowrap"><a class="code" href="/certificate.php?code=<?= urlencode($cert['certificate_code']) ?>" target="_blank" rel="noopener"><?= $h($cert['certificate_code']) ?></a></td>
              <td class="c-hide-sm c-nowrap num"><?= $h($fmtDT($cert['issued_at'])) ?></td>
              <td class="c-act"><button type="button" class="kebab" aria-haspopup="menu" aria-expanded="false" aria-label="<?= $h($t('row_menu', ['name' => $cert['certificate_code']])) ?>" data-menu="cert" data-id="<?= (int)$cert['id'] ?>" data-code="<?= $h($cert['certificate_code']) ?>" data-name="<?= $h($cert['student_name']) ?>" data-acts="open,copy,revoke_cert"><?= $ico['dots'] ?></button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <p class="tbl-empty" id="ce-empty" hidden><?= $t('pe_none') ?></p>
      </div>
    </section>
  </section>

  <!-- ============ MESSAGES ============ -->
  <section id="tab-communications" class="tab-content pm-panel" data-panel hidden>
    <div class="pm-head"><div><h1><?= $t('ms_h') ?></h1><p><?= $t('ms_p') ?></p></div></div>

    <section class="pm-sec">
      <header>
        <h2><?= $t('nl_h') ?></h2>
        <span class="dotline <?= $smtpConfigured ? '' : 'off' ?>"><?= $smtpConfigured ? $t('nl_smtp_on') : $t('nl_smtp_off') ?></span>
      </header>
      <div class="compose">
        <form id="newsletter-form" novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrfToken(); ?>">
          <div class="field"><label for="nl-aud"><?= $t('nl_audience') ?></label>
            <select class="input" id="nl-aud" name="audience">
              <option value="subscribers"><?= $t('nl_a_sub') ?> (<?= $audCounts['subscribers'] ?>)</option>
              <option value="students"><?= $t('nl_a_stu') ?> (<?= $audCounts['students'] ?>)</option>
              <option value="all"><?= $t('nl_a_all') ?> (<?= $audCounts['all'] ?>)</option>
            </select></div>
          <p class="audience-line" id="nl-line" aria-live="polite"></p>
          <div class="field"><label for="nl-subject"><?= $t('nl_subject') ?></label><input class="input" id="nl-subject" type="text" name="subject" required maxlength="200" placeholder="<?= $h($t('nl_subject_ph')) ?>"></div>
          <div class="field"><label for="nl-body"><?= $t('nl_body') ?></label><textarea class="input" id="nl-body" name="body_html" rows="9" required placeholder="<?= $h($t('nl_body_ph')) ?>"></textarea></div>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="nl-send" <?= $smtpConfigured ? '' : 'disabled' ?>><?= $t('nl_send') ?></button>
            <button type="button" class="btn btn-ghost" id="test-smtp-btn" <?= $smtpConfigured ? '' : 'disabled' ?>><?= $t('nl_test') ?></button>
          </div>
        </form>
        <div>
          <p class="mail-cap"><?= $t('nl_preview') ?></p>
          <div class="mail" aria-live="polite">
            <div class="mail-head"><span id="pv-to"></span><b id="pv-subject"></b></div>
            <div class="mail-body empty" id="pv-body"><?= $t('nl_prev_empty') ?></div>
            <div class="mail-foot"><?= $t('nl_prev_foot') ?></div>
          </div>
        </div>
      </div>

      <h3 style="margin:2.5rem 0 .8rem"><?= $t('nl_hist_h') ?></h3>
      <?php if (empty($newsletterCampaigns)): ?><p class="muted"><?= $t('nl_hist_none') ?></p><?php else: ?>
        <ul class="plain-list">
          <?php foreach ($newsletterCampaigns as $nc): ?>
            <li><div><b><?= $h($nc['subject']) ?></b><small><?= $h($t('nl_hist_item', ['n' => (int)$nc['recipient_count'], 'u' => $nc['sender_name'], 'd' => $fmtD($nc['sent_at'])])) ?></small></div></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <details class="more"><summary><?= $t('nl_subs_h') ?> (<?= $kpiNewsletterSubs ?>)</summary>
        <?php if (empty($newsletterSubscribers)): ?><p class="muted"><?= $t('nl_subs_none') ?></p><?php else: ?>
          <ul class="plain-list">
            <?php foreach (array_slice($newsletterSubscribers, 0, 10) as $sub): ?>
              <li><span><?= $h($sub['email']) ?></span><small class="num"><?= $h($fmtD($sub['subscribed_at'])) ?></small></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </details>
    </section>

    <section class="pm-sec">
      <header><h2><?= $t('kb_h') ?></h2></header>
      <p class="pm-lead"><?= $t('kb_p') ?></p>
      <div class="compose" style="margin-top:1.2rem">
        <div>
          <p class="audience-line"><?= $h($t('kb_line', ['n' => count($students)])) ?></p>
          <div class="form-actions"><button type="button" class="btn btn-primary" id="send-keys-btn" data-count="<?= count($students) ?>" <?= $smtpConfigured && count($students) ? '' : 'disabled' ?>><?= $t('kb_send') ?></button><span id="keys-broadcast-status" class="note" hidden></span></div>
        </div>
        <div>
          <p class="mail-cap"><?= $t('kb_list') ?></p>
          <?php if (!$keyedCourses): ?><p class="muted"><?= $t('kb_none') ?></p><?php else: ?>
            <ul class="plain-list">
              <?php foreach ($keyedCourses as $c): ?><li><span><?= $h($c['title']) ?></span><b class="num"><?= $h($c['enrollment_key']) ?></b></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </section>

  <!-- ============ API KEYS ============ -->
  <section id="tab-api" class="tab-content pm-panel" data-panel hidden>
    <div class="pm-head"><div><h1><?= $t('ak_h') ?></h1><p><?= $t('ak_p') ?></p></div></div>

    <div class="reveal" id="api-key-result" hidden role="alert">
      <h3><?= $t('ak_once_h') ?></h3>
      <p class="warn-line"><?= $t('ak_once_p') ?></p>
      <div class="copyrow">
        <input class="input" type="text" id="api-key-value" readonly aria-label="<?= $h($t('ak_once_h')) ?>">
        <button type="button" class="btn btn-primary" id="api-key-copy"><?= $t('ak_copy') ?></button>
      </div>
      <p class="note"><?= $t('ak_use') ?></p>
      <div class="code-sample" style="margin:.6rem 0 1rem">GET /api/v1/courses
GET /api/v1/modules
X-API-Key: &lt;key&gt;</div>
      <button type="button" class="btn btn-ghost" id="api-key-done"><?= $t('ak_done') ?></button>
    </div>

    <form class="block" id="api-key-form" style="max-width:560px">
      <div class="field"><label for="ak-label"><?= $t('ak_label') ?></label><input class="input" id="ak-label" type="text" maxlength="100" placeholder="<?= $h($t('ak_label_ph')) ?>"><span class="hint"><?= $t('ak_label_hint') ?></span></div>
      <button type="submit" class="btn btn-primary" id="api-key-create"><?= $t('ak_create') ?></button>
    </form>

    <section class="pm-sec">
      <header><h2><?= $t('ak_list_h') ?></h2><p><?= $t('ak_nolast') ?></p></header>
      <div class="tbl-wrap flat">
        <table class="tbl">
          <thead><tr><th><?= $t('th_name') ?></th><th><?= $t('th_status') ?></th><th class="c-hide-sm"><?= $t('th_created') ?></th><th class="c-hide-sm"><?= $t('th_by') ?></th><th class="c-act"><span class="sr"><?= $t('th_actions') ?></span></th></tr></thead>
          <tbody id="api-keys-body"><tr><td colspan="5" class="tbl-empty"><?= $t('ak_loading') ?></td></tr></tbody>
        </table>
      </div>
    </section>
  </section>

  <!-- ============ AUDIT & EXPORTS ============ -->
  <section id="tab-audit" class="tab-content pm-panel" data-panel hidden>
    <div class="pm-head"><div><h1><?= $t('au_h') ?></h1><p><?= $t('au_p') ?></p></div></div>

    <section class="pm-sec">
      <header><h2><?= $t('au_log') ?></h2><a class="link" href="/promoter/export-excel.php?type=audit_logs"><?= $t('au_export') ?></a></header>
      <div class="toolbar"><div class="search"><?= $ico['search'] ?><input class="input" type="search" id="au-q" placeholder="<?= $h($t('au_search')) ?>" aria-label="<?= $h($t('au_search')) ?>" autocomplete="off"></div></div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th><?= $t('th_date') ?></th><th><?= $t('th_author') ?></th><th><?= $t('th_action') ?></th><th class="c-hide-sm"><?= $t('th_details') ?></th></tr></thead>
          <tbody id="au-body">
          <?php if (empty($auditLogs)): ?><tr><td colspan="4" class="tbl-empty"><?= $t('au_none') ?></td></tr><?php endif; ?>
          <?php foreach ($auditLogs as $log): ?>
            <tr data-q="<?= $h(mb_strtolower(($log['user_name'] ?? '') . ' ' . $log['action'] . ' ' . ($log['details'] ?? ''))) ?>">
              <td class="c-nowrap num"><?= $h($fmtDT($log['created_at'])) ?></td>
              <td><?= $h($log['user_name'] ?? '—') ?></td>
              <td><b style="font-weight:600"><?= $h($log['action']) ?></b><span class="sub role-inline" style="display:none"><?= $h($log['details'] ?? '') ?></span></td>
              <td class="c-hide-sm" style="color:var(--ink-2)"><?= $h($log['details'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <p class="tbl-empty" id="au-empty" hidden><?= $t('pe_none') ?></p>
      </div>
    </section>

    <section class="pm-sec">
      <header><h2><?= $t('ex_h') ?></h2></header>
      <p class="pm-lead"><?= $t('ex_p') ?></p>
      <p style="margin-top:1rem"><a class="btn btn-ghost" href="/promoter/export-excel.php?type=all"><?= $t('ex_btn') ?></a></p>
    </section>

    <section class="pm-sec">
      <header><h2><?= $t('ai_h') ?></h2></header>
      <p class="pm-lead"><?= $t('ai_p') ?></p>
      <p style="margin-top:1rem"><button type="button" class="btn btn-ghost" id="audit-btn"><?= $t('ai_btn') ?></button></p>
      <p class="note" id="audit-loading" hidden role="status" style="margin-top:1rem"><?= $t('ai_wait') ?></p>
      <div id="audit-result-container" hidden class="block" style="margin-top:1rem">
        <div id="audit-text" style="white-space:pre-line;line-height:1.65"></div>
        <p style="margin-top:1rem"><button type="button" class="link" onclick="window.print()"><?= $t('ai_print') ?></button></p>
      </div>
    </section>
  </section>

  </main>
</div>

<!-- Hidden POST forms (preserved server handlers) -->
<form id="remove-cert-form" action="/promoter/dashboard.php" method="POST" hidden><input type="hidden" name="action" value="remove_certificate"><input type="hidden" name="certificate_id" value=""></form>
<form id="cancel-session-form" action="/promoter/dashboard.php" method="POST" hidden><input type="hidden" name="action" value="cancel_session"><input type="hidden" name="session_id" value=""></form>

<!-- Dialogs -->
<dialog class="dlg" id="user-modal" aria-labelledby="cu-h">
  <form id="create-user-form" method="dialog">
    <h2 id="cu-h"><?= $t('cu_h') ?></h2>
    <p class="dlg-p"><?= $t('cu_p') ?></p>
    <p class="err" id="modal-error" hidden></p>
    <div class="field"><label for="cu-name"><?= $t('cu_name') ?></label><input class="input" id="cu-name" type="text" name="name" required placeholder="<?= $h($t('cu_name_ph')) ?>"></div>
    <div class="field"><label for="cu-email"><?= $t('cu_email') ?></label><input class="input" id="cu-email" type="email" name="email" required placeholder="<?= $h($t('cu_email_ph')) ?>"></div>
    <div class="row2">
      <div class="field"><label for="cu-pass"><?= $t('cu_pass') ?></label><input class="input" id="cu-pass" type="password" name="password" required minlength="8" autocomplete="new-password"><span class="hint"><?= $t('cu_pass_hint') ?></span></div>
      <div class="field"><label for="cu-role"><?= $t('cu_role') ?></label>
        <select class="input" id="cu-role" name="role" required>
          <option value="teacher"><?= $t('role_teacher') ?></option>
          <option value="student"><?= $t('role_student') ?></option>
          <option value="promoter"><?= $t('cu_role_promoter') ?></option>
        </select></div>
    </div>
    <div class="dlg-actions"><button type="button" class="btn btn-ghost" data-close><?= $t('cancel') ?></button><button type="submit" class="btn btn-primary" id="create-user-btn"><?= $t('cu_submit') ?></button></div>
  </form>
</dialog>

<dialog class="dlg" id="direct-message-modal" aria-labelledby="dm-h">
  <form id="direct-message-form" method="dialog">
    <h2 id="dm-h"><?= $t('dm_h') ?></h2>
    <p class="dlg-p"><?= $t('dm_p') ?></p>
    <input type="hidden" name="student_id" id="dm-student-id">
    <div class="field"><label for="dm-student-name"><?= $t('dm_to') ?></label><input class="input" type="text" id="dm-student-name" readonly></div>
    <div class="field"><label for="dm-subject"><?= $t('dm_subject') ?></label><input class="input" id="dm-subject" type="text" name="subject" required placeholder="<?= $h($t('dm_subject_ph')) ?>"></div>
    <div class="field"><label for="dm-msg"><?= $t('dm_msg') ?></label><textarea class="input" id="dm-msg" name="message" rows="6" required></textarea></div>
    <div class="dlg-actions"><button type="button" class="btn btn-ghost" data-close><?= $t('cancel') ?></button><button type="submit" class="btn btn-primary" id="send-dm-btn"><?= $t('dm_send') ?></button></div>
  </form>
</dialog>

<dialog class="dlg" id="module-modal" aria-labelledby="md-h">
  <form id="module-form" method="dialog">
    <h2 id="md-h"><?= $t('mod_edit_h') ?></h2>
    <input type="hidden" name="module_id" id="md-id">
    <div class="field"><label for="md-title"><?= $t('mod_title') ?></label><input class="input" id="md-title" type="text" name="module_title" required></div>
    <div class="field"><label for="md-desc"><?= $t('mod_desc') ?></label><textarea class="input" id="md-desc" name="module_desc" rows="4"></textarea></div>
    <div class="dlg-actions"><button type="button" class="btn btn-ghost" data-close><?= $t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= $t('lv_pp_save') ?></button></div>
  </form>
</dialog>

<dialog class="dlg" id="postpone-modal" aria-labelledby="pp-h">
  <form action="/promoter/dashboard.php" method="POST">
    <h2 id="pp-h"><?= $t('lv_pp_h') ?></h2>
    <p class="dlg-p" id="pp-name"></p>
    <input type="hidden" name="action" value="postpone_session">
    <input type="hidden" name="session_id" id="pp-id">
    <div class="row2">
      <div class="field"><label for="pp-start"><?= $t('lv_pp_start') ?></label><input class="input" id="pp-start" type="datetime-local" name="start_time" required></div>
      <div class="field"><label for="pp-end"><?= $t('lv_pp_end') ?></label><input class="input" id="pp-end" type="datetime-local" name="end_time" required></div>
    </div>
    <div class="dlg-actions"><button type="button" class="btn btn-ghost" data-close><?= $t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= $t('lv_pp_save') ?></button></div>
  </form>
</dialog>

<dialog class="dlg" id="confirm-dlg" aria-labelledby="cf-h" aria-describedby="cf-p">
  <div class="dlg-in">
    <h2 id="cf-h"></h2>
    <p class="dlg-p" id="cf-p"></p>
    <div id="cf-phrase" hidden>
      <p class="note" id="cf-phrase-hint"></p>
      <input class="input phrase" type="text" id="cf-phrase-in" autocomplete="off" autocapitalize="off" spellcheck="false" aria-labelledby="cf-phrase-hint">
    </div>
    <div class="dlg-actions"><button type="button" class="btn btn-ghost" id="cf-no"><?= $t('cancel') ?></button><button type="button" class="btn btn-primary" id="cf-yes"><?= $t('confirm') ?></button></div>
  </div>
</dialog>

<div class="toasts" id="toasts" aria-live="polite" role="status"></div>

<script>
window.SV_T = <?= json_encode($JT, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.SV_PM = <?= json_encode([
    'lang' => $lang,
    'me' => $myId,
    'aud' => $audCounts,
    'audLabels' => ['subscribers' => $t('nl_a_sub'), 'students' => $t('nl_a_stu'), 'all' => $t('nl_a_all')],
    'audDesc' => ['subscribers' => $JT['a_sub'], 'students' => $JT['a_stu'], 'all' => $JT['a_all']],
    'ok' => $okMsg,
    'perPage' => 50,
    'mailReady' => $smtpConfigured,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="/assets/js/promoter.js"></script>
<div id="qr-sheet" class="qs" aria-hidden="true">
  <img class="qs-logo" src="/assets/img/logo-wordmark.svg" alt="StudyVibe">
  <h2><?= $h($PI['qr_scan']) ?></h2>
  <div class="qs-qr"></div>
  <p class="qs-name"></p>
  <p class="qs-sub"><?= $h($PI['qr_poster_sub']) ?></p>
  <p class="qs-url"></p>
</div>

<script>
window.PM_I18N = <?= json_encode($PI, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.PM_BASE = <?= json_encode((defined('APP_URL') && APP_URL !== '' && !str_contains((string)APP_URL, 'localhost') ? rtrim((string)APP_URL, '/') : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')))) ?>;
window.PM_COURSES = <?= json_encode(array_map(fn($c) => ['id' => (int)$c['id'], 'title' => $c['title']], $courses), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.PM_LIVE = <?= json_encode(array_map(fn($l) => ['id' => (int)$l['id'], 'title' => $l['title'], 'code' => $l['session_code']], $liveSessions), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="/assets/vendor/qrcode-generator.js"></script>
<script src="/assets/js/promoter-insights.js"></script>
<?php require_once __DIR__ . '/../lib/PhonePrompt.php'; PhonePrompt::render($user, (string)($lang ?? TranslationService::getLang())); ?>

</body>
</html>
