<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé.'
    ]);
    exit;
}

$studentId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // requireCsrf();
}

$pdo = Database::getInstance();

try {
    // 1. Handle Name Update
    if (isset($_POST['name'])) {
        $name = trim((string)$_POST['name']);
        
        if (empty($name)) {
            echo json_encode([
                'success' => false,
                'message' => 'Le nom complet ne peut pas être vide.'
            ]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE users SET name = :name WHERE id = :id");
        $stmt->execute(['name' => $name, 'id' => $studentId]);

        echo json_encode([
            'success' => true,
            'message' => 'Nom mis à jour avec succès.'
        ]);
        exit;
    }

    // 2. Handle Avatar Upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['avatar']['tmp_name'];
        $fileName = $_FILES['avatar']['name'];
        $fileSize = $_FILES['avatar']['size'];
        $fileType = $_FILES['avatar']['type'];
        
        // Validation constraints
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validate file size (2 MB max)
        if ($fileSize > 2 * 1024 * 1024) {
            echo json_encode([
                'success' => false,
                'message' => 'Le fichier est trop volumineux. La limite est de 2 Mo.'
            ]);
            exit;
        }

        // Validate type & extension
        if (in_array($fileExtension, $allowedExtensions, true) && in_array($fileType, $allowedTypes, true)) {
            $uploadDir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Create a unique hached filename
            $newFileName = md5(uniqid()) . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                // Delete previous avatar file if exists
                $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = :id");
                $stmt->execute(['id' => $studentId]);
                $oldAvatar = $stmt->fetchColumn();

                if ($oldAvatar && is_file($uploadDir . $oldAvatar)) {
                    unlink($uploadDir . $oldAvatar);
                }

                // Update path in database
                $stmt = $pdo->prepare("UPDATE users SET avatar_path = :avatar_path WHERE id = :id");
                $stmt->execute(['avatar_path' => $newFileName, 'id' => $studentId]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Avatar mis à jour.',
                    'avatar_path' => $newFileName
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors du déplacement du fichier téléchargé.'
                ]);
                exit;
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Type de fichier non autorisé. Seuls JPG, PNG, GIF sont acceptés.'
            ]);
            exit;
        }
    }

    echo json_encode([
        'success' => false,
        'message' => 'Aucune donnée valide reçue.'
    ]);
    exit;

} catch (PDOException $e) {
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'update-profile.php');
}
