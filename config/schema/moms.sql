-- TRACS Minutes of Meeting schema.
-- Full fresh-install definitions are maintained in ../install.sql.

CREATE TABLE IF NOT EXISTS `tracs_moms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `type` ENUM('weekly','training','coordination','urgent') NOT NULL DEFAULT 'weekly',
  `objective` TEXT DEFAULT NULL,
  `participants` TEXT DEFAULT NULL,
  `meeting_at` DATETIME DEFAULT NULL,
  `meeting_url` VARCHAR(500) DEFAULT NULL,
  `status` ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
  `created_by` INT UNSIGNED NOT NULL,
  `created_by_name` VARCHAR(150) DEFAULT NULL,
  `scheduled_reminder_id` INT UNSIGNED DEFAULT NULL,
  `ops_status_id` INT UNSIGNED DEFAULT NULL,
  `started_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,
  `summary` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_moms_lifecycle` (`created_by`, `status`, `meeting_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_mom_agenda` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `mom_id` INT UNSIGNED NOT NULL, `topic` VARCHAR(255) NOT NULL, `notes` TEXT DEFAULT NULL, `status` ENUM('pending','completed','skipped') NOT NULL DEFAULT 'pending', `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), INDEX `idx_mom_agenda_mom` (`mom_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `tracs_mom_notes` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `mom_id` INT UNSIGNED NOT NULL, `content` LONGTEXT NOT NULL, `note_type` ENUM('discussion','decision','action','insight','risk') NOT NULL DEFAULT 'discussion', `created_by` INT UNSIGNED NOT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), INDEX `idx_mom_notes_mom` (`mom_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `tracs_mom_decisions` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `mom_id` INT UNSIGNED NOT NULL, `decision` TEXT NOT NULL, `rationale` TEXT DEFAULT NULL, `owner` VARCHAR(255) DEFAULT NULL, `status` ENUM('pending','approved','implemented','cancelled') NOT NULL DEFAULT 'pending', `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`), INDEX `idx_mom_decisions_mom` (`mom_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `tracs_mom_actions` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `mom_id` INT UNSIGNED NOT NULL, `title` VARCHAR(255) NOT NULL, `description` TEXT DEFAULT NULL, `assigned_to` VARCHAR(255) DEFAULT NULL, `priority` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium', `status` ENUM('pending','in_progress','completed','cancelled','blocked') NOT NULL DEFAULT 'pending', `due_date` DATETIME DEFAULT NULL, `linked_reminder_id` INT UNSIGNED DEFAULT NULL, `linked_case_id` INT UNSIGNED DEFAULT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`), INDEX `idx_mom_actions_mom` (`mom_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `tracs_mom_case_links` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `mom_id` INT UNSIGNED NOT NULL, `case_id` INT UNSIGNED NOT NULL, `link_context` VARCHAR(255) DEFAULT NULL, `linked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), UNIQUE KEY `uq_mom_case` (`mom_id`, `case_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `tracs_mom_screenshots` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `mom_id` INT UNSIGNED NOT NULL, `filename` VARCHAR(255) NOT NULL, `attached_to_type` ENUM('discussion','action','decision','general') NOT NULL DEFAULT 'general', `attached_to_id` INT UNSIGNED DEFAULT NULL, `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), INDEX `idx_mom_screenshots_mom` (`mom_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `tracs_mom_audit_log` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `mom_id` INT UNSIGNED NOT NULL, `action` VARCHAR(100) NOT NULL, `details` TEXT DEFAULT NULL, `user_id` INT UNSIGNED NOT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), INDEX `idx_mom_audit_mom` (`mom_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
