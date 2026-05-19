-- TRACS migration: profile picture/avatar support.
-- Stores only the optimized cropped avatar URL path, not base64 image data.

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_avatar_add_column_if_missing $$
CREATE PROCEDURE tracs_avatar_add_column_if_missing(
  IN p_table VARCHAR(64),
  IN p_column VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  IF NOT EXISTS (
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

DELIMITER ;

CALL tracs_avatar_add_column_if_missing('tracs_users', 'avatar_path', 'VARCHAR(255) NULL AFTER `shift_preference`');

DROP PROCEDURE IF EXISTS tracs_avatar_add_column_if_missing;
