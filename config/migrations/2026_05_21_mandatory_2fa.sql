-- TRACS mandatory 2FA migration.
-- Safe to re-run on MySQL/MariaDB installations.
-- Existing users are intentionally marked as requiring setup on their next login.

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_2fa_add_column_if_missing $$
CREATE PROCEDURE tracs_2fa_add_column_if_missing(
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

DROP PROCEDURE IF EXISTS tracs_2fa_add_index_if_missing $$
CREATE PROCEDURE tracs_2fa_add_index_if_missing(
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

DELIMITER ;

CALL tracs_2fa_add_column_if_missing('tracs_users', 'two_factor_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL tracs_2fa_add_column_if_missing('tracs_users', 'two_factor_secret', 'VARCHAR(512) NULL AFTER `two_factor_enabled`');
CALL tracs_2fa_add_column_if_missing('tracs_users', 'two_factor_confirmed_at', 'DATETIME NULL AFTER `two_factor_secret`');
CALL tracs_2fa_add_column_if_missing('tracs_users', 'two_factor_reset_required', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `two_factor_confirmed_at`');
CALL tracs_2fa_add_column_if_missing('tracs_users', 'two_factor_failed_attempts', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER `two_factor_reset_required`');
CALL tracs_2fa_add_column_if_missing('tracs_users', 'two_factor_locked_until', 'DATETIME NULL AFTER `two_factor_failed_attempts`');
CALL tracs_2fa_add_column_if_missing('tracs_users', 'two_factor_last_verified_at', 'DATETIME NULL AFTER `two_factor_locked_until`');

UPDATE `tracs_users`
SET `two_factor_reset_required` = 1,
    `two_factor_enabled` = 0,
    `two_factor_failed_attempts` = COALESCE(`two_factor_failed_attempts`, 0)
WHERE `two_factor_confirmed_at` IS NULL
   OR `two_factor_secret` IS NULL
   OR `two_factor_secret` = '';

CALL tracs_2fa_add_index_if_missing('tracs_users', 'idx_tracs_users_2fa_required', '(`two_factor_reset_required`, `two_factor_enabled`)');
CALL tracs_2fa_add_index_if_missing('tracs_users', 'idx_tracs_users_2fa_lock', '(`two_factor_locked_until`)');

DROP PROCEDURE IF EXISTS tracs_2fa_add_index_if_missing;
DROP PROCEDURE IF EXISTS tracs_2fa_add_column_if_missing;
