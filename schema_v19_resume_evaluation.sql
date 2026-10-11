-- Resuming an asynchronous evaluation: the question the student is on and when its timer started.
ALTER TABLE live_eval_registrations ADD COLUMN async_q_id INT DEFAULT NULL, ADD COLUMN async_q_started_at DATETIME DEFAULT NULL;
