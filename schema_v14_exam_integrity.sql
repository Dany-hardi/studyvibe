-- Migration v14 : intégrité des examens en direct
-- Appliquée automatiquement par Database.php (migration 2.6b). Ce fichier sert aux installations qui préfèrent migrer à la main.

ALTER TABLE live_eval_sessions
    ADD COLUMN shuffle_options TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN integrity_watch TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE live_eval_registrations
    ADD COLUMN focus_losses INT NOT NULL DEFAULT 0,
    ADD COLUMN last_focus_loss_at DATETIME DEFAULT NULL;
