-- TRACS migration: explicit client assigned admin ownership.
-- Existing clients remain unassigned until edited.

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_client_add_column_if_missing $$
CREATE PROCEDURE tracs_client_add_column_if_missing(
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

DROP PROCEDURE IF EXISTS tracs_client_add_index_if_missing $$
CREATE PROCEDURE tracs_client_add_index_if_missing(
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

DROP PROCEDURE IF EXISTS tracs_client_add_fk_if_missing $$
CREATE PROCEDURE tracs_client_add_fk_if_missing(
  IN p_table VARCHAR(128),
  IN p_fk VARCHAR(128),
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
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = p_fk
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_fk, '` ', p_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DELIMITER ;

CALL tracs_client_add_column_if_missing('tracs_clients', 'assigned_admin_id', 'INT UNSIGNED DEFAULT NULL AFTER owner_user_id');
CALL tracs_client_add_index_if_missing('tracs_clients', 'idx_tracs_clients_assigned_admin', '(`assigned_admin_id`, `status`)');
CALL tracs_client_add_fk_if_missing('tracs_clients', 'fk_tracs_clients_assigned_admin', 'FOREIGN KEY (`assigned_admin_id`) REFERENCES `tracs_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');

DROP PROCEDURE IF EXISTS tracs_client_add_fk_if_missing;
DROP PROCEDURE IF EXISTS tracs_client_add_index_if_missing;
DROP PROCEDURE IF EXISTS tracs_client_add_column_if_missing;
