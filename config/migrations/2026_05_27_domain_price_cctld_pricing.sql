-- Migration: Add ccTLD pricing support to Domain Price Crosscheck
-- Scope: internal registry/source pricing only. No external market comparison.

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_dpc_add_col $$
CREATE PROCEDURE tracs_dpc_add_col(
  IN p_table VARCHAR(64),
  IN p_column VARCHAR(64),
  IN p_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
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

CALL tracs_dpc_add_col('domain_price_tlds', 'tld_category', "ENUM('gtld','cctld') NOT NULL DEFAULT 'gtld' AFTER `tld_name`");

UPDATE `domain_price_sources`
SET `source_type` = 'registrar'
WHERE `source_type` NOT IN ('registrar','internal','registry');

ALTER TABLE `domain_price_sources`
  MODIFY `source_type` ENUM('registrar','internal','registry') NOT NULL DEFAULT 'registrar';

UPDATE `domain_price_sources`
SET `source_type` = 'internal'
WHERE `source_name` = 'IDCH Internal Pricing';

INSERT INTO `domain_price_tlds` (`tld_name`, `tld_category`, `is_active`, `sort_order`)
VALUES
  ('.AC.ID', 'cctld', 1, 5010),
  ('.BIZ.ID', 'cctld', 1, 5020),
  ('.CO.ID', 'cctld', 1, 5030),
  ('.ID', 'cctld', 1, 5040),
  ('.MY.ID', 'cctld', 1, 5050),
  ('.OR.ID', 'cctld', 1, 5060),
  ('.PONPES.ID', 'cctld', 1, 5070),
  ('.SCH.ID', 'cctld', 1, 5080),
  ('.WEB.ID', 'cctld', 1, 5090),
  ('.NET.ID', 'cctld', 1, 5100)
ON DUPLICATE KEY UPDATE
  `tld_category` = VALUES(`tld_category`),
  `is_active` = VALUES(`is_active`),
  `sort_order` = VALUES(`sort_order`);

UPDATE `domain_price_tlds`
SET `is_active` = 0
WHERE UPPER(`tld_name`) = '.GO.ID';

INSERT INTO `domain_price_sources` (`source_name`, `source_type`, `is_active`, `sort_order`)
VALUES
  ('PANDI Registry Pricing', 'registry', 1, 401),
  ('IDCH ccTLD Pricing', 'internal', 1, 402)
ON DUPLICATE KEY UPDATE
  `source_type` = VALUES(`source_type`),
  `is_active` = VALUES(`is_active`),
  `sort_order` = VALUES(`sort_order`);

DROP PROCEDURE IF EXISTS tracs_dpc_add_col;
