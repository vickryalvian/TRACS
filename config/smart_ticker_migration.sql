-- TRACS Smart Ticker optional schema support
-- Safe/non-destructive: adds archive/reset/ticker metadata only.
-- Review and run manually if you want persisted completion/archive fields.

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_add_column_if_missing $$
CREATE PROCEDURE tracs_add_column_if_missing(
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

DROP PROCEDURE IF EXISTS tracs_add_index_if_missing $$
CREATE PROCEDURE tracs_add_index_if_missing(
  IN p_table_name VARCHAR(64),
  IN p_index_name VARCHAR(64),
  IN p_index_sql TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND INDEX_NAME = p_index_name
  ) THEN
    SET @ddl = CONCAT('CREATE INDEX `', p_index_name, '` ON `', p_table_name, '` ', p_index_sql);
    PREPARE stmt FROM @ddl;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DELIMITER ;

CALL tracs_add_column_if_missing('tracs_reminders', 'completed_at', '`completed_at` DATETIME NULL AFTER `is_completed`');
CALL tracs_add_column_if_missing('tracs_reminders', 'archived_at', '`archived_at` DATETIME NULL AFTER `completed_at`');
CALL tracs_add_column_if_missing('tracs_reminders', 'reset_at', '`reset_at` DATETIME NULL AFTER `archived_at`');
CALL tracs_add_column_if_missing('tracs_reminders', 'recurrence_type', '`recurrence_type` ENUM(''none'',''daily'',''weekly'',''monthly'') NOT NULL DEFAULT ''none'' AFTER `reset_at`');
CALL tracs_add_column_if_missing('tracs_reminders', 'ticker_priority', '`ticker_priority` ENUM(''critical'',''high'',''medium'',''low'',''info'') NULL AFTER `recurrence_type`');
CALL tracs_add_column_if_missing('tracs_reminders', 'ticker_visible_until', '`ticker_visible_until` DATETIME NULL AFTER `ticker_priority`');

CALL tracs_add_column_if_missing('tracs_side_tasks', 'completed_at', '`completed_at` DATETIME NULL AFTER `is_completed`');
CALL tracs_add_column_if_missing('tracs_side_tasks', 'archived_at', '`archived_at` DATETIME NULL AFTER `completed_at`');
CALL tracs_add_column_if_missing('tracs_side_tasks', 'reset_at', '`reset_at` DATETIME NULL AFTER `archived_at`');
CALL tracs_add_column_if_missing('tracs_side_tasks', 'recurrence_type', '`recurrence_type` ENUM(''none'',''daily'',''weekly'',''monthly'') NOT NULL DEFAULT ''daily'' AFTER `reset_at`');
CALL tracs_add_column_if_missing('tracs_side_tasks', 'ticker_priority', '`ticker_priority` ENUM(''critical'',''high'',''medium'',''low'',''info'') NULL AFTER `recurrence_type`');
CALL tracs_add_column_if_missing('tracs_side_tasks', 'ticker_visible_until', '`ticker_visible_until` DATETIME NULL AFTER `ticker_priority`');

CALL tracs_add_index_if_missing('tracs_reminders', 'idx_rem_ticker_active', '(`user_id`, `is_completed`, `archived_at`, `due_date`, `ticker_visible_until`)');
CALL tracs_add_index_if_missing('tracs_side_tasks', 'idx_tasks_ticker_active', '(`user_id`, `is_completed`, `archived_at`, `reset_at`, `recurrence_type`)');

DROP PROCEDURE IF EXISTS tracs_add_column_if_missing;
DROP PROCEDURE IF EXISTS tracs_add_index_if_missing;
