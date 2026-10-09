<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Student space (v2)
 *
 * One page, six destinations: Today, Courses (mine / catalogue / library), Evaluations,
 * Results, Certificates, Profile. The lesson reader is a full-screen layer on top of it.
 * Markup and styles live here and in assets/css/student.css; behaviour in assets/js/student.js.
 *
 * @package    StudyVibe
 * @subpackage Student
 */

// =========================================================================
// SECTION 1: AUTHENTICATION, ACCESS GATES & DATA QUERIES
// =========================================================================

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/SmsGateway.php';
require_once __DIR__ . '/../lib/StudentLiveEvals.php';
require_once __DIR__ . '/../lib/CourseSchedule.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/student_i18n.php';
requireRole('student');

$user = getCurrentUser();
$pdo = Database::getInstance();
$lang = sdLang();

try {
    // 1. Courses the student can see (published or already enrolled) with their progress
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

    // 2. Certificates
    $stmt = $pdo->prepare("
        SELECT cert.*, c.title AS course_title
        FROM certificates cert
        LEFT JOIN courses c ON cert.course_id = c.id
        WHERE cert.student_id = :student_id
        ORDER BY cert.id DESC
    ");
    $stmt->execute(['student_id' => $user['id']]);
    $myCertificates = $stmt->fetchAll();
    $certByCourse = [];
    foreach ($myCertificates as $cert) {
        if (!empty($cert['course_id']) && !isset($certByCourse[(int)$cert['course_id']])) {
            $certByCourse[(int)$cert['course_id']] = $cert;
        }
    }

    // 3. Figures
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = :sid AND progress_percent = 100");
    $stmt->execute(['sid' => $user['id']]);
    $statCompleted = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(AVG(score), 0), COUNT(*) FROM certification_attempts WHERE student_id = :sid");
    $stmt->execute(['sid' => $user['id']]);
    [$avgRaw, $statAttempts] = array_map('floatval', $stmt->fetch(PDO::FETCH_NUM));
    $statAvgScore = round($avgRaw, 1);
    $statAttempts = (int)$statAttempts;

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(seconds_spent), 0) FROM study_sessions WHERE student_id = :sid");
    $stmt->execute(['sid' => $user['id']]);
    $statStudySecs = (int)$stmt->fetchColumn();
    $statStudyH = (int)floor($statStudySecs / 3600);
    $statStudyM = (int)floor(($statStudySecs % 3600) / 60);

    // 4. Lesson counts per course (for "2 of 4 lessons")
    $stmt = $pdo->prepare("
        SELECT ch.course_id, COUNT(l.id) AS total, SUM(COALESCE(lp.completed, 0) = 1) AS done
        FROM lessons l
        JOIN chapters ch ON ch.id = l.chapter_id
        LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.student_id = :sid
        GROUP BY ch.course_id
    ");
    $stmt->execute(['sid' => $user['id']]);
    $lessonCounts = [];
    foreach ($stmt->fetchAll() as $r) {
        $lessonCounts[(int)$r['course_id']] = ['total' => (int)$r['total'], 'done' => (int)$r['done']];
    }

    // 5. Where to resume: the enrolled, unfinished course touched most recently, and its next lesson
    $resume = null;
    $stmt = $pdo->prepare("
        SELECT e.course_id, e.last_lesson_id, e.progress_percent, e.enrolled_at,
               c.title AS course_title, m.title AS module_title,
               (SELECT MAX(ss.updated_at) FROM study_sessions ss
                  JOIN lessons l2 ON l2.id = ss.lesson_id
                  JOIN chapters ch2 ON ch2.id = l2.chapter_id
                 WHERE ch2.course_id = e.course_id AND ss.student_id = e.student_id) AS last_activity
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        JOIN modules m ON m.id = c.module_id
        WHERE e.student_id = :sid AND e.progress_percent < 100
    ");
    $stmt->execute(['sid' => $user['id']]);
    $openEnrollments = $stmt->fetchAll();
    usort($openEnrollments, function ($a, $b) {
        return strcmp((string)($b['last_activity'] ?? $b['enrolled_at']), (string)($a['last_activity'] ?? $a['enrolled_at']));
    });
    foreach ($openEnrollments as $en) {
        $ls = $pdo->prepare("
            SELECT l.id, l.title, l.content_type, CHAR_LENGTH(COALESCE(l.text_content, '')) AS text_len,
                   l.quiz_deadline, COALESCE(lp.completed, 0) AS completed
            FROM lessons l
            JOIN chapters ch ON ch.id = l.chapter_id
            LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.student_id = :sid
            WHERE ch.course_id = :cid
            ORDER BY ch.sort_order ASC, ch.id ASC, l.sort_order ASC, l.id ASC
        ");
        $ls->execute(['sid' => $user['id'], 'cid' => $en['course_id']]);
        $lessons = array_values(array_filter($ls->fetchAll(), function ($l) {
            return !((int)$l['completed'] === 0 && !empty($l['quiz_deadline']) && strtotime((string)$l['quiz_deadline']) < time());
        }));
        if (!$lessons) {
            continue;
        }
        $target = null;
        $lastIdx = -1;
        foreach ($lessons as $i => $l) {
            if ((int)$l['id'] === (int)$en['last_lesson_id']) {
                $lastIdx = $i;
            }
        }
        if ($lastIdx >= 0 && (int)$lessons[$lastIdx]['completed'] === 0) {
            $target = $lastIdx;
        } else {
            for ($i = max(0, $lastIdx + 1); $i < count($lessons); $i++) {
                if ((int)$lessons[$i]['completed'] === 0) { $target = $i; break; }
            }
            if ($target === null) {
                foreach ($lessons as $i => $l) { if ((int)$l['completed'] === 0) { $target = $i; break; } }
            }
        }
        if ($target === null) {
            continue;
        }
        $tl = $lessons[$target];
        $words = (int)round(((int)$tl['text_len']) / 6);
        $resume = [
            'course_id'    => (int)$en['course_id'],
            'course_title' => $en['course_title'],
            'module_title' => $en['module_title'],
            'lesson_id'    => (int)$tl['id'],
            'lesson_title' => $tl['title'],
            'type'         => $tl['content_type'],
            'minutes'      => ($words > 0 && $tl['content_type'] === 'text') ? max(1, (int)ceil($words / 220)) : 0,
            'position'     => $target + 1,
            'total'        => count($lessons),
            'progress'     => (int)$en['progress_percent'],
            'resumed'      => $en['last_activity'] !== null,
        ];
        break;
    }

    $deadlineAlerts = CourseSchedule::deadlineAlerts($pdo, (int)$user['id']);

    // 6. Badges
    require_once __DIR__ . '/../lib/BadgeHelper.php';
    $myBadges = BadgeHelper::evaluateBadges($pdo, (int)$user['id']);
    $earnedBadgesLookup = [];
    foreach ($myBadges as $b) {
        $earnedBadgesLookup[$b['badge_type']] = $b['earned_at'];
    }
    $allBadgesConfig = BadgeHelper::getAllBadgesConfig();

    // 7. Course library (isolated: self-healing if the table is missing)
    $studentLibraryItems = [];
    try {
        $libraryStmt = $pdo->prepare("
            SELECT cli.*, c.title AS course_title
            FROM course_library_items cli
            JOIN courses c ON cli.course_id = c.id
            JOIN enrollments e ON e.course_id = c.id
            WHERE e.student_id = :sid
            ORDER BY cli.created_at DESC
        ");
        $libraryStmt->execute(['sid' => (int)$user['id']]);
        $studentLibraryItems = $libraryStmt->fetchAll();
    } catch (Throwable $libEx) {
        $studentLibraryItems = [];
    }

    // 8. The student's tele-evaluations
    $stmt = $pdo->prepare("
        SELECT r.id AS registration_id, r.score, r.registered_at, r.cancelled_at,
               (SELECT k.status FROM result_contests k WHERE k.registration_id = r.id) AS contest_status, s.id AS session_id, s.title AS session_title, s.session_code, s.status AS session_status, s.is_async, c.title AS course_title
        FROM live_eval_registrations r
        JOIN live_eval_sessions s ON r.session_id = s.id
        JOIN courses c ON s.course_id = c.id
        WHERE r.student_id = :student_id OR r.email = :email
        ORDER BY r.registered_at DESC
    ");
    $stmt->execute(['student_id' => $user['id'], 'email' => $user['email']]);
    $myEvaluations = $stmt->fetchAll();

    // 9. What is coming: live sessions not over, open async sessions, on enrolled courses
    $upcoming = [];
    try {
        $upcoming = StudentLiveEvals::upcoming($pdo, (int)$user['id'], (string)$user['email']);
    } catch (Throwable $upEx) {
        $upcoming = [];
    }

    // 10. Certification attempts (for "latest result")
    $stmt = $pdo->prepare("
        SELECT ca.id, ca.score, ca.passed, ca.attempted_at, c.title AS course_title
        FROM certification_attempts ca JOIN courses c ON c.id = ca.course_id
        WHERE ca.student_id = :sid ORDER BY ca.attempted_at DESC
    ");
    $stmt->execute(['sid' => $user['id']]);
    $myAttempts = $stmt->fetchAll();

    // 11. Transcript
    $stmt = $pdo->prepare("
        SELECT c.title AS course_title, m.title AS module_title, e.course_id, e.progress_percent,
               (SELECT MAX(ca.score) FROM certification_attempts ca WHERE ca.student_id = e.student_id AND ca.course_id = e.course_id) AS best_score,
               (SELECT COUNT(*) FROM certification_attempts ca WHERE ca.student_id = e.student_id AND ca.course_id = e.course_id) AS attempts
        FROM enrollments e
        JOIN courses c ON c.id = e.course_id
        JOIN modules m ON m.id = c.module_id
        WHERE e.student_id = :sid
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute(['sid' => $user['id']]);
    $transcriptCourses = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT l.title AS lesson_title, c.title AS course_title, lp.score, lp.completed_at
        FROM lesson_progress lp
        JOIN lessons l ON l.id = lp.lesson_id
        JOIN chapters ch ON ch.id = l.chapter_id
        JOIN courses c ON c.id = ch.course_id
        WHERE lp.student_id = :sid AND lp.completed = 1 AND lp.score IS NOT NULL
        ORDER BY lp.completed_at DESC
        LIMIT 100
    ");
    $stmt->execute(['sid' => $user['id']]);
    $lessonScores = $stmt->fetchAll();

    // 12. The student's assignments: what is due, what was handed in, the mark and the feedback
    $myAssignments = [];
    try {
        $stmt = $pdo->prepare("
            SELECT l.id AS lesson_id, l.title AS lesson_title, l.assignment_title, l.assignment_deadline, l.assignment_max_score,
                   c.id AS course_id, c.title AS course_title,
                   s.submitted_at, s.is_late, s.score, s.feedback, s.graded_at, s.revision_requested_at
            FROM enrollments e
            JOIN courses c ON c.id = e.course_id
            JOIN chapters ch ON ch.course_id = c.id
            JOIN lessons l ON l.chapter_id = ch.id AND l.has_assignment = 1
            LEFT JOIN lesson_assignment_submissions s ON s.lesson_id = l.id AND s.student_id = e.student_id
            WHERE e.student_id = :sid
            ORDER BY (l.assignment_deadline IS NULL), l.assignment_deadline ASC, l.id ASC
        ");
        $stmt->execute(['sid' => $user['id']]);
        $myAssignments = $stmt->fetchAll();
    } catch (Throwable $asgEx) {
        $myAssignments = [];
    }
} catch (PDOException $e) {
    dieSafe('Erreur serveur. Veuillez réessayer.', $e, 'student/dashboard');
}

// ---- Derived values for the Today screen ----
$firstName = trim(explode(' ', trim((string)$user['name']))[0] ?? '');
$hour = (int)date('G');
$greetKey = $hour < 5 ? 'greet_evening' : ($hour < 18 ? ($hour < 12 ? 'greet_morning' : 'greet_afternoon') : 'greet_evening');

$latest = null;
foreach ($myEvaluations as $ev) {
    if ($ev['score'] === null) { continue; }
    $when = strtotime((string)$ev['registered_at']);
    if ($latest === null || $when > $latest['when']) {
        $latest = [
            'when'   => $when,
            'title'  => $ev['session_title'],
            'course' => $ev['course_title'],
            'score'  => (float)$ev['score'],
            'pass'   => (float)$ev['score'] >= 50,
            'kind'   => 'live',
            'url'    => '/student/evaluation-results.php?registration_id=' . (int)$ev['registration_id'] . '&token=' . hash_hmac('sha256', (string)$ev['registration_id'], APP_SECRET),
        ];
    }
}
foreach ($myAttempts as $at) {
    $when = strtotime((string)$at['attempted_at']);
    if ($latest === null || $when > $latest['when']) {
        $passed = (int)$at['passed'] === 1;
        $latest = [
            'when'   => $when,
            'title'  => sd('final_exam'),
            'course' => $at['course_title'],
            'score'  => (float)$at['score'],
            'pass'   => $passed,
            'kind'   => 'cert',
            'url'    => $passed ? '#certs' : '/student/certification-report.php?attempt_id=' . (int)$at['id'],
        ];
    }
}

$enrolledCourses = array_values(array_filter($courses, fn($c) => $c['is_enrolled']));
// in progress first, then not started, then finished
usort($enrolledCourses, function ($a, $b) {
    $rank = fn($c) => ((int)$c['progress_percent'] >= 100 ? 2 : ((int)$c['progress_percent'] > 0 ? 0 : 1));
    return $rank($a) <=> $rank($b);
});
$avatarSrc = $user['avatar_path'] ? mediaUrl('avatar', $user['avatar_path']) : 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user['email']))) . '?d=mp';
$studyTimeLabel = $statStudyH . ' h ' . str_pad((string)$statStudyM, 2, '0', STR_PAD_LEFT);

$badgeText = [
    'first_lesson'    => ['Pionnier', 'Pioneer', 'Complétez votre première leçon.', 'Complete your first lesson.'],
    'study_hour'      => ['Une heure d\'étude', 'First hour', 'Cumulez plus d\'une heure d\'étude.', 'Study for more than one hour in total.'],
    'course_complete' => ['Cours terminé', 'Course finished', 'Terminez un cours à 100 %.', 'Finish a course at 100%.'],
    'certified'       => ['Certifié', 'Certified', 'Obtenez votre premier certificat.', 'Earn your first certificate.'],
    'perfect_score'   => ['Sans faute', 'Perfect score', 'Obtenez 100 % à un quiz de leçon ou à un examen final.', 'Score 100% on a lesson quiz or a final exam.'],
    'multitasker'     => ['Trois cours', 'Three courses', 'Inscrivez-vous à au moins 3 cours.', 'Enrol in at least 3 courses.'],
    'night_owl'       => ['Travail de nuit', 'Night work', 'Terminez une leçon ou un examen entre 22 h et 4 h.', 'Finish a lesson or exam between 10 pm and 4 am.'],
    'note_taker'      => ['Prise de notes', 'Note taker', 'Enregistrez une note pendant une vidéo.', 'Save a note while watching a video.'],
    'speed_demon'     => ['Cinq en un jour', 'Five in a day', 'Terminez 5 leçons le même jour.', 'Finish 5 lessons on the same day.'],
    'marathoner'      => ['Dix heures', 'Ten hours', 'Cumulez 10 heures d\'étude.', 'Reach 10 hours of study.'],
    'quiz_master'     => ['Quiz maîtrisés', 'Quizzes mastered', 'Réussissez 10 quiz de leçon à 80 % ou plus.', 'Pass 10 lesson quizzes with 80% or more.'],
    'early_bird'      => ['Tôt le matin', 'Early start', 'Terminez une leçon ou un examen entre 5 h et 8 h.', 'Finish a lesson or exam between 5 am and 8 am.'],
    'bibliophile'     => ['Bibliothèque', 'Library', 'Ayez accès à 3 ressources de bibliothèque ou plus.', 'Have access to 3 or more library resources.'],
    'assignment_ace'  => ['Premier devoir', 'First assignment', 'Rendez un devoir à votre enseignant.', 'Hand in an assignment to your teacher.'],
    'tele_champion'   => ['En direct', 'Live session', 'Participez à une téléévaluation.', 'Take part in a tele-evaluation.'],
    'community_voice' => ['Dans la discussion', 'In the discussion', 'Postez au moins 3 questions ou réponses.', 'Post at least 3 questions or replies.'],
    'streak_master'   => ['Trois jours', 'Three days', 'Étudiez sur au moins 3 jours différents.', 'Study on at least 3 different days.'],
    'scholar_god'     => ['Trois certificats', 'Three certificates', 'Obtenez 3 certificats.', 'Earn 3 certificates.'],
];

/** One enrolled-course row: title, where they are, what to do next. */
function sdCourseRow(array $c, array $certByCourse, array $lessonCounts): string
{
    $id = (int)$c['id'];
    $p = (int)$c['progress_percent'];
    $lc = $lessonCounts[$id] ?? ['total' => 0, 'done' => 0];
    $cert = $certByCourse[$id] ?? null;
    ob_start(); ?>
    <li class="sd-row sd-course" data-course-id="<?= $id ?>">
        <div class="sd-course-main">
            <p class="sd-kicker"><?= sdH($c['module_title']) ?></p>
            <h3 class="sd-course-title"><?= sdH($c['title']) ?></h3>
            <p class="sd-meta"><?= sdH($c['teacher_name']) ?> · <?= $lc['total'] > 0 ? sdH(sd('lessons_of', ['done' => $lc['done'], 'total' => $lc['total']])) : sdH(sd('no_lessons')) ?></p>
        </div>
        <div class="sd-course-progress">
            <div class="sd-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $p ?>" aria-label="<?= sdH(sd('progress')) ?>"><i style="width:<?= $p ?>%"></i></div>
            <span class="num sd-pct"><?= $p ?> %</span>
        </div>
        <div class="sd-course-act">
            <button type="button" class="btn btn-ghost btn-sm" data-study="<?= $id ?>"><?= sdH($p > 0 && $p < 100 ? sd('continue') : sd('open')) ?></button>
            <?php if ($cert): ?>
                <a class="btn btn-text" href="/certificate.php?code=<?= urlencode($cert['certificate_code']) ?>"><?= sdH(sd('see_certificate')) ?></a>
            <?php elseif ($p === 100): ?>
                <button type="button" class="btn btn-primary btn-sm" data-start-exam="<?= $id ?>" data-title="<?= sdH($c['title']) ?>"><?= sdH(sd('take_exam')) ?></button>
            <?php else: ?>
                <span class="sd-hint"><?= sdH(sd('exam_locked')) ?></span>
            <?php endif; ?>
        </div>
    </li>
    <?php
    return (string)ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= sdH(sd('page_title')) ?> — StudyVibe</title>
    <meta name="theme-color" content="#F5F0E6">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="icon" type="image/png" href="/assets/img/favicon.png" sizes="32x32">
    <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
    <?= sdFontsLink() ?>
    <link rel="stylesheet" href="/assets/css/sv2.css">
    <?= Brand::headLinks() ?>
    <link rel="stylesheet" href="/assets/css/student.css">
    <link rel="stylesheet" href="/assets/css/reader-flow.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/contrib/auto-render.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked@9.1.6/marked.min.js"></script>
    <?= csrfMetaTag(); ?>
    <?= sdThemeBoot() ?>
</head>
<body class="v2 sd">
<a class="sd-skip" href="#sd-main"><?= sdH(sd('skip')) ?></a>

<!-- =========================================================================
     SECTION 2: APP SHELL — slim rail on desktop, top bar + bottom bar on phones
     ========================================================================= -->
<?php
$nav = [
    'home'    => ['home',    sd('nav_home')],
    'courses' => ['book',    sd('nav_courses')],
    'evals'   => ['exam',    sd('nav_evals')],
    'results' => ['results', sd('nav_results')],
    'certs'   => ['cert',    sd('nav_certs')],
];
?>
<aside class="sd-rail" aria-label="<?= sdH(sd('nav_label')) ?>">
    <a class="sd-logo" href="#home" data-nav="home" aria-label="StudyVibe"><?= Brand::logo('md') ?></a>
    <nav>
        <ul class="sd-navlist">
            <?php foreach ($nav as $key => [$ic, $label]): ?>
            <li><button type="button" class="sd-nav" data-nav="<?= $key ?>" id="tab-btn-<?= $key ?>"><?= sdIcon($ic) ?><span><?= sdH($label) ?></span></button></li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <div class="sd-rail-foot">
        <div class="sd-tools">
            <button type="button" class="sd-iconbtn" id="notif-btn" aria-haspopup="dialog" aria-label="<?= sdH(sd('notifications')) ?>"><?= sdIcon('bell') ?><span class="notif-count hidden" aria-live="polite">0</span></button>
            <button type="button" class="sd-iconbtn" data-dark-toggle aria-label="<?= sdH(sd('theme')) ?>"><span class="ic-sun"><?= sdIcon('sun') ?></span><span class="ic-moon"><?= sdIcon('moon') ?></span></button>
            <div class="seg" role="group" aria-label="<?= sdH(sd('language')) ?>">
                <a href="#" data-lang="fr" <?= $lang === 'fr' ? 'aria-current="true"' : '' ?>>FR</a>
                <a href="#" data-lang="en" <?= $lang === 'en' ? 'aria-current="true"' : '' ?>>EN</a>
            </div>
        </div>
        <button type="button" class="sd-me" data-nav="profile" id="tab-btn-profile">
            <img id="header-avatar" src="<?= sdH($avatarSrc) ?>" alt="" width="36" height="36">
            <span class="sd-me-t"><strong class="id-student-name"><?= sdH($user['name']) ?></strong><small><?= sdH(sd('my_profile')) ?></small></span>
        </button>
        <a class="sd-logout" href="/logout.php"><?= sdIcon('out', 16) ?><span><?= sdH(sd('logout')) ?></span></a>
    </div>
</aside>

<header class="sd-topbar">
    <a class="sd-topbar-logo" href="#home" data-nav="home" aria-label="StudyVibe"><?= Brand::logo('sm') ?></a>
    <div class="sd-topbar-tools">
        <button type="button" class="sd-iconbtn" id="notif-btn-m" aria-haspopup="dialog" aria-label="<?= sdH(sd('notifications')) ?>"><?= sdIcon('bell') ?><span class="notif-count hidden">0</span></button>
        <button type="button" class="sd-iconbtn" data-nav="profile" aria-label="<?= sdH(sd('my_profile')) ?>"><img src="<?= sdH($avatarSrc) ?>" alt="" width="28" height="28" class="sd-avatar-sm"></button>
    </div>
</header>

<nav class="sd-bar-nav" aria-label="<?= sdH(sd('nav_label')) ?>">
    <?php foreach ($nav as $key => [$ic, $label]): ?>
    <button type="button" class="sd-nav" data-nav="<?= $key ?>"><?= sdIcon($ic, 22) ?><span><?= sdH($label) ?></span></button>
    <?php endforeach; ?>
</nav>

<!-- Notifications popover (one element, opened from the rail or the top bar) -->
<div id="notif-panel-container" class="sd-pop hidden" role="dialog" aria-label="<?= sdH(sd('notifications')) ?>">
    <div class="sd-pop-head">
        <strong><?= sdH(sd('notifications')) ?></strong>
        <button type="button" class="btn btn-text" onclick="markAllNotificationsRead(event)"><?= sdH(sd('mark_all_read')) ?></button>
    </div>
    <div id="notif-panel" class="sd-pop-body"></div>
</div>

<main id="sd-main" class="sd-main">

<!-- =========================================================================
     SECTION 3: TODAY — continue, what is next, latest result, certificates
     ========================================================================= -->
<section id="tab-home" class="sd-panel" aria-labelledby="home-title">
    <?php if (!empty($deadlineAlerts)): ?>
    <div class="sd-notice" role="note">
        <strong><?= sdH(sd('deadline_title')) ?></strong>
        <?php foreach ($deadlineAlerts as $alert): ?>
        <span><?= sdH($alert['title']) ?> — <?= sdH(sd('deadline_before', ['date' => sdDate($alert['eval_deadline'], 'year')])) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <header class="sd-head">
        <p class="sd-date"><?= sdH(sdDate(time(), 'full')) ?></p>
        <h1 id="home-title"><?= sdH(sd($greetKey)) ?><?= $firstName !== '' ? ', <em>' . sdH($firstName) . '</em>' : '' ?>.</h1>
    </header>

    <div class="sd-today">
        <!-- Continue -->
        <article class="sd-continue">
            <?php if ($resume): ?>
                <p class="sd-kicker"><?= sdH($resume['resumed'] ? sd('continue_kicker') : sd('start_kicker')) ?></p>
                <p class="sd-continue-course"><?= sdH($resume['course_title']) ?></p>
                <h2 class="sd-continue-lesson"><?= sdH($resume['lesson_title']) ?></h2>
                <p class="sd-meta">
                    <?= sdH(sd('lesson_pos', ['n' => $resume['position'], 'total' => $resume['total']])) ?>
                    · <?= sdH(sd('type_' . $resume['type'])) ?>
                    <?php if ($resume['minutes'] > 0): ?> · <?= sdH(sd('read_min', ['n' => $resume['minutes']])) ?><?php endif; ?>
                </p>
                <div class="sd-continue-foot">
                    <div class="sd-bar sd-bar-lg" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $resume['progress'] ?>" aria-label="<?= sdH(sd('progress')) ?>"><i style="width:<?= $resume['progress'] ?>%"></i></div>
                    <span class="num sd-pct"><?= $resume['progress'] ?> %</span>
                    <button type="button" class="btn btn-primary btn-lg" onclick="resumeCourse(<?= $resume['course_id'] ?>, <?= $resume['lesson_id'] ?>)"><?= sdH($resume['resumed'] ? sd('resume') : sd('start')) ?> <?= sdIcon('arrow', 18) ?></button>
                </div>
                <p class="sd-study-total"><?= sdH(sd('study_total')) ?> <span class="num" data-kpi="time"><?= sdH($studyTimeLabel) ?></span></p>
            <?php elseif (empty($enrolledCourses)): ?>
                <p class="sd-kicker"><?= sdH(sd('first_kicker')) ?></p>
                <h2 class="sd-continue-lesson"><?= sdH(sd('empty_home_title')) ?></h2>
                <p class="sd-meta"><?= sdH(sd('empty_home_text')) ?></p>
                <div class="sd-continue-foot"><button type="button" class="btn btn-primary btn-lg" data-nav="courses" data-sub="catalogue"><?= sdH(sd('browse_catalogue')) ?> <?= sdIcon('arrow', 18) ?></button></div>
            <?php else: ?>
                <p class="sd-kicker"><?= sdH(sd('uptodate_kicker')) ?></p>
                <h2 class="sd-continue-lesson"><?= sdH(sd('uptodate_title')) ?></h2>
                <p class="sd-meta"><?= sdH(sd('uptodate_text')) ?></p>
                <div class="sd-continue-foot"><button type="button" class="btn btn-ghost btn-lg" data-nav="courses" data-sub="catalogue"><?= sdH(sd('browse_catalogue')) ?></button></div>
                <p class="sd-study-total"><?= sdH(sd('study_total')) ?> <span class="num" data-kpi="time"><?= sdH($studyTimeLabel) ?></span></p>
            <?php endif; ?>
        </article>

        <!-- Next / latest / certificates -->
        <div class="sd-side">
            <section class="sd-mini" aria-labelledby="mini-next">
                <h2 id="mini-next" class="sd-mini-title"><?= sdH(sd('next_eval')) ?></h2>
                <?php if (!empty($upcoming)): $u = $upcoming[0]; $isAsync = (int)$u['is_async'] === 1; ?>
                    <p class="sd-mini-when num"><?= $isAsync
                        ? (!empty($u['async_deadline']) ? sdH(sd('until', ['date' => sdDate($u['async_deadline'], 'dt')])) : sdH(sd('open_now')))
                        : sdH(sdDate($u['start_time'], 'dt')) ?></p>
                    <p class="sd-mini-name"><?= sdH($u['title']) ?></p>
                    <p class="sd-meta"><?= sdH($u['course_title']) ?> · <?= sdH($isAsync ? sd('async') : sd('live')) ?><?php
                        $rel = $isAsync ? (!empty($u['async_deadline']) ? sdRelative($u['async_deadline']) : '') : sdRelative($u['start_time']);
                        if ($rel !== '') { echo ' · ' . sdH($rel); } ?><?php if (!$isAsync): ?> · <strong class="sd-live-count" data-start-in="<?= (int)$u['seconds_to_start'] ?>"><?= sdH($u['is_running'] ? sd('live_now') : sd('starts_in', ['t' => gmdate('i:s', min(5999, (int)$u['seconds_to_start']))])) ?></strong><?php endif; ?></p>
                    <a class="btn btn-ghost btn-sm" href="/live-session.php?code=<?= urlencode($u['session_code']) ?>"><?= sdH($isAsync ? sd('start_eval') : ((int)$u['registered'] > 0 ? sd('join_room') : sd('register'))) ?></a>
                    <?php if (count($upcoming) > 1): ?><button type="button" class="btn btn-text" data-nav="evals"><?= sdH(sd('n_more', ['n' => count($upcoming) - 1])) ?></button><?php endif; ?>
                <?php else: ?>
                    <p class="sd-empty"><?= sdH(sd('no_upcoming')) ?></p>
                <?php endif; ?>
            </section>

            <section class="sd-mini" aria-labelledby="mini-latest">
                <h2 id="mini-latest" class="sd-mini-title"><?= sdH(sd('latest_result')) ?></h2>
                <?php if ($latest): ?>
                    <p class="sd-mini-score"><span class="num"><?= sdH(sdScore($latest['score'])) ?></span><small>%</small>
                        <span class="sd-verdict <?= $latest['pass'] ? 'is-pass' : 'is-fail' ?>"><?= sdIcon($latest['pass'] ? 'check' : 'x', 14) ?><?= sdH($latest['pass'] ? sd('passed') : sd('not_passed')) ?></span></p>
                    <p class="sd-mini-name"><?= sdH($latest['title']) ?></p>
                    <p class="sd-meta"><?= sdH($latest['course']) ?> · <?= sdH(sdDate($latest['when'], 'short')) ?></p>
                    <a class="btn btn-text" href="<?= sdH($latest['url']) ?>"><?= sdH($latest['kind'] === 'cert' && $latest['pass'] ? sd('see_certificate') : sd('see_report')) ?></a>
                <?php else: ?>
                    <p class="sd-empty"><?= sdH(sd('no_result')) ?></p>
                <?php endif; ?>
            </section>

            <section class="sd-mini" aria-labelledby="mini-certs">
                <h2 id="mini-certs" class="sd-mini-title"><?= sdH(sd('nav_certs')) ?></h2>
                <?php if (!empty($myCertificates)): ?>
                    <p class="sd-mini-name"><span class="num" data-kpi="certs"><?= count($myCertificates) ?></span> · <?= sdH($myCertificates[0]['course_title'] ?? '') ?></p>
                    <p class="sd-meta num"><?= sdH(sd('issued_on', ['date' => sdDate($myCertificates[0]['issued_at'], 'year')])) ?></p>
                    <a class="btn btn-text" href="/certificate.php?code=<?= urlencode($myCertificates[0]['certificate_code']) ?>"><?= sdH(sd('see_certificate')) ?></a>
                    <?php if (count($myCertificates) > 1): ?><button type="button" class="btn btn-text" data-nav="certs"><?= sdH(sd('all_certs')) ?></button><?php endif; ?>
                <?php else: ?>
                    <p class="sd-empty"><?= sdH(sd('no_cert_yet')) ?> <span class="hidden"><span data-kpi="certs">0</span></span></p>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <?php if (!empty($enrolledCourses)): ?>
    <section class="sd-block" aria-labelledby="home-courses">
        <div class="sd-block-head">
            <h2 id="home-courses"><?= sdH(sd('my_courses')) ?></h2>
            <button type="button" class="btn btn-text" data-nav="courses" data-sub="mine"><?= sdH(sd('all_my_courses')) ?></button>
        </div>
        <ul class="sd-list">
            <?php foreach (array_slice($enrolledCourses, 0, 4) as $ec) { echo sdCourseRow($ec, $certByCourse, $lessonCounts); } ?>
        </ul>
    </section>
    <?php endif; ?>
</section>

<!-- =========================================================================
     SECTION 4: COURSES — mine / catalogue / library
     ========================================================================= -->
<section id="tab-courses" class="sd-panel hidden" aria-labelledby="courses-title">
    <header class="sd-head sd-head-row">
        <div>
            <h1 id="courses-title"><?= sdH(sd('nav_courses')) ?></h1>
        </div>
        <div class="sd-tabs" role="tablist" aria-label="<?= sdH(sd('nav_courses')) ?>">
            <button type="button" role="tab" class="sd-tab" id="cs-btn-mine" data-sub="mine"><?= sdH(sd('sub_mine')) ?></button>
            <button type="button" role="tab" class="sd-tab" id="cs-btn-catalogue" data-sub="catalogue"><?= sdH(sd('sub_catalogue')) ?></button>
            <button type="button" role="tab" class="sd-tab" id="cs-btn-library" data-sub="library"><?= sdH(sd('sub_library')) ?><?php if (!empty($studentLibraryItems)): ?> <span class="num sd-count"><?= count($studentLibraryItems) ?></span><?php endif; ?></button>
        </div>
    </header>

    <!-- Mine -->
    <div id="cs-mine" role="tabpanel">
        <?php if (empty($enrolledCourses)): ?>
            <div class="sd-emptybox">
                <p><?= sdH(sd('no_enrolment')) ?></p>
                <button type="button" class="btn btn-primary" data-sub="catalogue"><?= sdH(sd('browse_catalogue')) ?></button>
            </div>
        <?php else: ?>
            <ul class="sd-list">
                <?php foreach ($enrolledCourses as $ec) { echo sdCourseRow($ec, $certByCourse, $lessonCounts); } ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Catalogue -->
    <div id="cs-catalogue" class="hidden" role="tabpanel">
        <div class="sd-search">
            <label class="sr-only" for="course-search"><?= sdH(sd('search_label')) ?></label>
            <input type="search" id="course-search" class="input" placeholder="<?= sdH(sd('search_ph')) ?>" autocomplete="off">
        </div>
        <p class="sd-meta sd-nores hidden" id="course-nores"><?= sdH(sd('no_match')) ?></p>
        <div class="sd-grid" id="course-grid">
            <?php foreach ($courses as $c): ?>
            <article class="sd-card" data-course-card data-search="<?= sdH(strtolower($c['title'] . ' ' . $c['module_title'] . ' ' . $c['teacher_name'] . ' ' . $c['description'])) ?>">
                <div class="sd-card-cover">
                    <?php if (!empty($c['cover_image'])): ?>
                        <img src="/download.php?type=cover&amp;file=<?= urlencode($c['cover_image']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <?= Brand::mark(30) ?>
                    <?php endif; ?>
                </div>
                <div class="sd-card-body">
                    <p class="sd-kicker"><?= sdH($c['module_title']) ?></p>
                    <h3 class="sd-card-title"><?= sdH($c['title']) ?></h3>
                    <p class="sd-meta"><?= sdH(sd('taught_by', ['name' => $c['teacher_name']])) ?></p>
                    <p class="sd-card-desc"><?= sdH($c['description']) ?></p>
                </div>
                <div class="sd-card-foot">
                    <?php if ($c['is_enrolled']): ?>
                        <span class="sd-state is-in"><?= sdIcon('check', 14) ?><?= sdH(sd('enrolled_pct', ['p' => (int)$c['progress_percent']])) ?></span>
                        <button type="button" class="btn btn-ghost btn-sm" data-study="<?= (int)$c['id'] ?>"><?= sdH(sd('open')) ?></button>
                    <?php else: ?>
                        <span class="sd-state"><?= $c['enrollment_key'] ? sdIcon('lock', 14) : '' ?><?= sdH($c['enrollment_key'] ? sd('key_required') : sd('free_access')) ?></span>
                        <button type="button" class="btn btn-primary btn-sm" onclick="attemptEnroll(<?= (int)$c['id'] ?>, <?= $c['enrollment_key'] ? 'true' : 'false' ?>)"><?= sdH(sd('enrol')) ?></button>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Library -->
    <div id="cs-library" class="hidden" role="tabpanel">
        <p class="sd-lede"><?= sdH(sd('library_lede')) ?></p>
        <?php if (empty($studentLibraryItems)): ?>
            <div class="sd-emptybox"><p><?= sdH(sd('library_empty')) ?></p></div>
        <?php else: ?>
        <ul class="sd-list">
            <?php foreach ($studentLibraryItems as $item):
                $cat = !empty($item['category']) ? $item['category'] : (!empty($item['item_type']) ? $item['item_type'] : 'document');
                $vUrl = !empty($item['video_url']) ? $item['video_url'] : (!empty($item['external_url']) ? $item['external_url'] : '');
            ?>
            <li class="sd-row sd-lib">
                <div class="sd-lib-main">
                    <p class="sd-kicker"><?= sdH((string)$cat) ?> · <?= sdH($item['course_title'] ?? '') ?></p>
                    <h3 class="sd-lib-title"><?= sdH($item['title']) ?></h3>
                    <?php if (!empty($item['description'])): ?><p class="sd-meta"><?= sdH($item['description']) ?></p><?php endif; ?>
                </div>
                <span class="sd-meta num"><?= sdH(sdDate($item['created_at'], 'date')) ?></span>
                <div class="sd-lib-act">
                    <?php if (!empty($item['file_path'])): ?>
                        <a class="btn btn-ghost btn-sm" href="/download.php?type=library&amp;file=<?= urlencode(basename($item['file_path'])) ?>" target="_blank" rel="noopener"><?= sdIcon('down', 16) ?><?= sdH(sd('download')) ?></a>
                    <?php elseif (!empty($vUrl)): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= sdH($vUrl) ?>" target="_blank" rel="noopener"><?= sdIcon('play', 16) ?><?= sdH(sd('watch')) ?></a>
                    <?php elseif (!empty($item['content_markdown'])): ?>
                        <button type="button" class="btn btn-ghost btn-sm" data-lib-text='<?= sdH(json_encode(['title' => $item['title'], 'md' => $item['content_markdown']], JSON_UNESCAPED_UNICODE)) ?>'><?= sdIcon('note', 16) ?><?= sdH(sd('read')) ?></button>
                    <?php else: ?>
                        <span class="sd-meta"><?= sdH(sd('online_resource')) ?></span>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</section>

<!-- =========================================================================
     SECTION 5: EVALUATIONS — upcoming, then past
     ========================================================================= -->
<section id="tab-evals" class="sd-panel hidden" aria-labelledby="evals-title">
    <header class="sd-head sd-head-row">
        <h1 id="evals-title"><?= sdH(sd('nav_evals')) ?></h1>
        <a class="btn btn-ghost" href="/evaluations.php"><?= sdH(sd('open_evals')) ?></a>
    </header>

    <section class="sd-block" aria-labelledby="ev-up">
        <h2 id="ev-up" class="sd-h2" data-ev-ids="<?= sdH(implode(',', array_map(fn($u) => (int)$u['id'] . ('' . ($u['is_running'] ? 'r' : 'w')), $upcoming))) ?>"><?= sdH(sd('upcoming')) ?></h2>
        <?php if (empty($upcoming)): ?>
            <p class="sd-empty"><?= sdH(sd('no_upcoming_long')) ?></p>
        <?php else: ?>
        <ul class="sd-list">
            <?php foreach ($upcoming as $u): $isAsync = (int)$u['is_async'] === 1; ?>
            <li class="sd-row sd-ev">
                <div class="sd-ev-when num">
                    <?php if ($isAsync): ?>
                        <strong><?= !empty($u['async_deadline']) ? sdH(sdDate($u['async_deadline'], 'short')) : '—' ?></strong>
                        <small><?= sdH(!empty($u['async_deadline']) ? sd('deadline') : sd('open_now')) ?></small>
                    <?php else: ?>
                        <strong><?= sdH(sdDate($u['start_time'], 'short')) ?></strong>
                        <small><?= sdH(sdDate($u['start_time'], 'time')) ?></small>
                    <?php endif; ?>
                </div>
                <div class="sd-ev-main">
                    <h3 class="sd-course-title"><?= sdH($u['title']) ?></h3>
                    <p class="sd-meta"><?= sdH($u['course_title']) ?> · <?= sdH($isAsync ? sd('async') : sd('live')) ?><?php
                        $rel = $isAsync ? (!empty($u['async_deadline']) ? sdRelative($u['async_deadline']) : '') : sdRelative($u['start_time']);
                        if ($rel !== '') { echo ' · ' . sdH($rel); } ?><?php if (!$isAsync): ?> · <strong class="sd-live-count" data-start-in="<?= (int)$u['seconds_to_start'] ?>"><?= sdH($u['is_running'] ? sd('live_now') : sd('starts_in', ['t' => gmdate('i:s', min(5999, (int)$u['seconds_to_start']))])) ?></strong><?php endif; ?></p>
                </div>
                <div class="sd-ev-act">
                    <a class="btn btn-primary btn-sm" href="/live-session.php?code=<?= urlencode($u['session_code']) ?>"><?= sdH($isAsync ? sd('start_eval') : ((int)$u['registered'] > 0 ? sd('join_room') : sd('register'))) ?></a>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <section class="sd-block" aria-labelledby="ev-past">
        <h2 id="ev-past" class="sd-h2"><?= sdH(sd('past')) ?></h2>
        <?php if (empty($myEvaluations)): ?>
            <p class="sd-empty"><?= sdH(sd('no_evals')) ?></p>
        <?php else: ?>
        <ul class="sd-list">
            <?php foreach ($myEvaluations as $eval):
                $isFinished = $eval['score'] !== null;
                $isCancelled = !empty($eval['cancelled_at']);
                $hasContest = !empty($eval['contest_status']);
                $token = $isFinished ? hash_hmac('sha256', (string)$eval['registration_id'], APP_SECRET) : '';
                $pass = $isFinished && (float)$eval['score'] >= 50;
            ?>
            <li class="sd-row sd-ev">
                <div class="sd-ev-when num">
                    <strong><?= sdH(sdDate($eval['registered_at'], 'short')) ?></strong>
                    <small><?= sdH(sdDate($eval['registered_at'], 'time')) ?></small>
                </div>
                <div class="sd-ev-main">
                    <h3 class="sd-course-title"><?= sdH($eval['session_title']) ?></h3>
                    <p class="sd-meta"><?= sdH($eval['course_title']) ?> · <?= sdH($eval['is_async'] ? sd('async') : sd('live')) ?></p>
                </div>
                <div class="sd-ev-score">
                    <?php if ($isCancelled): ?>
                        <span class="sd-verdict is-fail"><?= sdIcon('x', 14) ?><?= sdH(sd('ev_cancelled')) ?></span>
                    <?php elseif ($isFinished): ?>
                        <span class="num sd-score"><?= sdH(sdScore($eval['score'])) ?> %</span>
                        <span class="sd-verdict <?= $pass ? 'is-pass' : 'is-fail' ?>"><?= sdIcon($pass ? 'check' : 'x', 14) ?><?= sdH($pass ? sd('passed') : sd('not_passed')) ?></span>
                    <?php else: ?>
                        <span class="sd-verdict is-wait"><?= sdH(sd('pending')) ?></span>
                    <?php endif; ?>
                </div>
                <div class="sd-ev-act">
                    <?php if ($isCancelled || ($hasContest && !$isFinished)): ?>
                        <a class="btn btn-primary btn-sm" href="/student/contest-result.php?registration_id=<?= (int)$eval['registration_id'] ?>&amp;token=<?= sdH(hash_hmac('sha256', 'contest:' . $eval['registration_id'], APP_SECRET)) ?>"><?= sdH(sd(!$hasContest ? 'ev_contest' : ($eval['contest_status'] === 'open' ? 'ev_contest_open' : 'ev_contest_final'))) ?></a>
                    <?php elseif ($isFinished): ?>
                        <a class="btn btn-ghost btn-sm" href="/student/evaluation-results.php?registration_id=<?= (int)$eval['registration_id'] ?>&amp;token=<?= $token ?>"><?= sdH(sd('see_report')) ?></a>
                    <?php else: ?>
                        <a class="btn btn-primary btn-sm" href="/live-session.php?code=<?= urlencode($eval['session_code']) ?>"><?= sdH(sd('join')) ?></a>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
</section>

<!-- =========================================================================
     SECTION 6: RESULTS — a transcript
     ========================================================================= -->
<section id="tab-results" class="sd-panel hidden" aria-labelledby="results-title">
    <header class="sd-head sd-head-row">
        <h1 id="results-title"><?= sdH(sd('nav_results')) ?></h1>
        <a class="btn btn-ghost" href="/student/releve.php" target="_blank" rel="noopener"><?= sdIcon('print', 16) ?><?= sdH(sd('transcript_pdf')) ?></a>
    </header>

    <dl class="sd-figures">
        <div><dt><?= sdH(sd('fig_completed')) ?></dt><dd class="num"><span data-kpi="completed"><?= $statCompleted ?></span><small> / <?= count($enrolledCourses) ?></small></dd></div>
        <div><dt><?= sdH(sd('fig_avg')) ?></dt><dd class="num"><?= $statAttempts > 0 ? '<span data-kpi="score">' . sdH(sdScore($statAvgScore)) . '</span><small> %</small>' : '<span data-kpi="score">—</span>' ?></dd></div>
        <div><dt><?= sdH(sd('fig_time')) ?></dt><dd class="num"><span data-kpi="time2"><?= sdH($studyTimeLabel) ?></span></dd></div>
    </dl>

    <section class="sd-block" aria-labelledby="r-courses">
        <h2 id="r-courses" class="sd-h2"><?= sdH(sd('by_course')) ?></h2>
        <?php if (empty($transcriptCourses)): ?>
            <p class="sd-empty"><?= sdH(sd('no_enrolment')) ?></p>
        <?php else: ?>
        <div class="sd-tablewrap">
        <table class="sd-table">
            <thead><tr><th scope="col"><?= sdH(sd('th_course')) ?></th><th scope="col" class="r"><?= sdH(sd('th_progress')) ?></th><th scope="col" class="r"><?= sdH(sd('th_best')) ?></th><th scope="col" class="r"><?= sdH(sd('th_attempts')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($transcriptCourses as $tc): ?>
                <tr>
                    <th scope="row"><?= sdH($tc['course_title']) ?><small><?= sdH($tc['module_title']) ?></small></th>
                    <td class="r num" data-label="<?= sdH(sd('th_progress')) ?>"><?= (int)$tc['progress_percent'] ?> %</td>
                    <td class="r num" data-label="<?= sdH(sd('th_best')) ?>"><?= $tc['best_score'] !== null ? sdH(sdScore($tc['best_score'])) . ' %' : '—' ?></td>
                    <td class="r num" data-label="<?= sdH(sd('th_attempts')) ?>"><?= (int)$tc['attempts'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($myAssignments)): ?>
    <section class="sd-block" aria-labelledby="r-assign">
        <h2 id="r-assign" class="sd-h2"><?= sdH(sd('my_assignments')) ?></h2>
        <div class="sd-tablewrap">
        <table class="sd-table">
            <thead><tr><th scope="col"><?= sdH(sd('th_assignment')) ?></th><th scope="col" class="r"><?= sdH(sd('th_deadline')) ?></th><th scope="col" class="r"><?= sdH(sd('th_status')) ?></th><th scope="col" class="r"><?= sdH(sd('th_score')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($myAssignments as $ma):
                $maSub = $ma['submitted_at'] !== null ? ['submitted_at' => $ma['submitted_at'], 'graded_at' => $ma['graded_at'], 'revision_requested_at' => $ma['revision_requested_at']] : null;
                $maStatus = $maSub === null ? ((!empty($ma['assignment_deadline']) && strtotime($ma['assignment_deadline']) < time()) ? 'missed' : 'todo') : (!empty($ma['revision_requested_at']) && strtotime($ma['revision_requested_at']) >= strtotime($ma['submitted_at']) ? 'revision' : (!empty($ma['graded_at']) ? 'graded' : 'submitted'));
                $maMax = rtrim(rtrim(number_format((float)$ma['assignment_max_score'], 2, ',', ''), '0'), ',');
            ?>
                <tr>
                    <th scope="row"><?= sdH($ma['assignment_title'] ?: $ma['lesson_title']) ?><small><?= sdH($ma['course_title']) ?><?= !empty($ma['is_late']) ? ' · ' . sdH(sd('as_late_tag')) : '' ?></small></th>
                    <td class="r num" data-label="<?= sdH(sd('th_deadline')) ?>"><?= !empty($ma['assignment_deadline']) ? sdH(sdDate($ma['assignment_deadline'], 'date')) : '—' ?></td>
                    <td class="r" data-label="<?= sdH(sd('th_status')) ?>"><?= sdH(sd('as_status_' . $maStatus)) ?></td>
                    <td class="r num" data-label="<?= sdH(sd('th_score')) ?>">
                        <?php if ($ma['score'] !== null): ?><?= sdH(rtrim(rtrim(number_format((float)$ma['score'], 2, ',', ''), '0'), ',')) ?> / <?= sdH($maMax) ?><?php else: ?>—<?php endif; ?>
                        <?php if (!empty($ma['feedback'])): ?><details class="sd-meta"><summary><?= sdH(sd('as_feedback_link')) ?></summary><?= nl2br(sdH($ma['feedback'])) ?></details><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($lessonScores)): ?>
    <section class="sd-block" aria-labelledby="r-lessons">
        <h2 id="r-lessons" class="sd-h2"><?= sdH(sd('lesson_quizzes')) ?></h2>
        <div class="sd-tablewrap">
        <table class="sd-table">
            <thead><tr><th scope="col"><?= sdH(sd('th_lesson')) ?></th><th scope="col" class="r"><?= sdH(sd('th_date')) ?></th><th scope="col" class="r"><?= sdH(sd('th_score')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($lessonScores as $ls): ?>
                <tr>
                    <th scope="row"><?= sdH($ls['lesson_title']) ?><small><?= sdH($ls['course_title']) ?></small></th>
                    <td class="r num" data-label="<?= sdH(sd('th_date')) ?>"><?= sdH(sdDate($ls['completed_at'], 'date')) ?></td>
                    <td class="r num" data-label="<?= sdH(sd('th_score')) ?>"><?= (int)$ls['score'] ?> %</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
    <?php endif; ?>

    <?php $failed = array_values(array_filter($myAttempts, fn($a) => (int)$a['passed'] === 0)); ?>
    <?php if (!empty($failed)): ?>
    <section class="sd-block" aria-labelledby="r-failed">
        <h2 id="r-failed" class="sd-h2"><?= sdH(sd('failed_attempts')) ?></h2>
        <p class="sd-meta"><?= sdH(sd('failed_hint')) ?></p>
        <ul class="sd-list">
            <?php foreach ($failed as $a): ?>
            <li class="sd-row sd-ev">
                <div class="sd-ev-when num"><strong><?= sdH(sdDate($a['attempted_at'], 'short')) ?></strong><small><?= sdH(sdDate($a['attempted_at'], 'time')) ?></small></div>
                <div class="sd-ev-main"><h3 class="sd-course-title"><?= sdH($a['course_title']) ?></h3></div>
                <div class="sd-ev-score"><span class="num sd-score"><?= sdH(sdScore($a['score'])) ?> %</span><span class="sd-verdict is-fail"><?= sdIcon('x', 14) ?><?= sdH(sd('not_passed')) ?></span></div>
                <div class="sd-ev-act"><a class="btn btn-ghost btn-sm" href="/student/certification-report.php?attempt_id=<?= (int)$a['id'] ?>" target="_blank" rel="noopener"><?= sdH(sd('see_report')) ?></a></div>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
</section>

<!-- =========================================================================
     SECTION 7: CERTIFICATES & ACHIEVEMENTS
     ========================================================================= -->
<section id="tab-certs" class="sd-panel hidden" aria-labelledby="certs-title">
    <header class="sd-head">
        <h1 id="certs-title"><?= sdH(sd('nav_certs')) ?></h1>
        <p class="sd-lede"><?= sdH(sd('certs_lede')) ?></p>
    </header>

    <?php if (empty($myCertificates)): ?>
        <div class="sd-emptybox"><p><?= sdH(sd('no_cert_long')) ?></p><button type="button" class="btn btn-ghost" data-nav="courses" data-sub="mine"><?= sdH(sd('my_courses')) ?></button></div>
    <?php else: ?>
    <ul class="sd-certs">
        <?php foreach ($myCertificates as $cert): ?>
        <li class="sd-cert">
            <div class="sd-cert-seal" aria-hidden="true"><?= Brand::mark(28) ?></div>
            <div class="sd-cert-main">
                <p class="sd-kicker"><?= sdH(sd('cert_kicker')) ?></p>
                <h3 class="sd-cert-title"><?= sdH($cert['course_title'] ?? sd('unknown_course')) ?></h3>
                <p class="sd-meta"><?= sdH(sd('issued_to', ['name' => $user['name']])) ?> · <span class="num"><?= sdH(sdDate($cert['issued_at'], 'year')) ?></span></p>
                <p class="sd-code"><?= sdH(sd('code')) ?> <span class="num"><?= sdH($cert['certificate_code']) ?></span></p>
            </div>
            <div class="sd-cert-act">
                <a class="btn btn-primary btn-sm" href="/certificate.php?code=<?= urlencode($cert['certificate_code']) ?>"><?= sdH(sd('open_print')) ?></a>
                <a class="btn btn-text" href="/verify.php?code=<?= urlencode($cert['certificate_code']) ?>" target="_blank" rel="noopener"><?= sdH(sd('verify')) ?></a>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <section class="sd-block" aria-labelledby="ach-title">
        <div class="sd-block-head">
            <h2 id="ach-title"><?= sdH(sd('achievements')) ?></h2>
            <span class="sd-meta num"><?= sdH(sd('n_of_total', ['n' => count($myBadges), 'total' => count($allBadgesConfig)])) ?></span>
        </div>
        <ul class="sd-badges" id="badges-grid-container">
            <?php foreach ($allBadgesConfig as $key => $config):
                $isEarned = isset($earnedBadgesLookup[$key]);
                $bt = $badgeText[$key] ?? [$config['title'], $config['title'], $config['desc'], $config['desc']];
                $title = $bt[$lang === 'en' ? 1 : 0];
                $desc = $bt[$lang === 'en' ? 3 : 2];
            ?>
            <li class="sd-badge <?= $isEarned ? 'is-on' : 'is-off' ?>" data-badge-key="<?= sdH($key) ?>">
                <span class="sd-badge-ic"><?= $isEarned ? sdIcon('cert', 22) : sdIcon('lock', 20) ?></span>
                <div>
                    <h3><?= sdH($title) ?></h3>
                    <p><?= sdH($desc) ?></p>
                    <p class="sd-badge-state num"><?= $isEarned ? sdH(sd('earned_on', ['date' => sdDate($earnedBadgesLookup[$key], 'date')])) : sdH(sd('not_yet')) ?></p>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
</section>

<!-- =========================================================================
     SECTION 8: PROFILE
     ========================================================================= -->
<section id="tab-profile" class="sd-panel hidden" aria-labelledby="profile-title">
    <header class="sd-head"><h1 id="profile-title"><?= sdH(sd('profile_title')) ?></h1><p class="sd-lede"><?= sdH(sd('profile_lede')) ?></p></header>

    <div class="sd-profile">
        <div class="sd-profile-avatar">
            <img id="profile-avatar-preview" src="<?= sdH($avatarSrc) ?>" alt="" width="96" height="96">
            <div>
                <input type="file" id="avatar-input" accept="image/*" class="hidden" onchange="uploadAvatar()">
                <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('avatar-input').click()"><?= sdH(sd('upload_photo')) ?></button>
                <p class="hint"><?= sdH(sd('photo_hint')) ?></p>
            </div>
        </div>

        <div class="field">
            <label for="profile-email"><?= sdH(sd('email')) ?></label>
            <input type="email" id="profile-email" class="input" disabled value="<?= sdH($user['email']) ?>">
            <span class="hint"><?= sdH(sd('email_locked')) ?></span>
        </div>
        <div class="field">
            <label for="profile-name"><?= sdH(sd('full_name')) ?></label>
            <input type="text" id="profile-name" class="input" value="<?= sdH($user['name']) ?>" autocomplete="name">
        </div>
        <div class="field">
            <label for="profile-matricule"><?= sdH(sd('matricule')) ?></label>
            <input type="text" id="profile-matricule" class="input sd-mat" value="<?= sdH($user['matricule'] ?? '') ?>" placeholder="<?= sdH(sd('mat_ph')) ?>" maxlength="7" autocomplete="off" autocapitalize="characters" spellcheck="false" oninput="clearMatError()">
            <div id="profile-matricule-error" class="hint err hidden" role="alert"></div>
            <span class="hint"><?= sdH(sd('matricule_hint')) ?></span>
        </div>
        <div class="field">
            <label><?= sdH($lang === 'en' ? 'Security' : 'Sécurité') ?></label>
            <a class="btn btn-ghost btn-sm" href="/account/security.php"><?= sdH(SmsGateway::enabled() ? ($lang === 'en' ? 'Phone number and two-factor authentication' : 'Téléphone et double authentification') : ($lang === 'en' ? 'Two-factor authentication' : 'Double authentification')) ?></a>
        </div>
        <div class="sd-profile-act">
            <button type="button" class="btn btn-primary" onclick="updateProfileName()"><?= sdH(sd('save')) ?></button>
            <span id="profile-status" class="sd-saved hidden" role="status"><?= sdIcon('check', 14) ?><?= sdH(sd('saved')) ?></span>
        </div>

        <div class="sd-prefs">
            <h2 class="sd-h2"><?= sdH(sd('preferences')) ?></h2>
            <div class="sd-pref"><span><?= sdH(sd('language')) ?></span>
                <div class="seg" role="group" aria-label="<?= sdH(sd('language')) ?>">
                    <a href="#" data-lang="fr" <?= $lang === 'fr' ? 'aria-current="true"' : '' ?>>FR</a>
                    <a href="#" data-lang="en" <?= $lang === 'en' ? 'aria-current="true"' : '' ?>>EN</a>
                </div>
            </div>
            <div class="sd-pref"><span><?= sdH(sd('theme')) ?></span>
                <button type="button" class="sd-iconbtn" data-dark-toggle aria-label="<?= sdH(sd('theme')) ?>"><span class="ic-sun"><?= sdIcon('sun') ?></span><span class="ic-moon"><?= sdIcon('moon') ?></span></button>
            </div>
            <a class="sd-logout sd-logout-m" href="/logout.php"><?= sdIcon('out', 16) ?><span><?= sdH(sd('logout')) ?></span></a>
        </div>
    </div>
</section>

</main>

<!-- =========================================================================
     SECTION 9: THE READER — a calm full-screen layer
     ========================================================================= -->
<div id="study-modal" class="rd hidden" role="dialog" aria-modal="true" aria-labelledby="study-lesson-title">
    <header id="study-header" class="rd-top">
        <button type="button" class="rd-leave" onclick="closeStudyModal()"><?= sdIcon('arrow', 16) ?><span><?= sdH(sd('leave')) ?></span></button>
        <div class="rd-crumb">
            <span id="study-course-module" class="rd-crumb-mod"></span>
            <span id="study-course-title" class="rd-crumb-title"></span>
        </div>
        <div class="rd-tools">
            <span class="rd-timer" title="<?= sdH(sd('study_time')) ?>"><span class="sr-only"><?= sdH(sd('study_time')) ?></span><span id="lesson-session-timer" class="sv-timer num">00:00</span></span>
            <button type="button" class="rd-toolbtn" id="btn-outline" onclick="toggleOutline()" aria-expanded="true" aria-controls="study-sidebar"><?= sdIcon('list', 18) ?><span><?= sdH(sd('outline')) ?></span></button>
            <button type="button" class="rd-toolbtn" id="btn-companion" onclick="toggleCompanion()" aria-expanded="false" aria-controls="study-companion-panel"><?= sdIcon('chat', 18) ?><span><?= sdH(sd('companion')) ?></span></button>
        </div>
        <div class="rd-progress" aria-hidden="true"><i id="rd-progress-bar"></i></div>
    </header>

    <div class="rd-body" id="rd-body">
        <nav id="study-sidebar" class="rd-outline" aria-label="<?= sdH(sd('outline')) ?>">
            <div class="rd-outline-head">
                <p class="sd-kicker"><?= sdH(sd('course_plan')) ?></p>
                <p class="rd-outline-count num" id="rd-outline-count"></p>
                <div class="sd-bar" aria-hidden="true"><i id="rd-course-bar"></i></div>
            </div>
            <div id="study-chapters-container" class="rd-chapters"></div>
        </nav>
        <div class="rd-scrim" onclick="closeSheets()"></div>

        <main id="study-viewer-content" class="rd-main" tabindex="-1">
            <article class="rd-article">
                <div id="lesson-viewer-header" class="rd-head hidden">
                    <p class="sd-kicker"><span id="study-lesson-badge"></span><span id="rd-readtime"></span></p>
                    <h1 id="study-lesson-title"></h1>
                </div>

                <div id="study-media-container" class="rd-media"></div>

                <div id="lesson-complete-bar" class="rd-foot hidden">
                    <div>
                        <p class="rd-foot-title"><?= sdH(sd('lesson_progress')) ?></p>
                        <p id="lesson-complete-hint" class="sd-meta"><?= sdH(sd('js_hint_finish')) ?></p>
                    </div>
                    <p id="lesson-complete-status" class="sd-verdict is-pass hidden"><?= sdIcon('check', 14) ?><?= sdH(sd('lesson_done')) ?></p>
                    <button type="button" id="mark-lesson-complete-btn" class="btn btn-primary"><?= sdH(sd('mark_done')) ?></button>
                </div>

                <section id="lesson-quiz-locked" class="rd-card hidden">
                    <h2 class="rd-card-title"><?= sdH(sd('lesson_quiz')) ?></h2>
                    <p class="sd-meta"><?= sdH(sd('quiz_locked_text')) ?></p>
                    <p id="lesson-content-progress" class="sd-meta num"></p>
                </section>

                <section id="lesson-quiz-container" class="rd-card hidden">
                    <h2 class="rd-card-title"><?= sdH(sd('lesson_quiz')) ?></h2>
                    <div id="lesson-completed-success-msg" class="sd-note is-ok hidden"><?= sdIcon('check', 16) ?><span><?= sdH(sd('quiz_unlocked')) ?></span></div>
                    <p id="lesson-quiz-hint" class="sd-meta"><?= sdH(sd('quiz_hint')) ?></p>
                    <p id="lesson-quiz-complete-msg" class="sd-verdict is-pass hidden"><?= sdIcon('check', 14) ?><?= sdH(sd('quiz_done')) ?></p>
                    <div id="lesson-quiz-active">
                        <form id="lesson-quiz-form">
                            <input type="hidden" id="quiz-lesson-id" name="lesson_id" value="">
                            <input type="hidden" id="quiz-question-id" name="question_id" value="">
                            <div id="lesson-quiz-question-box"></div>
                            <div class="rd-quiz-act">
                                <button type="submit" id="lesson-quiz-submit-btn" class="btn btn-primary"><?= sdH(sd('submit')) ?></button>
                                <span id="lesson-quiz-feedback" class="rd-feedback" role="status"></span>
                            </div>
                        </form>
                    </div>
                </section>

                <section id="lesson-assignment-container" class="rd-card hidden">
                    <header class="rd-card-head">
                        <div>
                            <p class="sd-kicker"><?= sdH(sd('assignment')) ?></p>
                            <h2 id="assignment-display-title" class="rd-card-title"><?= sdH(sd('assignment_default')) ?></h2>
                        </div>
                        <span id="assignment-display-deadline" class="sd-meta num"></span>
                    </header>
                    <div id="assignment-display-instructions" class="rd-instr"></div>

                    <form id="student-assignment-form" onsubmit="submitStudentAssignment(event)">
                        <input type="hidden" id="assignment-lesson-id" value="">
                        <div id="assignment-existing-status" class="sd-note is-ok hidden">
                            <?= sdIcon('check', 16) ?>
                            <div><strong id="assignment-status-text"><?= sdH(sd('assignment_sent')) ?></strong><div id="assignment-existing-details" class="rd-sub"></div></div>
                        </div>
                        <div class="rd-two">
                            <div class="field"><label for="assignment-student-name"><?= sdH(sd('full_name')) ?> *</label>
                                <input type="text" id="assignment-student-name" class="input" required value="<?= sdH($user['name'] ?? '') ?>"></div>
                            <div class="field"><label for="assignment-student-matricule"><?= sdH(sd('matricule')) ?> *</label>
                                <input type="text" id="assignment-student-matricule" class="input sd-mat" required value="<?= sdH($user['matricule'] ?? '') ?>" placeholder="<?= sdH(sd('mat_ph')) ?>" maxlength="7"></div>
                        </div>
                        <div class="rd-two">
                            <div id="assignment-file-wrapper" class="field"><label for="assignment-file-input"><?= sdH(sd('as_file')) ?></label>
                                <input type="file" id="assignment-file-input" class="input" accept=".pdf,.docx,.doc">
                                <span id="assignment-allowed-types-label" class="hint"></span></div>
                            <div id="assignment-link-wrapper" class="field"><label for="assignment-link-input"><?= sdH(sd('as_link')) ?></label>
                                <input type="url" id="assignment-link-input" class="input" placeholder="https://…">
                                <span class="hint"><?= sdH(sd('as_link_hint')) ?></span></div>
                        </div>
                        <div class="field"><label for="assignment-comment-input"><?= sdH(sd('as_comment')) ?></label>
                            <textarea id="assignment-comment-input" class="input" rows="2"></textarea></div>
                        <div class="rd-quiz-act">
                            <button type="submit" id="assignment-submit-btn" class="btn btn-primary"><span><?= sdH(sd('as_submit')) ?></span></button>
                            <span id="assignment-form-message" class="rd-feedback" role="status"></span>
                        </div>
                    </form>
                </section>
            </article>
        </main>

        <aside id="study-companion-panel" class="rd-side hidden" aria-label="<?= sdH(sd('companion')) ?>">
            <div class="rd-side-head" role="tablist">
                <button type="button" role="tab" id="companion-btn-ai" class="rd-stab is-on" onclick="switchCompanionTab('ai')"><?= sdH(sd('tab_ai')) ?></button>
                <button type="button" role="tab" id="companion-btn-notes" class="rd-stab" onclick="switchCompanionTab('notes')"><?= sdH(sd('tab_notes')) ?></button>
                <button type="button" role="tab" id="companion-btn-qa" class="rd-stab" onclick="switchCompanionTab('qa')"><?= sdH(sd('tab_qa')) ?></button>
                <button type="button" class="rd-side-close" onclick="toggleCompanion()" aria-label="<?= sdH(sd('close')) ?>"><?= sdIcon('x', 18) ?></button>
            </div>

            <div class="rd-side-body">
                <div id="companion-tab-ai" class="rd-pane">
                    <div id="ai-chat-messages" class="rd-chat" aria-live="polite">
                        <div class="rd-msg is-bot"><?= sdH(sd('ai_hello')) ?></div>
                    </div>
                    <div class="rd-chips">
                        <button type="button" onclick="triggerAiAction('summarize')"><?= sdH(sd('ai_summarize')) ?></button>
                        <button type="button" onclick="triggerAiAction('explain')"><?= sdH(sd('ai_explain')) ?></button>
                        <button type="button" onclick="triggerAiAction('generate_quiz')"><?= sdH(sd('ai_quiz')) ?></button>
                    </div>
                    <form id="ai-chat-form" class="rd-chatform" onsubmit="sendAiMessage(event)">
                        <label class="sr-only" for="ai-chat-input"><?= sdH(sd('ai_ph')) ?></label>
                        <input type="text" id="ai-chat-input" class="input" placeholder="<?= sdH(sd('ai_ph')) ?>" autocomplete="off">
                        <button type="submit" class="btn btn-primary btn-sm" aria-label="<?= sdH(sd('send')) ?>"><?= sdIcon('arrow', 16) ?></button>
                    </form>
                </div>

                <div id="companion-tab-notes" class="rd-pane hidden">
                    <div id="video-notes-section">
                        <p class="sd-meta" id="notes-intro"><?= sdH(sd('notes_intro')) ?></p>
                        <div id="video-notes-list" class="rd-notes">
                            <p id="no-notes-msg" class="sd-meta"><?= sdH(sd('no_notes')) ?></p>
                        </div>
                        <form id="video-note-form" class="rd-noteform">
                            <div class="rd-noterow">
                                <div class="field" id="note-ts-field"><label for="note-timestamp"><?= sdH(sd('note_time')) ?></label>
                                    <input type="text" id="note-timestamp" class="input num" placeholder="04:32" maxlength="8" inputmode="numeric"></div>
                                <div class="field rd-grow"><label for="note-text"><?= sdH(sd('note_text')) ?></label>
                                    <input type="text" id="note-text" class="input" required></div>
                            </div>
                            <button type="submit" class="btn btn-ghost btn-sm"><?= sdH(sd('add_note')) ?></button>
                        </form>
                    </div>
                </div>

                <div id="companion-tab-qa" class="rd-pane hidden">
                    <div id="lesson-qa-container" class="rd-qa">
                        <div id="lesson-comments-list" class="rd-comments"></div>
                        <form id="lesson-comment-form" class="rd-chatform">
                            <input type="hidden" id="comment-lesson-id" value="">
                            <label class="sr-only" for="comment-input"><?= sdH(sd('qa_ph')) ?></label>
                            <input type="text" id="comment-input" class="input" placeholder="<?= sdH(sd('qa_ph')) ?>" required>
                            <button type="submit" class="btn btn-ghost btn-sm"><?= sdH(sd('send')) ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

<!-- =========================================================================
     SECTION 10: DIALOGS — final exam, enrolment key, library text
     ========================================================================= -->
<div id="final-exam-modal" class="sd-modal hidden" role="dialog" aria-modal="true" aria-labelledby="exam-course-title">
    <div class="sd-sheet sd-sheet-wide">
        <header class="sd-sheet-head">
            <div>
                <p class="sd-kicker"><?= sdH(sd('final_exam')) ?></p>
                <h2 id="exam-course-title">—</h2>
                <p class="sd-meta"><?= sdH(sd('exam_threshold')) ?> <span id="exam-attempts-info"></span></p>
            </div>
            <div class="sd-exam-clock">
                <span class="sd-meta"><?= sdH(sd('time_left')) ?></span>
                <span id="exam-timer" class="sv-timer num">90:00</span>
                <button type="button" class="btn btn-text sd-danger" onclick="abandonExam()"><?= sdH(sd('abandon')) ?></button>
            </div>
        </header>
        <form id="final-exam-form">
            <input type="hidden" id="exam-course-id" name="course_id" value="">
            <div id="exam-questions-container" class="sd-exam-q"></div>
            <footer class="sd-sheet-foot">
                <span id="exam-error-alert" class="sd-feedback is-bad hidden" role="alert"><?= sdH(sd('exam_answer_all')) ?></span>
                <button type="submit" class="btn btn-primary btn-lg"><?= sdH(sd('exam_submit')) ?></button>
            </footer>
        </form>
    </div>
</div>

<div id="enroll-modal" class="sd-modal hidden" role="dialog" aria-modal="true" aria-labelledby="enroll-title">
    <div class="sd-sheet">
        <h2 id="enroll-title"><?= sdH(sd('key_title')) ?></h2>
        <p class="sd-meta"><?= sdH(sd('key_text')) ?></p>
        <form id="enroll-form">
            <input type="hidden" id="enroll-course-id" name="course_id" value="">
            <div class="field"><label for="enroll-key-input"><?= sdH(sd('key_label')) ?></label>
                <input type="text" id="enroll-key-input" name="enrollment_key" class="input" required placeholder="ALGO2026" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="go"></div>
            <div id="enroll-error" class="hint err hidden" role="alert"></div>
            <div class="sd-sheet-act">
                <button type="button" class="btn btn-ghost" onclick="toggleModal('enroll-modal')"><?= sdH(sd('cancel')) ?></button>
                <button type="submit" class="btn btn-primary"><?= sdH(sd('enrol')) ?></button>
            </div>
        </form>
    </div>
</div>

<div id="lib-modal" class="sd-modal hidden" role="dialog" aria-modal="true" aria-labelledby="lib-modal-title">
    <div class="sd-sheet sd-sheet-wide">
        <header class="sd-sheet-head"><h2 id="lib-modal-title"></h2><button type="button" class="sd-iconbtn" onclick="toggleModal('lib-modal')" aria-label="<?= sdH(sd('close')) ?>"><?= sdIcon('x', 18) ?></button></header>
        <div id="lib-modal-body" class="rd-prose"></div>
    </div>
</div>

<!-- =========================================================================
     SECTION 11: COMPULSORY MATRICULE (kept exactly: blocks until filled)
     ========================================================================= -->
<?php
require_once __DIR__ . '/../lib/Matricule.php';
$matDuplicate = !empty($user['matricule']) && Matricule::isTaken($pdo, Matricule::normalize((string)$user['matricule']), (int)$user['id'], true);
if (empty($user['matricule']) || $matDuplicate): ?>
<div id="matricule-alert-modal" class="sd-modal sd-modal-hard" role="alertdialog" aria-modal="true" aria-labelledby="mat-title">
    <div class="sd-sheet">
        <p class="sd-kicker"><?= sdH(sd($matDuplicate ? 'mat_dup_kicker' : 'mat_kicker')) ?></p>
        <h2 id="mat-title"><?= sdH(sd($matDuplicate ? 'mat_dup_title' : 'mat_title')) ?></h2>
        <p class="sd-meta"><?= sdH(sd($matDuplicate ? 'mat_dup_text' : 'mat_text')) ?></p>
        <form id="matricule-alert-form" onsubmit="submitQuickMatricule(event)">
            <div class="field"><label for="quick-matricule-input"><?= sdH(sd('mat_label')) ?></label>
                <input type="text" id="quick-matricule-input" class="input sd-mat" required value="<?= $matDuplicate ? sdH((string)$user['matricule']) : '' ?>" placeholder="<?= sdH(sd('mat_ph')) ?>" maxlength="7" autocomplete="off" autocapitalize="characters" spellcheck="false" oninput="clearMatError()"></div>
            <div id="quick-matricule-error" class="hint err hidden" role="alert"></div>
            <button type="submit" class="btn btn-primary btn-lg sd-full"><?= sdH(sd('mat_save')) ?></button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- =========================================================================
     SECTION 12: SCRIPTS
     ========================================================================= -->
<script>
window.SV_T = <?= json_encode(sdJs(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.SV_LANG = <?= json_encode($lang) ?>;
</script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/pdf-reader.js"></script>
<script src="/assets/js/video-chain.js"></script>
<script src="/assets/js/student.js"></script>
<?php
$matBlocks = empty($user['matricule']) || !empty($matDuplicate);
require_once __DIR__ . '/../lib/PhonePrompt.php';
$phoneBlocks = PhonePrompt::render($user, $lang, $matBlocks);   // the matricule form comes first, then the phone number
require_once __DIR__ . '/../lib/Tour.php'; Tour::render('student', $lang, $matBlocks || $phoneBlocks);
?>
</body>
</html>
