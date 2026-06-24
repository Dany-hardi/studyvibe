-- StudyVibe LMS — Migration Téléévaluation Synchrone (QuizBox)
-- Exécuter sur la base de données studyvibe

USE studyvibe;

-- Table des sessions de téléévaluation
CREATE TABLE IF NOT EXISTS live_eval_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    session_code VARCHAR(64) UNIQUE NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 0, -- 0: Désactivé, 1: Activé
    default_time_limit INT NOT NULL DEFAULT 30, -- Temps en secondes par défaut par question
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des questions associées aux téléévaluations
CREATE TABLE IF NOT EXISTS live_eval_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct_option CHAR(1) NOT NULL,
    time_limit INT DEFAULT NULL, -- Surcharge éventuelle du temps limite pour cette question (en secondes)
    image_path VARCHAR(255) DEFAULT NULL, -- Chemin de l'image si applicable
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (session_id) REFERENCES live_eval_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des inscriptions temporaires des participants
CREATE TABLE IF NOT EXISTS live_eval_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    score DECIMAL(5,2) DEFAULT NULL, -- Note finale stockée après correction
    UNIQUE KEY unique_session_email (session_id, email),
    FOREIGN KEY (session_id) REFERENCES live_eval_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des réponses données par les participants
CREATE TABLE IF NOT EXISTS live_eval_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option CHAR(1) NOT NULL,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reg_question (registration_id, question_id),
    FOREIGN KEY (registration_id) REFERENCES live_eval_registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES live_eval_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
