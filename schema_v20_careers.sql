-- StudyVibe v20: careers (job postings, applications, review trail).
-- Applied automatically by Database.php (migration 2.20); this file documents the schema for a manual install.
-- Résumés are stored under uploads/career-resumes/ (MediaStore kind "career_resume") and read only from promoter/careers/resume.php.

CREATE TABLE IF NOT EXISTS `career_jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(120) NOT NULL,
    `title` VARCHAR(160) NOT NULL,
    `department` VARCHAR(80) NOT NULL DEFAULT '',
    `location` VARCHAR(120) NOT NULL DEFAULT '',
    `work_mode` ENUM('onsite','hybrid','remote') NOT NULL DEFAULT 'onsite',
    `employment_type` ENUM('full_time','part_time','internship','contract','volunteer') NOT NULL DEFAULT 'full_time',
    `level` VARCHAR(40) NOT NULL DEFAULT '',
    `summary` VARCHAR(400) NOT NULL DEFAULT '',
    `description` TEXT, `responsibilities` TEXT, `requirements` TEXT, `nice_to_have` TEXT, `benefits` TEXT,
    `salary_note` VARCHAR(160) NOT NULL DEFAULT '',
    `openings` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `status` ENUM('draft','open','paused','closed') NOT NULL DEFAULT 'draft',
    `closes_at` DATE DEFAULT NULL, `published_at` DATETIME DEFAULT NULL, `closed_at` DATETIME DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_career_slug` (`slug`),
    KEY `idx_career_status` (`status`, `published_at`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `career_applications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reference` VARCHAR(16) NOT NULL,
    `job_id` INT DEFAULT NULL,
    `job_title` VARCHAR(160) NOT NULL DEFAULT '',
    `full_name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `phone` VARCHAR(40) NOT NULL DEFAULT '',
    `location` VARCHAR(120) NOT NULL DEFAULT '',
    `linkedin_url` VARCHAR(255) NOT NULL DEFAULT '',
    `portfolio_url` VARCHAR(255) NOT NULL DEFAULT '',
    `cover_letter` TEXT,
    `resume_file` VARCHAR(80) DEFAULT NULL, `resume_name` VARCHAR(160) DEFAULT NULL, `resume_size` INT UNSIGNED NOT NULL DEFAULT 0,
    `availability` VARCHAR(80) NOT NULL DEFAULT '',
    `salary_expectation` VARCHAR(80) NOT NULL DEFAULT '',
    `source` VARCHAR(60) NOT NULL DEFAULT '',
    `lang` CHAR(2) NOT NULL DEFAULT 'fr',
    `consent_at` DATETIME NOT NULL,
    `status` ENUM('new','reviewing','shortlisted','interview','offer','hired','rejected') NOT NULL DEFAULT 'new',
    `rating` TINYINT UNSIGNED DEFAULT NULL,
    `is_starred` TINYINT(1) NOT NULL DEFAULT 0,
    `ip_hash` CHAR(64) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `status_changed_at` DATETIME DEFAULT NULL,
    `reviewed_by` INT DEFAULT NULL,
    UNIQUE KEY `uq_career_app_ref` (`reference`),
    UNIQUE KEY `uq_career_app_once` (`job_id`, `email`),
    KEY `idx_career_app_status` (`status`, `created_at`),
    KEY `idx_career_app_job` (`job_id`, `status`),
    KEY `idx_career_app_email` (`email`),
    FOREIGN KEY (`job_id`) REFERENCES `career_jobs`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `career_application_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT NOT NULL,
    `actor_id` INT DEFAULT NULL,
    `kind` ENUM('note','status','email','rating') NOT NULL,
    `from_status` VARCHAR(20) DEFAULT NULL, `to_status` VARCHAR(20) DEFAULT NULL,
    `body` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_career_event_app` (`application_id`, `created_at`),
    FOREIGN KEY (`application_id`) REFERENCES `career_applications`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`actor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
