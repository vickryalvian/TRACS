-- TRACS Shift Activity / Handover optional schema support
-- Safe/non-destructive: creates activity history and adds completion metadata.
-- Review and run manually. No tables are dropped and no existing data is deleted.

CREATE TABLE IF NOT EXISTS `tracs_shift_activities` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shift_report_id` INT UNSIGNED NULL,
  `shift_name` VARCHAR(50) NOT NULL,
  `activity_type` ENUM('checklist','reminder','case','domain','finance','meeting','ticker','manual') NOT NULL DEFAULT 'manual',
  `reference_id` INT UNSIGNED NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('completed','pending','attention','critical','info') NOT NULL DEFAULT 'info',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_shift_name` (`shift_name`),
  INDEX `idx_activity_type` (`activity_type`),
  INDEX `idx_reference_id` (`reference_id`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_shift_handover` (`created_by`, `shift_name`, `created_at`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_shift_add_column_if_missing $$
CREATE PROCEDURE tracs_shift_add_column_if_missing(
  IN p_table_name VARCHAR(64),
  IN p_column_name VARCHAR(64),
  IN p_column_sql TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND COLUMN_NAME = p_column_name
  ) THEN
    SET @ddl = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN ', p_column_sql);
    PREPARE stmt FROM @ddl;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DELIMITER ;

CALL tracs_shift_add_column_if_missing('tracs_side_tasks', 'completed_at', '`completed_at` DATETIME NULL AFTER `is_completed`');
CALL tracs_shift_add_column_if_missing('tracs_side_tasks', 'completed_by', '`completed_by` INT UNSIGNED NULL AFTER `completed_at`');
CALL tracs_shift_add_column_if_missing('tracs_side_tasks', 'archived_at', '`archived_at` DATETIME NULL AFTER `completed_by`');
CALL tracs_shift_add_column_if_missing('tracs_side_tasks', 'reset_at', '`reset_at` DATETIME NULL AFTER `archived_at`');
CALL tracs_shift_add_column_if_missing('tracs_side_tasks', 'recurrence_type', '`recurrence_type` ENUM(''none'',''daily'',''weekly'',''monthly'') NOT NULL DEFAULT ''daily'' AFTER `reset_at`');

CALL tracs_shift_add_column_if_missing('tracs_reminders', 'completed_at', '`completed_at` DATETIME NULL AFTER `is_completed`');
CALL tracs_shift_add_column_if_missing('tracs_reminders', 'completed_by', '`completed_by` INT UNSIGNED NULL AFTER `completed_at`');
CALL tracs_shift_add_column_if_missing('tracs_reminders', 'archived_at', '`archived_at` DATETIME NULL AFTER `completed_by`');

DROP PROCEDURE IF EXISTS tracs_shift_add_column_if_missing;
