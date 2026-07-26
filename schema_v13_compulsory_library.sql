-- Schema Migration v13: Compulsory/Optional Lessons & Course Library (Bibliothèque)
ALTER TABLE lessons ADD COLUMN IF NOT EXISTS is_compulsory TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order;

CREATE TABLE IF NOT EXISTS course_library_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    category ENUM('syllabus', 'pdf', 'video', 'guide', 'text_markdown', 'other') NOT NULL DEFAULT 'pdf',
    description TEXT NULL,
    content_markdown LONGTEXT NULL,
    file_path VARCHAR(255) NULL,
    file_size BIGINT NULL,
    video_url VARCHAR(500) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
