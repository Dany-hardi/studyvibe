<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);


/**
 * Sert les fichiers uploadés (PDF, avatars) après vérification des droits.
 * L'accès direct au dossier uploads/ est bloqué par .htaccess.
 */

require_once __DIR__ . '/auth.php';

$type = (string)($_GET['type'] ?? '');
$file = basename((string)($_GET['file'] ?? ''));

if ($file === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
    http_response_code(400);
    exit('Fichier invalide.');
}

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Authentification requise.');
}

$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['user_role'];

try {
    $pdo = Database::getInstance();

    if ($type === 'pdf') {
        $stmt = $pdo->prepare("
        SELECT l.id
        FROM lessons l
        JOIN chapters ch ON l.chapter_id = ch.id
        JOIN courses c ON ch.course_id = c.id
        LEFT JOIN enrollments e ON e.course_id = c.id AND e.student_id = :uid
        WHERE l.pdf_path = :file
          AND (
              :role = 'promoter'
              OR (:role2 = 'teacher' AND c.teacher_id = :uid2)
              OR (:role3 = 'student' AND e.id IS NOT NULL)
          )
        LIMIT 1
    ");
    $stmt->execute([
        'file'  => $file,
        'uid'   => $userId,
        'uid2'  => $userId,
        'role'  => $role,
        'role2' => $role,
        'role3' => $role,
    ]);        if (!$stmt->fetch()) {
            http_response_code(403);
            exit('Accès refusé.');
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

    } else {
        http_response_code(400);
        exit('Type de fichier non supporté.');
    }

    if (!is_file($path)) {
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
