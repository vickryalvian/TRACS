-- TRACS cases schema.

CREATE TABLE IF NOT EXISTS `tracs_cases` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_by_name` VARCHAR(150) DEFAULT NULL,
  `title` VARCHAR(500) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('active','pending','in_progress','stuck','on_hold','completed') NOT NULL DEFAULT 'active',
  `priority` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `next_check_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_cases_user` (`user_id`),
  INDEX `idx_cases_created_by` (`created_by`),
  INDEX `idx_cases_alert` (`user_id`, `priority`, `status`, `next_check_at`),
  CONSTRAINT `fk_cases_user` FOREIGN KEY (`user_id`) REFERENCES `tracs_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `case_attachments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `case_id` INT NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `stored_filename` VARCHAR(255) NOT NULL,
  `thumbnail_filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `thumbnail_path` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `uploaded_by` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_case_attachments_case` (`case_id`),
  INDEX `idx_case_attachments_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
