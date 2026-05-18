-- TRACS migration: Task SLA and timing metrics.
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

DROP PROCEDURE IF EXISTS tracs_tm_modify_status_if_needed $$
CREATE PROCEDURE tracs_tm_modify_status_if_needed()
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tracs_task_assignments'
      AND COLUMN_NAME = 'status'
      AND COLUMN_TYPE NOT LIKE '%completed_on_time%'
  ) THEN
    ALTER TABLE `tracs_task_assignments`
      MODIFY `status` ENUM('assigned','not_started','in_progress','completed','completed_on_time','completed_late','overdue','need_review','reviewed','cancelled','reassigned') NOT NULL DEFAULT 'assigned';
  END IF;
END $$

DELIMITER ;

CALL tracs_tm_modify_status_if_needed();

CALL tracs_tm_add_column_if_missing('tracs_task_assignments', 'assigned_at', 'DATETIME NULL AFTER `assigned_by`');
CALL tracs_tm_add_column_if_missing('tracs_task_assignments', 'started_at', 'DATETIME NULL AFTER `assigned_at`');
CALL tracs_tm_add_column_if_missing('tracs_task_assignments', 'reviewed_by', 'INT UNSIGNED NULL AFTER `completed_by`');
CALL tracs_tm_add_column_if_missing('tracs_task_assignments', 'reviewed_at', 'DATETIME NULL AFTER `completed_at`');
CALL tracs_tm_add_column_if_missing('tracs_task_assignments', 'cancelled_at', 'DATETIME NULL AFTER `reviewed_at`');
CALL tracs_tm_add_column_if_missing('tracs_task_assignments', 'completion_seconds', 'INT UNSIGNED NULL AFTER `cancelled_at`');
CALL tracs_tm_add_column_if_missing('tracs_task_assignments', 'overdue_seconds', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `completion_seconds`');
CALL tracs_tm_add_column_if_missing('tracs_task_assignments', 'start_delay_seconds', 'INT UNSIGNED NULL AFTER `overdue_seconds`');

UPDATE `tracs_task_assignments`
SET `assigned_at` = COALESCE(`assigned_at`, `created_at`)
WHERE `assigned_at` IS NULL;

UPDATE `tracs_task_assignments` ta
INNER JOIN `tracs_tasks` t ON t.id = ta.task_id
SET
  ta.status = CASE
    WHEN ta.status = 'completed' AND t.due_at IS NOT NULL AND ta.completed_at > t.due_at THEN 'completed_late'
    WHEN ta.status = 'completed' THEN 'completed_on_time'
    WHEN ta.status = 'assigned' THEN 'not_started'
    ELSE ta.status
  END,
  ta.completion_seconds = CASE
    WHEN ta.completed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, COALESCE(ta.started_at, ta.assigned_at, ta.created_at), ta.completed_at)
    ELSE ta.completion_seconds
  END,
  ta.overdue_seconds = CASE
    WHEN ta.completed_at IS NOT NULL AND t.due_at IS NOT NULL AND ta.completed_at > t.due_at THEN TIMESTAMPDIFF(SECOND, t.due_at, ta.completed_at)
    WHEN ta.completed_at IS NULL AND t.due_at IS NOT NULL AND NOW() > t.due_at THEN TIMESTAMPDIFF(SECOND, t.due_at, NOW())
    ELSE COALESCE(ta.overdue_seconds, 0)
  END;

CALL tracs_tm_add_index_if_missing('tracs_task_assignments', 'idx_tracs_task_assignments_timing', '(`assigned_at`, `started_at`, `completed_at`)');
CALL tracs_tm_add_index_if_missing('tracs_task_assignments', 'idx_tracs_task_assignments_review', '(`reviewed_at`, `reviewed_by`)');

DROP PROCEDURE IF EXISTS tracs_tm_modify_status_if_needed;
DROP PROCEDURE IF EXISTS tracs_tm_add_index_if_missing;
DROP PROCEDURE IF EXISTS tracs_tm_add_column_if_missing;
