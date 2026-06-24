-- StudyVibe LMS — Migration v2 (nouvelles fonctionnalités)
-- Exécuter après schema.sql sur une base existante

USE studyvibe;

-- Calendrier académique & durée QCM
ALTER TABLE courses
    ADD COLUMN IF NOT EXISTS start_date DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS end_date DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS eval_deadline DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS exam_duration_minutes INT NOT NULL DEFAULT 90;

-- Q&A sous les leçons
CREATE TABLE IF NOT EXISTS lesson_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES lesson_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chronomètre de session d'étude
CREATE TABLE IF NOT EXISTS study_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    lesson_id INT NOT NULL,
    seconds_spent INT NOT NULL DEFAULT 0,
    session_date DATE NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_daily_session (student_id, lesson_id, session_date),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gamification — badges
CREATE TABLE IF NOT EXISTS student_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    badge_type VARCHAR(50) NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_badge (student_id, badge_type),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Certificats délivrés manuellement
ALTER TABLE certificates
    ADD COLUMN IF NOT EXISTS manual_issue TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS issued_by INT DEFAULT NULL;

-- Clés API REST
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_hash VARCHAR(64) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    created_by INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
