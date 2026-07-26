<?php
declare(strict_types=1);

// Active l'affichage direct des erreurs PHP dans le navigateur
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Configure default server timezone for consistent timestamping
date_default_timezone_set('Africa/Douala');

// Optimiser les limites d'upload et de mémoire pour les documents PDF (jusqu'à 60Mo)
@ini_set('upload_max_filesize', '64M');
@ini_set('post_max_size', '128M');
@ini_set('memory_limit', '256M');
@ini_set('max_execution_time', '300');
@ini_set('max_input_time', '300');

/**
 * StudyVibe LMS - Configuration Loader
 * 
 * Centralized loader script to establish system-wide constants. Reads configuration 
 * from local `.env` files and system environments, offering reliable fallback defaults.
 * 
 * @package    StudyVibe
 * @author     Advanced Engineering Team
 */

// =========================================================================
// SECTION 2: LOCAL ENV FILE PARSING ROUTINE
// =========================================================================

$envFile = __DIR__ . '/.env';

if (is_file($envFile)) {
    // Read the file lines, ignoring empty lines and trimming return chars
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip comment lines or lines lacking assignment operators
        if (str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        
        // Split key/value at the first equal sign
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Strip wrapping single or double quotes
        $value = trim($value, '\"\'');

        // Define global constant if not already set
        if (!defined($key)) {
            define($key, $value);
        }
    }
}

// =========================================================================
// SECTION 3: SYSTEM ENVIRONMENT FALLBACK LOADER (CLOUDS / PAAS)
// =========================================================================

$envKeys = [
    'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS',
    'APP_SECRET', 'APP_URL', 'HTTPS_ONLY',
    'LOGIN_MAX_ATTEMPTS', 'LOGIN_LOCKOUT_MINUTES',
    'SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_FROM', 'SMTP_FROM_NAME',
    'GEMINI_API_KEY'
];

foreach ($envKeys as $key) {
    // Attempt loading from PHP environment arrays or system environment values
    $val = $_ENV[$key] ?? getenv($key) ?? null;
    if ($val !== null && $val !== false && !defined($key)) {
        define($key, (string)$val);
    }
}

// Halt runtime if no configuration source can be resolved
if (!is_file($envFile) && !defined('DB_HOST')) {
    die('Fichier de configuration .env introuvable et variables d\'environnement système non définies.');
}

// =========================================================================
// SECTION 4: DEFAULT CONFIGURATION VALUES
// =========================================================================

defined('DB_HOST')               || define('DB_HOST', '127.0.0.1');
defined('DB_PORT')               || define('DB_PORT', '3306');
defined('DB_NAME')               || define('DB_NAME', 'studyvibe');
defined('DB_USER')               || define('DB_USER', 'root');
defined('DB_PASS')               || define('DB_PASS', '');
defined('APP_SECRET')            || define('APP_SECRET', 'fallback_secret_key');
defined('APP_URL')               || define('APP_URL', 'http://127.0.0.1:8000');
defined('HTTPS_ONLY')            || define('HTTPS_ONLY', 'false');
defined('LOGIN_MAX_ATTEMPTS')    || define('LOGIN_MAX_ATTEMPTS', '5');
defined('LOGIN_LOCKOUT_MINUTES') || define('LOGIN_LOCKOUT_MINUTES', '15');
defined('SMTP_HOST')             || define('SMTP_HOST', '');
defined('SMTP_PORT')             || define('SMTP_PORT', '587');
defined('SMTP_USER')             || define('SMTP_USER', '');
defined('SMTP_PASS')             || define('SMTP_PASS', '');
defined('SMTP_FROM')             || define('SMTP_FROM', 'noreply@studyvibe.edu');
defined('SMTP_FROM_NAME')        || define('SMTP_FROM_NAME', 'StudyVibe');
defined('GEMINI_API_KEY')        || define('GEMINI_API_KEY', 'votre_cle_api_gemini_ici');

// =========================================================================
// SECTION 5: HTTPS RE-ROUTING (PRODUCTION ENFORCEMENT)
// =========================================================================

if (HTTPS_ONLY === 'true' && PHP_SAPI !== 'cli') {
    // Check if HTTPS header is present or if running behind a reverse proxy (X-Forwarded-Proto)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    if (!$isHttps && !empty($_SERVER['HTTP_HOST'])) {
        // Enforce 301 Permanent Redirect to SSL equivalent URL
        header('Location: https://' . $_SERVER['HTTP_HOST'] . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
}