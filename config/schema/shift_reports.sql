-- TRACS shift reports schema.

CREATE TABLE IF NOT EXISTS `tracs_shift_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shift_name` VARCHAR(50) NOT NULL DEFAULT 'Shift 1',
  `title` VARCHAR(255) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `priority` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` ENUM('active','on_hold','resolved') NOT NULL DEFAULT 'active',
  `resolution_note` TEXT DEFAULT NULL,
  `active_date` DATE NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_by_name` VARCHAR(150) DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `visible_to_next_shift` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_sr_history` (`active_date`, `shift_name`, `status`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_shift_activities` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shift_report_id` INT UNSIGNED DEFAULT NULL,
  `shift_name` VARCHAR(50) NOT NULL,
  `activity_type` ENUM('checklist','reminder','case','domain','finance','meeting','ticker','manual') NOT NULL DEFAULT 'manual',
  `reference_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('completed','pending','attention','critical','info') NOT NULL DEFAULT 'info',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_shift_handover` (`created_by`, `shift_name`, `created_at`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shift_report_attachments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shift_report_id` INT UNSIGNED NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `stored_filename` VARCHAR(255) NOT NULL,
  `thumbnail_filename` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `uploaded_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shift_report_attachments_report` (`shift_report_id`),
  KEY `idx_shift_report_attachments_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
