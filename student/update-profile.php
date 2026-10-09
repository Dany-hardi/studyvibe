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
require_once __DIR__ . '/../lib/Matricule.php';
require_once __DIR__ . '/../lib/Avatar.php';

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
            $matricule = Matricule::normalize((string)$_POST['matricule']);

            // Only a changed value is re-checked, so a name-only edit never trips over a number saved earlier.
            $cur = $pdo->prepare('SELECT matricule FROM users WHERE id = :id');
            $cur->execute(['id' => $studentId]);
            $unchanged = $matricule !== '' && strcasecmp((string)$cur->fetchColumn(), $matricule) === 0;

            // A changed number must be free. An unchanged one stays valid unless an older account already held it.
            $code = $unchanged ? null : Matricule::check($matricule);
            if ($code === null && Matricule::isTaken($pdo, $matricule, (int)$studentId, $unchanged)) {
                $code = 'taken';
            }
            if ($code !== null) {
                echo json_encode(['success' => false, 'code' => 'matricule_' . $code, 'message' => Matricule::message($code)]);
                exit;
            }
            $updates[] = 'matricule = :matricule';
            $params['matricule'] = $matricule;
            $_SESSION['user_matricule'] = $matricule;
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
    if (isset($_FILES['avatar'])) {
        echo json_encode(Avatar::replace($pdo, (int)$studentId, $_FILES['avatar']));
        exit;
    }

    echo json_encode([
        'success' => false,
        'message' => 'Aucune donnée valide reçue.'
    ]);
    exit;

} catch (PDOException $e) {
    if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'matricule')) {
        // Two sign-ups racing for the same number: the unique index lets only one through.
        echo json_encode(['success' => false, 'code' => 'matricule_taken', 'message' => Matricule::message('taken')]);
        exit;
    }
    jsonError('Erreur serveur. Veuillez réessayer.', $e, 'update-profile.php');
}
