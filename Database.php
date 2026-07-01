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

            // Auto-migration check for is_async and async_deadline columns
            try {
                self::$instance->query("SELECT is_async, async_deadline FROM live_eval_sessions LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `is_async` TINYINT(1) NOT NULL DEFAULT 0");
                    self::$instance->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `async_deadline` DATETIME DEFAULT NULL");
                } catch (PDOException $ex) {
                    // Silently fail if columns are already being altered or added
                }
            }

            // Auto-migration check for last_activity column in live_eval_registrations
            try {
                self::$instance->query("SELECT last_activity FROM live_eval_registrations LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `live_eval_registrations` ADD COLUMN `last_activity` DATETIME DEFAULT NULL");
                } catch (PDOException $ex) {
                    // Silently fail if columns are already being altered or added
                }
            }

            // Auto-migration check for is_paused, paused_at, pause_duration columns in live_eval_sessions
            try {
                self::$instance->query("SELECT is_paused, paused_at, pause_duration FROM live_eval_sessions LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `is_paused` TINYINT(1) NOT NULL DEFAULT 0");
                    self::$instance->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `paused_at` DATETIME DEFAULT NULL");
                    self::$instance->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `pause_duration` INT NOT NULL DEFAULT 0");
                } catch (PDOException $ex) {
                    // Silently fail if columns are already being altered or added
                }
            }

            // Auto-migration check for explanation in lesson_questions, course_questions, live_eval_questions
            try {
                self::$instance->query("SELECT explanation FROM lesson_questions LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `lesson_questions` ADD COLUMN `explanation` TEXT DEFAULT NULL");
                } catch (PDOException $ex) {}
            }
            try {
                self::$instance->query("SELECT explanation FROM course_questions LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `course_questions` ADD COLUMN `explanation` TEXT DEFAULT NULL");
                } catch (PDOException $ex) {}
            }
            try {
                self::$instance->query("SELECT explanation FROM live_eval_questions LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `live_eval_questions` ADD COLUMN `explanation` TEXT DEFAULT NULL");
                } catch (PDOException $ex) {}
            }

            // Auto-migration check for student_id column in live_eval_registrations
            try {
                self::$instance->query("SELECT student_id FROM live_eval_registrations LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `live_eval_registrations` ADD COLUMN `student_id` INT DEFAULT NULL");
                    self::$instance->exec("ALTER TABLE `live_eval_registrations` ADD CONSTRAINT `fk_live_eval_regs_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
                } catch (PDOException $ex) {}
            }

            // Auto-migration check for question_type in live_eval_questions and sizing adjustments for open/written answers
            try {
                self::$instance->query("SELECT question_type FROM live_eval_questions LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `live_eval_questions` ADD COLUMN `question_type` VARCHAR(32) NOT NULL DEFAULT 'mcq'");
                    self::$instance->exec("ALTER TABLE `live_eval_questions` MODIFY COLUMN `correct_option` VARCHAR(255) NOT NULL");
                    self::$instance->exec("ALTER TABLE `live_eval_answers` MODIFY COLUMN `selected_option` VARCHAR(255) NOT NULL");
                } catch (PDOException $ex) {}
            }

            // Auto-migration check for webinar tables
            try {
                self::$instance->query("SELECT id FROM webinars LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("
                        CREATE TABLE IF NOT EXISTS webinars (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            course_id INT NOT NULL,
                            teacher_id INT NOT NULL,
                            title VARCHAR(255) NOT NULL,
                            description TEXT,
                            scheduled_at DATETIME NOT NULL,
                            duration INT NOT NULL DEFAULT 60,
                            status ENUM('scheduled', 'live', 'completed') NOT NULL DEFAULT 'scheduled',
                            meeting_id VARCHAR(100) NOT NULL,
                            recording_url VARCHAR(255) DEFAULT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
                            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    ");
                } catch (PDOException $ex) {}
            }
            try {
                self::$instance->query("SELECT id FROM webinar_attendance LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("
                        CREATE TABLE IF NOT EXISTS webinar_attendance (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            webinar_id INT NOT NULL,
                            student_id INT NOT NULL,
                            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            total_minutes_present INT DEFAULT 0,
                            UNIQUE KEY unique_webinar_student (webinar_id, student_id),
                            FOREIGN KEY (webinar_id) REFERENCES webinars(id) ON DELETE CASCADE,
                            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    ");
                } catch (PDOException $ex) {}
            }
            try {
                self::$instance->query("SELECT id FROM webinar_qa LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("
                        CREATE TABLE IF NOT EXISTS webinar_qa (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            webinar_id INT NOT NULL,
                            student_id INT NOT NULL,
                            question_text TEXT NOT NULL,
                            votes INT NOT NULL DEFAULT 0,
                            is_answered BOOLEAN NOT NULL DEFAULT FALSE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (webinar_id) REFERENCES webinars(id) ON DELETE CASCADE,
                            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                    ");
                } catch (PDOException $ex) {}
            }
        }
        
        return self::$instance;
    }

    // Empêcher le clonage ou la désérialisation
    private function __clone() {}
    public function __wakeup(): void { throw new \Exception('Désérialisation non autorisée.'); }
}
