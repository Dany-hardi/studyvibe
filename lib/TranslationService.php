<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database.php';

class TranslationService
{
    private static ?array $dictionary = null;
    private static string $lang = 'fr';

    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Déterminer la langue active (Session -> Cookie -> DB si connecté -> Défaut 'fr')
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
                // Ignore DB errors in translation initialization
            }
        }

        // Sécuriser les valeurs autorisées
        if (!in_array(self::$lang, ['fr', 'en'], true)) {
            self::$lang = 'fr';
        }

        // Charger le fichier de dictionnaire correspondant
        $filePath = __DIR__ . '/../locales/' . self::$lang . '.php';
        if (file_exists($filePath)) {
            self::$dictionary = require $filePath;
        } else {
            self::$dictionary = [];
        }
    }

    public static function getLang(): string
    {
        return self::$lang;
    }

    public static function translate(string $key, array $replacements = []): string
    {
        if (self::$dictionary === null) {
            self::init();
        }

        $translation = self::$dictionary[$key] ?? $key;

        // Remplacer les placeholders dynamiques s'il y en a (ex: :name)
        foreach ($replacements as $placeholder => $value) {
            $translation = str_replace(':' . $placeholder, (string)$value, $translation);
        }

        return $translation;
    }
}

// Raccourci global pratique
if (!function_exists('__')) {
    function __(string $key, array $replacements = []): string
    {
        return TranslationService::translate($key, $replacements);
    }
}
