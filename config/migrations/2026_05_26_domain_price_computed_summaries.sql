-- Migration: Add computed summary columns for the Phase 10 recalculation engine
-- Date: 2026-05-26

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_dpc_col $$
CREATE PROCEDURE tracs_dpc_col(
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

-- Computed stats for the recalculation engine
CALL tracs_dpc_col('domain_price_summaries', 'website_below_cost_register', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL tracs_dpc_col('domain_price_summaries', 'website_below_cost_renewal',  'TINYINT(1) NOT NULL DEFAULT 0');
CALL tracs_dpc_col('domain_price_summaries', 'paas_below_cost_register',    'TINYINT(1) NOT NULL DEFAULT 0');
CALL tracs_dpc_col('domain_price_summaries', 'paas_below_cost_renewal',     'TINYINT(1) NOT NULL DEFAULT 0');
CALL tracs_dpc_col('domain_price_summaries', 'prev_lowest_register_cost',   'DECIMAL(15,2) DEFAULT NULL');
CALL tracs_dpc_col('domain_price_summaries', 'prev_lowest_renewal_cost',    'DECIMAL(15,2) DEFAULT NULL');
CALL tracs_dpc_col('domain_price_summaries', 'cost_register_diff',          'DECIMAL(15,2) DEFAULT NULL');
CALL tracs_dpc_col('domain_price_summaries', 'cost_renewal_diff',           'DECIMAL(15,2) DEFAULT NULL');
CALL tracs_dpc_col('domain_price_summaries', 'cost_register_change_pct',    'DECIMAL(7,2) DEFAULT NULL');
CALL tracs_dpc_col('domain_price_summaries', 'cost_renewal_change_pct',     'DECIMAL(7,2) DEFAULT NULL');

DROP PROCEDURE IF EXISTS tracs_dpc_col;
