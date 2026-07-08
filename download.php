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

if ($type !== 'cover') {
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
            SELECT id
            FROM lessons
            WHERE pdf_path = :file
            LIMIT 1
        ");
        $stmt->execute(['file' => $file]);
        $lesson = $stmt->fetch();

        if (!$lesson) {
            http_response_code(404);
            exit('Fichier introuvable.');
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
                        <stop offset="0%" stop-color="#004B23"/>
                        <stop offset="100%" stop-color="#006630"/>
                    </linearGradient>
                </defs>
                <rect x="20" y="20" width="760" height="410" rx="16" fill="url(#g)" opacity="0.06"/>
                <circle cx="400" cy="225" r="90" fill="url(#g)" opacity="0.08"/>
                <text x="50%" y="233" font-family="system-ui, -apple-system, sans-serif" font-size="28" font-weight="600" fill="#004B23" text-anchor="middle" opacity="0.5">StudyVibe Course</text>
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
