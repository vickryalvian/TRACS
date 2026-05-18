-- TRACS migration: Task Management & Monitoring.
-- Safe to re-run on MySQL/MariaDB installations.

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_tm_add_column_if_missing $$
CREATE PROCEDURE tracs_tm_add_column_if_missing(
  IN p_table VARCHAR(128),
  IN p_column VARCHAR(128),
  IN p_definition TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DROP PROCEDURE IF EXISTS tracs_tm_add_index_if_missing $$
CREATE PROCEDURE tracs_tm_add_index_if_missing(
  IN p_table VARCHAR(128),
  IN p_index VARCHAR(128),
  IN p_columns TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` ', p_columns);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DELIMITER ;

CREATE TABLE IF NOT EXISTS `tracs_tasks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(180) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `category` ENUM('daily_checklist','case_follow_up','domain_transfer','balance_transfer','finance_log_mutasi','ssl_check','mom_follow_up','training_task','intern_task','custom') NOT NULL DEFAULT 'custom',
  `priority` ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `assignment_scope` ENUM('users','roles','divisions','mixed') NOT NULL DEFAULT 'users',
  `due_at` DATETIME DEFAULT NULL,
  `recurrence_type` ENUM('none','daily') NOT NULL DEFAULT 'none',
  `reference_url` VARCHAR(500) DEFAULT NULL,
  `requires_review` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `assigned_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tracs_tasks_due` (`due_at`),
  INDEX `idx_tracs_tasks_category` (`category`),
  INDEX `idx_tracs_tasks_priority` (`priority`),
  INDEX `idx_tracs_tasks_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_task_assignments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('assigned','in_progress','completed','overdue','cancelled','need_review') NOT NULL DEFAULT 'assigned',
  `progress_note` TEXT DEFAULT NULL,
  `completion_note` TEXT DEFAULT NULL,
  `review_note` TEXT DEFAULT NULL,
  `assigned_by` INT UNSIGNED DEFAULT NULL,
  `completed_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `linked_checklist_task_id` INT UNSIGNED DEFAULT NULL,
  `linked_reminder_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracs_task_user` (`task_id`, `user_id`),
  INDEX `idx_tracs_task_assignments_user` (`user_id`, `status`),
  INDEX `idx_tracs_task_assignments_status` (`status`),
  INDEX `idx_tracs_task_assignments_checklist` (`linked_checklist_task_id`),
  INDEX `idx_tracs_task_assignments_reminder` (`linked_reminder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_task_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` INT UNSIGNED NOT NULL,
  `assignment_id` INT UNSIGNED DEFAULT NULL,
  `actor_user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(80) NOT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tracs_task_logs_task` (`task_id`, `created_at`),
  INDEX `idx_tracs_task_logs_assignment` (`assignment_id`, `created_at`),
  INDEX `idx_tracs_task_logs_actor` (`actor_user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_task_reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` INT UNSIGNED NOT NULL,
  `reviewer_user_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('pending','approved','changes_requested') NOT NULL DEFAULT 'pending',
  `review_note` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tracs_task_reviews_assignment` (`assignment_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_task_reminders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` INT UNSIGNED NOT NULL,
  `reminder_id` INT UNSIGNED DEFAULT NULL,
  `trigger_at` DATETIME DEFAULT NULL,
  `triggered_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tracs_task_reminders_assignment` (`assignment_id`),
  INDEX `idx_tracs_task_reminders_trigger` (`trigger_at`, `triggered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL tracs_tm_add_column_if_missing('tracs_side_tasks', 'linked_assignment_id', 'INT UNSIGNED NULL AFTER `completed_by`');
CALL tracs_tm_add_column_if_missing('tracs_reminders', 'linked_assignment_id', 'INT UNSIGNED NULL AFTER `completed_by`');
CALL tracs_tm_add_index_if_missing('tracs_side_tasks', 'idx_tasks_linked_assignment', '(`linked_assignment_id`)');
CALL tracs_tm_add_index_if_missing('tracs_reminders', 'idx_reminders_linked_assignment', '(`linked_assignment_id`)');

INSERT INTO `tracs_permissions` (`permission_key`, `category`, `description`)
VALUES
  ('tasks.view_own', 'Tasks', 'View assigned tasks'),
  ('tasks.update_own', 'Tasks', 'Update assigned task progress'),
  ('tasks.create', 'Tasks', 'Create and assign tasks'),
  ('tasks.monitor', 'Tasks', 'View task monitoring dashboard'),
  ('tasks.review', 'Tasks', 'Review assigned task completion')
ON DUPLICATE KEY UPDATE
  `category` = VALUES(`category`),
  `description` = VALUES(`description`);

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN ('tasks.view_own','tasks.update_own','tasks.create','tasks.monitor','tasks.review')
WHERE r.slug IN ('super_admin','admin','supervisor','mentor');

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN ('tasks.view_own','tasks.update_own')
WHERE r.slug IN ('agent','intern','viewer');

DROP PROCEDURE IF EXISTS tracs_tm_add_index_if_missing;
DROP PROCEDURE IF EXISTS tracs_tm_add_column_if_missing;
