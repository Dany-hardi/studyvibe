<?php
/**
 * StudyVibe Academic LMS - Student Profile Update Controller
 *
 * This controller processes requests by students to update their display name
 * and upload profile avatars, checking security boundaries and size constraints.
 *
 * PHP version 8.2
 *
 * @category  Controller
 * @package   StudyVibe\Student
 * @author    StudyVibe Team <development@studyvibe.academic>
 * @copyright 2026 StudyVibe
 * @license   Proprietary
 * @link      https://studyvibe.academic
 */

declare(strict_types=1);

// =========================================================================
// SECTION 1: AUTHENTICATION & CSRF SECURITY
// =========================================================================

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
$pdo = Database::getInstance();

try {
    // =========================================================================
    // SECTION 2: NAME AND MATRICULE UPDATE CONTROLLER
    // =========================================================================
    if (isset($_POST['name']) || isset($_POST['matricule'])) {
        $updates = [];
        $params  = ['id' => $studentId];

        if (isset($_POST['name'])) {
            $name = trim((string)$_POST['name']);
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Le nom complet ne peut pas être vide.']);
                exit;
            }
            $updates[] = 'name = :name';
            $params['name'] = $name;
        }

        if (isset($_POST['matricule'])) {
            $matricule = trim((string)$_POST['matricule']);
            if (empty($matricule)) {
                echo json_encode(['success' => false, 'message' => 'Le numéro de matricule ne peut pas être vide.']);
                exit;
            }
            $updates[] = 'matricule = :matricule';
            $params['matricule'] = strtoupper($matricule);
            $_SESSION['user_matricule'] = strtoupper($matricule);
        }

        if (!empty($updates)) {
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Profil mis à jour avec succès.',
            'matricule' => $_SESSION['user_matricule'] ?? ''
        ]);
        exit;
    }

    // =========================================================================
    // SECTION 3: AVATAR IMAGE FILE UPLOAD PROCESSOR
    // =========================================================================
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
