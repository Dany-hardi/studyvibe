<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Language Setting REST API Endpoint
 * 
 * Mutates user locale preferences, saving values in active PHP session variables, 
 * long-lived client cookies (1-year lifespan with security flags), and database 
 * profile preferences if the user is authenticated.
 * 
 * @package    StudyVibe
 * @subpackage API
 * @author     Advanced Engineering Team
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Database.php';

header('Content-Type: application/json');

// Read JSON input payload
$data = json_decode(file_get_contents('php://input'), true);
$lang = strtolower(trim((string)($data['lang'] ?? '')));

// =========================================================================
// SECTION 1: VALIDATION & SECURITY
// =========================================================================

if (!in_array($lang, ['fr', 'en'], true)) {
    echo json_encode(['success' => false, 'message' => 'Langue non supportée.']);
    exit;
}

// =========================================================================
// SECTION 2: SESSION & COOKIE PERSISTENCE
// =========================================================================

$_SESSION['user_lang'] = $lang;

// Set cookie for 1 year with Strict SameSite and httponly flags to mitigate CSRF/XSS
setcookie('studyvibe_lang', $lang, [
    'expires' => time() + (365 * 24 * 60 * 60),
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) || (defined('HTTPS_ONLY') && HTTPS_ONLY === 'true'),
    'httponly' => true,
    'samesite' => 'Strict'
]);

// =========================================================================
// SECTION 3: DATABASE PROFILE SYNCHRONIZATION
// =========================================================================

if (isset($_SESSION['user_id'])) {
    try {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("UPDATE users SET lang = :lang WHERE id = :id");
        $stmt->execute([
            'lang' => $lang,
            'id' => $_SESSION['user_id']
        ]);
    } catch (Exception $e) {
        // Log database failure internally but do not interrupt response flow
    }
}

echo json_encode(['success' => true, 'lang' => $lang]);
exit;
