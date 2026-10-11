-- Web push: devices that accepted notifications, and the queue the worker (lib/email-worker.php) empties.
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NOT NULL, `endpoint` VARCHAR(600) NOT NULL, `endpoint_hash` CHAR(64) NOT NULL,
    `p256dh` VARCHAR(200) NOT NULL, `auth` VARCHAR(100) NOT NULL, `user_agent` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `last_ok_at` DATETIME DEFAULT NULL, `failures` INT NOT NULL DEFAULT 0,
    UNIQUE KEY `uq_push_endpoint` (`endpoint_hash`), KEY `idx_push_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `push_outbox` (
    `id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NOT NULL, `payload` TEXT NOT NULL, `status` ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    `attempts` INT NOT NULL DEFAULT 0, `last_error` VARCHAR(255) DEFAULT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `sent_at` DATETIME DEFAULT NULL,
    KEY `idx_push_outbox` (`status`, `id`), FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
