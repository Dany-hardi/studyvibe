-- Assignments: rubrics, release of marks, annotated copies, similarity check.
-- Applied automatically by Database.php (migration 2.6j); this file is the same thing for a manual run.

ALTER TABLE lessons ADD COLUMN assignment_rubric MEDIUMTEXT DEFAULT NULL;
ALTER TABLE lessons ADD COLUMN assignment_release_mode VARCHAR(12) NOT NULL DEFAULT 'immediate';
ALTER TABLE lessons ADD COLUMN assignment_similarity_at DATETIME DEFAULT NULL;

ALTER TABLE lesson_assignment_submissions ADD COLUMN released_at DATETIME DEFAULT NULL;
ALTER TABLE lesson_assignment_submissions ADD COLUMN rubric_scores MEDIUMTEXT DEFAULT NULL;
ALTER TABLE lesson_assignment_submissions ADD COLUMN feedback_file_path VARCHAR(255) DEFAULT NULL;
ALTER TABLE lesson_assignment_submissions ADD COLUMN feedback_file_name VARCHAR(255) DEFAULT NULL;
-- what was marked before this feature stays visible
UPDATE lesson_assignment_submissions SET released_at = graded_at WHERE graded_at IS NOT NULL AND released_at IS NULL;

ALTER TABLE lesson_assignment_history ADD COLUMN rubric_scores MEDIUMTEXT DEFAULT NULL;
ALTER TABLE lesson_assignment_history ADD COLUMN feedback_file_path VARCHAR(255) DEFAULT NULL;
ALTER TABLE lesson_assignment_history ADD COLUMN feedback_file_name VARCHAR(255) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS lesson_assignment_similarity (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id   INT NOT NULL,
    sub_a       INT NOT NULL,
    sub_b       INT NOT NULL,
    score       DECIMAL(5,2) NOT NULL,
    shared      INT NOT NULL DEFAULT 0,
    excerpts    MEDIUMTEXT DEFAULT NULL,
    computed_at DATETIME NOT NULL,
    KEY idx_sim_lesson (lesson_id, score),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (sub_a) REFERENCES lesson_assignment_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (sub_b) REFERENCES lesson_assignment_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
