-- TRACS creator tracking migration
-- Safe for legacy data. Re-runnable on MySQL/MariaDB installations.

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_add_column_if_missing $$
CREATE PROCEDURE tracs_add_column_if_missing(
  IN p_table VARCHAR(128),
  IN p_column VARCHAR(128),
  IN p_definition TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = p_column
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DROP PROCEDURE IF EXISTS tracs_add_index_if_missing $$
CREATE PROCEDURE tracs_add_index_if_missing(
  IN p_table VARCHAR(128),
  IN p_index VARCHAR(128),
  IN p_columns TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND INDEX_NAME = p_index
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` ', p_columns);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DROP PROCEDURE IF EXISTS tracs_backfill_created_by $$
CREATE PROCEDURE tracs_backfill_created_by(
  IN p_table VARCHAR(128),
  IN p_source VARCHAR(128)
)
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = 'created_by'
  ) AND EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = p_source
  ) THEN
    SET @sql = CONCAT('UPDATE `', p_table, '` SET `created_by` = `', p_source, '` WHERE `created_by` IS NULL AND `', p_source, '` IS NOT NULL');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DELIMITER ;

CALL tracs_add_column_if_missing('tracs_cases', 'created_by', 'INT UNSIGNED NULL DEFAULT NULL AFTER `user_id`');
CALL tracs_add_column_if_missing('tracs_cases', 'created_by_name', 'VARCHAR(150) NULL DEFAULT NULL AFTER `created_by`');
CALL tracs_add_index_if_missing('tracs_cases', 'idx_cases_created_by', '(`created_by`)');
CALL tracs_backfill_created_by('tracs_cases', 'user_id');

CALL tracs_add_column_if_missing('tracs_reminders', 'created_by', 'INT UNSIGNED NULL DEFAULT NULL AFTER `user_id`');
CALL tracs_add_column_if_missing('tracs_reminders', 'created_by_name', 'VARCHAR(150) NULL DEFAULT NULL AFTER `created_by`');
CALL tracs_add_index_if_missing('tracs_reminders', 'idx_rem_created_by', '(`created_by`)');
CALL tracs_backfill_created_by('tracs_reminders', 'user_id');

CALL tracs_add_column_if_missing('tracs_side_tasks', 'created_by', 'INT UNSIGNED NULL DEFAULT NULL AFTER `user_id`');
CALL tracs_add_column_if_missing('tracs_side_tasks', 'created_by_name', 'VARCHAR(150) NULL DEFAULT NULL AFTER `created_by`');
CALL tracs_add_index_if_missing('tracs_side_tasks', 'idx_tasks_created_by', '(`created_by`)');
CALL tracs_backfill_created_by('tracs_side_tasks', 'user_id');

CALL tracs_add_column_if_missing('tracs_shift_reports', 'created_by_name', 'VARCHAR(150) NULL DEFAULT NULL AFTER `created_by`');
CALL tracs_add_column_if_missing('tracs_moms', 'created_by_name', 'VARCHAR(150) NULL DEFAULT NULL AFTER `created_by`');

CALL tracs_add_column_if_missing('balance_transfers', 'created_by', 'INT UNSIGNED NULL DEFAULT NULL AFTER `id`');
CALL tracs_add_column_if_missing('balance_transfers', 'created_by_name', 'VARCHAR(150) NULL DEFAULT NULL AFTER `created_by`');
CALL tracs_add_index_if_missing('balance_transfers', 'idx_balance_transfers_created_by', '(`created_by`)');

CALL tracs_add_column_if_missing('domain_transfers', 'created_by_name', 'VARCHAR(150) NULL DEFAULT NULL AFTER `created_by`');
CALL tracs_add_column_if_missing('activity_feed', 'created_by_name', 'VARCHAR(150) NULL DEFAULT NULL AFTER `created_by`');

CALL tracs_add_column_if_missing('tracs_cancellation_feedback', 'created_by', 'INT UNSIGNED NULL DEFAULT NULL AFTER `id`');
CALL tracs_add_column_if_missing('tracs_cancellation_feedback', 'created_by_name', 'VARCHAR(150) NULL DEFAULT NULL AFTER `created_by`');
CALL tracs_add_index_if_missing('tracs_cancellation_feedback', 'idx_feedback_created_by', '(`created_by`)');

CALL tracs_add_column_if_missing('tracs_domains', 'created_by', 'INT UNSIGNED NULL DEFAULT NULL AFTER `user_id`');
CALL tracs_add_column_if_missing('tracs_domains', 'created_by_name', 'VARCHAR(150) NULL DEFAULT NULL AFTER `created_by`');
CALL tracs_add_index_if_missing('tracs_domains', 'idx_tracs_domains_created_by', '(`created_by`)');
CALL tracs_backfill_created_by('tracs_domains', 'user_id');

CALL tracs_add_column_if_missing('tracs_finance_transfers', 'created_by', 'INT UNSIGNED NULL DEFAULT NULL AFTER `user_id`');
CALL tracs_add_column_if_missing('tracs_finance_transfers', 'created_by_name', 'VARCHAR(150) NULL DEFAULT NULL AFTER `created_by`');
CALL tracs_add_index_if_missing('tracs_finance_transfers', 'idx_tracs_finance_created_by', '(`created_by`)');
CALL tracs_backfill_created_by('tracs_finance_transfers', 'user_id');

CALL tracs_add_column_if_missing('tracs_ticker_messages', 'created_by', 'INT UNSIGNED NULL DEFAULT NULL AFTER `user_id`');
CALL tracs_add_column_if_missing('tracs_ticker_messages', 'created_by_name', 'VARCHAR(150) NULL DEFAULT NULL AFTER `created_by`');
CALL tracs_add_index_if_missing('tracs_ticker_messages', 'idx_ticker_messages_created_by', '(`created_by`)');
CALL tracs_backfill_created_by('tracs_ticker_messages', 'user_id');

CALL tracs_add_column_if_missing('tracs_ticker_events', 'created_by', 'INT UNSIGNED NULL DEFAULT NULL AFTER `user_id`');
CALL tracs_add_column_if_missing('tracs_ticker_events', 'created_by_name', 'VARCHAR(150) NULL DEFAULT NULL AFTER `created_by`');
CALL tracs_add_index_if_missing('tracs_ticker_events', 'idx_ticker_events_created_by', '(`created_by`)');
CALL tracs_backfill_created_by('tracs_ticker_events', 'user_id');

DROP PROCEDURE IF EXISTS tracs_add_index_if_missing;
DROP PROCEDURE IF EXISTS tracs_backfill_created_by;
DROP PROCEDURE IF EXISTS tracs_add_column_if_missing;
