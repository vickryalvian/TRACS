-- TRACS migration: Abuse Report Management module.
-- Additive only: new module tables plus abuse_reports permissions.

CREATE TABLE IF NOT EXISTS `tracs_abuse_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_number` VARCHAR(32) DEFAULT NULL,
  `title` VARCHAR(220) NOT NULL,
  `report_type` VARCHAR(60) NOT NULL DEFAULT 'phishing',
  `status` ENUM('incoming','investigating','waiting_external','action_taken','resolved','closed') NOT NULL DEFAULT 'incoming',
  `priority` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `affected_domain` VARCHAR(255) DEFAULT NULL,
  `affected_ip` VARCHAR(64) DEFAULT NULL,
  `reporter` VARCHAR(160) DEFAULT NULL,
  `reporter_contact` VARCHAR(190) DEFAULT NULL,
  `customer_name` VARCHAR(190) DEFAULT NULL,
  `customer_reference` VARCHAR(190) DEFAULT NULL,
  `assigned_user_id` INT UNSIGNED DEFAULT NULL,
  `assigned_staff_name` VARCHAR(150) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `tags` VARCHAR(500) DEFAULT NULL,
  `sla_due_at` DATETIME DEFAULT NULL,
  `related_domain_id` INT UNSIGNED DEFAULT NULL,
  `related_server_id` INT UNSIGNED DEFAULT NULL,
  `related_case_id` INT UNSIGNED DEFAULT NULL,
  `related_shift_report_id` INT UNSIGNED DEFAULT NULL,
  `board_order` INT NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_by_name` VARCHAR(150) DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_abuse_report_number` (`report_number`),
  INDEX `idx_abuse_reports_board` (`status`, `board_order`),
  INDEX `idx_abuse_reports_priority` (`priority`, `status`, `sla_due_at`),
  INDEX `idx_abuse_reports_assigned` (`assigned_user_id`, `status`),
  INDEX `idx_abuse_reports_reporter` (`reporter`),
  INDEX `idx_abuse_reports_domain` (`affected_domain`),
  INDEX `idx_abuse_reports_created` (`created_at`),
  INDEX `idx_abuse_reports_related_case` (`related_case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_abuse_report_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `actor_name` VARCHAR(150) DEFAULT NULL,
  `event_type` VARCHAR(80) NOT NULL,
  `field_name` VARCHAR(80) DEFAULT NULL,
  `old_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_abuse_events_report` (`report_id`, `created_at`),
  INDEX `idx_abuse_events_type` (`event_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_abuse_report_notes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT UNSIGNED NOT NULL,
  `body` TEXT NOT NULL,
  `body_format` ENUM('plain','markdown') NOT NULL DEFAULT 'markdown',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_by_name` VARCHAR(150) DEFAULT NULL,
  `edited_at` DATETIME DEFAULT NULL,
  `edit_history_json` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_abuse_notes_report` (`report_id`, `created_at`),
  INDEX `idx_abuse_notes_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_abuse_report_evidence` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT UNSIGNED NOT NULL,
  `evidence_type` ENUM('screenshot','email','header','log','document','image','other') NOT NULL DEFAULT 'other',
  `original_filename` VARCHAR(255) NOT NULL,
  `stored_filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(120) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `uploaded_by` INT UNSIGNED DEFAULT NULL,
  `uploaded_by_name` VARCHAR(150) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_abuse_evidence_report` (`report_id`, `created_at`),
  INDEX `idx_abuse_evidence_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tracs_permissions` (`permission_key`, `category`, `description`)
VALUES
  ('abuse_reports.view', 'Abuse Reports', 'View abuse reports'),
  ('abuse_reports.manage', 'Abuse Reports', 'Create, update, and move abuse reports')
ON DUPLICATE KEY UPDATE
  `category` = VALUES(`category`),
  `description` = VALUES(`description`);

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p
WHERE r.slug = 'super_admin';

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN ('abuse_reports.view','abuse_reports.manage')
WHERE r.slug IN ('admin','supervisor','agent');

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key = 'abuse_reports.view'
WHERE r.slug = 'viewer';
