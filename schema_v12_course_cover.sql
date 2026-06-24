-- Migration v12 : Image de couverture pour les cours
-- Ajoute une colonne cover_image a la table courses

ALTER TABLE courses ADD COLUMN IF NOT EXISTS cover_image VARCHAR(255) DEFAULT NULL;
