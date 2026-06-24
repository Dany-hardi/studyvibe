-- StudyVibe — Relevés de tentative certification (échec)
USE studyvibe;

ALTER TABLE certification_attempts
    ADD COLUMN IF NOT EXISTS total_questions INT DEFAULT NULL;
