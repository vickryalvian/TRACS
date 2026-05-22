-- Migration: Add workflow and manual notes columns to domain_price_summaries
-- Date: 2026-05-23
-- For Phase 6 Domain Price Crosscheck Module

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_dpc_add_column_if_missing $$
CREATE PROCEDURE tracs_dpc_add_column_if_missing(
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

-- Add Phase 6 workflow columns to domain_price_summaries
CALL tracs_dpc_add_column_if_missing('domain_price_summaries', 'auto_status', 'VARCHAR(50) DEFAULT NULL');
CALL tracs_dpc_add_column_if_missing('domain_price_summaries', 'suggested_action', 'VARCHAR(255) DEFAULT NULL');
CALL tracs_dpc_add_column_if_missing('domain_price_summaries', 'manual_note', 'VARCHAR(255) DEFAULT NULL');
CALL tracs_dpc_add_column_if_missing('domain_price_summaries', 'detailed_note', 'TEXT DEFAULT NULL');
CALL tracs_dpc_add_column_if_missing('domain_price_summaries', 'follow_up_status', 'ENUM(''No Action'', ''Need Review'', ''Waiting Finance'', ''Waiting Approval'', ''Updated'') DEFAULT ''No Action''');
CALL tracs_dpc_add_column_if_missing('domain_price_summaries', 'updated_by', 'INT UNSIGNED DEFAULT NULL');

-- Clean up helper procedure
DROP PROCEDURE IF EXISTS tracs_dpc_add_column_if_missing;
