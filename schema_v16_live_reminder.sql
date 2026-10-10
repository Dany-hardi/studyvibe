-- The reminder sent 15 minutes before a live evaluation (applied automatically by Database.php, migration 2.6k).
ALTER TABLE live_eval_sessions ADD COLUMN reminder_version VARCHAR(16) DEFAULT NULL;
