-- TRACS migration: Client Portfolio addons, renewal history, and spend-ready service fields.
-- Safe to re-run on MySQL/MariaDB installations.

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_cp_add_column_if_missing $$
CREATE PROCEDURE tracs_cp_add_column_if_missing(
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

DROP PROCEDURE IF EXISTS tracs_cp_add_index_if_missing $$
CREATE PROCEDURE tracs_cp_add_index_if_missing(
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

DELIMITER ;

CALL tracs_cp_add_column_if_missing('tracs_client_services', 'plan_spec', 'TEXT DEFAULT NULL AFTER `service_reference`');
CALL tracs_cp_add_column_if_missing('tracs_client_services', 'auto_renew', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `renewal_date`');
CALL tracs_cp_add_index_if_missing('tracs_client_services', 'idx_client_services_type_status', '(`service_type`, `status`)');

ALTER TABLE `tracs_client_services`
  MODIFY `billing_cycle` ENUM('monthly','quarterly','semiannual','annual','one_time','custom') NOT NULL DEFAULT 'monthly',
  MODIFY `status` ENUM('active','monitoring','pending_renewal','suspended','terminated','inactive') NOT NULL DEFAULT 'active';

CREATE TABLE IF NOT EXISTS `tracs_client_service_addons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(190) NOT NULL,
  `price` DECIMAL(14,2) DEFAULT NULL,
  `billing_cycle` ENUM('included','monthly','quarterly','semiannual','annual','one_time','custom') NOT NULL DEFAULT 'included',
  `renewal_date` DATE DEFAULT NULL,
  `status` ENUM('active','monitoring','pending_renewal','suspended','terminated','inactive') NOT NULL DEFAULT 'active',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_client_addons_service` (`service_id`, `status`),
  INDEX `idx_client_addons_renewal` (`renewal_date`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_client_service_renewal_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` INT UNSIGNED NOT NULL,
  `client_id` INT UNSIGNED NOT NULL,
  `old_renewal_date` DATE DEFAULT NULL,
  `new_renewal_date` DATE NOT NULL,
  `old_price` DECIMAL(14,2) DEFAULT NULL,
  `new_price` DECIMAL(14,2) DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_by_name` VARCHAR(150) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_client_renewal_history_service` (`service_id`, `created_at`),
  INDEX `idx_client_renewal_history_client` (`client_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS tracs_cp_add_column_if_missing;
DROP PROCEDURE IF EXISTS tracs_cp_add_index_if_missing;
