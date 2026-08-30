-- TRACS migration: recurring task interval + auto-rollover support.
-- Lets a recurring task repeat every N days (not just literally daily) and
-- gives the lazy on-page-load refresh (see TaskManagementModel::refreshRecurringTasks)
-- a column to read the interval from. Safe to re-run.

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_tri_add_column_if_missing $$
CREATE PROCEDURE tracs_tri_add_column_if_missing(
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

DELIMITER ;

CALL tracs_tri_add_column_if_missing('tracs_tasks', 'recurrence_interval_days', 'INT UNSIGNED NOT NULL DEFAULT 1 AFTER `recurrence_type`');

DROP PROCEDURE IF EXISTS tracs_tri_add_column_if_missing;
