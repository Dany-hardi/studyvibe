-- Migration v8 : tables médias de leçon (vidéos multiples + ressources)
-- Utilisées par teacher/dashboard.php

USE studyvibe;

CREATE TABLE IF NOT EXISTS lesson_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    label VARCHAR(255) NOT NULL DEFAULT 'Vidéo',
    url VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    INDEX idx_lesson_videos_lesson (lesson_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    label VARCHAR(255) NOT NULL DEFAULT 'Ressource',
    url VARCHAR(500) NOT NULL,
    resource_type ENUM('link', 'file', 'reference') NOT NULL DEFAULT 'link',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    INDEX idx_lesson_resources_lesson (lesson_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
