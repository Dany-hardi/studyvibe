<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Student Dashboard Portal View
 * 
 * Serves as the primary client-facing portal for registered students. Handles 
 * data queries for courses, certifications, badges, study statistics, 
 * live assessments, and initializes the modal system structures.
 * 
 * @package    StudyVibe
 * @subpackage Student
 * @author     Advanced Engineering Team
 */

// =========================================================================
// SECTION 1: AUTHENTICATION, ACCESS GATES & DATA QUERY CONTROLLER
// =========================================================================

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/CourseSchedule.php';
requireRole('student');

$user = getCurrentUser();
$pdo = Database::getInstance();

try {
    // 1. Fetch available courses
    // We fetch all courses, indicating if the current student is enrolled, and what their progress is.
    $stmt = $pdo->prepare("
        SELECT c.*, m.title AS module_title, COALESCE(u.name, 'Non assigné') AS teacher_name,
               e.progress_percent,
               (e.id IS NOT NULL) AS is_enrolled
        FROM courses c
        JOIN modules m ON c.module_id = m.id
        LEFT JOIN users u ON c.teacher_id = u.id
        LEFT JOIN enrollments e ON e.course_id = c.id AND e.student_id = :student_id
        WHERE c.is_published = 1 OR e.id IS NOT NULL
        ORDER BY c.id DESC
    ");
    $stmt->execute(['student_id' => $user['id']]);
    $courses = $stmt->fetchAll();

    // 2. Fetch completed certificates
    $stmt = $pdo->prepare("
        SELECT cert.*, c.title AS course_title
        FROM certificates cert
        LEFT JOIN courses c ON cert.course_id = c.id
        WHERE cert.student_id = :student_id
        ORDER BY cert.id DESC
    ");
    $stmt->execute(['student_id' => $user['id']]);
    $myCertificates = $stmt->fetchAll();

    // 3. Stats KPI apprenant
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = :sid AND progress_percent = 100");
    $stmt->execute(['sid' => $user['id']]);
    $statCompleted = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(AVG(score), 0) FROM certification_attempts WHERE student_id = :sid");
    $stmt->execute(['sid' => $user['id']]);
    $statAvgScore = round((float)$stmt->fetchColumn(), 1);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(seconds_spent), 0) FROM study_sessions WHERE student_id = :sid");
    $stmt->execute(['sid' => $user['id']]);
    $statStudySecs = (int)$stmt->fetchColumn();
    $statStudyH = (int)floor($statStudySecs / 3600);
    $statStudyM = (int)floor(($statStudySecs % 3600) / 60);

    $continueStmt = $pdo->prepare("
        SELECT e.course_id, e.last_lesson_id, c.title AS course_title, l.title AS lesson_title
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        LEFT JOIN lessons l ON l.id = e.last_lesson_id
        WHERE e.student_id = :sid AND e.last_lesson_id IS NOT NULL AND e.progress_percent < 100
        ORDER BY e.enrolled_at DESC LIMIT 1
    ");
    $continueStmt->execute(['sid' => $user['id']]);
    $continueCourse = $continueStmt->fetch() ?: null;

    $deadlineAlerts = CourseSchedule::deadlineAlerts($pdo, (int)$user['id']);

    // --- Dynamic Self-Healing Badges Logic ---
    $sid = (int)$user['id'];
    $earnedTypes = [];

    // Check first_lesson
    $checkFirst = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id = :sid AND completed = 1");
    $checkFirst->execute(['sid' => $sid]);
    if ((int)$checkFirst->fetchColumn() > 0) {
        $earnedTypes[] = 'first_lesson';
    }

    // Check study_hour
    $checkStudy = $pdo->prepare("SELECT COALESCE(SUM(duration), 0) FROM study_sessions WHERE student_id = :sid");
    $checkStudy->execute(['sid' => $sid]);
    if ((int)$checkStudy->fetchColumn() >= 3600) {
        $earnedTypes[] = 'study_hour';
    }

    // Check course_complete
    $checkComplete = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = :sid AND progress_percent = 100");
    $checkComplete->execute(['sid' => $sid]);
    if ((int)$checkComplete->fetchColumn() > 0) {
        $earnedTypes[] = 'course_complete';
    }

    // Check certified
    $checkCert = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE student_id = :sid");
    $checkCert->execute(['sid' => $sid]);
    if ((int)$checkCert->fetchColumn() > 0) {
        $earnedTypes[] = 'certified';
    }

    // Check perfect_score
    $checkPerfectQuiz = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id = :sid AND score = 100");
    $checkPerfectQuiz->execute(['sid' => $sid]);
    $checkPerfectExam = $pdo->prepare("SELECT COUNT(*) FROM certification_attempts WHERE student_id = :sid AND score = 100");
    $checkPerfectExam->execute(['sid' => $sid]);
    if ((int)$checkPerfectQuiz->fetchColumn() > 0 || (int)$checkPerfectExam->fetchColumn() > 0) {
        $earnedTypes[] = 'perfect_score';
    }

    // Check multitasker
    $checkMulti = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = :sid");
    $checkMulti->execute(['sid' => $sid]);
    if ((int)$checkMulti->fetchColumn() >= 3) {
        $earnedTypes[] = 'multitasker';
    }

    // Check night_owl
    $checkNightQuiz = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id = :sid AND (HOUR(completed_at) >= 22 OR HOUR(completed_at) < 4)");
    $checkNightQuiz->execute(['sid' => $sid]);
    $checkNightExam = $pdo->prepare("SELECT COUNT(*) FROM certification_attempts WHERE student_id = :sid AND (HOUR(attempted_at) >= 22 OR HOUR(attempted_at) < 4)");
    $checkNightExam->execute(['sid' => $sid]);
    if ((int)$checkNightQuiz->fetchColumn() > 0 || (int)$checkNightExam->fetchColumn() > 0) {
        $earnedTypes[] = 'night_owl';
    }

    // Check note_taker
    $checkNote = $pdo->prepare("SELECT COUNT(*) FROM video_notes WHERE student_id = :sid");
    $checkNote->execute(['sid' => $sid]);
    if ((int)$checkNote->fetchColumn() > 0) {
        $earnedTypes[] = 'note_taker';
    }

    // Insert earned badges into student_badges table if they don't already exist
    if (!empty($earnedTypes)) {
        $insertBadge = $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_type) VALUES (:sid, :type)");
        foreach ($earnedTypes as $type) {
            $insertBadge->execute(['sid' => $sid, 'type' => $type]);
        }
    }

    $stmt = $pdo->prepare("SELECT badge_type, earned_at FROM student_badges WHERE student_id = :sid ORDER BY earned_at DESC");
    $stmt->execute(['sid' => $user['id']]);
    $myBadges = $stmt->fetchAll();

    $earnedBadgesLookup = [];
    foreach ($myBadges as $b) {
        $earnedBadgesLookup[$b['badge_type']] = $b['earned_at'];
    }

    $allBadgesConfig = [
        'first_lesson' => [
            'title' => 'Pionnier',
            'desc' => 'Compléter votre toute première leçon sur la plateforme.',
            'icon' => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>',
            'color' => 'from-blue-500 to-indigo-600',
            'border' => 'border-blue-200'
        ],
        'study_hour' => [
            'title' => 'Apprenant Assidu',
            'desc' => 'Cumuler plus d\'une heure de temps d\'étude sur StudyVibe.',
            'icon' => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            'color' => 'from-amber-500 to-orange-600',
            'border' => 'border-amber-200'
        ],
        'course_complete' => [
            'title' => 'Finisseur d\'Élite',
            'desc' => 'Compléter à 100% au moins un cours de votre programme.',
            'icon' => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0110 21a3.745 3.745 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.746 3.746 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" /></svg>',
            'color' => 'from-emerald-500 to-teal-600',
            'border' => 'border-emerald-200'
        ],
        'certified' => [
            'title' => 'Diplômé Officiel',
            'desc' => 'Obtenir votre premier certificat de réussite académique.',
            'icon' => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84a50.58 50.58 0 00-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" /></svg>',
            'color' => 'from-purple-500 to-fuchsia-600',
            'border' => 'border-purple-200'
        ],
        'perfect_score' => [
            'title' => 'Major de Promo',
            'desc' => 'Obtenir un score parfait de 100% à un quiz de leçon ou examen final.',
            'icon' => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499c.195-.558.976-.558 1.17 0l2.36 6.816a1 1 0 00.95.69h7.162c.582 0 .822.748.35 1.14l-5.797 4.837a1 1 0 00-.364 1.118l2.36 6.816c.196.558-.432 1.016-.906.69l-5.797-4.837a1 1 0 00-1.17 0l-5.797 4.837c-.474.326-1.102-.132-.906-.69l2.36-6.816a1 1 0 00-.364-1.118L2.05 12.139c-.472-.392-.232-1.14.35-1.14h7.162a1 1 0 00.95-.69l2.36-6.82z" /></svg>',
            'color' => 'from-yellow-500 to-rose-600',
            'border' => 'border-yellow-200'
        ],
        'multitasker' => [
            'title' => 'Esprit Polyvalent',
            'desc' => 'S\'inscrire activement à au moins 3 cours différents.',
            'icon' => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>',
            'color' => 'from-cyan-500 to-sky-600',
            'border' => 'border-cyan-200'
        ],
        'night_owl' => [
            'title' => 'Hibou Académique',
            'desc' => 'Valider une leçon ou un examen entre 22h et 4h du matin.',
            'icon' => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>',
            'color' => 'from-zinc-700 to-slate-900',
            'border' => 'border-zinc-500'
        ],
        'note_taker' => [
            'title' => 'Greffier Assidu',
            'desc' => 'Prendre votre première note d\'étude sur une vidéo de cours.',
            'icon' => '<svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.83 18.3 3 19.305l1.012-3.83 12.85-12.853zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>',
            'color' => 'from-pink-500 to-rose-600',
            'border' => 'border-pink-200'
        ]
    ];

    // 4. Récupérer les téléévaluations de l'étudiant
    $stmt = $pdo->prepare("
        SELECT r.id AS registration_id, r.score, r.registered_at, s.id AS session_id, s.title AS session_title, s.session_code, s.status AS session_status, s.is_async, c.title AS course_title
        FROM live_eval_registrations r
        JOIN live_eval_sessions s ON r.session_id = s.id
        JOIN courses c ON s.course_id = c.id
        WHERE r.student_id = :student_id OR r.email = :email
        ORDER BY r.registered_at DESC
    ");
    $stmt->execute(['student_id' => $user['id'], 'email' => $user['email']]);
    $myEvaluations = $stmt->fetchAll();



} catch (PDOException $e) {
    dieSafe('Erreur serveur. Veuillez réessayer.', $e, 'student/dashboard');
}
?>
<!-- =========================================================================
     SECTION 2: HTML HEAD, META ASSETS & TAILWIND CONFIGURATION
     ========================================================================= -->
<!DOCTYPE html>
<html lang="fr" class="h-full sv-cream">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="icon" type="image/png" href="/assets/img/favicon.png" sizes="32x32">
    <link rel="apple-touch-icon" href="/assets/img/favicon.png">

    <title>Espace Étudiant — StudyVibe</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <?= csrfMetaTag(); ?>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        serif: ['Plus Jakarta Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        .fade-in {
            animation: fadeIn 0.4s ease-out forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        /* Glassmorphic-like dialog style */
        .modal-active {
            animation: modalFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.97); }
            to { opacity: 1; transform: scale(1); }
        }
        /* WhatsApp iOS Chat Style */
        .wa-chat-bg {
            background-color: #efeae2;
            background-image: radial-gradient(#dfdcd6 0.8px, transparent 0), radial-gradient(#dfdcd6 0.8px, #efeae2 0);
            background-size: 8px 8px;
            background-position: 0 0, 4px 4px;
        }
        /* Hide scrollbars but keep functionality */
        .scrollbar-none::-webkit-scrollbar {
            display: none;
        }
        .scrollbar-none {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        /* Bouncing Dots Animation */
        .wa-dot {
            width: 6px;
            height: 6px;
            background-color: #8e8e93;
            border-radius: 50%;
            display: inline-block;
            animation: waBouncing 1.4s infinite ease-in-out both;
        }
        .wa-dot:nth-child(1) { animation-delay: -0.32s; }
        .wa-dot:nth-child(2) { animation-delay: -0.16s; }
        @keyframes waBouncing {
            0%, 80%, 100% { transform: scale(0.3); opacity: 0.3; }
            40% { transform: scale(1.1); opacity: 1; }
        }
    </style>
</head>
<body class="font-sans antialiased text-[#111111] dark:text-white bg-[#FAF9F6] dark:bg-[#121212] min-h-screen flex flex-col md:flex-row overflow-x-hidden">

    <!-- =========================================================================
         SECTION 3: HTML LAYOUT STRUCTURE (BODY, SIDEBAR & MAIN PORTAL VIEWS)
         ========================================================================= -->

    <!-- MOBILE TOP BAR -->
    <div class="w-full md:hidden bg-[#004B23] text-white py-4 px-4 flex justify-between items-center sticky top-0 z-30 shadow-md">
        <div class="flex items-center gap-3">
            <button onclick="toggleMobileDrawer()" class="p-1 text-white hover:text-white/80 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>
            <span class="font-serif text-lg font-bold tracking-tight">StudyVibe</span>
        </div>
        <div class="flex items-center gap-3">
            <!-- Notifications (Mobile) -->
            <div class="relative" id="mobile-notif-wrap">
                <button type="button" onclick="toggleMobileNotifs()" class="relative p-1.5 text-white hover:text-white/80 transition-colors rounded-full" aria-label="Notifications">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                    <span id="mobile-notif-count" class="hidden absolute -top-1 -right-1 bg-[#D32F2F] text-white text-[9px] font-bold px-1 py-0.2 rounded-full min-w-[15px] text-center border border-white">0</span>
                </button>
                <div id="mobile-notif-panel-container" class="hidden absolute right-0 top-full mt-2 w-72 bg-white dark:bg-[#1E1E1E] border border-[#E5E5E7] dark:border-[#2C2C2C] shadow-xl z-50 text-left text-sm rounded-lg overflow-hidden flex flex-col max-h-[300px]">
                    <div class="p-3 border-b border-[#E5E5E7] dark:border-[#2C2C2C] flex justify-between items-center bg-[#F9F7F4] dark:bg-[#252525] flex-shrink-0">
                        <span class="font-serif font-semibold text-xs uppercase tracking-wider text-[#111111] dark:text-white">Notifications</span>
                        <button onclick="markAllNotificationsRead(event)" class="text-[10px] text-[#004B23] dark:text-[#34C759] hover:underline font-semibold">Tout marquer comme lu</button>
                    </div>
                    <div id="mobile-notif-panel" class="overflow-y-auto flex-grow max-h-[250px] dark:text-white/80"></div>
                </div>
            </div>
            <a href="/logout.php" class="text-xs text-red-300 uppercase tracking-wider font-semibold hover:underline">Déconnexion</a>
        </div>
    </div>

    <!-- MOBILE DRAWER -->
    <div id="mobile-drawer" class="fixed inset-0 z-50 md:hidden hidden">
        <div onclick="toggleMobileDrawer()" class="fixed inset-0 bg-black/50 transition-opacity"></div>
        <div class="relative flex-1 flex flex-col max-w-xs w-full bg-[#004B23] pt-5 pb-4 transition-transform duration-300">
            <div class="absolute top-0 right-0 -mr-12 pt-2">
                <button onclick="toggleMobileDrawer()" class="ml-1 flex items-center justify-center h-10 w-10 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white">
                    <span class="sr-only">Close sidebar</span>
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="flex-shrink-0 flex items-center px-6 gap-3 border-b border-[#003619] pb-4">
                <span class="font-serif text-xl font-bold tracking-tight text-white">StudyVibe</span>
                <span class="text-[10px] uppercase tracking-widest bg-[#003619] text-white px-2 py-0.5 border border-[#002610] font-mono">Apprenant</span>
            </div>
            <div class="mt-5 flex-1 h-0 overflow-y-auto">
                <nav class="px-3 space-y-1">
                    <button onclick="switchTab('catalogue'); toggleMobileDrawer();" id="mobile-tab-btn-catalogue" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                        Catalogue
                    </button>
                    <button onclick="switchTab('mes-cours'); toggleMobileDrawer();" id="mobile-tab-btn-mes-cours" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                        Mes Études
                    </button>
                    <button onclick="switchTab('releve'); toggleMobileDrawer();" id="mobile-tab-btn-releve" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                        Relevé de Notes
                    </button>
                    <button onclick="switchTab('certifications'); toggleMobileDrawer();" id="mobile-tab-btn-certifications" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                        Certifications
                    </button>
                    <button onclick="switchTab('achievements'); toggleMobileDrawer();" id="mobile-tab-btn-achievements" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                        Succès & Badges
                    </button>
                    <button onclick="switchTab('tele-evaluations'); toggleMobileDrawer();" id="mobile-tab-btn-tele-evaluations" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                        Téléévaluations
                    </button>

                    <button onclick="switchTab('profil'); toggleMobileDrawer();" id="mobile-tab-btn-profil" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                        Mon Profil
                    </button>
                    <a href="/evaluations.php" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                        Évaluations
                    </a>
                </nav>
            </div>
            <div class="flex-shrink-0 flex border-t border-[#003619] p-4 bg-[#003c1c] items-center gap-3">
                <img src="<?= $user['avatar_path'] ? htmlspecialchars(mediaUrl('avatar', $user['avatar_path'])) : 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user['email']))) . '?d=mp'; ?>" 
                     alt="Photo de profil" class="w-8 h-8 rounded-full object-cover border border-white/20">
                <div class="flex-grow overflow-hidden">
                    <div class="text-xs font-semibold text-white truncate id-student-name"><?= htmlspecialchars($user['name']); ?></div>
                    <div class="text-[10px] text-white/60 truncate"><?= htmlspecialchars($user['email']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- LEFT SIDEBAR (Desktop) -->
    <aside class="w-64 bg-[#004B23] text-white flex flex-col justify-between h-screen sticky top-0 border-r border-[#003619] hidden md:flex flex-shrink-0 z-40">
        <!-- Logo / Brand Header -->
        <div class="p-6 border-b border-[#003619] flex items-center gap-3">
            <svg class="w-8 h-8" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="50" cy="50" r="46" stroke="#FFFFFF" stroke-width="3.5" />
                <line x1="33" y1="31" x2="62" y2="25" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="49" y2="53" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="14" y2="13" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="42" y2="11" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
                <line x1="33" y1="31" x2="20" y2="53" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
                <line x1="49" y1="53" x2="62" y2="25" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
                <circle cx="62" cy="25" r="6" fill="#34C759" />
                <circle cx="49" cy="53" r="6" fill="#34C759" />
                <circle cx="33" cy="31" r="6" fill="#34C759" />
                <circle cx="14" cy="13" r="6" fill="#34C759" />
                <circle cx="42" cy="11" r="6" fill="#34C759" />
                <circle cx="20" cy="53" r="6" fill="#34C759" />
                <path d="M56 10 C52 14, 52 24, 52 29 C52 31, 50 33, 49 33 L45 33 L49 35 C50 37, 51 38, 50 40 C49 41, 47 42, 49 44 C51 45, 54 46, 56 46 C59 46, 65 38, 66 41 C68 46, 60 52, 56 60 C51 68, 50 78, 53 88" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M33 55 C32 52, 32 48, 33 46 C34 44, 36 44, 37 47 C37 50, 37 53, 37 55 C37 51, 38 46, 39 44 C40 42, 42 42, 43 45 C43 48, 43 51, 43 54 C43 51, 44 47, 45 45 C46 43, 48 43, 49 46 C50 49, 51 57, 51 68 C51 75, 49 81, 47 85" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M33 55 C34 61, 35 68, 37 75 C38 81, 39 84, 40 86" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span class="font-serif text-lg font-semibold tracking-tight text-white">StudyVibe</span>
            <span class="text-[9px] uppercase tracking-widest bg-[#003619] text-white px-2 py-0.5 border border-[#002610] ml-2 font-mono">Apprenant</span>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-grow py-6 px-4 space-y-1.5 overflow-y-auto">
            <button onclick="switchTab('catalogue')" id="tab-btn-catalogue" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-semibold transition-all text-white bg-white/10 border-l-4 border-white text-left">
                Catalogue
            </button>
            <button onclick="switchTab('mes-cours')" id="tab-btn-mes-cours" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                Mes Études
            </button>
            <button onclick="switchTab('releve')" id="tab-btn-releve" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                Relevé de Notes
            </button>
            <button onclick="switchTab('certifications')" id="tab-btn-certifications" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                Certifications
            </button>
            <button onclick="switchTab('achievements')" id="tab-btn-achievements" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                Succès & Badges
            </button>
            <button onclick="switchTab('tele-evaluations')" id="tab-btn-tele-evaluations" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                Téléévaluations
            </button>

            <button onclick="switchTab('profil')" id="tab-btn-profil" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                Mon Profil
            </button>
            <a href="/evaluations.php" class="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left">
                Évaluations
            </a>
        </nav>

        <!-- Profile / Sidebar Footer -->
        <div class="p-4 border-t border-[#003619] bg-[#003c1c] flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 overflow-hidden">
                <img id="header-avatar" 
                     src="<?= $user['avatar_path'] ? htmlspecialchars(mediaUrl('avatar', $user['avatar_path'])) : 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user['email']))) . '?d=mp'; ?>" 
                     alt="Photo de profil" class="w-9 h-9 rounded-full object-cover border border-white/20">
                <div class="flex-grow overflow-hidden">
                    <div class="text-xs font-semibold text-white truncate id-student-name"><?= htmlspecialchars($user['name']); ?></div>
                    <div class="text-[10px] text-white/60 truncate"><?= htmlspecialchars($user['email']); ?></div>
                </div>
            </div>
            <a href="/logout.php" title="Déconnexion" class="text-white/60 hover:text-red-400 transition-colors flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTAINER -->
    <div class="flex-grow flex flex-col min-h-screen overflow-x-hidden">

        <!-- Top Header Controls (Desktop) -->
        <header class="hidden md:flex justify-between items-center py-4 px-8 border-b border-[#E5E5E7] dark:border-[#2C2C2C] bg-white dark:bg-[#1A1A1A] sticky top-0 z-30">
            <div class="flex items-center gap-2">
                <span class="text-xs text-[#888888] dark:text-[#AAAAAA] uppercase tracking-wider font-semibold">Tableau de Bord</span>
            </div>
            <div class="flex items-center gap-4">
                <!-- Notifications -->
                <div class="relative" id="notif-wrap">
                    <button type="button" id="notif-btn" class="relative p-1.5 text-[#555555] dark:text-[#AAAAAA] hover:text-[#004B23] dark:hover:text-[#34C759] transition-colors rounded-full hover:bg-[#F5F5F7] dark:hover:bg-[#252525]" aria-label="Notifications">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <span id="notif-count" class="hidden absolute -top-1 -right-1 bg-[#D32F2F] text-white text-[9px] font-bold px-1 py-0.2 rounded-full min-w-[15px] text-center border border-white">0</span>
                    </button>
                    <div id="notif-panel-container" class="hidden absolute right-0 top-full mt-2 w-80 bg-white dark:bg-[#1A1A1A] border border-[#E5E5E7] dark:border-[#2C2C2C] shadow-xl z-50 text-left text-sm rounded-lg overflow-hidden flex flex-col max-h-[360px]">
                        <div class="p-3 border-b border-[#E5E5E7] dark:border-[#2C2C2C] flex justify-between items-center bg-[#F9F7F4] dark:bg-[#252525] flex-shrink-0">
                            <span class="font-serif font-semibold text-xs uppercase tracking-wider text-[#111111] dark:text-white">Notifications</span>
                            <button onclick="markAllNotificationsRead(event)" class="text-[10px] text-[#004B23] dark:text-[#34C759] hover:underline font-semibold">Tout marquer comme lu</button>
                        </div>
                        <div id="notif-panel" class="overflow-y-auto flex-grow max-h-[300px] dark:text-white/80"></div>
                    </div>
                </div>

                <button class="sv-dark-toggle" data-dark-toggle title="Mode sombre"></button>
                
                <div class="relative inline-block text-left">
                    <select id="lang-selector" onchange="changeLanguage(this.value)" class="bg-transparent text-xs border border-[#E5E5E7] dark:border-[#2C2C2C] text-[#555555] dark:text-[#AAAAAA] rounded-sm py-1 px-2 focus:outline-none focus:border-[#004B23] dark:focus:border-[#34C759]">
                        <option value="fr" <?= TranslationService::getLang() === 'fr' ? 'selected' : ''; ?>>FR</option>
                        <option value="en" <?= TranslationService::getLang() === 'en' ? 'selected' : ''; ?>>EN</option>
                    </select>
                </div>
            </div>
        </header>

        <!-- Main Workspace Area -->
        <main class="flex-grow p-6 md:p-10 lg:p-12 space-y-10 max-w-7xl w-full mx-auto">

            <?php if ($continueCourse): ?>
            <div class="border border-[#004B23] dark:border-[#34C759] bg-[#f8fcf9] dark:bg-[#1a2e22] p-5 rounded-xl flex flex-wrap items-center justify-between gap-4 shadow-sm hover:shadow transition-shadow duration-300">
                <div>
                    <div class="text-[10px] uppercase tracking-widest text-[#004B23] dark:text-[#34C759] font-bold mb-1">Continuer l'apprentissage</div>
                    <div class="font-serif text-lg text-[#111111] dark:text-white"><?= htmlspecialchars($continueCourse['course_title']); ?></div>
                    <div class="text-xs text-[#555555] dark:text-[#AAAAAA] mt-0.5"><?= htmlspecialchars($continueCourse['lesson_title'] ?? 'Reprendre la leçon'); ?></div>
                </div>
                <button type="button" class="px-5 py-2.5 bg-[#004B23] dark:bg-[#34C759] text-white hover:bg-[#003619] dark:hover:bg-[#28a148] text-xs font-semibold uppercase tracking-wider transition-colors rounded-lg cursor-pointer"
                    onclick="resumeCourse(<?= (int)$continueCourse['course_id']; ?>, <?= (int)$continueCourse['last_lesson_id']; ?>)">
                    Reprendre
                </button>
            </div>
            <?php endif; ?>

            <?php if (!empty($deadlineAlerts)): ?>
            <div class="border border-[#E6A817] bg-[#fffbeb] dark:bg-[#2b2413] p-4 rounded-xl space-y-2">
                <div class="text-xs uppercase tracking-widest text-[#E6A817] font-semibold">Échéances de leçons proches</div>
                <?php foreach ($deadlineAlerts as $alert): ?>
                <p class="text-sm text-[#555555] dark:text-[#DDDDDD]">
                    <strong><?= htmlspecialchars($alert['title']); ?></strong> —
                    évaluation obligatoire avant le <?= date('d/m/Y', strtotime($alert['eval_deadline'])); ?>
                </p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- KPI Apprenant Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6" id="student-kpi">
                <!-- Card 1 -->
                <div class="bg-white dark:bg-[#1A1A1A] border border-[#E5E5E7] dark:border-[#2C2C2C] p-5 rounded-2xl flex items-center justify-between shadow-sm hover:shadow-md transition-all duration-300 group cursor-pointer" onclick="switchTab('mes-cours')">
                    <div class="space-y-1">
                        <span class="text-2xl md:text-3xl font-serif font-bold text-[#004B23] dark:text-[#34C759] transition-transform duration-300 inline-block group-hover:scale-110" id="kpi-completed"><?= $statCompleted; ?></span>
                        <div class="text-[10px] font-medium text-[#555555] dark:text-[#AAAAAA] uppercase tracking-wider">Cours terminés</div>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-[#004B23]/10 dark:bg-[#34C759]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#004B23] dark:text-[#34C759]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                    </div>
                </div>
                <!-- Card 2 -->
                <div class="bg-white dark:bg-[#1A1A1A] border border-[#E5E5E7] dark:border-[#2C2C2C] p-5 rounded-2xl flex items-center justify-between shadow-sm hover:shadow-md transition-all duration-300 group cursor-pointer" onclick="switchTab('releve')">
                    <div class="space-y-1">
                        <span class="text-2xl md:text-3xl font-serif font-bold text-[#004B23] dark:text-[#34C759] transition-transform duration-300 inline-block group-hover:scale-110" id="kpi-score"><?= $statAvgScore; ?>%</span>
                        <div class="text-[10px] font-medium text-[#555555] dark:text-[#AAAAAA] uppercase tracking-wider">Score moyen</div>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-[#004B23]/10 dark:bg-[#34C759]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#004B23] dark:text-[#34C759]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <!-- Card 3 -->
                <div class="bg-white dark:bg-[#1A1A1A] border border-[#E5E5E7] dark:border-[#2C2C2C] p-5 rounded-2xl flex items-center justify-between shadow-sm hover:shadow-md transition-all duration-300 group">
                    <div class="space-y-1">
                        <span class="text-2xl md:text-3xl font-serif font-bold text-[#004B23] dark:text-[#34C759] transition-transform duration-300 inline-block group-hover:scale-110" id="kpi-time"><?= $statStudyH; ?>h<?= str_pad((string)$statStudyM, 2, '0', STR_PAD_LEFT); ?></span>
                        <div class="text-[10px] font-medium text-[#555555] dark:text-[#AAAAAA] uppercase tracking-wider">Temps d'étude</div>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-[#004B23]/10 dark:bg-[#34C759]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#004B23] dark:text-[#34C759]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <!-- Card 4 -->
                <div class="bg-white dark:bg-[#1A1A1A] border border-[#E5E5E7] dark:border-[#2C2C2C] p-5 rounded-2xl flex items-center justify-between shadow-sm hover:shadow-md transition-all duration-300 group cursor-pointer" onclick="switchTab('certifications')">
                    <div class="space-y-1">
                        <span class="text-2xl md:text-3xl font-serif font-bold text-[#004B23] dark:text-[#34C759] transition-transform duration-300 inline-block group-hover:scale-110" id="kpi-certs"><?= count($myCertificates); ?></span>
                        <div class="text-[10px] font-medium text-[#555555] dark:text-[#AAAAAA] uppercase tracking-wider">Certifications</div>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-[#004B23]/10 dark:bg-[#34C759]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#004B23] dark:text-[#34C759]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" /></svg>
                    </div>
                </div>
            </div>

            <?php if (!empty($myBadges)): ?>
            <div class="flex flex-wrap gap-2 items-center" id="badges-row">
                <span class="text-[10px] font-mono uppercase tracking-widest text-[#888888]">Badges Obtenus :</span>
                <?php
                $badgeLabels = ['study_hour' => '1h d\'étude', 'first_lesson' => 'Première leçon', 'certified' => 'Certifié', 'course_complete' => 'Cours terminé'];
                foreach ($myBadges as $b): ?>
                    <span class="text-[11px] px-3 py-1 border border-[#004B23] dark:border-[#34C759] text-[#004B23] dark:text-[#34C759] rounded-full bg-white dark:bg-[#1C2C21] font-medium shadow-sm"><?= $badgeLabels[$b['badge_type']] ?? $b['badge_type']; ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <!-- 1. Onglet CATALOGUE -->
        <div id="tab-catalogue" class="tab-content space-y-12">
            <div class="space-y-3">
                <h2 class="font-serif text-3xl font-light">Catalogue des Enseignements</h2>
                <p class="text-sm font-light text-[#555555] max-w-xl">
                    Découvrez les programmes disponibles. Certains cours requièrent une clé de connexion fournie par l'enseignant.
                </p>
                <input type="search" id="course-search" placeholder="Rechercher un cours, module ou enseignant…"
                    class="w-full max-w-md px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" id="course-grid">
                <?php foreach ($courses as $c): ?>
                    <div class="bg-white border border-[#E5E5E7] hover:border-[#004B23] hover:shadow-lg transition-all duration-300 rounded-lg overflow-hidden flex flex-col justify-between"
                         data-course-card
                         data-search="<?= htmlspecialchars(strtolower($c['title'] . ' ' . $c['module_title'] . ' ' . $c['teacher_name'] . ' ' . $c['description'])); ?>">
                        <!-- Image ou Gradient de couverture -->
                        <div class="w-full h-44 flex-shrink-0 relative overflow-hidden select-none bg-gradient-to-br from-[#004B23] to-[#006630]">
                            <?php if (!empty($c['cover_image'])): ?>
                                <img src="/download.php?type=cover&file=<?= urlencode($c['cover_image']); ?>" 
                                     alt="Illustration <?= htmlspecialchars($c['title']); ?>" 
                                     class="w-full h-full object-cover transition-transform duration-500 hover:scale-105">
                            <?php else: ?>
                                <!-- Motif géométrique premium minimaliste de fallback -->
                                <div class="absolute inset-0 opacity-20 bg-[radial-gradient(#ffffff_1.5px,transparent_1.5px)] [background-size:16px_16px]"></div>
                                <div class="absolute bottom-4 left-4 right-4 flex items-center justify-between">
                                    <div class="h-10 w-10 bg-white/10 backdrop-blur-md flex items-center justify-center rounded-lg text-white">
                                        🎓
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="p-6 flex-grow flex flex-col justify-between space-y-4">
                            <div class="space-y-2">
                                <span class="text-[10px] font-mono uppercase tracking-widest text-[#888888] block">
                                    <?= htmlspecialchars($c['module_title']); ?>
                                </span>
                                <h3 class="font-serif text-lg font-semibold text-[#111111] leading-snug">
                                    <?= htmlspecialchars($c['title']); ?>
                                </h3>
                                <p class="text-xs text-[#888888] font-light">
                                    Enseigné par : <span class="font-normal text-[#555555]"><?= htmlspecialchars($c['teacher_name']); ?></span>
                                </p>
                                <p class="text-sm font-light text-[#555555] line-clamp-3 pt-2 leading-relaxed">
                                    <?= htmlspecialchars($c['description']); ?>
                                </p>
                            </div>

                            <div class="pt-4 border-t border-[#E5E5E7] flex justify-between items-center">
                                <?php if ($c['is_enrolled']): ?>
                                    <span class="text-xs text-[#004B23] font-semibold flex items-center gap-1.5">
                                        ✓ Déjà inscrit (<?= (int)$c['progress_percent']; ?>%)
                                    </span>
                                    <button onclick="switchTab('mes-cours')" class="text-xs font-semibold uppercase tracking-wider text-[#004B23] hover:underline">
                                        Étudier
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs font-mono text-[#888888]">
                                        <?= $c['enrollment_key'] ? '🔒 Clé requise' : '🔓 Libre'; ?>
                                    </span>
                                    <button onclick="attemptEnroll(<?= $c['id']; ?>, <?= $c['enrollment_key'] ? 'true' : 'false'; ?>)"
                                        class="px-4 py-2 bg-[#111111] hover:bg-[#004B23] text-[#FFFFFF] text-xs font-semibold uppercase tracking-wider transition-colors rounded-sm">
                                        S'inscrire
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 2. Onglet MES ÉTUDES -->
        <div id="tab-mes-cours" class="tab-content hidden space-y-12">
            <div class="space-y-3">
                <h2 class="font-serif text-3xl font-light">Mes Cours Actifs</h2>
                <p class="text-sm font-light text-[#555555]">
                    Consultez vos cours, étudiez les chapitres et passez les évaluations finales pour obtenir vos certifications.
                </p>
            </div>

            <div class="space-y-8">
                <?php 
                $enrolledCourses = array_filter($courses, fn($c) => $c['is_enrolled']);
                if (empty($enrolledCourses)):
                ?>
                    <div class="p-12 border border-dashed border-[#E5E5E7] text-center text-sm font-light text-[#888888]">
                        Vous n'êtes inscrit à aucun cours pour le moment. Parcourez le catalogue pour vous inscrire.
                    </div>
                <?php else: ?>
                    <?php foreach ($enrolledCourses as $ec): ?>
                        <div class="border border-[#E5E5E7] bg-white rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow duration-300">
                            <!-- Banner image / gradient -->
                            <div class="w-full h-32 flex-shrink-0 relative overflow-hidden select-none bg-gradient-to-r from-[#004B23] to-[#006630]">
                                <?php if (!empty($ec['cover_image'])): ?>
                                    <img src="/download.php?type=cover&file=<?= urlencode($ec['cover_image']); ?>" 
                                         alt="Illustration" 
                                         class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="absolute inset-0 opacity-15 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:12px_12px]"></div>
                                <?php endif; ?>
                            </div>

                            <div class="p-6 space-y-6">
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                <div>
                                    <span class="text-[10px] font-mono uppercase tracking-widest text-[#888888]">
                                        <?= htmlspecialchars($ec['module_title']); ?>
                                    </span>
                                    <h3 class="font-serif text-2xl font-light text-[#111111]">
                                        <?= htmlspecialchars($ec['title']); ?>
                                    </h3>
                                    <p class="text-xs text-[#888888] font-light mt-0.5">
                                        Responsable : <?= htmlspecialchars($ec['teacher_name']); ?>
                                    </p>
                                </div>
                                <div class="flex items-center gap-4 flex-shrink-0">
                                    <!-- Boutons actions -->
                                    <button onclick="studyCourse(<?= $ec['id']; ?>)" 
                                        class="px-5 py-2.5 bg-[#111111] text-[#FFFFFF] text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] transition-colors rounded-sm">
                                        Accéder aux Leçons
                                    </button>
                                    
                                    <!-- Bouton Certif (inactif si progress < 100) -->
                                    <?php if ((int)$ec['progress_percent'] === 100): ?>
                                        <button onclick="startFinalExam(<?= $ec['id']; ?>, '<?= htmlspecialchars($ec['title'], ENT_QUOTES); ?>')"
                                            class="px-5 py-2.5 bg-[#004B23] text-[#FFFFFF] text-xs font-semibold uppercase tracking-wider hover:bg-[#111111] transition-colors rounded-sm">
                                            Passer la Certification
                                        </button>
                                    <?php else: ?>
                                        <button disabled title="Complétez le cours à 100% pour débloquer le QCM final"
                                            class="px-5 py-2.5 bg-[#F5F5F7] border border-[#E5E5E7] text-[#888888] text-xs font-semibold uppercase tracking-wider cursor-not-allowed rounded-sm">
                                            Certifier (Bloqué)
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Barre de Progression Fine Verte s'animant -->
                            <div class="space-y-2">
                                <div class="flex justify-between text-xs font-light text-[#555555]">
                                    <span>Progression</span>
                                    <span class="font-semibold text-[#111111]"><?= (int)$ec['progress_percent']; ?>%</span>
                                </div>
                                <div class="h-1 w-full bg-[#F5F5F7] rounded-full overflow-hidden">
                                    <div class="h-full bg-[#004B23] transition-all duration-1000 ease-out" style="width: <?= (int)$ec['progress_percent']; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2b. Onglet RELEVÉ DE NOTES -->
        <div id="tab-releve" class="tab-content hidden space-y-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div class="space-y-3">
                    <h2 class="font-serif text-3xl font-light">Relevé de Notes</h2>
                    <p class="text-sm font-light text-[#555555]">Historique de progression, scores par leçon et relevés de tentatives de certification.</p>
                </div>
                <a href="/student/releve.php" target="_blank"
                    class="inline-flex items-center px-5 py-2.5 bg-[#111111] text-white text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] transition-colors rounded-sm flex-shrink-0">
                    Voir / Télécharger le relevé PDF
                </a>
            </div>
            <div id="transcript-container" class="space-y-6">
                <p class="text-sm text-[#888888] italic">Chargement du relevé…</p>
            </div>
        </div>

        <!-- 3. Onglet CERTIFICATIONS -->
        <div id="tab-certifications" class="tab-content hidden space-y-12">
            <div class="space-y-3">
                <h2 class="font-serif text-3xl font-light">Mes Certifications Obtenues</h2>
                <p class="text-sm font-light text-[#555555]">
                    Vos diplômes officiels délivrés après validation d'un module entier à la suite d'un score minimum de 80% au QCM.
                </p>
            </div>

            <div class="space-y-6">
                <?php if (empty($myCertificates)): ?>
                    <div class="p-12 border border-dashed border-[#E5E5E7] text-center text-sm font-light text-[#888888]">
                        Vous n'avez pas encore obtenu de certificat. Progressez dans vos cours et validez les examens finaux à plus de 80%.
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <?php foreach ($myCertificates as $cert): ?>
                            <div class="border border-[#111111] p-8 space-y-6 bg-[var(--sv-cream-light)] flex flex-col justify-between">
                                <div class="space-y-4">
                                    <div class="text-[10px] font-mono uppercase tracking-widest text-[#004B23] font-semibold">
                                        ✓ Certificat de Validation
                                    </div>
                                    <h3 class="font-serif text-2xl font-light text-[#111111] leading-tight">
                                        <?= htmlspecialchars($cert['course_title'] ?? 'Cours Inconnu'); ?>
                                    </h3>
                                    <p class="text-xs text-[#555555] font-light leading-relaxed">
                                        Délivré officiellement à <span class="font-semibold text-[#111111]"><?= htmlspecialchars($user['name']); ?></span> pour la validation réglementaire du cours de formation.
                                    </p>
                                </div>
                                <div class="pt-6 border-t border-[#E5E5E7] flex justify-between items-center text-xs flex-wrap gap-2">
                                    <span class="font-mono text-[#888888]">Code : <span class="text-[#111111] font-semibold"><?= htmlspecialchars($cert['certificate_code']); ?></span></span>
                                    <div class="flex gap-3">
                                        <a href="/certificate.php?code=<?= urlencode($cert['certificate_code']); ?>"
                                           class="text-[#004B23] font-semibold uppercase tracking-wider hover:underline">Voir / Imprimer PDF</a>
                                        <a href="/verify.php?code=<?= urlencode($cert['certificate_code']); ?>" target="_blank"
                                           class="text-[#888888] hover:underline">Vérifier</a>
                                    </div>
                                    <span class="text-[#888888]"><?= date('d/m/Y', strtotime($cert['issued_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3a. Onglet ACHIEVEMENTS -->
        <div id="tab-achievements" class="tab-content hidden space-y-12">
            <div class="space-y-3">
                <h2 class="font-serif text-3xl font-light">Mes Succès & Badges</h2>
                <p class="text-sm font-light text-[#555555]">
                    Débloquez des badges uniques en complétant vos cours, en obtenant de parfaits scores ou en étudiant à toute heure.
                </p>
            </div>

            <!-- Progression & Level Dashboard -->
            <?php
            $unlockedCount = count($myBadges);
            $totalBadges = count($allBadgesConfig);
            $percentUnlocked = $totalBadges > 0 ? round(($unlockedCount / $totalBadges) * 100) : 0;
            
            // Determine Level Rank
            if ($unlockedCount <= 1) {
                $rankTitle = "Novice Académique";
                $rankColor = "bg-zinc-100 text-zinc-800 border-zinc-200 dark:bg-zinc-900/50 dark:text-zinc-300 dark:border-zinc-800";
            } elseif ($unlockedCount <= 3) {
                $rankTitle = "Initié Studieux";
                $rankColor = "bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900/50";
            } elseif ($unlockedCount <= 5) {
                $rankTitle = "Spécialiste Éclairé";
                $rankColor = "bg-indigo-50 text-indigo-800 border-indigo-200 dark:bg-indigo-950/20 dark:text-indigo-400 dark:border-indigo-900/50";
            } elseif ($unlockedCount <= 7) {
                $rankTitle = "Expert Émérite";
                $rankColor = "bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/50";
            } else {
                $rankTitle = "Grand Maître StudyVibe";
                $rankColor = "bg-gradient-to-r from-yellow-500/10 via-pink-500/10 to-purple-500/10 text-purple-900 border-purple-300 dark:from-yellow-500/20 dark:to-purple-500/20 dark:text-purple-300 dark:border-purple-800";
            }
            ?>

            <div class="border border-[#111111] dark:border-zinc-800 p-8 bg-[var(--sv-cream-light)] dark:bg-[#1E1E1E]/50 space-y-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-full bg-[#004B23] text-white flex items-center justify-center font-serif text-2xl font-bold">
                            <?= strtoupper(substr($user['name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-widest text-[#888888] font-mono">Rang actuel</div>
                            <h3 class="font-serif text-2xl font-light text-[#111111] dark:text-white mt-0.5"><?= htmlspecialchars($user['name']); ?></h3>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border <?= $rankColor ?> mt-1.5">
                                <?= $rankTitle ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="text-left md:text-right">
                        <div class="text-xs uppercase tracking-widest text-[#888888] font-mono">Taux de Complétion</div>
                        <div class="text-3xl font-light text-[#111111] dark:text-white mt-0.5"><?= $unlockedCount ?> / <?= $totalBadges ?> Badges</div>
                        <p class="text-xs text-[#555555] dark:text-zinc-400 mt-1 font-light">
                            Prochain niveau après <?= min($totalBadges, $unlockedCount + 1) ?> badge<?= ($unlockedCount + 1 > 1) ? 's' : '' ?>.
                        </p>
                    </div>
                </div>

                <!-- Custom Progress Bar -->
                <div class="space-y-2 pt-2">
                    <div class="flex justify-between text-xs font-light text-[#555555] dark:text-zinc-400">
                        <span>Progression Générale</span>
                        <span class="font-semibold text-[#111111] dark:text-white"><?= $percentUnlocked ?>%</span>
                    </div>
                    <div class="h-3 w-full bg-white dark:bg-zinc-900 border border-[#111111] dark:border-zinc-800 rounded-full overflow-hidden p-0.5">
                        <div class="h-full bg-gradient-to-r from-[#004B23] to-[#34C759] rounded-full transition-all duration-1000 ease-out" style="width: <?= $percentUnlocked ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Badges Grid -->
            <div id="badges-grid-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($allBadgesConfig as $key => $config): ?>
                    <?php 
                    $isEarned = isset($earnedBadgesLookup[$key]);
                    $earnedDate = $isEarned ? $earnedBadgesLookup[$key] : null;
                    ?>
                    <div data-badge-key="<?= $key ?>"
                         onclick="openBadgeModal('<?= $key ?>', '<?= addslashes($config['title']) ?>', '<?= addslashes($config['desc']) ?>', '<?= $isEarned ? 'unlocked' : 'locked' ?>', '<?= $earnedDate ? date('d/m/Y', strtotime($earnedDate)) : '' ?>')" 
                         class="cursor-pointer border border-[#111111] dark:border-zinc-800 p-6 bg-white dark:bg-[#1E1E1E] transition-all hover:shadow-[4px_4px_0px_#111111] dark:hover:shadow-[4px_4px_0px_#34C759] duration-300 flex flex-col justify-between items-center text-center space-y-4 <?= $isEarned ? 'hover:scale-[1.02]' : 'opacity-65' ?> sv-badge-card">
                        
                        <!-- Icon Wrapper with status styling -->
                        <div class="relative w-20 h-20 flex items-center justify-center rounded-full border-2 <?= $isEarned ? 'bg-gradient-to-br ' . $config['color'] . ' text-white ' . $config['border'] : 'bg-zinc-100 text-zinc-400 border-dashed border-zinc-300 dark:bg-zinc-800 dark:border-zinc-700' ?> transition-all duration-500">
                            <?= $config['icon'] ?>
                            
                            <?php if (!$isEarned): ?>
                                <!-- Lock Badge -->
                                <div class="absolute -bottom-1 -right-1 bg-zinc-800 text-white rounded-full p-1 border border-white dark:border-zinc-900">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                                </div>
                            <?php else: ?>
                                <!-- Glow Effect -->
                                <div class="absolute inset-0 rounded-full bg-gradient-to-br <?= $config['color'] ?> opacity-25 blur-md -z-10 animate-pulse"></div>
                            <?php endif; ?>
                        </div>

                        <!-- Card text details -->
                        <div class="space-y-1 w-full">
                            <h4 class="font-serif text-lg font-semibold text-[#111111] dark:text-white"><?= htmlspecialchars($config['title']) ?></h4>
                            <p class="text-xs text-[#555555] dark:text-zinc-400 font-light line-clamp-2 leading-relaxed">
                                <?= htmlspecialchars($config['desc']) ?>
                            </p>
                        </div>

                        <!-- Earned status / Date tag -->
                        <div class="w-full pt-3 border-t border-[#E5E5E7] dark:border-zinc-800">
                            <?php if ($isEarned): ?>
                                <span class="inline-flex items-center gap-1 text-[10px] font-mono text-[#004B23] dark:text-[#34C759] font-bold">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                    Obtenu le <?= date('d/m/Y', strtotime($earnedDate)) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-[10px] font-mono text-zinc-500 uppercase tracking-wider">Verrouillé</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 3b. Onglet TÉLÉÉVALUATIONS -->
        <div id="tab-tele-evaluations" class="tab-content hidden space-y-12">
            <div class="space-y-3">
                <h2 class="font-serif text-3xl font-light">Mes Téléévaluations</h2>
                <p class="text-sm font-light text-[#555555]">
                    Retrouvez ici toutes vos séances d'évaluation en direct et asynchrones, vos scores et vos rapports de correction détaillés.
                </p>
            </div>

            <div class="space-y-6">
                <?php if (empty($myEvaluations)): ?>
                    <div class="p-12 border border-dashed border-[#E5E5E7] text-center text-sm font-light text-[#888888]">
                        Vous n'avez participé à aucune téléévaluation pour le moment.<br>
                        <a href="/evaluations.php" class="text-brand font-semibold hover:underline mt-2 inline-block">Parcourir les évaluations disponibles</a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto border border-[#E5E5E7] rounded-sm bg-white shadow-sm">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-[#111111] text-xs uppercase text-[#555555] bg-[#FAFAFA]">
                                    <th class="p-4 text-left font-medium">Session / Cours</th>
                                    <th class="p-4 text-center font-medium">Type</th>
                                    <th class="p-4 text-center font-medium">Date d'inscription</th>
                                    <th class="p-4 text-center font-medium">Score / Résultat</th>
                                    <th class="p-4 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myEvaluations as $eval): 
                                    $isFinished = $eval['score'] !== null;
                                    $token = $isFinished ? hash_hmac('sha256', (string)$eval['registration_id'], APP_SECRET) : '';
                                ?>
                                    <tr class="border-b border-[#E5E5E7] hover:bg-[#FAFAFA]/50 transition-colors">
                                        <td class="p-4">
                                            <div class="font-serif font-semibold text-[#111111] text-base"><?= htmlspecialchars($eval['session_title']); ?></div>
                                            <div class="text-xs text-[#888888] font-light mt-0.5"><?= htmlspecialchars($eval['course_title']); ?></div>
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="px-2.5 py-1 text-[10px] uppercase font-bold rounded-sm border <?= $eval['is_async'] ? 'bg-blue-50 text-blue-700 border-blue-200/50' : 'bg-orange-50 text-orange-700 border-orange-200/50' ?>">
                                                <?= $eval['is_async'] ? 'Asynchrone' : 'En direct' ?>
                                            </span>
                                        </td>
                                        <td class="p-4 text-center text-xs text-[#555555]">
                                            <?= date('d/m/Y H:i', strtotime($eval['registered_at'])); ?>
                                        </td>
                                        <td class="p-4 text-center">
                                            <?php if ($isFinished): ?>
                                                <span class="text-brand font-semibold text-sm"><?= round((float)$eval['score'], 1); ?> %</span>
                                            <?php else: ?>
                                                <span class="text-xs font-medium text-orange-600 bg-orange-50 px-2 py-0.5 border border-orange-200/50 rounded-sm">En attente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-right">
                                            <?php if ($isFinished): ?>
                                                <a href="/student/evaluation-results.php?registration_id=<?= $eval['registration_id']; ?>&token=<?= $token; ?>"
                                                   class="inline-block px-4 py-2 bg-[#111111] hover:bg-brand text-[#FFFFFF] text-xs font-semibold uppercase tracking-wider transition-colors rounded-sm shadow-sm">
                                                    Rapport détaillé
                                                </a>
                                            <?php else: ?>
                                                <a href="/live-session.php?code=<?= urlencode($eval['session_code']); ?>"
                                                   class="inline-block px-4 py-2 bg-brand hover:bg-brandHover text-[#FFFFFF] text-xs font-semibold uppercase tracking-wider transition-colors rounded-sm shadow-sm">
                                                    Rejoindre
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>



        <!-- 4. Onglet MON PROFIL -->
        <div id="tab-profil" class="tab-content hidden space-y-12">
            <div class="space-y-3">
                <h2 class="font-serif text-3xl font-light">Profil & Paramètres</h2>
                <p class="text-sm font-light text-[#555555]">
                    Gérez vos informations personnelles et votre avatar de profil académique.
                </p>
            </div>

            <div class="max-w-xl border border-[#E5E5E7] p-8 space-y-8 bg-[var(--sv-cream-light)]">
                <!-- Zone Avatar avec prévisualisation et upload asynchrone -->
                <div class="flex flex-col sm:flex-row items-center gap-6 pb-6 border-b border-[#E5E5E7]">
                    <div class="relative group">
                        <img id="profile-avatar-preview" 
                            src="<?= $user['avatar_path'] ? htmlspecialchars(mediaUrl('avatar', $user['avatar_path'])) : 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user['email']))) . '?d=mp'; ?>" 
                            alt="Avatar" class="w-24 h-24 rounded-full object-cover border border-[#E5E5E7] transition-opacity duration-300">
                    </div>
                    <div class="space-y-2 text-center sm:text-left">
                        <h4 class="text-sm font-semibold uppercase tracking-wider text-[#555555]">Avatar Académique</h4>
                        <input type="file" id="avatar-input" accept="image/*" class="hidden" onchange="uploadAvatar()">
                        <button onclick="document.getElementById('avatar-input').click()"
                            class="px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-xs font-semibold uppercase tracking-wider hover:border-[#111111] transition-all rounded-sm">
                            Téléverser une image
                        </button>
                        <p class="text-[10px] text-[#888888] font-light">Formats autorisés : JPG, PNG, GIF. Max 2 Mo.</p>
                    </div>
                </div>

                <!-- Formulaire de nom -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Adresse électronique (Non modifiable)</label>
                        <input type="email" disabled value="<?= htmlspecialchars($user['email']); ?>"
                            class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm text-[#888888] cursor-not-allowed rounded-sm">
                    </div>
                    <div>
                        <label for="profile-name" class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Nom Complet</label>
                        <input type="text" id="profile-name" value="<?= htmlspecialchars($user['name']); ?>"
                            class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] focus:bg-[#FFFFFF] transition-all rounded-sm">
                    </div>
                    
                    <button onclick="updateProfileName()"
                        class="px-6 py-2.5 bg-[#111111] text-[#FFFFFF] text-xs font-semibold uppercase tracking-widest hover:bg-[#004B23] transition-all rounded-sm">
                        Enregistrer les Modifications
                    </button>
                    <span id="profile-status" class="text-xs text-[#004B23] font-medium ml-4 hidden">Modifications enregistrées.</span>
                </div>
            </div>
        </div>

    </main>

    <!-- Pied de Page -->
    <footer class="border-t border-[#E5E5E7] dark:border-[#2C2C2C] py-6 px-12 flex justify-between items-center bg-white dark:bg-[#1A1A1A] text-xs text-[#888888] dark:text-[#AAAAAA] font-light">
        <div>StudyVibe Académique — Espace d'Étude</div>
        <div>Console Apprenant</div>
    </footer>
</div>
    <!-- Modal : Liseuse / Étude de Cours (Zen, spacieux) -->
    <div id="study-modal" class="hidden fixed inset-0 bg-[#FAF9F6] dark:bg-[#121212] z-50 flex flex-col justify-between">
        <!-- En-tête Liseuse -->
        <header id="study-header" class="border-b border-[#E5E5E7] dark:border-[#2C2C2C] py-4 px-6 md:px-12 flex justify-between items-center bg-white dark:bg-[#1E1E1E]">
            <div class="flex items-center gap-4">
                <button onclick="toggleOutline()" class="p-2 text-xs font-semibold border border-[#E5E5E7] dark:border-[#2C2C2C] hover:bg-[#F5F5F7] dark:hover:bg-[#252525] rounded transition-all" title="Afficher/Masquer le programme">
                    Programme
                </button>
                <div>
                    <span id="study-course-module" class="text-[10px] font-mono uppercase tracking-widest text-[#888888] dark:text-[#AAAAAA]">Module</span>
                    <h2 id="study-course-title" class="font-serif text-base font-semibold text-[#111111] dark:text-white leading-tight">Titre du Cours</h2>
                </div>
            </div>
            
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2 text-xs text-[#555555] dark:text-[#AAAAAA]">
                    <span class="w-2 h-2 rounded-full bg-[#004B23] dark:bg-[#34C759] animate-pulse"></span>
                    <span>Temps d'étude :</span>
                    <span id="lesson-session-timer" class="sv-timer font-mono font-semibold text-[#004B23] dark:text-[#34C759]">00:00</span>
                </div>
                <button onclick="toggleCompanion()" class="p-2 text-xs font-semibold border border-[#E5E5E7] dark:border-[#2C2C2C] hover:bg-[#F5F5F7] dark:hover:bg-[#252525] rounded transition-all" title="Ouvrir le compagnon d'étude">
                    Compagnon d'étude
                </button>
                <button onclick="closeStudyModal()" class="px-3 py-1.5 bg-[#D32F2F] text-white text-xs uppercase tracking-wider font-semibold hover:bg-[#B71C1C] rounded transition-all">
                    Quitter ✕
                </button>
            </div>
        </header>

        <!-- Contenu principal split : leçons à gauche, visualiseur au centre, compagnon à droite -->
        <div class="flex-grow flex flex-col md:flex-row overflow-hidden relative">
            
            <!-- Sidebar : Arborescence du cours -->
            <div id="study-sidebar" class="w-full md:w-80 border-r border-[#E5E5E7] dark:border-[#2C2C2C] bg-white dark:bg-[#1C1C1E] p-6 overflow-y-auto flex-shrink-0 space-y-6">
                <div class="flex justify-between items-center pb-2 border-b border-[#E5E5E7] dark:border-[#2C2C2C]">
                    <h3 class="text-xs font-semibold uppercase tracking-widest text-[#888888] dark:text-[#AAAAAA]">Programme du cours</h3>
                    <button onclick="toggleOutline()" class="text-xs text-[#888888] hover:text-[#111111] dark:hover:text-white font-bold">✕</button>
                </div>
                <div id="study-chapters-container" class="space-y-4">
                    <!-- Généré dynamiquement en JS -->
                </div>
            </div>

            <!-- Viewer central (Spacieux, Scrollable) -->
            <div id="study-viewer-content" class="flex-grow p-6 md:p-10 overflow-y-auto space-y-8 bg-[#FFFFFF] dark:bg-[#121212] flex flex-col">
                <!-- Titre leçon et type -->
                <div id="lesson-viewer-header" class="border-b border-[#E5E5E7] dark:border-[#2C2C2C] pb-4 hidden flex justify-between items-start gap-4">
                    <div>
                        <h3 id="study-lesson-title" class="font-serif text-2xl font-light text-[#111111] dark:text-white">Titre de la leçon</h3>
                        <span id="study-lesson-badge" class="text-[9px] font-mono uppercase tracking-widest bg-[#F5F5F7] dark:bg-[#252525] border border-[#E5E5E7] dark:border-[#2C2C2C] px-2 py-0.5 mt-2 inline-block text-[#555555] dark:text-[#AAAAAA]">Badge</span>
                    </div>
                </div>

                <!-- Zone d'affichage des médias -->
                <div id="study-media-container" class="space-y-6 dark:text-white/95">
                    <!-- Texte, PDF, Vidéo injectés ici -->
                </div>

                <!-- Marquer la leçon comme terminée -->
                <div id="lesson-complete-bar" class="hidden flex flex-wrap items-center justify-between gap-4 p-5 border border-[#E5E5E7] dark:border-[#2C2C2C] bg-[#FAF9F6] dark:bg-[#1C1C1E] rounded-xl shadow-sm">
                    <div>
                        <p class="text-sm font-semibold text-[#111111] dark:text-white">Progression de la leçon</p>
                        <p id="lesson-complete-hint" class="text-xs text-[#555555] dark:text-[#AAAAAA] mt-0.5">Une fois le contenu lu, marquez la leçon comme terminée pour mettre à jour votre avancement.</p>
                    </div>
                    <p id="lesson-complete-status" class="hidden text-sm text-[#004B23] dark:text-[#34C759] font-semibold flex items-center gap-1.5">✓ Leçon terminée</p>
                    <button type="button" id="mark-lesson-complete-btn"
                        class="px-5 py-2.5 bg-[#111111] dark:bg-[#FFFFFF] text-[#FFFFFF] dark:text-[#111111] text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] dark:hover:bg-[#34C759] dark:hover:text-white transition-colors rounded-lg flex-shrink-0">
                        Marquer la leçon comme terminée
                    </button>
                </div>

                <!-- Zone mini-quizz de leçon (une question à la fois) -->
                <div id="lesson-quiz-locked" class="hidden p-6 border border-[#E5E5E7] dark:border-[#2C2C2C] bg-[#FAFAFA] dark:bg-[#1A1A1A] rounded-xl space-y-2">
                    <h4 class="font-serif text-lg font-semibold text-[#111111] dark:text-white">Évaluation de Leçon</h4>
                    <p class="text-xs text-[#555555] dark:text-[#AAAAAA] font-light">Terminez la lecture, le document PDF ou la vidéo pour débloquer l'évaluation de cette leçon.</p>
                    <p id="lesson-content-progress" class="text-[10px] text-[#888888] dark:text-[#AAAAAA] font-mono uppercase tracking-wider"></p>
                </div>
                
                <div id="lesson-quiz-container" class="hidden p-6 border border-[#E5E5E7] dark:border-[#2C2C2C] bg-[#FAF9F6] dark:bg-[#1C1C1E] rounded-xl space-y-4">
                    <h4 class="font-serif text-lg font-semibold text-[#111111] dark:text-white">Évaluation de Leçon</h4>
                    <p id="lesson-quiz-hint" class="text-xs font-light text-[#555555] dark:text-[#AAAAAA]">Répondez à chaque question pour valider la leçon.</p>
                    <p id="lesson-quiz-complete-msg" class="hidden text-sm text-[#004B23] dark:text-[#34C759] font-medium">✓ Évaluation terminée — leçon validée.</p>

                    <div id="lesson-quiz-active">
                        <form id="lesson-quiz-form" class="space-y-4">
                            <input type="hidden" id="quiz-lesson-id" name="lesson_id" value="">
                            <input type="hidden" id="quiz-question-id" name="question_id" value="">
                            <div id="lesson-quiz-question-box" class="space-y-3"></div>
                            <div class="pt-2 flex items-center gap-4">
                                <button type="submit" id="lesson-quiz-submit-btn"
                                    class="px-5 py-2 bg-[#111111] dark:bg-[#FFFFFF] text-white dark:text-[#111111] text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] dark:hover:bg-[#34C759] dark:hover:text-white transition-colors rounded-lg">
                                    Soumettre
                                </button>
                                <span id="lesson-quiz-feedback" class="text-xs font-medium"></span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- COMPAGNON SIDEBAR (Right) : Tabbed Companion Widget -->
            <div id="study-companion-panel" class="w-full md:w-96 border-l border-[#E5E5E7] dark:border-[#2C2C2C] bg-[#FAF9F6] dark:bg-[#1C1C1E] flex flex-col flex-shrink-0 overflow-hidden relative shadow-lg">
                <!-- Companion Tab Header -->
                <div class="px-4 pt-3 pb-2 border-b border-[#E5E5E7] dark:border-[#2C2C2C] bg-white dark:bg-[#1E1E1E] flex justify-between items-center">
                    <div class="flex gap-4">
                        <button onclick="switchCompanionTab('ai')" id="companion-btn-ai" class="pb-2 text-xs font-semibold border-b-2 border-[#004B23] text-[#004B23] dark:text-[#34C759] uppercase tracking-wider transition-all">Assistant IA</button>
                        <button onclick="switchCompanionTab('notes')" id="companion-btn-notes" class="pb-2 text-xs font-medium border-b-2 border-transparent text-[#555555] dark:text-[#AAAAAA] uppercase tracking-wider transition-all">Notes</button>
                        <button onclick="switchCompanionTab('qa')" id="companion-btn-qa" class="pb-2 text-xs font-medium border-b-2 border-transparent text-[#555555] dark:text-[#AAAAAA] uppercase tracking-wider transition-all">Q&R</button>
                    </div>
                    <button onclick="toggleCompanion()" class="text-xs text-[#888888] hover:text-[#111111] dark:hover:text-white font-bold pb-2" title="Fermer">✕</button>
                </div>

                <!-- COMPANION TABS CONTENT -->
                <div class="flex-grow flex flex-col overflow-hidden relative">

                    <!-- Tab 1: AI Assistant (WhatsApp style) -->
                    <div id="companion-tab-ai" class="flex-grow flex flex-col overflow-hidden">
                        <!-- AI chat messages area -->
                        <div id="ai-chat-messages" class="flex-grow p-4 overflow-y-auto space-y-4 font-light text-sm leading-relaxed text-[#111111] wa-chat-bg flex flex-col dark:text-white">
                            <div class="flex justify-start w-full my-2">
                                <div class="px-4 py-2 bg-[#FFFFFF] dark:bg-[#2C2C2E] text-[#000000] dark:text-white text-xs rounded-[16px_16px_16px_4px] max-w-[85%] shadow-sm border border-[#E5E5E7] dark:border-[#2C2C2C] relative break-words">
                                    Bonjour. Je suis votre assistant StudyVibe. Comment puis-je vous aider à comprendre cette leçon aujourd'hui ?
                                </div>
                            </div>
                        </div>

                        <!-- Quick reply pills -->
                        <div class="px-4 py-2 bg-white/40 dark:bg-[#1E1E1E]/40 backdrop-blur-md border-t border-[#E5E5E7]/80 dark:border-[#2C2C2C] flex gap-2 overflow-x-auto scrollbar-none select-none relative z-10 flex-shrink-0">
                            <button type="button" onclick="triggerAiAction('summarize')" class="flex-shrink-0 px-3 py-1.5 bg-[#FFFFFF]/60 hover:bg-[#FFFFFF]/90 dark:bg-[#2C2C2E]/60 dark:hover:bg-[#2C2C2E]/90 border border-[#FFFFFF]/60 dark:border-[#2C2C2C] text-[11px] font-semibold text-[#004B23] dark:text-[#34C759] transition-all rounded-full shadow-sm">
                                Résumer le cours
                            </button>
                            <button type="button" onclick="triggerAiAction('explain')" class="flex-shrink-0 px-3 py-1.5 bg-[#FFFFFF]/60 hover:bg-[#FFFFFF]/90 dark:bg-[#2C2C2E]/60 dark:hover:bg-[#2C2C2E]/90 border border-[#FFFFFF]/60 dark:border-[#2C2C2C] text-[11px] font-semibold text-[#004B23] dark:text-[#34C759] transition-all rounded-full shadow-sm">
                                Expliquer simplement
                            </button>
                            <button type="button" onclick="triggerAiAction('generate_quiz')" class="flex-shrink-0 px-3 py-1.5 bg-[#FFFFFF]/60 hover:bg-[#FFFFFF]/90 dark:bg-[#2C2C2E]/60 dark:hover:bg-[#2C2C2E]/90 border border-[#FFFFFF]/60 dark:border-[#2C2C2C] text-[11px] font-semibold text-[#004B23] dark:text-[#34C759] transition-all rounded-full shadow-sm">
                                S'auto-évaluer
                            </button>
                        </div>

                        <!-- AI message input bar -->
                        <form id="ai-chat-form" class="p-3 border-t border-[#E5E5E7] dark:border-[#2C2C2C] bg-white dark:bg-[#1E1E1E] flex items-center gap-2" onsubmit="sendAiMessage(event)">
                            <div class="flex-grow relative">
                                <input type="text" id="ai-chat-input" placeholder="Posez une question sur le cours..." autocomplete="off"
                                    class="w-full px-4 py-2 border border-[#E5E5E7] dark:border-[#2C2C2C] text-xs focus:outline-none focus:border-[#D5D0C8] rounded-full bg-[#F5F5F7] dark:bg-[#2C2C2E] text-[#111111] dark:text-white placeholder-[#888888]">
                            </div>
                            <button type="submit"
                                class="w-8 h-8 flex items-center justify-center bg-[#004B23] dark:bg-[#34C759] hover:bg-[#003619] text-[#FFFFFF] rounded-full transition-colors shadow-md flex-shrink-0">
                                <svg class="w-4 h-4 fill-current rotate-45 transform translate-x-[-1px] translate-y-[1px]" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                </svg>
                            </button>
                        </form>
                    </div>

                    <!-- Tab 2: Timestamp Notes -->
                    <div id="companion-tab-notes" class="flex-grow flex flex-col overflow-y-auto p-4 space-y-4 hidden">
                        <div id="video-notes-section" class="space-y-4 flex flex-col h-full justify-between">
                            <div class="space-y-3">
                                <div class="flex items-center gap-2 border-b pb-2 dark:border-[#2C2C2C]">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-[#888888] dark:text-[#AAAAAA]">Mes Notes Vidéo</span>
                                    <span class="text-[9px] font-mono uppercase tracking-wider text-[#888888] bg-[#F5F5F7] dark:bg-[#252525] px-2 py-0.5 rounded-sm">calées sur les timestamps</span>
                                </div>
                                <div id="video-notes-list" class="flex flex-col gap-2 min-h-[5rem] max-h-[300px] overflow-y-auto">
                                    <p id="no-notes-msg" class="text-xs text-[#888888] italic">Aucune note. Ajoutez-en ci-dessous pendant la vidéo.</p>
                                </div>
                            </div>
                            
                            <form id="video-note-form" class="border-t pt-4 dark:border-[#2C2C2C] flex flex-col gap-3">
                                <div class="flex gap-2">
                                    <div class="flex flex-col gap-1 w-24">
                                        <label class="text-[9px] uppercase tracking-wider text-[#555555] dark:text-[#AAAAAA] font-bold">Timestamp</label>
                                        <input type="text" id="note-timestamp" placeholder="04:32" maxlength="6"
                                            class="w-full px-2 py-1.5 bg-white dark:bg-[#2C2C2E] border border-[#E5E5E7] dark:border-[#2C2C2C] text-xs focus:outline-none focus:border-[#004B23] rounded font-mono text-center">
                                    </div>
                                    <div class="flex flex-col gap-1 flex-1">
                                        <label class="text-[9px] uppercase tracking-wider text-[#555555] dark:text-[#AAAAAA] font-bold">Votre note</label>
                                        <input type="text" id="note-text" placeholder="Ma remarque à ce moment…" required
                                            class="w-full px-3 py-1.5 bg-white dark:bg-[#2C2C2E] border border-[#E5E5E7] dark:border-[#2C2C2C] text-xs focus:outline-none focus:border-[#004B23] rounded">
                                    </div>
                                </div>
                                <button type="submit" class="w-full py-2 bg-[#004B23] dark:bg-[#34C759] text-white text-xs font-semibold uppercase tracking-wider hover:bg-[#111111] transition-colors rounded-lg flex justify-center items-center gap-1.5 shadow-sm">
                                    <span>+ Ajouter la note</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Tab 3: Q&A Comments -->
                    <div id="companion-tab-qa" class="flex-grow flex flex-col overflow-hidden hidden p-4">
                        <div id="lesson-qa-container" class="flex flex-col h-full justify-between space-y-4 overflow-hidden">
                            <div class="flex-grow flex flex-col overflow-hidden space-y-2">
                                <h4 class="text-xs font-semibold uppercase tracking-widest text-[#888888] dark:text-[#AAAAAA] border-b pb-2 dark:border-[#2C2C2C]">Questions &amp; Réponses</h4>
                                <div id="lesson-comments-list" class="flex-grow overflow-y-auto space-y-3 pr-1"></div>
                            </div>
                            
                            <form id="lesson-comment-form" class="border-t pt-3 dark:border-[#2C2C2C] flex gap-2 flex-shrink-0">
                                <input type="hidden" id="comment-lesson-id" value="">
                                <input type="text" id="comment-input" placeholder="Poser une question…" required
                                    class="flex-1 px-3 py-2 bg-white dark:bg-[#2C2C2E] border border-[#E5E5E7] dark:border-[#2C2C2C] text-xs focus:outline-none focus:border-[#004B23] rounded-lg">
                                <button type="submit" class="px-4 py-2 bg-[#111111] dark:bg-[#FFFFFF] text-white dark:text-[#111111] text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] rounded-lg transition-all flex-shrink-0">Envoyer</button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- Modal : QCM Final de Certification (30 Questions minimum) -->
    <div id="final-exam-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm z-50 flex items-center justify-center p-6">
        <div class="bg-[#FFFFFF] p-8 md:p-12 max-w-3xl w-full border border-[#E5E5E7] space-y-8 max-h-[90vh] overflow-y-auto sv-modal-enter">
            <div class="border-b border-[#E5E5E7] pb-4 flex justify-between items-start gap-4">
                <div>
                    <h3 class="font-serif text-3xl font-light" id="exam-course-title">Examen Final</h3>
                    <p class="text-xs font-light text-[#888888] mt-1">
                        Épreuve de certification officielle. Seuil d'admission : 80%.
                        <span id="exam-attempts-info" class="block mt-1"></span>
                    </p>
                </div>
                <div class="text-right flex-shrink-0">
                    <div class="text-[10px] uppercase tracking-wider text-[#888888] mb-1">Temps restant</div>
                    <div id="exam-timer" class="sv-timer text-2xl font-mono font-semibold text-[#111111]">90:00</div>
                    <button type="button" onclick="toggleModal('final-exam-modal'); ExamTimer.stop();" class="text-xs text-[#D32F2F] mt-2 uppercase font-semibold">Abandonner</button>
                </div>
            </div>

            <!-- Questionnaire -->
            <form id="final-exam-form" class="space-y-8">
                <input type="hidden" id="exam-course-id" name="course_id" value="">
                
                <div id="exam-questions-container" class="space-y-8 divide-y divide-[#E5E5E7] max-h-96 overflow-y-auto pr-4">
                    <!-- Questions générées dynamiquement -->
                </div>

                <div class="pt-6 border-t border-[#E5E5E7] flex justify-between items-center">
                    <span id="exam-error-alert" class="text-xs text-[#D32F2F] font-medium hidden">Veuillez répondre à toutes les questions avant de valider.</span>
                    <button type="submit"
                        class="px-6 py-3 bg-[#111111] text-[#FFFFFF] text-sm font-semibold uppercase tracking-widest hover:bg-[#004B23] transition-colors rounded-sm">
                        Valider l'Épreuve
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal : Clé d'inscription -->
    <div id="enroll-modal" class="hidden fixed inset-0 bg-black bg-opacity-40 backdrop-blur-sm z-50 flex items-center justify-center p-6">
        <div class="bg-[#FFFFFF] p-8 max-w-sm w-full border border-[#E5E5E7] space-y-6 modal-active">
            <h3 class="font-serif text-2xl font-light">Clé de Connexion Secrète</h3>
            <p class="text-xs font-light text-[#555555]">Ce cours est protégé. Veuillez saisir la clé d'inscription fournie par le professeur.</p>
            
            <form id="enroll-form" class="space-y-4">
                <input type="hidden" id="enroll-course-id" name="course_id" value="">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-[#555555] mb-2">Clé d'inscription</label>
                    <input type="text" id="enroll-key-input" name="enrollment_key" required placeholder="ex: ALGO2026"
                        class="w-full px-4 py-2 bg-[#F5F5F7] border border-[#E5E5E7] text-sm focus:outline-none focus:border-[#004B23] rounded-sm">
                </div>
                <div id="enroll-error" class="hidden text-xs text-[#D32F2F] font-medium">Clé incorrecte. Veuillez réessayer.</div>
                
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="toggleModal('enroll-modal')"
                        class="px-4 py-2 bg-[#F5F5F7] text-[#111111] text-xs font-semibold uppercase tracking-wider border border-[#E5E5E7] rounded-sm">
                        Annuler
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-[#111111] text-[#FFFFFF] text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] rounded-sm">
                        S'inscrire
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================================
         SECTION 4: CLIENT-SIDE JAVASCRIPT CONTROLLERS (STATE & INTERACTION LOGIC)
         ========================================================================= -->
    <script src="/assets/js/app.js"></script>
    <script>
        /**
         * Toggles the visibility of the course outline sidebar in the learning liseuse.
         * @return {void}
         */
        function toggleOutline() {
            const container = document.getElementById('study-chapters-container')?.parentElement;
            if (container) {
                container.classList.toggle('hidden');
            }
        }

        /**
         * Toggles the visibility of the study companion sidebar panel.
         * @return {void}
         */
        function toggleCompanion() {
            const companion = document.getElementById('study-companion-panel');
            if (companion) {
                companion.classList.toggle('hidden');
            }
        }

        /**
         * Switches the active tab in the study companion panel.
         * @param {string} tabId - Target tab identifier ('ai' | 'notes' | 'qa').
         * @return {void}
         */
        function switchCompanionTab(tabId) {
            const tabs = ['ai', 'notes', 'qa'];
            tabs.forEach(t => {
                const btn = document.getElementById('companion-btn-' + t);
                const content = document.getElementById('companion-tab-' + t);
                if (btn) {
                    if (t === tabId) {
                        btn.classList.remove('border-transparent', 'text-[#555555]', 'dark:text-[#AAAAAA]', 'font-medium');
                        btn.classList.add('border-[#004B23]', 'dark:border-[#34C759]', 'text-[#004B23]', 'dark:text-[#34C759]', 'font-semibold');
                    } else {
                        btn.classList.remove('border-[#004B23]', 'dark:border-[#34C759]', 'text-[#004B23]', 'dark:text-[#34C759]', 'font-semibold');
                        btn.classList.add('border-transparent', 'text-[#555555]', 'dark:text-[#AAAAAA]', 'font-medium');
                    }
                }
                if (content) {
                    content.classList.toggle('hidden', t !== tabId);
                }
            });
        }

        /**
         * Opens or closes the mobile navigation drawer menu.
         * @return {void}
         */
        function toggleMobileDrawer() {
            const drawer = document.getElementById('mobile-drawer');
            if (drawer) {
                if (drawer.classList.contains('hidden')) {
                    drawer.classList.remove('hidden');
                    drawer.classList.add('flex');
                } else {
                    drawer.classList.add('hidden');
                    drawer.classList.remove('flex');
                }
            }
        }

        /**
         * Opens or closes the mobile notification panel drawer.
         * @return {void}
         */
        function toggleMobileNotifs() {
            const panel = document.getElementById('mobile-notif-panel-container');
            if (panel) {
                panel.classList.toggle('hidden');
                if (!panel.classList.contains('hidden')) {
                    loadNotifications();
                }
            }
        }
        const STUDENT_TABS = ['catalogue', 'mes-cours', 'releve', 'certifications', 'achievements', 'profil', 'tele-evaluations', 'webinaires'];

        document.addEventListener('DOMContentLoaded', () => {
            initCourseSearch('course-search', 'course-grid');

            // GSAP Number Counter Animations for Student KPIs
            const completedEl = document.getElementById('kpi-completed');
            const scoreEl = document.getElementById('kpi-score');
            const timeEl = document.getElementById('kpi-time');
            const certsEl = document.getElementById('kpi-certs');

            if (completedEl && scoreEl && timeEl && certsEl) {
                // Parse values from HTML content
                const completedVal = parseInt(completedEl.textContent.trim()) || 0;
                const scoreVal = parseFloat(scoreEl.textContent.replace('%', '').trim()) || 0.0;
                
                // Parse time (e.g. 5h32)
                const timeText = timeEl.textContent.trim();
                const timeParts = timeText.split('h');
                const timeHVal = parseInt(timeParts[0]) || 0;
                const timeMVal = parseInt(timeParts[1]) || 0;
                
                const certsVal = parseInt(certsEl.textContent.trim()) || 0;

                const counterObj = { completed: 0, score: 0, timeH: 0, timeM: 0, certs: 0 };
                
                gsap.to(counterObj, {
                    completed: completedVal,
                    score: scoreVal,
                    timeH: timeHVal,
                    timeM: timeMVal,
                    certs: certsVal,
                    duration: 1.6,
                    ease: "power2.out",
                    onUpdate: () => {
                        completedEl.textContent = Math.floor(counterObj.completed);
                        scoreEl.textContent = counterObj.score.toFixed(1) + '%';
                        const minsStr = String(Math.floor(counterObj.timeM)).padStart(2, '0');
                        timeEl.textContent = Math.floor(counterObj.timeH) + 'h' + minsStr;
                        certsEl.textContent = Math.floor(counterObj.certs);
                    }
                });
            }

            // Stagger load the Course grid cards
            gsap.from("#course-grid [data-course-card]", {
                opacity: 0,
                y: 35,
                stagger: 0.1,
                duration: 0.85,
                ease: "power2.out"
            });
        });

        /**
         * Updates visual states of side navigation buttons to reflect active tab.
         * @param {string} activeTab - The newly active tab key.
         * @return {void}
         */
        function updateSidebarButtons(activeTab) {
            STUDENT_TABS.forEach(t => {
                const btn = document.getElementById('tab-btn-' + t);
                if (btn) {
                    if (t === activeTab) {
                        btn.className = "w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-semibold transition-all text-white bg-white/10 border-l-4 border-white text-left";
                    } else {
                        btn.className = "w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left";
                    }
                }
                const mBtn = document.getElementById('mobile-tab-btn-' + t);
                if (mBtn) {
                    if (t === activeTab) {
                        mBtn.className = "w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-semibold transition-all text-white bg-white/10 border-l-4 border-white text-left";
                    } else {
                        mBtn.className = "w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all text-white/70 hover:text-white hover:bg-white/10 text-left";
                    }
                }
            });
        }

        /**
         * Orchestrates tab transitions with custom GSAP fade-in micro-animations.
         * @param {string} tabName - Target tab key.
         * @return {void}
         */
        function switchTab(tabName) {
            switchTabAnimated(tabName, STUDENT_TABS);
            updateSidebarButtons(tabName);
            if (tabName === 'releve') loadTranscript();
            if (tabName === 'achievements') animateBadgesEntrance();
        }

        /**
         * Asynchronously loads student academic transcript, including lesson grades and exam failures.
         * @return {void}
         */
        function loadTranscript() {
            const container = document.getElementById('transcript-container');
            fetch('/student/get-transcript.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) { container.innerHTML = '<p class="text-sm text-[#D32F2F]">Erreur de chargement.</p>'; return; }
                let html = '<div class="overflow-x-auto border border-[#E5E5E7]"><table class="w-full text-sm"><thead><tr class="border-b border-[#111111] text-xs uppercase text-[#555555]"><th class="p-3 text-left">Cours</th><th class="p-3">Progression</th><th class="p-3">Meilleur score</th><th class="p-3">Tentatives</th></tr></thead><tbody>';
                data.courses.forEach(c => {
                    html += `<tr class="border-b border-[#E5E5E7]"><td class="p-3"><strong>${c.course_title}</strong><br><span class="text-xs text-[#888]">${c.module_title}</span></td><td class="p-3 text-center">${c.progress_percent}%</td><td class="p-3 text-center">${c.best_score ? c.best_score + '%' : '—'}</td><td class="p-3 text-center">${c.attempts}</td></tr>`;
                });
                html += '</tbody></table></div>';
                if (data.lesson_scores.length) {
                    html += '<h3 class="font-serif text-xl mt-8 mb-4">Leçons complétées</h3><div class="space-y-2 border border-[#E5E5E7] p-4">';
                    data.lesson_scores.forEach(l => {
                        html += `<div class="flex justify-between text-xs border-b border-[#E5E5E7] py-2"><span>${l.lesson_title} <span class="text-[#888]">(${l.course_title})</span></span><span class="font-semibold">${l.score}%</span></div>`;
                    });
                    html += '</div>';
                }
                if (data.failed_attempts && data.failed_attempts.length) {
                    html += '<h3 class="font-serif text-xl mt-8 mb-4">Tentatives de certification non validées</h3>';
                    html += '<p class="text-xs text-[#555555] mb-4">Récapitulatif sans détail des réponses — consultez ou téléchargez chaque relevé.</p>';
                    html += '<div class="space-y-3">';
                    data.failed_attempts.forEach(a => {
                        const d = new Date(a.attempted_at).toLocaleString('fr-FR');
                        html += `<div class="flex flex-wrap justify-between items-center gap-3 p-4 border border-[#E5E5E7] bg-[#F5F5F7]">
                            <div><strong class="text-sm">${a.course_title}</strong><br>
                            <span class="text-xs text-[#888]">${a.module_title} · ${d}</span><br>
                            <span class="text-xs text-[#D32F2F] font-semibold">Score : ${parseFloat(a.score).toFixed(1)} % — Non validé (seuil 80 %)</span></div>
                            <div class="flex gap-2">
                                <a href="${a.report_url}" target="_blank" class="px-4 py-2 bg-[#111111] text-white text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] rounded-sm">Voir / Télécharger</a>
                            </div></div>`;
                    });
                    html += '</div>';
                }
                container.innerHTML = html;
            });
        }

        /**
         * Fetches and refreshes global KPIs displayed on the student dashboard main panel.
         * @return {void}
         */
        function refreshDashboard() {
            fetch('/student/get-stats.php').then(r => r.json()).then(data => {
                if (!data.success) return;
                document.getElementById('kpi-completed').textContent = data.completed_courses;
                document.getElementById('kpi-score').textContent = data.avg_score + '%';
                document.getElementById('kpi-time').textContent = data.study_time.hours + 'h' + String(data.study_time.minutes).padStart(2,'0');
                document.getElementById('kpi-certs').textContent = data.certificates;
            });
        }

        /**
         * Initiates enrollment action for a course, checking if access key is needed.
         * @param {number} courseId - The unique course database ID.
         * @param {boolean} needsKey - Indication if course is locked by key.
         * @return {void}
         */
        function attemptEnroll(courseId, needsKey) {
            if (needsKey) {
                document.getElementById('enroll-course-id').value = courseId;
                document.getElementById('enroll-key-input').value = '';
                document.getElementById('enroll-error').classList.add('hidden');
                toggleModal('enroll-modal');
            } else {
                submitEnrollment(courseId, null);
            }
        }

        document.getElementById('enroll-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const courseId = parseInt(document.getElementById('enroll-course-id').value);
            const key = document.getElementById('enroll-key-input').value.trim();
            submitEnrollment(courseId, key);
        });

        /**
         * Dispatches secure AJAX request to enroll in target course.
         * @param {number} courseId - Target course unique key.
         * @param {string|null} key - Password enrollment key.
         * @return {void}
         */
        function submitEnrollment(courseId, key) {
            const formData = new FormData();
            formData.append('course_id', courseId);
            formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
            if (key) formData.append('enrollment_key', key);

            fetch('/student/enroll.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Toast.success('Inscription réussie !');
                    toggleModal('enroll-modal');
                    refreshDashboard();
                    setTimeout(() => location.reload(), 800);
                } else {
                    if (key) {
                        const errorDiv = document.getElementById('enroll-error');
                        errorDiv.textContent = data.message || 'Clé incorrecte.';
                        errorDiv.classList.remove('hidden');
                    } else {
                        Toast.error(data.message || 'Erreur lors de l\'inscription.');
                    }
                }
            })
            .catch(err => Toast.error('Erreur réseau: ' + err.message));
        }

        /**
         * Asynchronously updates student's public profile name.
         * @return {void}
         */
        function updateProfileName() {
            const newName = document.getElementById('profile-name').value.trim();
            const statusLabel = document.getElementById('profile-status');
            
            if (newName === '') return;

            const formData = new FormData();
            formData.append('name', newName);

            fetch('/student/update-profile.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    statusLabel.classList.remove('hidden');
                    
                    // Update layout values
                    document.querySelectorAll('.id-student-name').forEach(el => {
                        el.textContent = newName;
                    });
                    
                    setTimeout(() => statusLabel.classList.add('hidden'), 3000);
                } else {
                    Toast.error('Erreur: ' + data.message);
                }
            })
            .catch(err => Toast.error('Erreur réseau: ' + err.message));
        }

        /**
         * Submits selected avatar image file to profile upload controller.
         * @return {void}
         */
        function uploadAvatar() {
            const avatarInput = document.getElementById('avatar-input');
            const file = avatarInput.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('avatar', file);

            fetch('/student/update-profile.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update preview images
                    const newPath = '/download.php?type=avatar&file=' + encodeURIComponent(data.avatar_path);
                    document.getElementById('profile-avatar-preview').src = newPath;
                    document.getElementById('header-avatar').src = newPath;
                    Toast.success('Avatar mis à jour.');
                } else {
                    Toast.error('Erreur: ' + data.message);
                }
            })
            .catch(err => Toast.error('Erreur réseau: ' + err.message));
        }

        // =========================================================================
        // SECTION 5: STUDY MODAL, LESSON CONSOLE & AI COMPANION CONTROLLERS
        // =========================================================================
        let currentLessonId = 0;
        let studyCourseIdGlobal = 0;

        /**
         * Loads and presents the study course modal layout with chapter hierarchy.
         * @param {number} courseId - The course identifier to read.
         * @return {void}
         */
        function studyCourse(courseId) {
            studyCourseIdGlobal = courseId;
            fetch(`/student/get-course-details.php?course_id=${courseId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('study-course-module').textContent = data.course.module_title;
                    document.getElementById('study-course-title').textContent = data.course.title;
                    
                    // Render Chapters and Lessons
                    const container = document.getElementById('study-chapters-container');
                    container.innerHTML = '';
                    
                    data.chapters.forEach(ch => {
                        const chapterDiv = document.createElement('div');
                        chapterDiv.className = 'space-y-2';
                        
                        const titleEl = document.createElement('h4');
                        titleEl.className = 'text-xs font-semibold uppercase tracking-wider text-[#555555]';
                        titleEl.textContent = ch.title;
                        chapterDiv.appendChild(titleEl);
                        
                        const lessonsList = document.createElement('div');
                        lessonsList.className = 'space-y-1.5 pl-2 border-l border-[#E5E5E7]';
                        
                        ch.lessons.forEach(les => {
                            let isExpired = false;
                            if (les.quiz_deadline && parseInt(les.completed) === 0) {
                                if (new Date() > new Date(les.quiz_deadline)) {
                                    isExpired = true;
                                }
                            }

                            const lessonBtn = document.createElement('button');
                            if (isExpired) {
                                lessonBtn.className = `w-full text-left text-xs py-1 px-2 text-[#888888] cursor-not-allowed flex justify-between items-center opacity-60`;
                                lessonBtn.onclick = () => {
                                    Toast.error("Le délai d'accès à cette leçon/quiz a expiré.");
                                };
                            } else {
                                lessonBtn.className = `w-full text-left text-xs py-1 px-2 transition-all hover:bg-[#E5E5E7] rounded-sm flex justify-between items-center ${parseInt(les.completed) === 1 ? 'text-[#004B23] font-medium' : 'text-[#555555]'}`;
                                lessonBtn.onclick = () => loadLesson(les.id);
                            }
                            
                            const titleSpan = document.createElement('span');
                            titleSpan.textContent = les.title + (isExpired ? ' (Expiré) 🔒' : '');
                            lessonBtn.appendChild(titleSpan);
                            
                            if (parseInt(les.completed) === 1) {
                                const checkSpan = document.createElement('span');
                                checkSpan.textContent = '✓';
                                checkSpan.className = 'font-bold';
                                lessonBtn.appendChild(checkSpan);
                            }
                            
                            lessonsList.appendChild(lessonBtn);
                        });
                        
                        chapterDiv.appendChild(lessonsList);
                        container.appendChild(chapterDiv);
                    });
                    
                    // Open study modal
                    document.getElementById('study-modal').classList.remove('hidden');
                    
                    // Auto-load first non-expired lesson if available
                    let firstNonExpiredLesson = null;
                    for (const ch of data.chapters) {
                        for (const les of ch.lessons) {
                            let isExpired = false;
                            if (les.quiz_deadline && parseInt(les.completed) === 0) {
                                if (new Date() > new Date(les.quiz_deadline)) {
                                    isExpired = true;
                                }
                            }
                            if (!isExpired) {
                                firstNonExpiredLesson = les;
                                break;
                            }
                        }
                        if (firstNonExpiredLesson) break;
                    }

                    if (firstNonExpiredLesson) {
                        loadLesson(firstNonExpiredLesson.id);
                    } else if (data.chapters.length > 0 && data.chapters[0].lessons.length > 0) {
                        document.getElementById('lesson-viewer-header').classList.add('hidden');
                        document.getElementById('study-media-container').innerHTML = '<p class="text-sm italic text-[#888888]">Toutes les leçons de ce cours ont expiré.</p>';
                    } else {
                        document.getElementById('lesson-viewer-header').classList.add('hidden');
                        document.getElementById('study-media-container').innerHTML = '<p class="text-sm italic text-[#888888]">Aucune leçon disponible.</p>';
                    }
                } else {
                    Toast.error('Erreur: ' + data.message);
                }
            })
            .catch(err => Toast.error('Erreur réseau: ' + err.message));
        }

        /**
         * Helper returning local current time formatted as HH:MM.
         * @return {string} Formatted time string.
         */
        function getCurrentTime() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            return `${hours}:${minutes}`;
        }

        /**
         * Toggles the study companion's AI chat drawer panel.
         * @return {void}
         */
        function toggleAiDrawer() {
            const drawer = document.getElementById('ai-chat-drawer');
            if (drawer.classList.contains('hidden')) {
                drawer.classList.remove('hidden');
                // Scroll to bottom
                const msgs = document.getElementById('ai-chat-messages');
                msgs.scrollTop = msgs.scrollHeight;
            } else {
                drawer.classList.add('hidden');
            }
        }

        /**
         * Dispatches pre-defined actions to the AI Student Assistant (e.g., summarize, explain, generate quiz).
         * @param {string} action - Action key ('summarize' | 'explain' | 'generate_quiz').
         * @return {void}
         */
        function triggerAiAction(action) {
            if (!currentLessonId) {
                Toast.error("Veuillez d'abord charger une lecon.");
                return;
            }
            
            const msgs = document.getElementById('ai-chat-messages');
            
            // Message de chargement temporaire
            const loaderId = 'ai-loader-' + Date.now();
            let actionLabel = "Assistant prépare le résumé";
            if (action === 'explain') actionLabel = "Assistant simplifie les concepts";
            if (action === 'generate_quiz') actionLabel = "Assistant prépare le quiz";

            const loaderHTML = `<div class="px-4 py-3 bg-[#FFFFFF] text-[#555555] text-sm rounded-[16px_16px_16px_4px] max-w-[85%] shadow-sm border border-[#E5E5E7] flex items-center gap-2">`
                             + `<span class="text-xs italic">${actionLabel}</span>`
                             + `<div class="flex gap-1 items-center justify-center">`
                             + `<span class="wa-dot"></span>`
                             + `<span class="wa-dot"></span>`
                             + `<span class="wa-dot"></span>`
                             + `</div>`
                             + `</div>`;
            
            const loaderDiv = document.createElement('div');
            loaderDiv.id = loaderId;
            loaderDiv.className = 'flex justify-start w-full my-2';
            loaderDiv.innerHTML = loaderHTML;
            msgs.appendChild(loaderDiv);
            msgs.scrollTop = msgs.scrollHeight;

            fetch('/api/ai-student.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lesson_id: currentLessonId, action: action })
            })
            .then(res => res.json())
            .then(data => {
                const loader = document.getElementById(loaderId);
                if (loader) loader.remove();

                if (data.success) {
                    if (action === 'generate_quiz' && data.quiz) {
                        renderAiQuiz(data.quiz);
                    } else if (data.response) {
                        appendAiMessage('Assistant', data.response, false);
                    }
                } else {
                    appendAiMessage('Systeme', 'Une erreur est survenue : ' + data.error, true);
                }
            })
            .catch(err => {
                const loader = document.getElementById(loaderId);
                if (loader) loader.remove();
                appendAiMessage('Systeme', 'Erreur reseau : ' + err.message, true);
            });
        }

        /**
         * Dynamically builds and inserts an interactive AI-generated MCQ inside the chat flow.
         * @param {Array<Object>} questions - List of questions generated by AI Client.
         * @return {void}
         */
        function renderAiQuiz(questions) {
            const msgs = document.getElementById('ai-chat-messages');
            const container = document.createElement('div');
            container.className = 'p-4 bg-white border border-[#E5E5E7] rounded-[16px] space-y-4 my-2 shadow-sm max-w-[90%] mr-auto';
            
            const title = document.createElement('h5');
            title.className = 'font-serif text-sm font-semibold text-[#111111]';
            title.textContent = 'Auto-évaluation rapide';
            container.appendChild(title);

            questions.forEach((q, idx) => {
                const qBox = document.createElement('div');
                qBox.className = 'space-y-2';
                
                const qText = document.createElement('p');
                qText.className = 'text-xs font-medium text-[#111111]';
                qText.textContent = `${idx + 1}. ${q.question}`;
                qBox.appendChild(qText);

                const optsContainer = document.createElement('div');
                optsContainer.className = 'grid grid-cols-1 gap-1.5';

                Object.entries(q.options).forEach(([key, val]) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'w-full text-left px-3 py-2 border border-[#E5E5E7] hover:border-[#004B23] text-xs bg-[#F9F9FB] rounded-xl transition-all';
                    btn.textContent = `${key}. ${val}`;
                    
                    btn.onclick = function() {
                        // Desactiver les boutons de cette question
                        [...optsContainer.children].forEach(b => b.disabled = true);
                        if (key === q.correct) {
                            btn.className = 'w-full text-left px-3 py-2 border border-[#34C759] text-xs bg-[#E8F5E9] text-[#2E7D32] rounded-xl font-semibold';
                            btn.textContent += ' (Correct)';
                        } else {
                            btn.className = 'w-full text-left px-3 py-2 border border-[#FF3B30] text-xs bg-[#FFEBEE] text-[#C62828] rounded-xl';
                            btn.textContent += ' (Incorrect)';
                            // Mettre le correct en vert
                            const correctBtn = [...optsContainer.children].find(b => b.textContent.startsWith(q.correct + '.'));
                            if (correctBtn) {
                                correctBtn.className = 'w-full text-left px-3 py-2 border border-[#34C759] text-xs bg-[#E8F5E9] text-[#2E7D32] rounded-xl font-semibold';
                            }
                        }
                    };
                    optsContainer.appendChild(btn);
                });

                qBox.appendChild(optsContainer);
                container.appendChild(qBox);
            });

            msgs.appendChild(container);
            msgs.scrollTop = msgs.scrollHeight;
        }

        /**
         * Appends a chat bubble inside the student assistant drawer.
         * @param {string} sender - Bubble owner label ('Moi' | 'Assistant' | 'Systeme').
         * @param {string} text - Message content text.
         * @param {boolean} [isSystem=false] - If true, style as alert warning.
         * @return {void}
         */
        function appendAiMessage(sender, text, isSystem = false) {
            const msgs = document.getElementById('ai-chat-messages');
            const div = document.createElement('div');
            
            if (sender === 'Moi') {
                div.className = 'flex justify-end w-full my-2';
                div.innerHTML = `<div class="px-4 py-2 bg-[#DCF8C6] text-[#000000] text-sm rounded-[16px_16px_4px_16px] max-w-[80%] shadow-sm relative break-words">`
                              + `<div class="whitespace-pre-line text-xs font-light text-[#111111] text-left">${escapeHTML(text)}</div>`
                              + `<span class="block text-[9px] text-[#666666] text-right mt-1 font-mono">${getCurrentTime()}</span>`
                              + `</div>`;
            } else if (isSystem) {
                div.className = 'flex justify-center w-full my-2';
                div.innerHTML = `<div class="px-4 py-1.5 bg-[#FFEFEF] border border-[#FFD2D2] text-[#C62828] text-xs rounded-lg max-w-[90%] text-center">`
                              + `<div class="font-medium">${escapeHTML(text)}</div>`
                              + `</div>`;
            } else {
                div.className = 'flex justify-start w-full my-2';
                div.innerHTML = `<div class="px-4 py-2 bg-[#FFFFFF] text-[#000000] text-sm rounded-[16px_16px_16px_4px] max-w-[85%] shadow-sm border border-[#E5E5E7] relative break-words">`
                              + `<div class="whitespace-pre-line text-xs font-light text-[#111111] text-left">${escapeHTML(text)}</div>`
                              + `<span class="block text-[9px] text-[#888888] text-right mt-1 font-mono">${getCurrentTime()}</span>`
                              + `</div>`;
            }
            
            msgs.appendChild(div);
            msgs.scrollTop = msgs.scrollHeight;
        }

        /**
         * Escapes special characters to prevent HTML injections inside the chat drawer.
         * @param {string} str - Unescaped raw string.
         * @return {string} Secure HTML escaped string.
         */
        function escapeHTML(str) {
            return str.replace(/[&<>'"]/g, 
                tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
            );
        }

        /**
         * Dispatches custom student free-text queries to the API assistant.
         * @param {Event} [e] - Form submission event context.
         * @return {void}
         */
        function sendAiMessage(e) {
            if (e) e.preventDefault();
            
            const input = document.getElementById('ai-chat-input');
            const text = input.value.trim();
            if (!text) return;

            if (!currentLessonId) {
                Toast.error("Veuillez charger une lecon d'abord.");
                return;
            }

            appendAiMessage('Moi', text);
            input.value = '';

            const msgs = document.getElementById('ai-chat-messages');
            const loaderId = 'ai-loader-' + Date.now();
            const loaderHTML = `<div class="px-4 py-3 bg-[#FFFFFF] text-[#555555] text-sm rounded-[16px_16px_16px_4px] max-w-[85%] shadow-sm border border-[#E5E5E7] flex items-center gap-2">`
                             + `<span class="text-xs italic">Assistant est en train d'écrire</span>`
                             + `<div class="flex gap-1 items-center justify-center">`
                             + `<span class="wa-dot"></span>`
                             + `<span class="wa-dot"></span>`
                             + `<span class="wa-dot"></span>`
                             + `</div>`
                             + `</div>`;
            
            const loaderDiv = document.createElement('div');
            loaderDiv.id = loaderId;
            loaderDiv.className = 'flex justify-start w-full my-2';
            loaderDiv.innerHTML = loaderHTML;
            msgs.appendChild(loaderDiv);
            msgs.scrollTop = msgs.scrollHeight;

            fetch('/api/ai-student.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lesson_id: currentLessonId, action: 'chat', message: text })
            })
            .then(res => res.json())
            .then(data => {
                const loader = document.getElementById(loaderId);
                if (loader) loader.remove();

                if (data.success) {
                    appendAiMessage('Assistant', data.response, false);
                } else {
                    appendAiMessage('Systeme', data.error, true);
                }
            })
            .catch(err => {
                const loader = document.getElementById(loaderId);
                if (loader) loader.remove();
                appendAiMessage('Systeme', 'Erreur reseau : ' + err.message, true);
            });
        }

        /**
         * Closes the study modal, terminates study timers, and triggers layout KPI updates.
         * @return {void}
         */
        function closeStudyModal() {
            SessionTimer.stop();
            LessonContentGate.reset();
            const media = document.getElementById('study-media-container');
            [...media.children].forEach(el => { if (el._pdfCleanup) el._pdfCleanup(); });
            const modal = document.getElementById('study-modal');
            if (modal) {
                modal.classList.remove('sv-pdf-focus-mode');
                modal.classList.add('hidden');
            }
            refreshDashboard();
        }

        let pendingLessonQuiz = null;
        let lessonContentConsumed = false;
        let currentLessonContentType = '';

        /**
         * Submits AJAX request to flag a lesson's learning materials as viewed/consumed.
         * @param {number} lessonId - Target lesson database key.
         * @return {Promise<Object>} Promise resolving to response confirmation.
         */
        function markContentConsumedOnServer(lessonId) {
            const fd = new FormData();
            fd.append('lesson_id', lessonId);
            return fetch('/student/mark-content-consumed.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .catch(() => ({ success: false }));
        }

        /**
         * Inspects lesson schemas to compute visual requirements for complete content consumption.
         * @param {Object} lesson - Lesson database record object.
         * @param {Array<Object>|null} videos - Companion videos associated with the lesson.
         * @return {Array<string>} List of localized requirements ('texte', 'PDF', 'vidéo').
         */
        function getContentRequirements(lesson, videos) {
            const reqs = [];
            if ((lesson.content_type === 'text' || lesson.content_type === 'mixed') && lesson.text_content) reqs.push('texte');
            if ((lesson.content_type === 'pdf' || lesson.content_type === 'mixed') && lesson.pdf_path) reqs.push('PDF');
            const hasVideo = (lesson.content_type === 'video' || lesson.content_type === 'mixed') && (lesson.video_url || (videos && videos.length > 0));
            if (hasVideo) reqs.push('vidéo');
            return reqs;
        }

        /**
         * Updates content progression hints text above the lesson assessments box.
         * @param {Object} lesson - Current lesson data object.
         * @param {Array<Object>|null} videos - Lesson videos.
         * @param {boolean} consumed - If learning contents have been successfully completed.
         * @return {void}
         */
        function updateContentProgressHint(lesson, videos, consumed) {
            const el = document.getElementById('lesson-content-progress');
            if (!el) return;
            const reqs = getContentRequirements(lesson, videos);
            if (!reqs.length) { el.textContent = ''; return; }
            el.textContent = consumed
                ? 'Contenu terminé — évaluation disponible'
                : 'À terminer : ' + reqs.join(', ');
        }

        /**
         * Triggers unlocking state of lesson quizzes/assessments once content is completed.
         * @param {number} lessonId - Unique lesson database key.
         * @param {Object} lesson - Lesson metadata structure.
         * @param {boolean} autoLaunchQuiz - If true, immediately triggers the quiz interface.
         * @param {Array<Object>|null} videos - Videos list.
         * @return {void}
         */
        function unlockLessonEvaluations(lessonId, lesson, autoLaunchQuiz, videos) {
            lessonContentConsumed = true;
            updateContentProgressHint(lesson, videos, true);
            document.getElementById('lesson-quiz-locked').classList.add('hidden');

            const isCompleted = document.getElementById('lesson-complete-status').classList.contains('hidden') === false;

            if (autoLaunchQuiz) {
                // Pour une leçon vidéo terminée
                if (pendingLessonQuiz && pendingLessonQuiz.has_quiz && pendingLessonQuiz.questions.length > 0) {
                    const quizBox = document.getElementById('lesson-quiz-container');
                    quizBox.classList.remove('hidden');
                    renderLessonQuestion(pendingLessonQuiz.questions[0], pendingLessonQuiz.questions.length);
                    Toast.info('Vidéo terminée — lancement de l\'évaluation.');
                    quizBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    // On s'assure que la barre de complétion est masquée pendant le quiz
                    updateLessonCompleteBar(isCompleted, true, true);
                } else {
                    // Si pas de quiz, on valide directement la leçon
                    markLessonComplete(lessonId);
                }
            } else {
                // Pour une leçon texte/PDF terminée
                updateLessonCompleteBar(isCompleted, true, !!(pendingLessonQuiz && pendingLessonQuiz.has_quiz));
            }
        }

        /**
         * Renders the locked blocker overlay for lesson evaluations.
         * @param {Object} lesson - Target lesson context.
         * @param {Array<Object>|null} videos - Companion videos array.
         * @return {void}
         */
        function showLockedLessonEvaluations(lesson, videos) {
            lessonContentConsumed = false;
            document.getElementById('lesson-quiz-container').classList.add('hidden');
            const locked = document.getElementById('lesson-quiz-locked');
            const reqs = getContentRequirements(lesson, videos);
            if (reqs.length > 0 && pendingLessonQuiz && pendingLessonQuiz.has_quiz) {
                locked.classList.remove('hidden');
                updateContentProgressHint(lesson, videos, false);
            } else {
                locked.classList.add('hidden');
            }
        }

        /**
         * Loads all lesson details, sets up integrated PDF/video players, starts
         * session timer, and initializes content gates.
         * @param {number} lessonId - Target lesson database key.
         * @return {void}
         */
        function loadLesson(lessonId) {
            currentLessonId = lessonId;
            currentLessonContentType = '';
            LessonContentGate.reset();
            pendingLessonQuiz = null;
            lessonContentConsumed = false;

            // Reinitialiser le chat IA de l'etudiant a chaque changement de lecon
            const chatMsgs = document.getElementById('ai-chat-messages');
            if (chatMsgs) {
                chatMsgs.innerHTML = `<div class="flex justify-start w-full my-2">`
                                   + `<div class="px-4 py-2 bg-[#FFFFFF] text-[#000000] text-sm rounded-[16px_16px_16px_4px] max-w-[85%] shadow-sm border border-[#E5E5E7] relative break-words">`
                                   + `Bonjour. Je suis votre assistant StudyVibe. Comment puis-je vous aider a comprendre cette lecon aujourd'hui ?`
                                   + `</div>`
                                   + `</div>`;
            }
            // Masquer le tiroir par defaut pour ne pas encombrer
            const drawer = document.getElementById('ai-chat-drawer');
            if (drawer) {
                drawer.classList.add('hidden');
            }

            fetch(`/student/get-lesson-details.php?lesson_id=${lessonId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const l = data.lesson;
                    const alreadyUnlocked = data.content_consumed || data.completed || data.quiz_complete;
                    lessonContentConsumed = alreadyUnlocked;

                    currentLessonContentType = l.content_type || '';
                    document.getElementById('lesson-viewer-header').classList.remove('hidden');
                    document.getElementById('study-lesson-title').textContent = l.title;
                    document.getElementById('study-lesson-badge').textContent = l.content_type.toUpperCase();
                    
                    const mediaContainer = document.getElementById('study-media-container');
                    [...mediaContainer.children].forEach(el => { if (el._pdfCleanup) el._pdfCleanup(); });
                    mediaContainer.innerHTML = '';

                    let textEl = null;
                    let videoContainer = null;
                    
                    // 1. Text Content
                    if ((l.content_type === 'text' || l.content_type === 'mixed') && l.text_content) {
                        const p = document.createElement('div');
                        p.className = 'text-base font-light leading-relaxed text-[#111111] max-w-3xl whitespace-pre-line sv-lesson-text';
                        p.textContent = l.text_content;
                        mediaContainer.appendChild(p);
                        textEl = p;
                    }
                    
                    // 2. PDF — liseuse intégrée
                    if ((l.content_type === 'pdf' || l.content_type === 'mixed') && l.pdf_path) {
                        const pdfBox = document.createElement('div');
                        pdfBox.className = 'w-full max-w-4xl mx-auto';
                        mediaContainer.appendChild(pdfBox);
                        const onPdfComplete = () => {
                            LessonContentGate.markDone('pdf');
                        };
                        PdfViewer.render(pdfBox, '/download.php?type=pdf&file=' + encodeURIComponent(l.pdf_path), {
                            title: l.title || 'Document PDF',
                            onComplete: onPdfComplete,
                        });
                    }
                    
                    // Réunir toutes les vidéos associées à la leçon
                    const videos = [];
                    const seenUrls = new Set();
                    if (l.video_url) {
                        const cleanUrl = l.video_url.trim();
                        if (cleanUrl) {
                            videos.push({ url: cleanUrl, label: 'Vidéo principale' });
                            seenUrls.add(cleanUrl);
                        }
                    }
                    if (data.videos && Array.isArray(data.videos)) {
                        data.videos.forEach((v, index) => {
                            if (v.url) {
                                const cleanUrl = v.url.trim();
                                if (cleanUrl && !seenUrls.has(cleanUrl)) {
                                    videos.push({ url: cleanUrl, label: v.label || ('Vidéo ' + (index + 1)) });
                                    seenUrls.add(cleanUrl);
                                }
                            }
                        });
                    }

                    // 3. Video (YouTube ou Générique — multi-vidéo)
                    const needsVideo = (l.content_type === 'video' || l.content_type === 'mixed') && videos.length > 0;
                    if (needsVideo) {
                        videoContainer = document.createElement('div');
                        videoContainer.className = 'w-full max-w-3xl flex flex-col gap-8';
                        
                        if (alreadyUnlocked) {
                            videos.forEach(v => {
                                const videoWrapper = document.createElement('div');
                                videoWrapper.className = 'w-full space-y-2';

                                const labelEl = document.createElement('div');
                                labelEl.className = 'text-xs font-semibold uppercase tracking-wider text-[#555555]';
                                labelEl.textContent = v.label;
                                videoWrapper.appendChild(labelEl);

                                const videoId = v.url.match(/(?:v=|youtu\.be\/)([^&?/]+)/)?.[1];
                                if (videoId) {
                                    videoWrapper.insertAdjacentHTML('beforeend', '<div class="aspect-video w-full"><iframe class="w-full h-[400px]" src="https://www.youtube.com/embed/' + videoId + '" frameborder="0" allowfullscreen></iframe></div>');
                                } else {
                                    videoWrapper.insertAdjacentHTML('beforeend', '<a href="' + v.url + '" target="_blank" class="text-[#004B23] underline text-sm">' + v.url + '</a>');
                                }
                                videoContainer.appendChild(videoWrapper);
                            });
                        }
                        
                        mediaContainer.appendChild(videoContainer);
                    }

                    // 4. Quiz de leçon — conditionné par la consommation du contenu
                    const quizBox      = document.getElementById('lesson-quiz-container');
                    const quizActive   = document.getElementById('lesson-quiz-active');
                    const quizComplete = document.getElementById('lesson-quiz-complete-msg');
                    const quizHint     = document.getElementById('lesson-quiz-hint');
                    const feedback     = document.getElementById('lesson-quiz-feedback');
                    const quizForm     = document.getElementById('lesson-quiz-form');

                    feedback.textContent = '';
                    quizForm.reset();
                    quizComplete.classList.add('hidden');
                    quizActive.classList.remove('hidden');
                    quizHint.classList.remove('hidden');
                    quizBox.classList.add('hidden');
                    document.getElementById('lesson-quiz-locked').classList.add('hidden');

                    document.getElementById('quiz-lesson-id').value = lessonId;

                    pendingLessonQuiz = {
                        has_quiz: data.has_quiz,
                        questions: data.questions || [],
                    };

                    if (alreadyUnlocked) {
                        if (data.has_quiz && data.questions.length > 0) {
                            quizBox.classList.remove('hidden');
                            renderLessonQuestion(data.questions[0], data.questions.length);
                        }
                    } else if (data.has_quiz) {
                        showLockedLessonEvaluations(l, videos);
                    }

                    const scrollRoot = document.querySelector('#study-modal .flex-grow.overflow-y-auto');

                    const onContentComplete = (lastType) => {
                        const autoLaunchQuiz = lastType === 'video';
                        markContentConsumedOnServer(lessonId).then(() => {
                            unlockLessonEvaluations(lessonId, l, autoLaunchQuiz, videos);
                        });
                    };

                    if (!alreadyUnlocked) {
                        LessonContentGate.init({
                            lesson: l,
                            videos: videos,
                            mediaContainer,
                            textEl,
                            videoContainer,
                            scrollRoot,
                            onComplete: onContentComplete,
                        });
                    }

                    SessionTimer.start(lessonId, 'lesson-session-timer');
                    if (l.course_id) {
                        const fd = new FormData();
                        fd.append('lesson_id', lessonId);
                        fd.append('course_id', l.course_id);
                        svPost('/student/mark-lesson-visited.php', fd).catch(() => {});
                    }
                    updateLessonCompleteBar(data.completed || data.quiz_complete, alreadyUnlocked, data.has_quiz);
                    loadLessonComments(lessonId);
                } else {
                    Toast.error('Erreur: ' + data.message);
                }
            })
            .catch(err => Toast.error('Erreur réseau: ' + err.message));
        }

        /**
         * Asynchronously loads collaborative comments and Q&A questions/answers for a specific lesson.
         * @param {number} lessonId - Unique lesson database key.
         * @return {void}
         */
        function loadLessonComments(lessonId) {
            const qaBox = document.getElementById('lesson-qa-container');
            const list  = document.getElementById('lesson-comments-list');
            document.getElementById('comment-lesson-id').value = lessonId;
            qaBox.classList.remove('hidden');

            fetch(`/student/get-comments.php?lesson_id=${lessonId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                list.innerHTML = data.comments.length ? '' : '<p class="text-xs text-[#888] italic">Aucune question pour le moment.</p>';
                data.comments.forEach(c => {
                    const div = document.createElement('div');
                    div.className = 'text-xs border-b border-[#E5E5E7] pb-3';
                    let html = `<strong class="text-[#004B23]">${c.author_name}</strong> <span class="text-[#888]">(${c.author_role})</span><p class="mt-1 text-[#555]">${c.comment_text}</p>`;
                    if (c.teacher_reply) {
                        html += `<p class="mt-2 pl-3 border-l-2 border-[#004B23] text-[#333]"><strong>Réponse enseignant :</strong> ${c.teacher_reply}</p>`;
                    }
                    div.innerHTML = html;
                    list.appendChild(div);
                });
            });
        }


        document.getElementById('lesson-comment-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData();
            fd.append('lesson_id', document.getElementById('comment-lesson-id').value);
            fd.append('comment_text', document.getElementById('comment-input').value.trim());
            fetch('/student/post-comment.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('comment-input').value = '';
                    loadLessonComments(document.getElementById('comment-lesson-id').value);
                    Toast.success('Question publiée.');
                } else Toast.error(data.message);
            });
        });

        /**
         * Renders a single lesson evaluation MCQ inside the liseuse sidebar.
         * @param {Object} q - Question structure.
         * @param {number} remainingTotal - Remaining questions in queue.
         * @return {void}
         */
        function renderLessonQuestion(q, remainingTotal) {
            const questionBox = document.getElementById('lesson-quiz-question-box');
            const hint = document.getElementById('lesson-quiz-hint');
            document.getElementById('quiz-question-id').value = q.id;
            questionBox.innerHTML = '';

            hint.textContent = remainingTotal > 1
                ? `${remainingTotal} question(s) restante(s)`
                : 'Dernière question — validez pour terminer la leçon.';

            const qDiv = document.createElement('div');
            qDiv.id = 'current-quiz-question';
            qDiv.className = 'space-y-3';

            const qText = document.createElement('p');
            qText.className = 'text-sm font-medium text-[#111111]';
            qText.textContent = q.question_text;
            qDiv.appendChild(qText);

            ['A', 'B', 'C', 'D'].forEach(opt => {
                const val = q[`option_${opt.toLowerCase()}`];
                const label = document.createElement('label');
                label.className = 'sv-quiz-option flex items-center gap-3 p-3 border border-[#E5E5E7] hover:bg-[#FFFFFF] cursor-pointer rounded-sm text-xs font-light';
                label.dataset.option = opt;

                const input = document.createElement('input');
                input.type = 'radio';
                input.name = 'answer';
                input.value = opt;
                input.required = true;
                input.className = 'text-[#004B23] focus:ring-[#004B23]';

                label.appendChild(input);
                label.appendChild(document.createTextNode(`${opt}. ${val}`));
                qDiv.appendChild(label);
            });

            questionBox.appendChild(qDiv);
            document.getElementById('lesson-quiz-submit-btn').disabled = false;
        }

        /**
         * Closes and hides the lesson evaluation card with a smooth fade interface.
         * @return {void}
         */
        function hideLessonQuizComplete() {
            document.getElementById('lesson-quiz-active').classList.add('hidden');
            document.getElementById('lesson-quiz-hint').classList.add('hidden');
            document.getElementById('lesson-quiz-complete-msg').classList.remove('hidden');
            setTimeout(() => document.getElementById('lesson-quiz-container').classList.add('hidden'), 1800);
        }

        /**
         * Highlights MCQ options based on user answer validity.
         * @param {string} selectedOpt - Student selected option ('A'|'B'|'C'|'D').
         * @param {string} correctOpt - Absolute correct option key.
         * @return {void}
         */
        function highlightQuizAnswer(selectedOpt, correctOpt) {
            document.querySelectorAll('.sv-quiz-option').forEach(label => {
                const opt = label.dataset.option;
                label.style.pointerEvents = 'none';
                if (opt === correctOpt) label.classList.add('sv-quiz-option-correct');
                if (opt === selectedOpt && opt !== correctOpt) label.classList.add('sv-quiz-option-wrong');
            });
        }

        /**
         * Refreshes completion action bars/hints for lesson read status.
         * @param {boolean} isCompleted - If lesson is marked complete.
         * @param {boolean} contentConsumed - If media requirements are satisfied.
         * @param {boolean} hasQuiz - If lesson requires passing an MCQ.
         * @return {void}
         */
        function updateLessonCompleteBar(isCompleted, contentConsumed, hasQuiz) {
            const bar    = document.getElementById('lesson-complete-bar');
            const btn    = document.getElementById('mark-lesson-complete-btn');
            const status = document.getElementById('lesson-complete-status');
            const hint   = document.getElementById('lesson-complete-hint');
            if (!bar) return;

            if (hasQuiz && !isCompleted) {
                bar.classList.add('hidden');
                return;
            }

            bar.classList.remove('hidden');
            if (isCompleted) {
                btn.classList.add('hidden');
                status.classList.remove('hidden');
                if (hint) hint.classList.add('hidden');
            } else {
                btn.classList.remove('hidden');
                status.classList.add('hidden');
                if (hint) {
                    hint.textContent = contentConsumed
                        ? 'Contenu terminé — vous pouvez marquer la leçon comme terminée.'
                        : 'Terminez la lecture, le PDF ou la vidéo pour débloquer la validation.';
                }
                btn.disabled = !contentConsumed;
                btn.classList.toggle('opacity-50', !contentConsumed);
                btn.classList.toggle('cursor-not-allowed', !contentConsumed);
                btn.textContent = contentConsumed
                    ? 'Marquer la leçon comme terminée'
                    : 'Terminez le contenu pour valider';
            }
        }

        /**
         * Dispatches secure AJAX request to mark a lesson as completed.
         * @param {number} lessonId - Unique lesson database key.
         * @return {void}
         */
        function markLessonComplete(lessonId) {
            const btn = document.getElementById('mark-lesson-complete-btn');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Enregistrement…';
            }

            const formData = new FormData();
            formData.append('lesson_id', lessonId);
            formData.append('mark_complete', 'true');
            formData.append('csrf_token', getCsrfToken());

            fetch('/student/submit-lesson-quiz.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.lesson_complete) {
                    Toast.success('Leçon marquée comme terminée !');
                    updateLessonCompleteBar(true, true, false);
                    document.getElementById('lesson-quiz-container').classList.add('hidden');
                    studyCourse(studyCourseIdGlobal);
                } else {
                    Toast.error(data.message || 'Impossible de valider la leçon.');
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Marquer la leçon comme terminée';
                    }
                }
            })
            .catch(err => {
                Toast.error('Erreur réseau: ' + err.message);
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Marquer la leçon comme terminée';
                }
            });
        }

        document.getElementById('mark-lesson-complete-btn').addEventListener('click', () => {
            if (!currentLessonId || !lessonContentConsumed) {
                Toast.error('Terminez d\'abord la lecture ou la vidéo.');
                return;
            }

            // Pour les leçons texte/PDF avec un quiz : révéler le quiz au lieu de marquer terminée
            if (pendingLessonQuiz && pendingLessonQuiz.has_quiz && pendingLessonQuiz.questions.length > 0
                && currentLessonContentType !== 'video') {
                const quizBox = document.getElementById('lesson-quiz-container');
                quizBox.classList.remove('hidden');
                renderLessonQuestion(pendingLessonQuiz.questions[0], pendingLessonQuiz.questions.length);
                quizBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
                // Masquer la barre de complétion pendant le quiz
                document.getElementById('lesson-complete-bar').classList.add('hidden');
                return;
            }

            markLessonComplete(currentLessonId);
        });

        // Soumission quiz leçon — une question à la fois
        document.getElementById('lesson-quiz-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const lessonId   = document.getElementById('quiz-lesson-id').value;
            const questionId = document.getElementById('quiz-question-id').value;
            const feedback   = document.getElementById('lesson-quiz-feedback');
            const submitBtn  = document.getElementById('lesson-quiz-submit-btn');
            const selected   = document.querySelector('input[name="answer"]:checked');

            if (!selected) {
                feedback.textContent = 'Veuillez sélectionner une réponse.';
                feedback.className = 'text-xs text-[#D32F2F] font-medium';
                return;
            }

            submitBtn.disabled = true;
            feedback.textContent = 'Vérification…';
            feedback.className = 'text-xs text-[#555555] font-medium';

            const formData = new FormData();
            formData.append('lesson_id', lessonId);
            formData.append('question_id', questionId);
            formData.append('answer', selected.value);

            fetch('/student/submit-lesson-quiz.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    Toast.error(data.message || 'Erreur lors de la validation.');
                    submitBtn.disabled = false;
                    return;
                }

                highlightQuizAnswer(selected.value, data.correct_option);

                if (data.correct) {
                    feedback.textContent = `✓ Correct ! La bonne réponse était ${data.correct_option}. ${data.correct_text}`;
                    feedback.className = 'text-xs text-[#004B23] font-medium';
                } else {
                    feedback.textContent = `✕ Incorrect. La bonne réponse était ${data.correct_option}. ${data.correct_text}`;
                    feedback.className = 'text-xs text-[#D32F2F] font-medium';
                }

                // Fade-out animation after 2.6s
                setTimeout(() => {
                    const qEl = document.getElementById('current-quiz-question');
                    if (qEl) qEl.classList.add('sv-quiz-question-exit');
                }, 2600);

                // Auto-advance to next question or complete after 3s
                setTimeout(() => {
                    if (data.lesson_complete) {
                        hideLessonQuizComplete();
                        updateLessonCompleteBar(true, true, false);
                        studyCourse(studyCourseIdGlobal);
                        Toast.success(data.correct ? 'Leçon validée avec succès !' : 'Évaluation terminée.');
                    } else {
                        fetch(`/student/get-lesson-details.php?lesson_id=${lessonId}`)
                        .then(r => r.json())
                        .then(d => {
                            if (d.success && d.questions.length > 0) {
                                renderLessonQuestion(d.questions[0], d.questions.length);
                                feedback.textContent = '';
                            } else {
                                hideLessonQuizComplete();
                                studyCourse(studyCourseIdGlobal);
                            }
                        });
                    }
                }, 3000);
            })
            .catch(err => {
                Toast.error('Erreur réseau: ' + err.message);
                submitBtn.disabled = false;
            });
        });

        // =========================================================================
        // SECTION 6: FINAL CERTIFICATION EXAMS & GLOBAL HUD UTILITIES
        // =========================================================================
        let examExpired = false;

        /**
         * Loads evaluation questions and launches the timed certification exam session.
         * @param {number} courseId - Course unique identifier database key.
         * @param {string} courseTitle - Name of the course for display.
         * @return {void}
         */
        function startFinalExam(courseId, courseTitle) {
            document.getElementById('exam-course-id').value = courseId;
            document.getElementById('exam-course-title').textContent = courseTitle;
            document.getElementById('exam-error-alert').classList.add('hidden');
            examExpired = false;
            ExamTimer.stop();

            fetch(`/student/get-exam-questions.php?course_id=${courseId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('exam-questions-container');
                    container.innerHTML = '';
                    document.getElementById('exam-attempts-info').textContent =
                        `Tentatives restantes : ${data.attempts_left}/3 (24h)`;

                    data.questions.forEach((q, idx) => {
                        const qBox = document.createElement('div');
                        qBox.className = 'py-4 space-y-3';
                        const title = document.createElement('p');
                        title.className = 'text-sm font-semibold text-[#111111]';
                        title.textContent = `${idx + 1}. ${q.question_text}`;
                        qBox.appendChild(title);
                        ['A','B','C','D'].forEach(o => {
                            const label = document.createElement('label');
                            label.className = 'flex items-center gap-3 p-3 border border-[#E5E5E7] hover:bg-[#F5F5F7] cursor-pointer rounded-sm text-xs font-light';
                            const input = document.createElement('input');
                            input.type = 'radio'; input.name = `question_${q.id}`; input.value = o; input.required = true;
                            label.appendChild(input);
                            label.appendChild(document.createTextNode(`${o}. ${q[`option_${o.toLowerCase()}`]}`));
                            qBox.appendChild(label);
                        });
                        container.appendChild(qBox);
                    });

                    document.getElementById('final-exam-modal').classList.remove('hidden');
                    const seconds = data.seconds_left ?? (data.exam_minutes || 90) * 60;
                    ExamTimer.startSeconds('exam-timer', seconds, () => {
                        examExpired = true;
                        Toast.error('Temps écoulé ! Soumission automatique…');
                        document.getElementById('final-exam-form').requestSubmit();
                    });
                } else {
                    Toast.error(data.message || 'Impossible de charger les questions.');
                }
            })
            .catch(err => Toast.error('Erreur réseau: ' + err.message));
        }

        document.getElementById('final-exam-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const errorAlert = document.getElementById('exam-error-alert');
            const courseId = document.getElementById('exam-course-id').value;
            
            errorAlert.classList.add('hidden');
            const formData = new FormData(form);
            
            fetch('/student/submit-final-exam.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                ExamTimer.stop();
                if (data.success) {
                    toggleModal('final-exam-modal');
                    if (data.passed) {
                        CertCelebration.show(data.score, data.certificate_code);
                    } else {
                        Toast.error(`Échec — Score : ${data.score}%. Seuil : 80%.`, 6000);
                        if (data.report_url) {
                            setTimeout(() => {
                                Toast.info('Un relevé de tentative a été généré — consultez l\'onglet Relevé de Notes.', 7000);
                            }, 800);
                        }
                    }
                    refreshDashboard();
                    setTimeout(() => location.reload(), data.passed ? 4000 : 1500);
                } else {
                    Toast.error(data.message || 'Erreur lors de la soumission.');
                }
            })
            .catch(err => Toast.error('Erreur réseau: ' + err.message));
        });

        /**
         * Opens or closes a specific modal overlay view using Tailwind hidden helper.
         * @param {string} modalId - Target HTML element container identifier.
         * @return {void}
         */
        function toggleModal(modalId) {
            document.getElementById(modalId).classList.toggle('hidden');
        }

        /**
         * Resumes a course by loading the outline and auto-loading a specified lesson.
         * @param {number} courseId - Course database ID.
         * @param {number} lessonId - Lesson database ID.
         * @return {void}
         */
        function resumeCourse(courseId, lessonId) {
            studyCourse(courseId);
            setTimeout(() => loadLesson(lessonId), 700);
        }

        /**
         * Converts database timestamp string into localized elapsed time ago display.
         * @param {string} dateString - Source date string formatted as YYYY-MM-DD HH:MM:SS.
         * @return {string} Localized elapsed string.
         */
        function timeAgo(dateString) {
            const now = new Date();
            const date = new Date(dateString.replace(' ', 'T'));
            const seconds = Math.floor((now - date) / 1000);
            if (isNaN(seconds)) return '';
            if (seconds < 60) return "À l'instant";
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return `Il y a ${minutes} min`;
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return `Il y a ${hours} h`;
            const days = Math.floor(hours / 24);
            if (days === 1) return "Hier";
            return `Le ${date.toLocaleDateString('fr-FR')}`;
        }

        /**
         * Invokes AJAX controller to mark all unread student notifications as read.
         * @param {Event} [e] - Click event context.
         * @return {Promise<void>}
         */
        async function markAllNotificationsRead(e) {
            if (e) { e.preventDefault(); e.stopPropagation(); }
            const r = await fetch('/student/mark-all-read.php', { method: 'POST' });
            const d = await r.json();
            if (d.success) {
                loadNotifications();
            }
        }

        /**
         * Loads and populates current student notification list into layouts.
         * @return {void}
         */
        function loadNotifications() {
            fetch('/student/get-notifications.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                
                // Update desktop and mobile count badges
                const badges = [document.getElementById('notif-count'), document.getElementById('mobile-notif-count')];
                badges.forEach(badge => {
                    if (badge) {
                        if (data.unread_count > 0) {
                            badge.textContent = data.unread_count;
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                    }
                });

                const iconMap = {
                    certification: '🏆',
                    quiz: '📝',
                    course_created: '📚',
                    grade: '💯',
                    general: '🔔'
                };

                const panels = [document.getElementById('notif-panel'), document.getElementById('mobile-notif-panel')];
                const contentHtml = data.notifications.length
                    ? data.notifications.map(n => {
                        const icon = iconMap[n.type] || iconMap.general;
                        return `
                            <a href="${n.link || '#'}" class="flex gap-3 p-3 border-b border-[#E5E5E7] dark:border-[#2C2C2C] hover:bg-[#F9F9FB] dark:hover:bg-[#252525] transition-colors items-start ${n.is_read == 0 ? 'bg-[#004B23]/5 dark:bg-[#34C759]/5 font-semibold' : ''}">
                                <div class="text-base flex-shrink-0 mt-0.5">${icon}</div>
                                <div class="flex-grow">
                                    <div class="text-xs text-[#111111] dark:text-white">${n.title}</div>
                                    <div class="text-[11px] text-[#555555] dark:text-[#AAAAAA] font-light mt-0.5">${n.body || ''}</div>
                                    <div class="text-[9px] text-[#888888] dark:text-[#AAAAAA] font-mono mt-1">${timeAgo(n.created_at)}</div>
                                </div>
                                ${n.is_read == 0 ? '<span class="h-2 w-2 rounded-full bg-[#004B23] dark:bg-[#34C759] flex-shrink-0 mt-2"></span>' : ''}
                            </a>
                        `;
                    }).join('')
                    : `
                        <div class="p-8 text-center space-y-2 select-none">
                            <div class="text-2xl opacity-40">🔔</div>
                            <div class="text-xs font-semibold text-[#111111] dark:text-white">Tout est calme ici</div>
                            <div class="text-[11px] text-[#888888] dark:text-[#AAAAAA] font-light">Aucune nouvelle notification pour le moment.</div>
                        </div>
                    `;
                
                panels.forEach(panel => {
                    if (panel) {
                        panel.innerHTML = contentHtml;
                    }
                });
            });
        }

        document.getElementById('notif-btn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            document.getElementById('notif-panel-container').classList.toggle('hidden');
            loadNotifications();
        });

        document.addEventListener('click', (e) => {
            const container = document.getElementById('notif-panel-container');
            if (container && !container.classList.contains('hidden') && !container.contains(e.target) && !e.target.closest('#notif-btn')) {
                container.classList.add('hidden');
            }
            const mobileContainer = document.getElementById('mobile-notif-panel-container');
            if (mobileContainer && !mobileContainer.classList.contains('hidden') && !mobileContainer.contains(e.target) && !e.target.closest('#mobile-notif-wrap button')) {
                mobileContainer.classList.add('hidden');
            }
        });

        loadNotifications();

        // =========================================================================
        // SECTION 7: VIDEO TIMESTAMP NOTES (LocalStorage & UI Chips)
        // =========================================================================
        const VideoNotes = (() => {
            const STORAGE_KEY = 'sv_video_notes';

            function getAll() {
                try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); }
                catch { return {}; }
            }

            function saveAll(data) {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
            }

            function getForLesson(lessonId) {
                return getAll()[lessonId] || [];
            }

            function addNote(lessonId, timestamp, text) {
                const all = getAll();
                if (!all[lessonId]) all[lessonId] = [];
                all[lessonId].push({ ts: timestamp || '—', text: text, id: Date.now() });
                all[lessonId].sort((a, b) => {
                    const toSec = t => { const p = t.split(':'); return p.length === 2 ? parseInt(p[0]) * 60 + parseInt(p[1]) : 0; };
                    return toSec(a.ts) - toSec(b.ts);
                });
                saveAll(all);
            }

            function deleteNote(lessonId, noteId) {
                const all = getAll();
                if (!all[lessonId]) return;
                all[lessonId] = all[lessonId].filter(n => n.id !== noteId);
                saveAll(all);
            }

            return { getForLesson, addNote, deleteNote };
        })();

        /**
         * Renders the student's timestamp video notes list as interactive tags.
         * @param {number} lessonId - Unique lesson database key.
         * @return {void}
         */
        function renderVideoNotes(lessonId) {
            const section = document.getElementById('video-notes-section');
            const list = document.getElementById('video-notes-list');
            const noMsg = document.getElementById('no-notes-msg');
            if (!section || !list) return;

            const notes = VideoNotes.getForLesson(lessonId);
            list.innerHTML = '';

            if (notes.length === 0) {
                const p = document.createElement('p');
                p.id = 'no-notes-msg';
                p.className = 'text-xs text-[#888888] italic';
                p.textContent = 'Aucune note. Ajoutez-en ci-dessous pendant la vidéo.';
                list.appendChild(p);
            } else {
                notes.forEach(note => {
                    const chip = document.createElement('span');
                    chip.className = 'sv-video-note-chip group relative';
                    chip.innerHTML = `<span class="font-mono">${note.ts}</span><span class="max-w-[180px] truncate">${note.text}</span><button type="button" class="ml-1 opacity-60 hover:opacity-100 text-[10px] font-bold" onclick="deleteVideoNote(${lessonId}, ${note.id})" title="Supprimer">✕</button>`;
                    chip.title = `${note.ts} — ${note.text}`;
                    list.appendChild(chip);
                });
            }
        }

        /**
         * Deletes a specific timestamp note from local storage.
         * @param {number} lessonId - Unique lesson database key.
         * @param {number} noteId - Unique note timestamp database key.
         * @return {void}
         */
        function deleteVideoNote(lessonId, noteId) {
            VideoNotes.deleteNote(lessonId, noteId);
            renderVideoNotes(lessonId);
        }

        /**
         * Toggles the visibility of the timestamp notes section.
         * @param {number} lessonId - Unique lesson database key.
         * @param {string} contentType - Type of the lesson content.
         * @return {void}
         */
        function updateVideoNotesVisibility(lessonId, contentType) {
            const section = document.getElementById('video-notes-section');
            if (!section) return;
            const isVideo = contentType === 'video' || contentType === 'mixed';
            section.classList.toggle('hidden', !isVideo);
            if (isVideo) renderVideoNotes(lessonId);
        }

        document.getElementById('video-note-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const lessonId = parseInt(document.getElementById('comment-lesson-id').value);
            const ts = document.getElementById('note-timestamp').value.trim();
            const text = document.getElementById('note-text').value.trim();
            if (!text) return;
            VideoNotes.addNote(lessonId, ts, text);
            renderVideoNotes(lessonId);
            document.getElementById('note-timestamp').value = '';
            document.getElementById('note-text').value = '';
            Toast.success('Note ajoutée.');
        });

        const _origLoadLesson = loadLesson;
        loadLesson = function(lessonId) {
            _origLoadLesson(lessonId);
            setTimeout(() => {
                const badge = document.getElementById('study-lesson-badge');
                const contentType = badge ? badge.textContent.toLowerCase() : '';
                updateVideoNotesVisibility(lessonId, contentType);
            }, 600);
        };

        // ═══════════════════════════════════════════════════
        // ÉTAPE 8 — SKELETON LOADERS (zones de chargement)
        // ═══════════════════════════════════════════════════

        /**
         * Generates and injects a skeleton loading template into a container.
         * @param {string} containerId - The ID of the HTML element to hold the skeleton.
         * @param {number} [rows=3] - Number of skeleton rows to display.
         * @return {void}
         */
        function showSkeleton(containerId, rows = 3) {
            const el = document.getElementById(containerId);
            if (!el) return;
            let html = '';
            for (let i = 0; i < rows; i++) {
                html += `<div class="space-y-2 p-4 border border-[#E5E5E7] mb-3">
                    <div class="sv-skeleton sv-skeleton-title" style="width:${45 + Math.random()*30}%"></div>
                    <div class="sv-skeleton sv-skeleton-text"></div>
                    <div class="sv-skeleton sv-skeleton-text" style="width:70%"></div>
                </div>`;
            }
            el.innerHTML = html;
        }

        // Apply skeleton to comments list while loading
        const _origLoadComments = loadLessonComments;
        loadLessonComments = function(lessonId) {
            showSkeleton('lesson-comments-list', 2);
            _origLoadComments(lessonId);
        };

        // --- Achievements system interactive functions ---
        const LORE_MAP = {
            'first_lesson': "« Chaque grand voyage commence par un seul pas. Le vôtre vient de débuter. »",
            'study_hour': "« Le temps consacré à l'esprit n'est jamais perdu. La persévérance façonne l'expertise. »",
            'course_complete': "« Franchir la ligne d'arrivée démontre une volonté de fer. Rien ne vous arrête. »",
            'certified': "« Un parchemin de réussite officiel, témoin de votre rigueur et de votre talent. »",
            'perfect_score': "« L'excellence n'est pas un acte, c'est une habitude. Un score absolument impeccable ! »",
            'multitasker': "« Curieux de tout, avide d'apprendre. Votre polyvalence est une force inestimable. »",
            'night_owl': "« Quand le monde s'endort, l'esprit s'éveille. Les secrets du savoir appartiennent à la nuit. »",
            'note_taker': "« L'écriture fixe la pensée. En consignant vos observations, vous gravez le savoir. »"
        };

        function openBadgeModal(key, title, desc, status, earnedDate) {
            const modal = document.getElementById('badge-modal');
            const content = document.getElementById('badge-modal-content');
            if (!modal || !content) return;

            // Set Title & Description
            document.getElementById('badge-modal-title').textContent = title;
            document.getElementById('badge-modal-desc').textContent = desc;

            // Set Lore
            const lore = LORE_MAP[key] || "";
            document.getElementById('badge-modal-lore').textContent = lore;

            // Find clicked card's icon and clone it
            const card = document.querySelector(`[data-badge-key="${key}"]`);
            const iconWrap = document.getElementById('badge-modal-icon-wrap');
            
            if (card && iconWrap) {
                const cardIcon = card.querySelector('.relative.w-20.h-20');
                if (cardIcon) {
                    iconWrap.className = cardIcon.className.replace('w-20 h-20', 'w-24 h-24 mx-auto') + ' flex items-center justify-center rounded-full border-2';
                    iconWrap.innerHTML = cardIcon.innerHTML;
                    
                    const lockDiv = iconWrap.querySelector('.absolute');
                    if (lockDiv) lockDiv.remove();
                }
            }

            // Set status
            const statusWrap = document.getElementById('badge-modal-status-wrap');
            if (statusWrap) {
                if (status === 'unlocked') {
                    statusWrap.innerHTML = `<span class="inline-flex items-center gap-1.5 px-3.5 py-1 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-800 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50 rounded-full text-xs font-semibold font-mono">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        Débloqué le ${earnedDate}
                    </span>`;
                } else {
                    statusWrap.innerHTML = `<span class="inline-flex items-center gap-1.5 px-3.5 py-1 bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700 rounded-full text-xs font-semibold font-mono">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                        Verrouillé
                    </span>`;
                }
            }

            modal.classList.remove('hidden');
            gsap.killTweensOf(content);
            gsap.fromTo(content, 
                { scale: 0.9, opacity: 0 },
                { scale: 1, opacity: 1, duration: 0.35, ease: "back.out(1.5)" }
            );
        }

        function closeBadgeModal() {
            const modal = document.getElementById('badge-modal');
            const content = document.getElementById('badge-modal-content');
            if (!modal || !content) return;

            gsap.to(content, {
                scale: 0.9,
                opacity: 0,
                duration: 0.25,
                ease: "power2.in",
                onComplete: () => {
                    modal.classList.add('hidden');
                }
            });
        }

        function animateBadgesEntrance() {
            gsap.fromTo(".sv-badge-card", 
                { opacity: 0, y: 25, scale: 0.95 },
                { 
                    opacity: (i, el) => el.classList.contains('opacity-65') ? 0.65 : 1, 
                    y: 0, 
                    scale: 1, 
                    duration: 0.45, 
                    stagger: 0.06, 
                    ease: "power2.out",
                    overwrite: "auto"
                }
            );
        }

    </script>

    <!-- ACHIEVEMENTS BADGE MODAL -->
    <div id="badge-modal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
        <div onclick="closeBadgeModal()" class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity duration-300"></div>
        <div class="relative bg-white dark:bg-[#1E1E1E] border border-[#111111] dark:border-zinc-800 max-w-sm w-full p-8 mx-4 shadow-[8px_8px_0px_#111111] dark:shadow-[8px_8px_0px_#004B23] transition-all duration-300 z-10 flex flex-col items-center text-center space-y-6" id="badge-modal-content">
            <button onclick="closeBadgeModal()" class="absolute top-4 right-4 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
            
            <div id="badge-modal-icon-wrap" class="w-24 h-24 rounded-full flex items-center justify-center text-white border-2 relative">
                <!-- SVG Icon -->
            </div>
            
            <div class="space-y-2">
                <h3 id="badge-modal-title" class="font-serif text-2xl font-bold text-[#111111] dark:text-white"></h3>
                <p id="badge-modal-desc" class="text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed font-light"></p>
            </div>
            
            <div id="badge-modal-status-wrap" class="w-full pt-4 border-t border-zinc-100 dark:border-zinc-850">
                <!-- Status tag -->
            </div>

            <div id="badge-modal-lore" class="text-xs italic text-zinc-400 dark:text-zinc-550 font-serif leading-relaxed px-4"></div>
            
            <button onclick="closeBadgeModal()" class="w-full py-3 bg-[#111111] dark:bg-[#004B23] dark:hover:bg-[#00602D] text-white text-xs font-semibold uppercase tracking-wider hover:bg-[#004B23] transition-colors rounded-sm shadow-md">
                Fermer
            </button>
        </div>
    </div>
</body>
</html>
