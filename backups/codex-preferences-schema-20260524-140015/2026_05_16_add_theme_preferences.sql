-- TRACS migration: add extensible user preferences.
-- Safe to re-run. The current UI stores theme locally; this table prepares
-- server-side preferences without changing application behavior.

CREATE TABLE IF NOT EXISTS `tracs_user_preferences` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `preference_key` VARCHAR(100) NOT NULL,
  `preference_value` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_preference` (`user_id`, `preference_key`),
  INDEX `idx_user_preferences_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
