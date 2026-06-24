<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Classe de connexion à la base de données.
 * Singleton PDO — lit les credentials depuis config.php (.env).
 */
class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // Activer SSL si l'hôte pointe vers Aiven ou si SSL est spécifié
            $host = DB_HOST;
            $useSsl = str_contains(strtolower($host), 'aivencloud.com') 
                      || (defined('DB_SSL') && (DB_SSL === 'true' || DB_SSL === true));

            if ($useSsl && defined('PDO::MYSQL_ATTR_SSL_CA')) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = '';
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        }

        return self::$instance;
    }

    // Empêcher le clonage ou la désérialisation
    private function __clone() {}
    public function __wakeup(): void { throw new \Exception('Désérialisation non autorisée.'); }
}
