-- Migration v10 : Date limite pour les quiz de leçons
USE studyvibe;

ALTER TABLE lessons
    ADD COLUMN quiz_deadline DATETIME NULL DEFAULT NULL;
