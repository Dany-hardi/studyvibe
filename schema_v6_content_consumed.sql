ALTER TABLE lesson_progress
    ADD COLUMN content_consumed TINYINT(1) NOT NULL DEFAULT 0 AFTER completed;
