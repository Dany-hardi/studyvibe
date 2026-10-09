<?php
declare(strict_types=1);

/**
 * A student hands in an assignment, or a new version of it (file and/or link, with a comment).
 *
 * The rules (deadline, late work, resubmission, what a file must contain) live in lib/Assignments.php and are enforced here on the
 * server. The student's name and matricule are taken from the account: the form cannot change them.
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Assignments.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/RateLimit.php';
require_once __DIR__ . '/../lib/Notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
Security::requirePostFromSameSite();

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé. Session étudiant requise.']);
    exit;
}

$lang = TranslationService::getLang() === 'en' ? 'en' : 'fr';
$M = [
    'closed'      => ['La date limite est dépassée : le dépôt est fermé pour ce devoir.', 'The deadline has passed: handing in is closed for this assignment.'],
    'graded'      => ['Ce devoir a déjà été noté. Si votre enseignant demande une nouvelle version, vous pourrez la déposer.', 'This assignment has already been marked. If your teacher asks for a new version, you will be able to send it.'],
    'locked'      => ['Votre devoir est déposé. L’enseignant n’autorise pas de nouveau dépôt.', 'Your assignment is handed in. Your teacher does not allow another submission.'],
    'no_matricule'=> ['Renseignez votre matricule dans votre profil avant de déposer un devoir.', 'Add your student number in your profile before handing in an assignment.'],
    'bad_type'    => ['Ce format de fichier n’est pas accepté pour ce devoir.', 'This file format is not accepted for this assignment.'],
    'bad_content' => ['Le contenu du fichier ne correspond pas à son format. Exportez-le à nouveau depuis votre logiciel.', 'The content of the file does not match its format. Export it again from your software.'],
    'too_big'     => ['Le fichier dépasse 20 Mo.', 'The file is larger than 20 MB.'],
    'need_file'   => ['L’enseignant exige un fichier.', 'Your teacher requires a file.'],
    'need_link'   => ['L’enseignant exige un lien web.', 'Your teacher requires a web link.'],
    'need_one'    => ['Joignez un fichier ou indiquez un lien.', 'Attach a file or give a link.'],
    'bad_link'    => ['Ce lien n’est pas valide.', 'This link is not valid.'],
    'upload'      => ['Le transfert du fichier a échoué. Réessayez.', 'The file transfer failed. Please try again.'],
    'server'      => ['Échec de l’enregistrement. Réessayez.', 'Could not save. Please try again.'],
    'not_found'   => ['Devoir introuvable.', 'Assignment not found.'],
    'not_enrolled'=> ['Vous n’êtes pas inscrit au cours correspondant.', 'You are not enrolled in this course.'],
    'too_many'    => ['Trop de dépôts en peu de temps. Patientez un instant.', 'Too many submissions in a short time. Please wait a moment.'],
];
$fail = static function (string $code, int $http = 200) use ($M, $lang): never {
    http_response_code($http);
    echo json_encode(['success' => false, 'error' => $code, 'message' => ($M[$code] ?? ['Erreur.', 'Error.'])[$lang === 'en' ? 1 : 0]]);
    exit;
};

try {
    $pdo = Database::getInstance();
    $student = getCurrentUser();
    $lessonId = (int)($_POST['lesson_id'] ?? 0);
    if (!$student || $lessonId <= 0) {
        $fail('not_found', 404);
    }

    $st = $pdo->prepare(
        "SELECT l.*, ch.course_id FROM lessons l JOIN chapters ch ON ch.id = l.chapter_id WHERE l.id = :id"
    );
    $st->execute(['id' => $lessonId]);
    $lesson = $st->fetch(PDO::FETCH_ASSOC);
    if (!$lesson || empty($lesson['has_assignment'])) {
        $fail('not_found', 404);
    }
    $en = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id = :s AND course_id = :c");
    $en->execute(['s' => $student['id'], 'c' => $lesson['course_id']]);
    if (!$en->fetchColumn()) {
        $fail('not_enrolled', 403);
    }
    if (!RateLimit::allow($pdo, 'asg:' . $student['id'], 12, 600)) {
        $fail('too_many', 429);
    }

    $res = Assignments::submit(
        $pdo, $lesson, $student,
        isset($_FILES['assignment_file']) ? $_FILES['assignment_file'] : null,
        trim((string)($_POST['assignment_link'] ?? $_POST['submitted_link'] ?? '')),
        trim(mb_substr((string)($_POST['student_comment'] ?? ''), 0, 2000))
    );
    if (!$res['ok']) {
        $fail((string)$res['error']);
    }

    // The teacher is told in the dashboard
    $teacher = $pdo->prepare("SELECT teacher_id FROM courses WHERE id = :c");
    $teacher->execute(['c' => $lesson['course_id']]);
    if ($tid = (int)$teacher->fetchColumn()) {
        $attempt = (int)($res['submission']['attempt_count'] ?? 1);
        Notifications::send($pdo, $tid, 'assignment', ($attempt > 1 ? 'Nouvelle version : ' : 'Nouveau devoir : ') . $student['name'], (string)($lesson['assignment_title'] ?: $lesson['title']) . (!empty($res['late']) ? ' (en retard)' : ''), '/teacher/dashboard.php');
    }
    auditLog('assignment_submitted', "Lesson #{$lessonId}" . (!empty($res['late']) ? ' (late)' : ''));

    $sub = $res['submission'];
    echo json_encode([
        'success' => true,
        'message' => 'Votre travail a été déposé avec succès !',
        'late' => !empty($res['late']),
        'submission' => [
            'id' => (int)$sub['id'], 'submitted_at' => $sub['submitted_at'], 'attempt_count' => (int)$sub['attempt_count'], 'is_late' => (int)$sub['is_late'],
        ],
    ]);
} catch (Throwable $e) {
    logServerError($e, 'submit-assignment');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors du dépôt du devoir.']);
}
