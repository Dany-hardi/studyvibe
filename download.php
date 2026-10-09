<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);


/**
 * Sert les fichiers uploadés (PDF, avatars) après vérification des droits.
 * L'accès direct au dossier uploads/ est bloqué par .htaccess.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/MediaStore.php';

$type = (string)($_GET['type'] ?? '');
$file = basename((string)($_GET['file'] ?? ''));

if ($file === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
    http_response_code(400);
    exit('Fichier invalide.');
}

// Covers are public. Live-question pictures are also open to a guest who joined that exam room without an account
// (checked below); every other file needs a signed-in user.
if ($type !== 'cover' && $type !== 'live_question') {
    if (!isLoggedIn()) {
        http_response_code(401);
        exit('Authentification requise.');
    }
    $userId = (int)$_SESSION['user_id'];
    $role   = $_SESSION['user_role'];
} else {
    $userId = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
    $role   = isLoggedIn() ? $_SESSION['user_role'] : '';
}


try {
    $pdo = Database::getInstance();

    if ($type === 'pdf') {
        $stmt = $pdo->prepare("
            SELECT l.id, ch.course_id
            FROM lessons l
            JOIN chapters ch ON ch.id = l.chapter_id
            WHERE l.pdf_path = :file
        ");
        $stmt->execute(['file' => $file]);
        $lessonRows = $stmt->fetchAll();

        if (!$lessonRows) {
            http_response_code(404);
            exit('Fichier introuvable.');
        }

        // A student gets the file only through a lesson of a course they are enrolled in and have reached.
        if ($role === 'student') {
            require_once __DIR__ . '/lib/LessonFlow.php';
            $allowed = false;
            foreach ($lessonRows as $lr) {
                $enr = $pdo->prepare('SELECT 1 FROM enrollments WHERE student_id = :s AND course_id = :c');
                $enr->execute(['s' => $userId, 'c' => $lr['course_id']]);
                if ($enr->fetchColumn() && LessonFlow::blockerFor($pdo, $userId, (int)$lr['course_id'], (int)$lr['id']) === null) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                http_response_code(403);
                exit('Accès refusé : terminez d’abord les leçons précédentes.');
            }
        }

        $path = __DIR__ . '/uploads/pdfs/' . $file;
        $mime = 'application/pdf';

    } elseif ($type === 'avatar') {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE avatar_path = :file LIMIT 1");
        $stmt->execute(['file' => $file]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            exit('Fichier introuvable.');
        }

        $path = __DIR__ . '/uploads/avatars/' . $file;
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'application/octet-stream',
        };

    } elseif ($type === 'cover') {
        $path = __DIR__ . '/uploads/course-covers/' . $file;
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            default       => 'image/jpeg',
        };

    } elseif ($type === 'assignment') {
        $stmt = $pdo->prepare("SELECT student_id, submitted_file_name FROM lesson_assignment_submissions WHERE submitted_file_path = :file LIMIT 1");
        $stmt->execute(['file' => $file]);
        $sub = $stmt->fetch();
        if (!$sub) {
            http_response_code(404);
            exit('Fichier introuvable.');
        }

        if ($role !== 'teacher' && $role !== 'promoter' && $userId !== (int)$sub['student_id']) {
            http_response_code(403);
            exit('Accès non autorisé.');
        }

        $path = __DIR__ . '/uploads/assignments/' . $file;
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'pdf'  => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc'  => 'application/msword',
            default => 'application/octet-stream',
        };

    } elseif ($type === 'library') {
        try {
            $stmt = $pdo->prepare("SELECT id FROM course_library_items WHERE file_path = :file LIMIT 1");
            $stmt->execute(['file' => $file]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                exit('Fichier introuvable.');
            }
        } catch (Throwable $e) {
            http_response_code(404);
            exit('Fichier introuvable ou table non disponible.');
        }

        $path = __DIR__ . '/uploads/library/' . $file;
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'pdf'  => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc'  => 'application/msword',
            'zip'  => 'application/zip',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };

    } elseif ($type === 'live_question') {
        // Who may see a question picture: the teacher who owns the session, a signed-in student, or anybody holding a
        // registration for the exam room the picture belongs to (guests have no account).
        $own = $pdo->prepare("
            SELECT s.session_code, s.teacher_id FROM live_eval_questions q
            JOIN live_eval_sessions s ON s.id = q.session_id
            WHERE q.image_path = :file LIMIT 1
        ");
        $own->execute(['file' => $file]);
        $owner = $own->fetch();
        if (!$owner) {
            http_response_code(404);
            exit('Fichier introuvable.');
        }
        $registered = isset($_SESSION['live_registrations'][$owner['session_code']]);
        $teacherOwns = $role === 'teacher' && $userId === (int)$owner['teacher_id'];
        if (!$registered && !$teacherOwns && $role !== 'student' && $role !== 'promoter') {
            http_response_code(403);
            exit('Accès refusé.');
        }
        $path = __DIR__ . '/uploads/live_questions/' . $file;
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'image/jpeg',
        };

    } else {
        http_response_code(400);
        exit('Type de fichier non supporté.');
    }

    if (!is_file($path)) {
        MediaStore::restore($pdo, $type . '/' . $file, $path);
    }

    if (!is_file($path)) {
        if ($type === 'pdf') {
            $restoreStmt = $pdo->prepare("SELECT pdf_data FROM lessons WHERE pdf_path = :file AND pdf_data IS NOT NULL LIMIT 1");
            $restoreStmt->execute(['file' => $file]);
            $blob = $restoreStmt->fetch();
            if ($blob && !empty($blob['pdf_data'])) {
                @mkdir(dirname($path), 0777, true);
                @file_put_contents($path, $blob['pdf_data']);
            }
        } elseif ($type === 'cover') {
            $restoreStmt = $pdo->prepare("SELECT cover_image_data FROM courses WHERE cover_image = :file AND cover_image_data IS NOT NULL LIMIT 1");
            $restoreStmt->execute(['file' => $file]);
            $blob = $restoreStmt->fetch();
            if ($blob && !empty($blob['cover_image_data'])) {
                @mkdir(dirname($path), 0777, true);
                @file_put_contents($path, $blob['cover_image_data']);
            }
        }
    }

    if (!is_file($path)) {
        if ($type === 'avatar') {
            header('Location: https://www.gravatar.com/avatar/' . md5($file) . '?d=mp', true, 302);
            exit;
        }
        if ($type === 'cover') {
            header('Content-Type: image/svg+xml');
            header('Cache-Control: public, max-age=86400');
            echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 450" width="800" height="450">
                <rect width="100%" height="100%" fill="#FDFCF7"/>
                <defs>
                    <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#B5482A"/>
                        <stop offset="100%" stop-color="#B5482A"/>
                    </linearGradient>
                </defs>
                <rect x="20" y="20" width="760" height="410" rx="16" fill="url(#g)" opacity="0.06"/>
                <circle cx="400" cy="225" r="90" fill="url(#g)" opacity="0.08"/>
                <text x="50%" y="233" font-family="system-ui, -apple-system, sans-serif" font-size="28" font-weight="600" fill="#B5482A" text-anchor="middle" opacity="0.5">StudyVibe Course</text>
            </svg>';
            exit;
        }
        http_response_code(404);
        exit('Fichier introuvable.');
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($path));
    header('Content-Disposition: inline; filename="' . $file . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;

} catch (PDOException $e) {
    logServerError($e, 'download');
    http_response_code(500);
    exit('Erreur serveur.');
}
