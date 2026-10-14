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

        // Migration 2.6h: a student can contest a cancelled result once; the teacher answers (restore or keep) and the answer is final.
        try {
            $pdo->query("SELECT 1 FROM result_contests LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `result_contests` (
                        `id`               INT AUTO_INCREMENT PRIMARY KEY,
                        `registration_id`  INT NOT NULL,
                        `message`          TEXT NOT NULL,
                        `status`           ENUM('open','accepted','rejected') NOT NULL DEFAULT 'open',
                        `teacher_response` TEXT DEFAULT NULL,
                        `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `resolved_at`      DATETIME DEFAULT NULL,
                        `resolved_by`      INT DEFAULT NULL,
                        UNIQUE KEY `uq_contest_registration` (`registration_id`),
                        KEY `idx_contest_status` (`status`),
                        FOREIGN KEY (`registration_id`) REFERENCES `live_eval_registrations`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (PDOException $ex) {}
        }

        // Migration 2.6i: assignments become gradable. The lesson carries the scale and the late/resubmission policy; each submission
        // carries its mark, the teacher's feedback, whether it was late and how many versions were sent; older versions are kept.
        foreach ([
            "ALTER TABLE `lessons` ADD COLUMN `assignment_max_score` DECIMAL(6,2) NOT NULL DEFAULT 20",
            "ALTER TABLE `lessons` ADD COLUMN `assignment_allow_late` TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE `lessons` ADD COLUMN `assignment_allow_resubmit` TINYINT(1) NOT NULL DEFAULT 1",
            "ALTER TABLE `lesson_assignment_submissions` ADD COLUMN `is_late` TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE `lesson_assignment_submissions` ADD COLUMN `attempt_count` INT NOT NULL DEFAULT 1",
            "ALTER TABLE `lesson_assignment_submissions` ADD COLUMN `score` DECIMAL(6,2) DEFAULT NULL",
            "ALTER TABLE `lesson_assignment_submissions` ADD COLUMN `feedback` TEXT DEFAULT NULL",
            "ALTER TABLE `lesson_assignment_submissions` ADD COLUMN `graded_at` DATETIME DEFAULT NULL",
            "ALTER TABLE `lesson_assignment_submissions` ADD COLUMN `graded_by` INT DEFAULT NULL",
            "ALTER TABLE `lesson_assignment_submissions` ADD COLUMN `revision_requested_at` DATETIME DEFAULT NULL",
            "ALTER TABLE `lesson_assignment_submissions` ADD COLUMN `revision_note` VARCHAR(500) DEFAULT NULL",
        ] as $ddl) {
            try {
                $pdo->exec($ddl);
            } catch (PDOException $ex) {
                // already applied
            }
        }
        try {
            $pdo->query("SELECT 1 FROM lesson_assignment_history LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `lesson_assignment_history` (
                        `id`             INT AUTO_INCREMENT PRIMARY KEY,
                        `submission_id`  INT NOT NULL,
                        `attempt`        INT NOT NULL,
                        `file_path`      VARCHAR(255) DEFAULT NULL,
                        `file_name`      VARCHAR(255) DEFAULT NULL,
                        `link`           VARCHAR(1000) DEFAULT NULL,
                        `comment`        TEXT DEFAULT NULL,
                        `submitted_at`   DATETIME NOT NULL,
                        `score`          DECIMAL(6,2) DEFAULT NULL,
                        `feedback`       TEXT DEFAULT NULL,
                        `graded_at`      DATETIME DEFAULT NULL,
                        KEY `idx_hist_submission` (`submission_id`),
                        FOREIGN KEY (`submission_id`) REFERENCES `lesson_assignment_submissions`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (PDOException $ex) {}
        }

        // Migration 2.6j: rubrics, release of marks, annotated copies, similarity check (see docs/ASSIGNMENTS_AUDIT.md).
        //  - lessons.assignment_rubric          JSON list of criteria (title, max points, optional levels)
        //  - lessons.assignment_release_mode    'immediate' (default) or 'manual' (marks stay hidden until the teacher releases them)
        //  - submissions.released_at            when the student was allowed to see the mark (NULL while held)
        //  - submissions.rubric_scores          JSON snapshot of the marking grid as it was when the work was marked
        //  - submissions.feedback_file_*        the annotated copy returned by the teacher
        //  - history.*                          the same three for archived versions
        //  - lesson_assignment_similarity       pairs of submissions that look alike (teacher only)
        $releasedColumnIsNew = false;
        try {
            $pdo->query("SELECT released_at FROM lesson_assignment_submissions LIMIT 1");
        } catch (PDOException $e) {
            $releasedColumnIsNew = true;
        }
        foreach ([
            "ALTER TABLE `lessons` ADD COLUMN `assignment_rubric` MEDIUMTEXT DEFAULT NULL",
            "ALTER TABLE `lessons` ADD COLUMN `assignment_release_mode` VARCHAR(12) NOT NULL DEFAULT 'immediate'",
            "ALTER TABLE `lessons` ADD COLUMN `assignment_similarity_at` DATETIME DEFAULT NULL",
            "ALTER TABLE `lesson_assignment_submissions` ADD COLUMN `released_at` DATETIME DEFAULT NULL",
            "ALTER TABLE `lesson_assignment_submissions` ADD COLUMN `rubric_scores` MEDIUMTEXT DEFAULT NULL",
            "ALTER TABLE `lesson_assignment_submissions` ADD COLUMN `feedback_file_path` VARCHAR(255) DEFAULT NULL",
            "ALTER TABLE `lesson_assignment_submissions` ADD COLUMN `feedback_file_name` VARCHAR(255) DEFAULT NULL",
            "ALTER TABLE `lesson_assignment_history` ADD COLUMN `rubric_scores` MEDIUMTEXT DEFAULT NULL",
            "ALTER TABLE `lesson_assignment_history` ADD COLUMN `feedback_file_path` VARCHAR(255) DEFAULT NULL",
            "ALTER TABLE `lesson_assignment_history` ADD COLUMN `feedback_file_name` VARCHAR(255) DEFAULT NULL",
        ] as $ddl) {
            try {
                $pdo->exec($ddl);
            } catch (PDOException $ex) {
                // already applied
            }
        }
        if ($releasedColumnIsNew) {
            // Everything marked before this feature existed was visible at once: it stays visible
            try {
                $pdo->exec("UPDATE `lesson_assignment_submissions` SET `released_at` = `graded_at` WHERE `graded_at` IS NOT NULL AND `released_at` IS NULL");
            } catch (PDOException $ex) {}
        }
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `lesson_assignment_similarity` (
                    `id`          INT AUTO_INCREMENT PRIMARY KEY,
                    `lesson_id`   INT NOT NULL,
                    `sub_a`       INT NOT NULL,
                    `sub_b`       INT NOT NULL,
                    `score`       DECIMAL(5,2) NOT NULL,
                    `shared`      INT NOT NULL DEFAULT 0,
                    `excerpts`    MEDIUMTEXT DEFAULT NULL,
                    `computed_at` DATETIME NOT NULL,
                    KEY `idx_sim_lesson` (`lesson_id`, `score`),
                    FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`sub_a`) REFERENCES `lesson_assignment_submissions`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`sub_b`) REFERENCES `lesson_assignment_submissions`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $ex) {}

        // Migration 2.6k: the reminder sent 15 minutes before a live evaluation. reminder_version holds the version (hash of the start
        // time) of the session for which it was sent, so moving the exam later sends a new reminder at the new time.
        try {
            $pdo->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `reminder_version` VARCHAR(16) DEFAULT NULL");
        } catch (PDOException $ex) {
            // already applied
        }

        // Migration 2.6l: requests from a student to a teacher (a review of a mark, a question about a result, a message about a course).
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `student_requests` (
                    `id`           INT AUTO_INCREMENT PRIMARY KEY,
                    `student_id`   INT NOT NULL,
                    `teacher_id`   INT NOT NULL,
                    `course_id`    INT NOT NULL,
                    `kind`         ENUM('assignment','live_result','course') NOT NULL,
                    `ref_id`       INT DEFAULT NULL,
                    `ref_version`  INT NOT NULL DEFAULT 0,
                    `subject`      VARCHAR(200) NOT NULL,
                    `message`      TEXT NOT NULL,
                    `status`       ENUM('open','answered') NOT NULL DEFAULT 'open',
                    `response`     TEXT DEFAULT NULL,
                    `answered_at`  DATETIME DEFAULT NULL,
                    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_request_item` (`student_id`, `kind`, `ref_id`, `ref_version`),
                    KEY `idx_request_teacher` (`teacher_id`, `status`, `created_at`),
                    KEY `idx_request_student` (`student_id`, `created_at`),
                    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`course_id`)  REFERENCES `courses`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $ex) {}

        // Migration 2.6m: web push (the installed app / browser notifications): one row per device, and an outbox the worker empties.
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `push_subscriptions` (
                    `id`          INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id`     INT NOT NULL,
                    `endpoint`    VARCHAR(600) NOT NULL,
                    `endpoint_hash` CHAR(64) NOT NULL,
                    `p256dh`      VARCHAR(200) NOT NULL,
                    `auth`        VARCHAR(100) NOT NULL,
                    `user_agent`  VARCHAR(255) DEFAULT NULL,
                    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `last_ok_at`  DATETIME DEFAULT NULL,
                    `failures`    INT NOT NULL DEFAULT 0,
                    UNIQUE KEY `uq_push_endpoint` (`endpoint_hash`),
                    KEY `idx_push_user` (`user_id`),
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `push_outbox` (
                    `id`          INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id`     INT NOT NULL,
                    `payload`     TEXT NOT NULL,
                    `status`      ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
                    `attempts`    INT NOT NULL DEFAULT 0,
                    `last_error`  VARCHAR(255) DEFAULT NULL,
                    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `sent_at`     DATETIME DEFAULT NULL,
                    KEY `idx_push_outbox` (`status`, `id`),
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $ex) {}

        // Migration 2.6n: where a student of an asynchronous evaluation stands, so that coming back (another tab, another day, another
        // device) puts them on the question they left, with the time they had.
        try {
            $pdo->query("SELECT async_q_id FROM live_eval_registrations LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE live_eval_registrations ADD COLUMN async_q_id INT DEFAULT NULL, ADD COLUMN async_q_started_at DATETIME DEFAULT NULL");
            } catch (PDOException $ex) {}
        }

        // Migration 2.6o: every transcript handed out is recorded (code, summary, fingerprint) so that its QR code can be checked on verify.php.
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `transcript_issues` (
                    `id`            INT AUTO_INCREMENT PRIMARY KEY,
                    `code`          VARCHAR(24) NOT NULL,
                    `student_id`    INT NOT NULL,
                    `summary`       TEXT NOT NULL,
                    `fingerprint`   CHAR(64) NOT NULL,
                    `issued_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `issued_ip`     VARCHAR(45) DEFAULT NULL,
                    `revoked_at`    DATETIME DEFAULT NULL,
                    `revoked_reason` VARCHAR(255) DEFAULT NULL,
                    UNIQUE KEY `uq_transcript_code` (`code`),
                    KEY `idx_transcript_student` (`student_id`, `issued_at`),
                    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $ex) {}

        // Migration 2.6p: one-time codes e-mailed to open the control center (kept hashed, short-lived, single use).
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `ops_unlock_codes` (
                    `id`          INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id`     INT NOT NULL,
                    `code_hash`   CHAR(64) NOT NULL,
                    `attempts`    INT NOT NULL DEFAULT 0,
                    `ip`          VARCHAR(45) DEFAULT NULL,
                    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `expires_at`  DATETIME NOT NULL,
                    `consumed_at` DATETIME DEFAULT NULL,
                    KEY `idx_ops_unlock_user` (`user_id`, `consumed_at`),
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $ex) {}

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

        // Migration 2.20: careers. Job postings the administrator publishes, the applications they receive, and the review trail.
        // Kept apart from every other table on purpose: nothing here references courses, lessons or students, and the files live in
        // their own storage kind (career_resume) that only the administrator's careers workspace can read.
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `career_jobs` (
                    `id`               INT AUTO_INCREMENT PRIMARY KEY,
                    `slug`             VARCHAR(120) NOT NULL,
                    `title`            VARCHAR(160) NOT NULL,
                    `department`       VARCHAR(80)  NOT NULL DEFAULT '',
                    `location`         VARCHAR(120) NOT NULL DEFAULT '',
                    `work_mode`        ENUM('onsite','hybrid','remote') NOT NULL DEFAULT 'onsite',
                    `employment_type`  ENUM('full_time','part_time','internship','contract','volunteer') NOT NULL DEFAULT 'full_time',
                    `level`            VARCHAR(40)  NOT NULL DEFAULT '',
                    `summary`          VARCHAR(400) NOT NULL DEFAULT '',
                    `description`      TEXT DEFAULT NULL,
                    `responsibilities` TEXT DEFAULT NULL,
                    `requirements`     TEXT DEFAULT NULL,
                    `nice_to_have`     TEXT DEFAULT NULL,
                    `benefits`         TEXT DEFAULT NULL,
                    `salary_note`      VARCHAR(160) NOT NULL DEFAULT '',
                    `openings`         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                    `status`           ENUM('draft','open','paused','closed') NOT NULL DEFAULT 'draft',
                    `closes_at`        DATE DEFAULT NULL,
                    `published_at`     DATETIME DEFAULT NULL,
                    `closed_at`        DATETIME DEFAULT NULL,
                    `created_by`       INT DEFAULT NULL,
                    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_career_slug` (`slug`),
                    KEY `idx_career_status` (`status`, `published_at`),
                    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            // job_id is NULL for an open (spontaneous) application, and after a posting is deleted; job_title keeps what it was.
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `career_applications` (
                    `id`                INT AUTO_INCREMENT PRIMARY KEY,
                    `reference`         VARCHAR(16) NOT NULL,
                    `job_id`            INT DEFAULT NULL,
                    `job_title`         VARCHAR(160) NOT NULL DEFAULT '',
                    `full_name`         VARCHAR(120) NOT NULL,
                    `email`             VARCHAR(190) NOT NULL,
                    `phone`             VARCHAR(40)  NOT NULL DEFAULT '',
                    `location`          VARCHAR(120) NOT NULL DEFAULT '',
                    `linkedin_url`      VARCHAR(255) NOT NULL DEFAULT '',
                    `portfolio_url`     VARCHAR(255) NOT NULL DEFAULT '',
                    `cover_letter`      TEXT DEFAULT NULL,
                    `resume_file`       VARCHAR(80)  DEFAULT NULL,
                    `resume_name`       VARCHAR(160) DEFAULT NULL,
                    `resume_size`       INT UNSIGNED NOT NULL DEFAULT 0,
                    `availability`      VARCHAR(80)  NOT NULL DEFAULT '',
                    `salary_expectation` VARCHAR(80) NOT NULL DEFAULT '',
                    `source`            VARCHAR(60)  NOT NULL DEFAULT '',
                    `lang`              CHAR(2) NOT NULL DEFAULT 'fr',
                    `consent_at`        DATETIME NOT NULL,
                    `status`            ENUM('new','reviewing','shortlisted','interview','offer','hired','rejected') NOT NULL DEFAULT 'new',
                    `rating`            TINYINT UNSIGNED DEFAULT NULL,
                    `is_starred`        TINYINT(1) NOT NULL DEFAULT 0,
                    `ip_hash`           CHAR(64) DEFAULT NULL,
                    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `status_changed_at` DATETIME DEFAULT NULL,
                    `reviewed_by`       INT DEFAULT NULL,
                    UNIQUE KEY `uq_career_app_ref` (`reference`),
                    UNIQUE KEY `uq_career_app_once` (`job_id`, `email`),
                    KEY `idx_career_app_status` (`status`, `created_at`),
                    KEY `idx_career_app_job` (`job_id`, `status`),
                    KEY `idx_career_app_email` (`email`),
                    FOREIGN KEY (`job_id`) REFERENCES `career_jobs`(`id`) ON DELETE SET NULL,
                    FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            // The review trail: notes, status changes, e-mails sent to the candidate.
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `career_application_events` (
                    `id`             INT AUTO_INCREMENT PRIMARY KEY,
                    `application_id` INT NOT NULL,
                    `actor_id`       INT DEFAULT NULL,
                    `kind`           ENUM('note','status','email','rating') NOT NULL,
                    `from_status`    VARCHAR(20) DEFAULT NULL,
                    `to_status`      VARCHAR(20) DEFAULT NULL,
                    `body`           TEXT DEFAULT NULL,
                    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_career_event_app` (`application_id`, `created_at`),
                    FOREIGN KEY (`application_id`) REFERENCES `career_applications`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`actor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $ex) {
            error_log('Migration 2.20 (careers): ' . $ex->getMessage());
        }

        // Migration 2.21: applicant tracking (ATS). Screening criteria on the posting; profile, screening result, bucket and tracking token on
        // the application; the interview schedule; the text extracted from the résumé (kept apart so lists stay light); worker bookkeeping.
        foreach ([
            "ALTER TABLE `career_jobs`
                ADD COLUMN `screen_enabled` TINYINT(1) NOT NULL DEFAULT 1,
                ADD COLUMN `must_have` TEXT DEFAULT NULL,
                ADD COLUMN `nice_have` TEXT DEFAULT NULL,
                ADD COLUMN `min_years` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                ADD COLUMN `min_education` VARCHAR(16) NOT NULL DEFAULT '',
                ADD COLUMN `screen_threshold` TINYINT UNSIGNED NOT NULL DEFAULT 35,
                ADD COLUMN `auto_decline_days` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                ADD COLUMN `screen_version` SMALLINT UNSIGNED NOT NULL DEFAULT 1",
            "ALTER TABLE `career_applications`
                ADD COLUMN `years_experience` TINYINT UNSIGNED DEFAULT NULL,
                ADD COLUMN `current_title` VARCHAR(120) NOT NULL DEFAULT '',
                ADD COLUMN `current_company` VARCHAR(120) NOT NULL DEFAULT '',
                ADD COLUMN `education_level` VARCHAR(16) NOT NULL DEFAULT '',
                ADD COLUMN `skills` VARCHAR(500) NOT NULL DEFAULT '',
                ADD COLUMN `languages` VARCHAR(160) NOT NULL DEFAULT '',
                ADD COLUMN `work_pref` VARCHAR(12) NOT NULL DEFAULT '',
                ADD COLUMN `relocate` TINYINT(1) DEFAULT NULL,
                ADD COLUMN `heard_from` VARCHAR(40) NOT NULL DEFAULT '',
                ADD COLUMN `talent_consent` TINYINT(1) NOT NULL DEFAULT 0,
                ADD COLUMN `bucket` ENUM('inbox','screened_out','archived') NOT NULL DEFAULT 'inbox',
                ADD COLUMN `bucket_changed_at` DATETIME DEFAULT NULL,
                ADD COLUMN `screen_state` ENUM('pending','done','error') NOT NULL DEFAULT 'pending',
                ADD COLUMN `screen_score` TINYINT UNSIGNED DEFAULT NULL,
                ADD COLUMN `screen_verdict` ENUM('strong','potential','review','weak') DEFAULT NULL,
                ADD COLUMN `screen_report` MEDIUMTEXT DEFAULT NULL,
                ADD COLUMN `screened_at` DATETIME DEFAULT NULL,
                ADD COLUMN `screen_version` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                ADD COLUMN `track_token` CHAR(40) NOT NULL DEFAULT '',
                ADD COLUMN `archived_at` DATETIME DEFAULT NULL,
                ADD COLUMN `withdrawn_at` DATETIME DEFAULT NULL",
            "ALTER TABLE `career_applications` MODIFY COLUMN `status` ENUM('new','reviewing','shortlisted','interview','offer','hired','rejected','withdrawn') NOT NULL DEFAULT 'new'",
            "ALTER TABLE `career_application_events` MODIFY COLUMN `kind` ENUM('note','status','email','rating','screen','interview','candidate','bucket') NOT NULL",
            "ALTER TABLE `career_application_events` ADD COLUMN `visible` TINYINT(1) NOT NULL DEFAULT 0",
        ] as $ddl) {
            try { $pdo->exec($ddl); } catch (PDOException $ex) { /* already applied */ }
        }
        try {
            $pdo->exec("UPDATE `career_applications` SET `track_token` = SHA1(CONCAT(id, RAND(), UUID(), NOW())) WHERE `track_token` = ''");
            $pdo->exec("UPDATE `career_applications` SET `bucket_changed_at` = `created_at` WHERE `bucket_changed_at` IS NULL");
        } catch (PDOException $ex) {}
        foreach ([
            "ALTER TABLE `career_applications` ADD UNIQUE KEY `uq_career_app_token` (`track_token`)",
            "ALTER TABLE `career_applications` ADD KEY `idx_career_app_bucket` (`bucket`, `created_at`)",
            "ALTER TABLE `career_applications` ADD KEY `idx_career_app_screen` (`screen_state`, `screen_version`)",
        ] as $ddl) {
            try { $pdo->exec($ddl); } catch (PDOException $ex) { /* already there */ }
        }
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `career_application_texts` (
                    `application_id` INT NOT NULL PRIMARY KEY,
                    `resume_text`    MEDIUMTEXT DEFAULT NULL,
                    `chars`          INT NOT NULL DEFAULT 0,
                    `via`            VARCHAR(12) NOT NULL DEFAULT '',
                    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`application_id`) REFERENCES `career_applications`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `career_interviews` (
                    `id`              INT AUTO_INCREMENT PRIMARY KEY,
                    `application_id`  INT NOT NULL,
                    `scheduled_at`    DATETIME NOT NULL,
                    `duration_min`    SMALLINT UNSIGNED NOT NULL DEFAULT 45,
                    `mode`            ENUM('video','onsite','phone') NOT NULL DEFAULT 'video',
                    `location`        VARCHAR(255) NOT NULL DEFAULT '',
                    `interviewer`     VARCHAR(120) NOT NULL DEFAULT '',
                    `instructions`    TEXT DEFAULT NULL,
                    `status`          ENUM('scheduled','confirmed','declined','reschedule_requested','completed','cancelled') NOT NULL DEFAULT 'scheduled',
                    `candidate_note`  VARCHAR(500) DEFAULT NULL,
                    `responded_at`    DATETIME DEFAULT NULL,
                    `invited_at`      DATETIME DEFAULT NULL,
                    `reminder24_at`   DATETIME DEFAULT NULL,
                    `reminder2_at`    DATETIME DEFAULT NULL,
                    `chase_at`        DATETIME DEFAULT NULL,
                    `created_by`      INT DEFAULT NULL,
                    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY `idx_career_int_app` (`application_id`, `scheduled_at`),
                    KEY `idx_career_int_when` (`status`, `scheduled_at`),
                    FOREIGN KEY (`application_id`) REFERENCES `career_applications`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `career_state` (
                    `name`       VARCHAR(60) NOT NULL PRIMARY KEY,
                    `value`      VARCHAR(255) NOT NULL DEFAULT '',
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $ex) {
            error_log('Migration 2.21 (ATS): ' . $ex->getMessage());
        }

        // Migration 2.22: every posting carries a 4-character code; application references are built from it (see Careers::newReference).
        try {
            try { $pdo->exec("ALTER TABLE `career_jobs` ADD COLUMN `code` CHAR(4) NOT NULL DEFAULT ''"); } catch (PDOException $ex) { /* already applied */ }
            require_once __DIR__ . '/lib/Careers.php';
            Careers::backfillCodes($pdo);
            try { $pdo->exec("ALTER TABLE `career_jobs` ADD UNIQUE KEY `uq_career_code` (`code`)"); } catch (PDOException $ex) { /* already there */ }
        } catch (Throwable $ex) {
            error_log('Migration 2.22 (job codes): ' . $ex->getMessage());
        }

        // Migration 2.23: the team of a posting is chosen from a fixed list (Careers::TEAMS); older free-text values are mapped to the nearest team.
        try {
            require_once __DIR__ . '/lib/Careers.php';
            foreach ($pdo->query("SELECT id, department FROM career_jobs WHERE department <> ''")->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = Careers::teamKey((string)$row['department']);
                if ($key !== '' && $key !== $row['department']) {
                    $pdo->prepare('UPDATE career_jobs SET department = :k WHERE id = :i')->execute(['k' => $key, 'i' => $row['id']]);
                }
            }
        } catch (Throwable $ex) {
            error_log('Migration 2.23 (teams): ' . $ex->getMessage());
        }

        // Migration 2.24: the cover letter is an uploaded file (stored privately like the résumé), its text is read for the screening.
        foreach ([
            "ALTER TABLE `career_applications` ADD COLUMN `cover_file` VARCHAR(80) DEFAULT NULL, ADD COLUMN `cover_name` VARCHAR(160) DEFAULT NULL, ADD COLUMN `cover_size` INT UNSIGNED NOT NULL DEFAULT 0",
            "ALTER TABLE `career_application_texts` ADD COLUMN `cover_text` MEDIUMTEXT DEFAULT NULL",
        ] as $ddl) {
            try { $pdo->exec($ddl); } catch (PDOException $ex) { /* already applied */ }
        }

        // Migration 2.25: public numeric id of a posting (the address is /jobs/<id>, not a slug).
        try { $pdo->exec("ALTER TABLE `career_jobs` ADD COLUMN `public_id` BIGINT UNSIGNED DEFAULT NULL"); } catch (PDOException $ex) { /* already applied */ }
        try {
            $missing = $pdo->query("SELECT id FROM career_jobs WHERE public_id IS NULL")->fetchAll(PDO::FETCH_COLUMN);
            $set = $pdo->prepare("UPDATE career_jobs SET public_id = :p WHERE id = :i AND public_id IS NULL");
            foreach ($missing as $jid) {
                for ($try = 0; $try < 20; $try++) {
                    try { $set->execute(['p' => random_int(1000000000, 9999999999), 'i' => $jid]); break; } catch (PDOException $dup) { /* collision: draw again */ }
                }
            }
        } catch (PDOException $ex) { /* table absent on a very old schema */ }
        try { $pdo->exec("ALTER TABLE `career_jobs` ADD UNIQUE KEY `uq_career_public_id` (`public_id`)"); } catch (PDOException $ex) { /* already applied */ }

        // Migration 2.30: load balancer of the marking keys (one lane per key and model: when it may be used again, how many calls this minute).
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `ai_key_state` (
                    `id`             INT AUTO_INCREMENT PRIMARY KEY,
                    `key_hash`       CHAR(16) NOT NULL,
                    `model`          VARCHAR(60) NOT NULL,
                    `cooldown_until` DATETIME DEFAULT NULL,
                    `window_start`   DATETIME DEFAULT NULL,
                    `window_calls`   INT NOT NULL DEFAULT 0,
                    `last_used`      DATETIME(3) DEFAULT NULL,
                    `ok_count`       INT NOT NULL DEFAULT 0,
                    `fail_count`     INT NOT NULL DEFAULT 0,
                    `last_error`     VARCHAR(20) DEFAULT NULL,
                    UNIQUE KEY `uq_ai_lane` (`key_hash`, `model`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $ex) {}

        // Migration 2.29: a teacher can re-mark a handed-in evaluation; the next results email then goes out again, as an update.
        try { $pdo->exec("ALTER TABLE `live_eval_registrations` ADD COLUMN `resend_results` TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $ex) { /* already applied */ }

        // Migration 2.28: free-pace evaluations (common start, everybody moves and hands in at their own speed): the answers wait in a bucket
        // (drafts) until the student submits them all; a timed question counts only the time it has been open.
        foreach ([
            "ALTER TABLE `live_eval_sessions` ADD COLUMN `free_pace` TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE `live_eval_registrations` ADD COLUMN `submitted_at` DATETIME DEFAULT NULL, ADD COLUMN `current_q_id` INT DEFAULT NULL, ADD COLUMN `current_since` DATETIME DEFAULT NULL",
            "CREATE TABLE IF NOT EXISTS `live_eval_drafts` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `registration_id` INT NOT NULL,
                `question_id` INT NOT NULL,
                `answer` MEDIUMTEXT NOT NULL,
                `seconds_used` INT NOT NULL DEFAULT 0,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_draft` (`registration_id`, `question_id`),
                CONSTRAINT `fk_draft_reg` FOREIGN KEY (`registration_id`) REFERENCES `live_eval_registrations` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_draft_q` FOREIGN KEY (`question_id`) REFERENCES `live_eval_questions` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ] as $ddl) {
            try { $pdo->exec($ddl); } catch (PDOException $ex) { /* already applied */ }
        }

        // Migration 2.27: code questions in live evaluations (the student writes code, an AI reads and marks it, the teacher can overrule).
        foreach ([
            "ALTER TABLE `live_eval_questions` ADD COLUMN `code_lang` VARCHAR(24) DEFAULT NULL, ADD COLUMN `code_reference` MEDIUMTEXT DEFAULT NULL, ADD COLUMN `code_notes` TEXT DEFAULT NULL",
            "CREATE TABLE IF NOT EXISTS `live_eval_code_reviews` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `registration_id` INT NOT NULL,
                `question_id` INT NOT NULL,
                `code` MEDIUMTEXT NOT NULL,
                `code_hash` CHAR(64) NOT NULL,
                `status` VARCHAR(12) NOT NULL DEFAULT 'pending',
                `verdict` TINYINT(1) DEFAULT NULL,
                `needs_human` TINYINT(1) NOT NULL DEFAULT 0,
                `feedback` TEXT DEFAULT NULL,
                `logic_analysis` TEXT DEFAULT NULL,
                `issues` MEDIUMTEXT DEFAULT NULL,
                `corrected_code` MEDIUMTEXT DEFAULT NULL,
                `model` VARCHAR(48) DEFAULT NULL,
                `attempts` INT NOT NULL DEFAULT 0,
                `last_error` VARCHAR(200) DEFAULT NULL,
                `next_attempt_at` DATETIME DEFAULT NULL,
                `locked_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `reviewed_at` DATETIME DEFAULT NULL,
                `override_verdict` TINYINT(1) DEFAULT NULL,
                `override_note` TEXT DEFAULT NULL,
                `override_by` INT DEFAULT NULL,
                `override_at` DATETIME DEFAULT NULL,
                UNIQUE KEY `uq_code_review` (`registration_id`, `question_id`),
                KEY `idx_code_status` (`status`, `next_attempt_at`),
                KEY `idx_code_hash` (`question_id`, `code_hash`),
                CONSTRAINT `fk_code_review_reg` FOREIGN KEY (`registration_id`) REFERENCES `live_eval_registrations` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_code_review_q` FOREIGN KEY (`question_id`) REFERENCES `live_eval_questions` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ] as $ddl) {
            try { $pdo->exec($ddl); } catch (PDOException $ex) { /* already applied */ }
        }

        // Migration 2.26: an evaluation can start live and go on as a free assignment once the live window is over.
        try { $pdo->exec("ALTER TABLE `live_eval_sessions` ADD COLUMN `async_after_live` TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $ex) { /* already applied */ }
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
