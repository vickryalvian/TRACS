-- Internal user notes (supervisor-tier scope). App also self-creates this
-- table on first use via core/user_notes.php::tracs_user_notes_ensure_schema(),
-- this migration is for environments that prefer explicit schema application.

CREATE TABLE IF NOT EXISTS `tracs_user_notes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `target_user_id` INT UNSIGNED NOT NULL,
  `author_user_id` INT UNSIGNED NOT NULL,
  `category` VARCHAR(40) NOT NULL DEFAULT 'administrative',
  `content` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tracs_user_notes_target` (`target_user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
