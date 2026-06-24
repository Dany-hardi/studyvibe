-- StudyVibe LMS — Migration v3 (réponses par question de leçon)
-- Exécuter après schema.sql / schema_v2_migration.sql

USE studyvibe;

CREATE TABLE IF NOT EXISTS lesson_question_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    question_id INT NOT NULL,
    answered_correctly TINYINT(1) NOT NULL DEFAULT 0,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_question (student_id, question_id),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES lesson_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
