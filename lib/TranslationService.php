<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database.php';

/**
 * StudyVibe LMS - Translation & Localization Service
 * 
 * Manages standard multi-lingual dictionaries (French, English) across the application.
 * Automatically detects user preferences via sessions, cookies, or user profile records.
 * Supports placeholder token replacements for dynamic translation strings.
 * 
 * @package    StudyVibe
 * @subpackage Lib
 * @author     Advanced Engineering Team
 */
class TranslationService
{
    /** @var array|null Loaded language key-value translation map */
    private static ?array $dictionary = null;

    /** @var string Active language ISO code (defaults to 'fr') */
    private static string $lang = 'fr';

    /**
     * Initializes the Translation Dictionary, detecting preferences from active session, 
     * client cookies, or authenticated profile settings. Fallback language is 'fr'.
     * 
     * @return void
     */
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        } elseif (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 1. Resolve active locale preference (Session -> Cookie -> DB profile -> Fallback 'fr')
        if (isset($_SESSION['user_lang'])) {
            self::$lang = $_SESSION['user_lang'];
        } elseif (isset($_COOKIE['studyvibe_lang'])) {
            self::$lang = $_COOKIE['studyvibe_lang'];
            $_SESSION['user_lang'] = self::$lang;
        } elseif (isset($_SESSION['user_id'])) {
            try {
                $pdo = Database::getInstance();
                $stmt = $pdo->prepare("SELECT lang FROM users WHERE id = :id");
                $stmt->execute(['id' => $_SESSION['user_id']]);
                $userLang = $stmt->fetchColumn();
                if ($userLang) {
                    self::$lang = $userLang;
                    $_SESSION['user_lang'] = self::$lang;
                }
            } catch (Exception $e) {
                // Fail silently to prevent database issues from breaking localization loading
            }
        }

        // Standardize and whitelist permitted languages
        if (!in_array(self::$lang, ['fr', 'en'], true)) {
            self::$lang = 'fr';
        }

        // Load targeted locale array dictionary file
        $filePath = __DIR__ . '/../locales/' . self::$lang . '.php';
        if (file_exists($filePath)) {
            self::$dictionary = require $filePath;
        } else {
            self::$dictionary = [];
        }
    }

    /**
     * Retrieves the ISO code of the active language.
     * 
     * @return string Active language code ('fr' | 'en').
     */
    public static function getLang(): string
    {
        return self::$lang;
    }

    /**
     * Resolves the translated string for a translation key.
     * Replaces placeholder parameters matching `:key` syntax with runtime details.
     * 
     * @param string $key          Target key within the localization dictionary.
     * @param array  $replacements List of placeholder substitutions (e.g. ['name' => 'Alex']).
     * @return string Translated string.
     */
    public static function translate(string $key, array $replacements = []): string
    {
        if (self::$dictionary === null) {
            self::init();
        }

        $translation = self::$dictionary[$key] ?? $key;

        // Perform dynamic placeholder substitution for patterns like :name
        foreach ($replacements as $placeholder => $value) {
            $translation = str_replace(':' . $placeholder, (string)$value, $translation);
        }

        return $translation;
    }
}

// =========================================================================
// GLOBAL LOGICAL HELPER WRAPPER: __()
// =========================================================================
if (!function_exists('__')) {
    /**
     * Globally accessible shorthand helper to perform translation Lookups.
     * 
     * @param string $key          Target translation key.
     * @param array  $replacements Placeholder values.
     * @return string Translated localized content.
     */
    function __(string $key, array $replacements = []): string
    {
        return TranslationService::translate($key, $replacements);
    }
}
