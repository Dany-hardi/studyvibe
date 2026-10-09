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

            // Keep the connection open between requests (one per web worker) instead of reconnecting every time.
            // Needs DB max_connections above the number of web workers. Off unless DB_PERSISTENT=1 is set in .env.
            if (defined('DB_PERSISTENT') && in_array(strtolower((string)DB_PERSISTENT), ['1', 'true', 'on'], true)) {
                $options[PDO::ATTR_PERSISTENT] = true;
            }

            // Enable SSL encryption if database host is remote (e.g. Aiven Cloud) or explicitly enabled
            $host = DB_HOST;
            $useSsl = str_contains(strtolower($host), 'aivencloud.com') 
                      || (defined('DB_SSL') && (DB_SSL === 'true' || DB_SSL === true));

            if ($useSsl && defined('PDO::MYSQL_ATTR_SSL_CA')) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = '';
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Schema migrations only run when this file changed since they last succeeded (see schemaStamp()).
            self::migrateOnce(self::$instance);
        }
        
        return self::$instance;
    }


    /** Changes whenever this file is edited, so adding a migration below re-runs them once, automatically. */
    private static function schemaStamp(): string
    {
        return (string)filemtime(__FILE__) . '-' . (string)filesize(__FILE__) . '-' . DB_NAME;
    }

    /** Used by the control center: forget the stamp and run every migration again. */
    public static function forceMigrate(): void
    {
        $pdo = self::getInstance();
        @unlink(self::stampFile());
        self::migrateOnce($pdo);
    }

    private static function stampFile(): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . '/studyvibe_schema_' . md5((string)DB_HOST . DB_PORT . DB_NAME) . '.stamp';
    }

    /**
     * Runs the migrations once per deployment instead of on every request.
     * Before this, each request paid about 20 probe queries and 8 failing ALTER TABLE statements.
     */
    private static function migrateOnce(PDO $pdo): void
    {
        $stamp = self::schemaStamp();
        $file  = self::stampFile();
        if (@file_get_contents($file) === $stamp) {
            return;
        }
        $locked = false;
        try {
            $locked = (bool)$pdo->query("SELECT GET_LOCK('studyvibe_schema', 30)")->fetchColumn();
            if (@file_get_contents($file) === $stamp) {   // another process finished while we waited
                return;
            }
            self::runMigrations($pdo);
            @file_put_contents($file, $stamp, LOCK_EX);
        } finally {
            if ($locked) {
                try { $pdo->query("SELECT RELEASE_LOCK('studyvibe_schema')"); } catch (PDOException) {}
            }
        }
    }

    private static function runMigrations(PDO $pdo): void
    {
        // =========================================================================
        // SECTION 2: AUTOMATIC INCREMENTAL SCHEMA MIGRATIONS
        // =========================================================================

        // Migration 2.1: Add support for asynchrone / homework options in live evaluations
        try {
            $pdo->query("SELECT is_async, async_deadline FROM live_eval_sessions LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `is_async` TINYINT(1) NOT NULL DEFAULT 0");
                $pdo->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `async_deadline` DATETIME DEFAULT NULL");
            } catch (PDOException $ex) {
                // Silently ignore if column alterations are already applied or currently locked
            }
        }

        // Migration 2.2: Add tracking for student last active duration in registration
        try {
            $pdo->query("SELECT last_activity FROM live_eval_registrations LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `live_eval_registrations` ADD COLUMN `last_activity` DATETIME DEFAULT NULL");
            } catch (PDOException $ex) {
                // Silently ignore if column alterations are already applied or currently locked
            }
        }

        // Migration 2.3: Support pausing live quiz rooms (teacher dashboard controls)
        try {
            $pdo->query("SELECT is_paused, paused_at, pause_duration FROM live_eval_sessions LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `is_paused` TINYINT(1) NOT NULL DEFAULT 0");
                $pdo->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `paused_at` DATETIME DEFAULT NULL");
                $pdo->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `pause_duration` INT NOT NULL DEFAULT 0");
            } catch (PDOException $ex) {
                // Silently ignore if column alterations are already applied or currently locked
            }
        }

        // Migration 2.4: Introduce text explanation / correction fields for all question banks
        try {
            $pdo->query("SELECT explanation FROM lesson_questions LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `lesson_questions` ADD COLUMN `explanation` TEXT DEFAULT NULL");
            } catch (PDOException $ex) {}
        }
        try {
            $pdo->query("SELECT explanation FROM course_questions LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `course_questions` ADD COLUMN `explanation` TEXT DEFAULT NULL");
            } catch (PDOException $ex) {}
        }
        try {
            $pdo->query("SELECT explanation FROM live_eval_questions LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `live_eval_questions` ADD COLUMN `explanation` TEXT DEFAULT NULL");
            } catch (PDOException $ex) {}
        }

        // Migration 2.4: Teacher approval flag (login, signup and the promoter dashboard read users.is_approved).
        // Default 1 keeps every existing account approved; signup sets 0 for new teachers.
        try {
            $pdo->query("SELECT is_approved FROM users LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_approved` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_active`");
            } catch (PDOException $ex) {}
        }

        // Migration 2.4b: Onboarding walkthrough flag. NULL = the user has not seen the tour yet.
        // Accounts that exist when the column is created are marked as already seen; only later signups get the tour.
        try {
            $pdo->query("SELECT tour_seen_at FROM users LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `tour_seen_at` DATETIME DEFAULT NULL");
                $pdo->exec("UPDATE `users` SET `tour_seen_at` = NOW()");
            } catch (PDOException $ex) {}
        }

        // Migration 2.4c: one matricule per account. Skipped (the app-level check still applies) while duplicates exist.
        try {
            $hasIdx = $pdo->query("SHOW INDEX FROM `users` WHERE Key_name = 'uq_users_matricule'")->fetch();
            if (!$hasIdx) {
                $pdo->exec("UPDATE `users` SET `matricule` = NULL WHERE `matricule` = ''");
                $dups = (int)$pdo->query("SELECT COUNT(*) FROM (SELECT `matricule` FROM `users` WHERE `matricule` IS NOT NULL GROUP BY `matricule` HAVING COUNT(*) > 1) d")->fetchColumn();
                if ($dups === 0) {
                    $pdo->exec("ALTER TABLE `users` ADD UNIQUE KEY `uq_users_matricule` (`matricule`)");
                }
            }
        } catch (PDOException $e) {}

        // Migration 2.5: Map live registrations to student users for unified dashboard tracking
        try {
            $pdo->query("SELECT student_id FROM live_eval_registrations LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `live_eval_registrations` ADD COLUMN `student_id` INT DEFAULT NULL");
                $pdo->exec("ALTER TABLE `live_eval_registrations` ADD CONSTRAINT `fk_live_eval_regs_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
            } catch (PDOException $ex) {}
        }

        // Migration 2.6: Support open-text / written answers beside traditional MCQs
        try {
            $pdo->query("SELECT question_type FROM live_eval_questions LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `live_eval_questions` ADD COLUMN `question_type` VARCHAR(32) NOT NULL DEFAULT 'mcq'");
                $pdo->exec("ALTER TABLE `live_eval_questions` MODIFY COLUMN `correct_option` VARCHAR(255) NOT NULL");
                $pdo->exec("ALTER TABLE `live_eval_answers` MODIFY COLUMN `selected_option` VARCHAR(255) NOT NULL");
            } catch (PDOException $ex) {}
        }

        // Migration 2.6b: Exam integrity options. shuffle_options mixes A to D per student; integrity_watch counts the times a
        // student leaves the exam tab. Both default to 0 so existing sessions behave exactly as before.
        try {
            $pdo->query("SELECT shuffle_options, integrity_watch FROM live_eval_sessions LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `shuffle_options` TINYINT(1) NOT NULL DEFAULT 0");
                $pdo->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `integrity_watch` TINYINT(1) NOT NULL DEFAULT 0");
            } catch (PDOException $ex) {}
        }
        try {
            $pdo->query("SELECT focus_losses FROM live_eval_registrations LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `live_eval_registrations` ADD COLUMN `focus_losses` INT NOT NULL DEFAULT 0");
                $pdo->exec("ALTER TABLE `live_eval_registrations` ADD COLUMN `last_focus_loss_at` DATETIME DEFAULT NULL");
            } catch (PDOException $ex) {}
        }

        // Migration 2.6c: database copy of every uploaded file (see lib/MediaStore.php). The web server's disk is wiped on
        // some hosts at each deploy; files are restored from these tables when they are missing.
        try {
            $pdo->query("SELECT 1 FROM media_files LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `media_files` (
                        `media_key`  VARCHAR(190) NOT NULL PRIMARY KEY,
                        `mime`       VARCHAR(100) NOT NULL DEFAULT '',
                        `size_bytes` BIGINT NOT NULL DEFAULT 0,
                        `sha256`     CHAR(64) NOT NULL,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `media_chunks` (
                        `media_key` VARCHAR(190) NOT NULL,
                        `seq`       INT NOT NULL,
                        `data`      MEDIUMBLOB NOT NULL,
                        PRIMARY KEY (`media_key`, `seq`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (PDOException $ex) {}
        }

        // Migration 2.6d: phone numbers, one-time codes, the SMS queue and a small rate-limit table.
        // phone_e164 only ever holds a number the user proved to own with a code, and it is unique.
        try {
            $pdo->query("SELECT phone_e164, phone_verified_at, phone_pending, sms_opt_in FROM users LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `phone_e164` VARCHAR(20) DEFAULT NULL");
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `phone_verified_at` DATETIME DEFAULT NULL");
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `phone_pending` VARCHAR(20) DEFAULT NULL");
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `sms_opt_in` TINYINT(1) NOT NULL DEFAULT 1");
                $pdo->exec("ALTER TABLE `users` ADD UNIQUE KEY `uq_users_phone_e164` (`phone_e164`)");
            } catch (PDOException $ex) {}
        }
        try {
            $pdo->query("SELECT 1 FROM otp_codes LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `otp_codes` (
                        `id`          INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id`     INT NOT NULL,
                        `purpose`     VARCHAR(32) NOT NULL,
                        `phone`       VARCHAR(20) NOT NULL,
                        `code_hash`   CHAR(64) NOT NULL,
                        `attempts`    TINYINT NOT NULL DEFAULT 0,
                        `expires_at`  DATETIME NOT NULL,
                        `consumed_at` DATETIME DEFAULT NULL,
                        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        KEY `idx_otp_user` (`user_id`, `purpose`),
                        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (PDOException $ex) {}
        }
        try {
            $pdo->query("SELECT 1 FROM sms_outbox LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `sms_outbox` (
                        `id`          BIGINT AUTO_INCREMENT PRIMARY KEY,
                        `user_id`     INT DEFAULT NULL,
                        `to_phone`    VARCHAR(20) NOT NULL,
                        `kind`        VARCHAR(24) NOT NULL,
                        `body`        VARCHAR(700) NOT NULL,
                        `dedupe_key`  VARCHAR(120) DEFAULT NULL,
                        `status`      ENUM('queued','sending','sent','failed') NOT NULL DEFAULT 'queued',
                        `attempts`    TINYINT NOT NULL DEFAULT 0,
                        `provider_id` VARCHAR(80) DEFAULT NULL,
                        `error`       VARCHAR(255) DEFAULT NULL,
                        `ip`          VARCHAR(45) DEFAULT NULL,
                        `next_try_at` DATETIME DEFAULT NULL,
                        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `sent_at`     DATETIME DEFAULT NULL,
                        UNIQUE KEY `uq_sms_dedupe` (`dedupe_key`),
                        KEY `idx_sms_status` (`status`, `next_try_at`),
                        KEY `idx_sms_phone` (`to_phone`, `created_at`),
                        KEY `idx_sms_user` (`user_id`, `kind`, `created_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (PDOException $ex) {}
        }
        try {
            $pdo->query("SELECT 1 FROM rate_hits LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `rate_hits` (
                        `bucket` VARCHAR(190) NOT NULL,
                        `hit_at` DATETIME NOT NULL,
                        KEY `idx_rate_bucket` (`bucket`, `hit_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (PDOException $ex) {}
        }

        // Migration 2.6e: two-factor authentication (authenticator app). The secret is stored encrypted (lib/Totp.php);
        // totp_last_step stops a code from being used twice; recovery codes are stored as hashes and work once.
        try {
            $pdo->query("SELECT totp_secret_enc, totp_enabled_at, totp_last_step FROM users LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `totp_secret_enc` VARCHAR(255) DEFAULT NULL");
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `totp_enabled_at` DATETIME DEFAULT NULL");
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `totp_last_step` BIGINT NOT NULL DEFAULT 0");
            } catch (PDOException $ex) {}
        }
        try {
            $pdo->query("SELECT 1 FROM user_recovery_codes LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `user_recovery_codes` (
                        `id`        INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id`   INT NOT NULL,
                        `code_hash` CHAR(64) NOT NULL,
                        `used_at`   DATETIME DEFAULT NULL,
                        KEY `idx_recovery_user` (`user_id`),
                        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (PDOException $ex) {}
        }

        // Migration 2.6f: queue of emails sent in the background (announcement of an evaluation to a whole class, cancelled results).
        // The row keeps the template name and its data, not the HTML, so it is drawn with the current design when it is sent.
        try {
            $pdo->query("SELECT 1 FROM email_outbox LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `email_outbox` (
                        `id`          BIGINT AUTO_INCREMENT PRIMARY KEY,
                        `user_id`     INT DEFAULT NULL,
                        `to_email`    VARCHAR(255) NOT NULL,
                        `template`    VARCHAR(40) NOT NULL,
                        `args`        MEDIUMTEXT NOT NULL,
                        `dedupe_key`  VARCHAR(120) DEFAULT NULL,
                        `status`      ENUM('queued','sending','sent','failed') NOT NULL DEFAULT 'queued',
                        `attempts`    TINYINT NOT NULL DEFAULT 0,
                        `error`       VARCHAR(255) DEFAULT NULL,
                        `next_try_at` DATETIME DEFAULT NULL,
                        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `sent_at`     DATETIME DEFAULT NULL,
                        UNIQUE KEY `uq_email_dedupe` (`dedupe_key`),
                        KEY `idx_email_status` (`status`, `next_try_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (PDOException $ex) {}
        }

        // Migration 2.6g: a teacher can cancel a student's result of a live evaluation (cheating, a problem during the exam) and
        // restore it later. The original mark is kept in cancelled_score. results_prompted_at: the "exam finished" window of the
        // teacher dashboard appears once per session.
        try {
            $pdo->query("SELECT cancelled_at, cancelled_reason, cancelled_score FROM live_eval_registrations LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `live_eval_registrations` ADD COLUMN `cancelled_at` DATETIME DEFAULT NULL");
                $pdo->exec("ALTER TABLE `live_eval_registrations` ADD COLUMN `cancelled_reason` VARCHAR(255) DEFAULT NULL");
                $pdo->exec("ALTER TABLE `live_eval_registrations` ADD COLUMN `cancelled_score` DECIMAL(5,2) DEFAULT NULL");
            } catch (PDOException $ex) {}
        }
        try {
            $pdo->query("SELECT results_prompted_at FROM live_eval_sessions LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `results_prompted_at` DATETIME DEFAULT NULL");
            } catch (PDOException $ex) {}
        }

        // Migration 2.7: Support binary storage backups for course cover images and lesson PDFs
        try {
            $pdo->query("SELECT cover_image_data FROM courses LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `courses` ADD COLUMN `cover_image_data` MEDIUMBLOB DEFAULT NULL");
            } catch (PDOException $ex) {}
        }
        try {
            $pdo->query("SELECT pdf_data FROM lessons LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `lessons` ADD COLUMN `pdf_data` MEDIUMBLOB DEFAULT NULL");
            } catch (PDOException $ex) {}
        }

        // Migration 2.8: Gamification — student badges table
        try {
            $pdo->query("SELECT id FROM student_badges LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
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

        // Migration 2.8b: per-video progress, so a lesson's videos can only be taken in order
        try {
            $pdo->query("SELECT 1 FROM lesson_video_progress LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `lesson_video_progress` (
                        `student_id`   INT NOT NULL,
                        `lesson_id`    INT NOT NULL,
                        `video_key`    VARCHAR(32) NOT NULL,
                        `started_at`   DATETIME DEFAULT NULL,
                        `completed_at` DATETIME DEFAULT NULL,
                        PRIMARY KEY (`student_id`, `lesson_id`, `video_key`),
                        KEY `idx_lvp_lesson` (`lesson_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (PDOException $ex) {}
        }

        // Migration 2.9: Study sessions table with seconds_spent column
        try {
            $pdo->query("SELECT seconds_spent FROM study_sessions LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
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
                    $pdo->exec("ALTER TABLE `study_sessions` ADD COLUMN `seconds_spent` INT NOT NULL DEFAULT 0");
                } catch (PDOException $ex2) {}
            }
        }

        // Migration 2.10: video_notes table for note_taker badge
        try {
            $pdo->query("SELECT id FROM video_notes LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
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
            $pdo->query("SELECT completed, score, completed_at FROM lesson_progress LIMIT 1");
        } catch (PDOException $e) {
            try { $pdo->exec("ALTER TABLE `lesson_progress` ADD COLUMN `completed` TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $ex) {}
            try { $pdo->exec("ALTER TABLE `lesson_progress` ADD COLUMN `score` DECIMAL(5,2) DEFAULT NULL"); } catch (PDOException $ex) {}
            try { $pdo->exec("ALTER TABLE `lesson_progress` ADD COLUMN `completed_at` DATETIME DEFAULT NULL"); } catch (PDOException $ex) {}
        }
        try { $pdo->exec("ALTER TABLE `lesson_progress` MODIFY COLUMN `completed_at` DATETIME NULL DEFAULT NULL"); } catch (PDOException $ex) {}

        // Migration 2.12: users — lang column for localization preferences
        try {
            $pdo->query("SELECT lang FROM users LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `lang` VARCHAR(5) NOT NULL DEFAULT 'fr'");
            } catch (PDOException $ex) {}
        }

        // Migration 2.13: certificates — course_id column for course-centric certification model
        try {
            $pdo->query("SELECT course_id FROM certificates LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `certificates` ADD COLUMN `course_id` INT DEFAULT NULL AFTER `student_id`");
                // Populate course_id for existing certificates based on their module_id
                $pdo->exec("
                    UPDATE `certificates` cert
                    JOIN `courses` c ON c.module_id = cert.module_id
                    SET cert.course_id = c.id
                    WHERE cert.course_id IS NULL
                ");
                // Add foreign key constraint
                $pdo->exec("ALTER TABLE `certificates` ADD CONSTRAINT `fk_certificates_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE");
            } catch (PDOException $ex) {}
        }

        // Migration 2.14: Automatic creation of lesson assignment submissions depot & lesson config columns
        try {
            $pdo->query("SELECT id FROM lesson_assignment_submissions LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `lesson_assignment_submissions` (
                        `id`                  INT AUTO_INCREMENT PRIMARY KEY,
                        `lesson_id`           INT NOT NULL,
                        `student_id`          INT NOT NULL,
                        `submission_type`     VARCHAR(32) NOT NULL DEFAULT 'file',
                        `submitted_file_path` VARCHAR(255) DEFAULT NULL,
                        `submitted_file_name` VARCHAR(255) DEFAULT NULL,
                        `submitted_link`      VARCHAR(512) DEFAULT NULL,
                        `student_comment`     TEXT DEFAULT NULL,
                        `submitted_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        KEY `idx_sub_lesson` (`lesson_id`),
                        KEY `idx_sub_student` (`student_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (PDOException $ex) {}
        }

        try { $pdo->exec("ALTER TABLE `lessons` ADD COLUMN `has_assignment` TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $ex) {}
        try { $pdo->exec("ALTER TABLE `lessons` ADD COLUMN `assignment_title` VARCHAR(255) DEFAULT NULL"); } catch (PDOException $ex) {}
        try { $pdo->exec("ALTER TABLE `lessons` ADD COLUMN `assignment_type` VARCHAR(32) NOT NULL DEFAULT 'both'"); } catch (PDOException $ex) {}
        try { $pdo->exec("ALTER TABLE `lessons` ADD COLUMN `allowed_file_types` VARCHAR(255) NOT NULL DEFAULT 'pdf,docx'"); } catch (PDOException $ex) {}
        try { $pdo->exec("ALTER TABLE `lessons` ADD COLUMN `assignment_instructions` TEXT DEFAULT NULL"); } catch (PDOException $ex) {}
        try { $pdo->exec("ALTER TABLE `lessons` ADD COLUMN `assignment_deadline` DATETIME DEFAULT NULL"); } catch (PDOException $ex) {}

        // Migration 2.15: Student name & matricule tracking on assignment submissions
        try { $pdo->exec("ALTER TABLE `lesson_assignment_submissions` ADD COLUMN `student_name` VARCHAR(255) DEFAULT NULL"); } catch (PDOException $ex) {}
        try { $pdo->exec("ALTER TABLE `lesson_assignment_submissions` ADD COLUMN `student_matricule` VARCHAR(64) DEFAULT NULL"); } catch (PDOException $ex) {}

        // Migration 2.16: Student matricule column in users table
        try {
            $pdo->query("SELECT matricule FROM users LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `matricule` VARCHAR(64) DEFAULT NULL AFTER `name`");
            } catch (PDOException $ex) {}
        }

        // Migration 2.17: results emails of live evaluations are queued and sent in the background, not inside the student's request
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `live_eval_mail_queue` (
                    `id`              INT AUTO_INCREMENT PRIMARY KEY,
                    `registration_id` INT NOT NULL,
                    `payload`         MEDIUMTEXT NOT NULL,
                    `status`          VARCHAR(12) NOT NULL DEFAULT 'pending',
                    `attempts`        TINYINT NOT NULL DEFAULT 0,
                    `claim`           VARCHAR(40) DEFAULT NULL,
                    `next_try_at`     DATETIME DEFAULT NULL,
                    `last_error`      VARCHAR(255) DEFAULT NULL,
                    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `sent_at`         DATETIME DEFAULT NULL,
                    UNIQUE KEY `uq_mailq_registration` (`registration_id`),
                    KEY `idx_mailq_status` (`status`, `next_try_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $ex) {}

        // Migration 2.18: acquisition analytics. Anonymous daily counters (no IP, no user id) and the channel a user signed up from.
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `site_events` (
                    `day`   DATE NOT NULL,
                    `event` VARCHAR(48) NOT NULL,
                    `src`   VARCHAR(40) NOT NULL DEFAULT '',
                    `n`     INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (`day`, `event`, `src`),
                    KEY `idx_site_events_event` (`event`, `day`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $ex) {}
        try { $pdo->query("SELECT signup_source FROM users LIMIT 1"); }
        catch (PDOException $e) {
            try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `signup_source` VARCHAR(40) DEFAULT NULL, ADD KEY `idx_users_signup_source` (`signup_source`)"); } catch (PDOException $ex) {}
        }
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `campaigns` (
                    `id`         INT AUTO_INCREMENT PRIMARY KEY,
                    `name`       VARCHAR(120) NOT NULL,
                    `slug`       VARCHAR(40) NOT NULL,
                    `target`     VARCHAR(16) NOT NULL DEFAULT 'signup',
                    `target_ref` VARCHAR(64) DEFAULT NULL,
                    `created_by` INT DEFAULT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `archived`   TINYINT(1) NOT NULL DEFAULT 0,
                    UNIQUE KEY `uq_campaign_slug` (`slug`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $ex) {}

        // Migration 2.19: indexes the promoter dashboard relies on (time-range queries over the audit log and activity tables)
        foreach ([
            "ALTER TABLE `audit_logs` ADD KEY `idx_audit_action_time` (`action`, `created_at`)",
            "ALTER TABLE `users` ADD KEY `idx_users_created` (`created_at`)",
            "ALTER TABLE `enrollments` ADD KEY `idx_enroll_time` (`enrolled_at`)",
            "ALTER TABLE `lesson_progress` ADD KEY `idx_progress_done_time` (`completed`, `completed_at`)",
        ] as $ddl) {
            try { $pdo->exec($ddl); } catch (PDOException $ex) { /* already there */ }
        }
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
