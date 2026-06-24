<?php
declare(strict_types=1);

date_default_timezone_set('Africa/Douala');


/**
 * Chargeur de configuration centralisé.
 * Lit le fichier .env et définit des constantes globales.
 * Doit être inclus en tout premier dans auth.php ou index.php.
 */

$envFile = __DIR__ . '/.env';

if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Ignorer les commentaires
        if (str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Retirer les guillemets éventuels
        $value = trim($value, '\"\'');

        if (!defined($key)) {
            define($key, $value);
        }
    }
}

// Charger depuis l'environnement système (pour Railway/Render)
$envKeys = [
    'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS',
    'APP_SECRET', 'APP_URL', 'HTTPS_ONLY',
    'LOGIN_MAX_ATTEMPTS', 'LOGIN_LOCKOUT_MINUTES',
    'SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_FROM', 'SMTP_FROM_NAME',
    'GEMINI_API_KEY'
];

foreach ($envKeys as $key) {
    $val = $_ENV[$key] ?? getenv($key) ?? null;
    if ($val !== null && $val !== false && !defined($key)) {
        define($key, (string)$val);
    }
}

// S'assurer qu'au moins l'un des deux (fichier .env ou variables système) est configuré
if (!is_file($envFile) && !defined('DB_HOST')) {
    die('Fichier de configuration .env introuvable et variables d\'environnement système non définies.');
}

// Valeurs par défaut si absentes
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

// Redirection HTTPS en production
if (HTTPS_ONLY === 'true' && PHP_SAPI !== 'cli') {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if (!$isHttps && !empty($_SERVER['HTTP_HOST'])) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
}