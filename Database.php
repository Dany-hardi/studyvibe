<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * StudyVibe LMS - Database Connection Manager
 * 
 * Manages the connection lifecycle to the MySQL database. Utilizes the 
 * Singleton Pattern to ensure only a single PDO connection is instantiated 
 * per request lifecycle. Automatically runs safe, incremental database schema 
 * migrations on initialization if new columns or tables are detected.
 * 
 * @package    StudyVibe
 * @author     Advanced Engineering Team
 */
class Database
{
    /**
     * Singleton instance of the PDO connection.
     * @var PDO|null
     */
    private static ?PDO $instance = null;

    /**
     * Retrieves the active database connection instance, initializing it if necessary.
     * Performs automatic structural migrations to keep the database schema in sync.
     * 
     * @return PDO Active connection object configured with exceptions and utf8mb4.
     * @throws PDOException If database connection fails or migration queries fail.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            // =========================================================================
            // SECTION 1: CONNECTION STRING & INITIALIZATION
            // =========================================================================
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

            // Enable SSL encryption if database host is remote (e.g. Aiven Cloud) or explicitly enabled
            $host = DB_HOST;
            $useSsl = str_contains(strtolower($host), 'aivencloud.com') 
                      || (defined('DB_SSL') && (DB_SSL === 'true' || DB_SSL === true));

            if ($useSsl && defined('PDO::MYSQL_ATTR_SSL_CA')) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = '';
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);

            // =========================================================================
            // SECTION 2: AUTOMATIC INCREMENTAL SCHEMA MIGRATIONS
            // =========================================================================

            // Migration 2.1: Add support for asynchrone / homework options in live evaluations
            try {
                self::$instance->query("SELECT is_async, async_deadline FROM live_eval_sessions LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `is_async` TINYINT(1) NOT NULL DEFAULT 0");
                    self::$instance->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `async_deadline` DATETIME DEFAULT NULL");
                } catch (PDOException $ex) {
                    // Silently ignore if column alterations are already applied or currently locked
                }
            }

            // Migration 2.2: Add tracking for student last active duration in registration
            try {
                self::$instance->query("SELECT last_activity FROM live_eval_registrations LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `live_eval_registrations` ADD COLUMN `last_activity` DATETIME DEFAULT NULL");
                } catch (PDOException $ex) {
                    // Silently ignore if column alterations are already applied or currently locked
                }
            }

            // Migration 2.3: Support pausing live quiz rooms (teacher dashboard controls)
            try {
                self::$instance->query("SELECT is_paused, paused_at, pause_duration FROM live_eval_sessions LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `is_paused` TINYINT(1) NOT NULL DEFAULT 0");
                    self::$instance->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `paused_at` DATETIME DEFAULT NULL");
                    self::$instance->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `pause_duration` INT NOT NULL DEFAULT 0");
                } catch (PDOException $ex) {
                    // Silently ignore if column alterations are already applied or currently locked
                }
            }

            // Migration 2.4: Introduce text explanation / correction fields for all question banks
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

            // Migration 2.5: Map live registrations to student users for unified dashboard tracking
            try {
                self::$instance->query("SELECT student_id FROM live_eval_registrations LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `live_eval_registrations` ADD COLUMN `student_id` INT DEFAULT NULL");
                    self::$instance->exec("ALTER TABLE `live_eval_registrations` ADD CONSTRAINT `fk_live_eval_regs_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
                } catch (PDOException $ex) {}
            }

            // Migration 2.6: Support open-text / written answers beside traditional MCQs
            try {
                self::$instance->query("SELECT question_type FROM live_eval_questions LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `live_eval_questions` ADD COLUMN `question_type` VARCHAR(32) NOT NULL DEFAULT 'mcq'");
                    self::$instance->exec("ALTER TABLE `live_eval_questions` MODIFY COLUMN `correct_option` VARCHAR(255) NOT NULL");
                    self::$instance->exec("ALTER TABLE `live_eval_answers` MODIFY COLUMN `selected_option` VARCHAR(255) NOT NULL");
                } catch (PDOException $ex) {}
            }

            // Migration 2.7: Support binary storage backups for course cover images and lesson PDFs
            try {
                self::$instance->query("SELECT cover_image_data FROM courses LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `courses` ADD COLUMN `cover_image_data` MEDIUMBLOB DEFAULT NULL");
                } catch (PDOException $ex) {}
            }
            try {
                self::$instance->query("SELECT pdf_data FROM lessons LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `lessons` ADD COLUMN `pdf_data` MEDIUMBLOB DEFAULT NULL");
                } catch (PDOException $ex) {}
            }

            // Migration 2.8: Gamification — student badges table
            try {
                self::$instance->query("SELECT id FROM student_badges LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("
                        CREATE TABLE IF NOT EXISTS `student_badges` (
                            `id`         INT AUTO_INCREMENT PRIMARY KEY,
                            `student_id` INT NOT NULL,
                            `badge_type` VARCHAR(64) NOT NULL,
                            `earned_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE KEY `uniq_student_badge` (`student_id`, `badge_type`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                } catch (PDOException $ex) {}
            }

            // Migration 2.9: Study sessions table with seconds_spent column
            try {
                self::$instance->query("SELECT seconds_spent FROM study_sessions LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("
                        CREATE TABLE IF NOT EXISTS `study_sessions` (
                            `id`           INT AUTO_INCREMENT PRIMARY KEY,
                            `student_id`   INT NOT NULL,
                            `lesson_id`    INT DEFAULT NULL,
                            `seconds_spent` INT NOT NULL DEFAULT 0,
                            `session_date` DATE DEFAULT NULL,
                            `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                } catch (PDOException $ex) {
                    // Table exists but missing column — add it
                    try {
                        self::$instance->exec("ALTER TABLE `study_sessions` ADD COLUMN `seconds_spent` INT NOT NULL DEFAULT 0");
                    } catch (PDOException $ex2) {}
                }
            }

            // Migration 2.10: video_notes table for note_taker badge
            try {
                self::$instance->query("SELECT id FROM video_notes LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("
                        CREATE TABLE IF NOT EXISTS `video_notes` (
                            `id`         INT AUTO_INCREMENT PRIMARY KEY,
                            `student_id` INT NOT NULL,
                            `lesson_id`  INT DEFAULT NULL,
                            `note`       TEXT DEFAULT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                } catch (PDOException $ex) {}
            }

            // Migration 2.11: lesson_progress — completed, score, completed_at columns
            try {
                self::$instance->query("SELECT completed, score, completed_at FROM lesson_progress LIMIT 1");
            } catch (PDOException $e) {
                try { self::$instance->exec("ALTER TABLE `lesson_progress` ADD COLUMN `completed` TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $ex) {}
                try { self::$instance->exec("ALTER TABLE `lesson_progress` ADD COLUMN `score` DECIMAL(5,2) DEFAULT NULL"); } catch (PDOException $ex) {}
                try { self::$instance->exec("ALTER TABLE `lesson_progress` ADD COLUMN `completed_at` DATETIME DEFAULT NULL"); } catch (PDOException $ex) {}
            }

            // Migration 2.12: users — lang column for localization preferences
            try {
                self::$instance->query("SELECT lang FROM users LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `users` ADD COLUMN `lang` VARCHAR(5) NOT NULL DEFAULT 'fr'");
                } catch (PDOException $ex) {}
            }

            // Migration 2.13: certificates — course_id column for course-centric certification model
            try {
                self::$instance->query("SELECT course_id FROM certificates LIMIT 1");
            } catch (PDOException $e) {
                try {
                    self::$instance->exec("ALTER TABLE `certificates` ADD COLUMN `course_id` INT DEFAULT NULL AFTER `student_id`");
                    // Populate course_id for existing certificates based on their module_id
                    self::$instance->exec("
                        UPDATE `certificates` cert
                        JOIN `courses` c ON c.module_id = cert.module_id
                        SET cert.course_id = c.id
                        WHERE cert.course_id IS NULL
                    ");
                    // Add foreign key constraint
                    self::$instance->exec("ALTER TABLE `certificates` ADD CONSTRAINT `fk_certificates_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE");
                } catch (PDOException $ex) {}
            }
        }
        
        return self::$instance;
    }

    /**
     * Prevent object cloning of the Singleton instance to maintain connection integrity.
     */
    private function __clone() {}

    /**
     * Prevent deserialization of the Singleton instance.
     * 
     * @throws Exception Always, to avoid external initialization bypasses.
     */
    public function __wakeup(): void 
    { 
        throw new \Exception('Désérialisation non autorisée.'); 
    }
}
