-- Migration v7 : création de cours par les enseignants, révocation d'assignation promoteur
ALTER TABLE courses
    ADD COLUMN created_by INT DEFAULT NULL AFTER teacher_id;

ALTER TABLE courses
    ADD CONSTRAINT fk_courses_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE courses
    MODIFY teacher_id INT NULL;
