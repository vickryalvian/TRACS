-- TRACS ticker and notification schema.

CREATE TABLE IF NOT EXISTS `tracs_ticker_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_by_name` VARCHAR(150) DEFAULT NULL,
  `text` VARCHAR(500) NOT NULL,
  `class` ENUM('normal','info','urgent','critical') NOT NULL DEFAULT 'normal',
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tm_active` (`user_id`, `enabled`, `class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_ticker_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_by_name` VARCHAR(150) DEFAULT NULL,
  `message` VARCHAR(500) NOT NULL,
  `type` ENUM('info','success','warning','critical') NOT NULL DEFAULT 'info',
  `module` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT UNSIGNED DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_te_active` (`user_id`, `expires_at`),
  INDEX `idx_te_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `notification_type` VARCHAR(80) NOT NULL,
  `target_user_id` INT UNSIGNED NOT NULL,
  `related_module` VARCHAR(80) NOT NULL DEFAULT '',
  `related_entity_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `trigger_type` VARCHAR(60) NOT NULL DEFAULT 'created',
  `dedupe_key` VARCHAR(190) NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `message` VARCHAR(500) NOT NULL,
  `related_url` VARCHAR(255) DEFAULT NULL,
  `actor_user_id` INT UNSIGNED DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME DEFAULT NULL,
  `push_status` ENUM('pending','sent','failed','unavailable','skipped') NOT NULL DEFAULT 'pending',
  `push_attempted_at` DATETIME DEFAULT NULL,
  `push_sent_at` DATETIME DEFAULT NULL,
  `push_error` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `scheduled_at` DATETIME DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracs_notification_dedupe` (`dedupe_key`),
  INDEX `idx_tracs_notifications_user_unread` (`target_user_id`, `is_read`, `sent_at`),
  INDEX `idx_tracs_notifications_push` (`target_user_id`, `push_status`, `sent_at`),
  INDEX `idx_tracs_notifications_related` (`related_module`, `related_entity_id`, `trigger_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_notification_triggers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dedupe_key` VARCHAR(190) NOT NULL,
  `target_user_id` INT UNSIGNED NOT NULL,
  `related_module` VARCHAR(80) NOT NULL DEFAULT '',
  `related_entity_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `trigger_type` VARCHAR(60) NOT NULL,
  `notification_id` BIGINT UNSIGNED DEFAULT NULL,
  `triggered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracs_notification_trigger` (`dedupe_key`),
  INDEX `idx_tracs_notification_trigger_lookup` (`target_user_id`, `related_module`, `related_entity_id`, `trigger_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_notification_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `notification_id` BIGINT UNSIGNED DEFAULT NULL,
  `target_user_id` INT UNSIGNED DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL,
  `message` VARCHAR(500) NOT NULL,
  `context_json` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tracs_notification_logs_notification` (`notification_id`, `created_at`),
  INDEX `idx_tracs_notification_logs_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
