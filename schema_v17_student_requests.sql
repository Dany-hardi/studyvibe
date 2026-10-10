-- Requests from a student to a teacher (applied automatically by Database.php, migration 2.6l).
CREATE TABLE IF NOT EXISTS student_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT NOT NULL,
    teacher_id   INT NOT NULL,
    course_id    INT NOT NULL,
    kind         ENUM('assignment','live_result','course') NOT NULL,
    ref_id       INT DEFAULT NULL,
    ref_version  INT NOT NULL DEFAULT 0,
    subject      VARCHAR(200) NOT NULL,
    message      TEXT NOT NULL,
    status       ENUM('open','answered') NOT NULL DEFAULT 'open',
    response     TEXT DEFAULT NULL,
    answered_at  DATETIME DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_request_item (student_id, kind, ref_id, ref_version),
    KEY idx_request_teacher (teacher_id, status, created_at),
    KEY idx_request_student (student_id, created_at),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
