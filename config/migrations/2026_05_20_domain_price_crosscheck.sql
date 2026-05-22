-- TRACS migration: Domain Price Crosscheck Module
-- Safe to re-run on MySQL/MariaDB installations.
-- Vickryalvian TRACS Project

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

DROP PROCEDURE IF EXISTS tracs_dpc_add_index_if_missing $$
CREATE PROCEDURE tracs_dpc_add_index_if_missing(
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

-- 1. Monthly Records Metadata Table
CREATE TABLE IF NOT EXISTS `domain_price_months` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `month` VARCHAR(7) NOT NULL, -- Format 'YYYY-MM'
  `year` INT NOT NULL,
  `exchange_rate_usd_idr` DECIMAL(10, 2) NOT NULL,
  `status` ENUM('draft', 'pending_review', 'approved') NOT NULL DEFAULT 'draft',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `submitted_by` INT UNSIGNED DEFAULT NULL,
  `submitted_at` DATETIME DEFAULT NULL,
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `approval_note` TEXT DEFAULT NULL,
  `unlocked_by` INT UNSIGNED DEFAULT NULL,
  `unlocked_at` DATETIME DEFAULT NULL,
  `unlock_reason` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain_price_months_month` (`month`),
  INDEX `idx_domain_price_months_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TLD Extensions Registry Table
CREATE TABLE IF NOT EXISTS `domain_price_tlds` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tld_name` VARCHAR(30) NOT NULL, -- e.g. '.com', '.id'
  `tld_category` ENUM('gtld','cctld') NOT NULL DEFAULT 'gtld',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain_price_tlds_name` (`tld_name`),
  INDEX `idx_domain_price_tlds_active` (`is_active`),
  INDEX `idx_domain_price_tlds_category` (`tld_category`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Registrar / Registry / Internal Sources Registry Table
CREATE TABLE IF NOT EXISTS `domain_price_sources` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_name` VARCHAR(100) NOT NULL,
  `source_type` ENUM('registrar','internal','registry') NOT NULL DEFAULT 'registrar',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain_price_sources_name` (`source_name`),
  INDEX `idx_domain_price_sources_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Price Entry Record Table
CREATE TABLE IF NOT EXISTS `domain_price_entries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `month_id` INT UNSIGNED NOT NULL,
  `tld_id` INT UNSIGNED NOT NULL,
  `source_id` INT UNSIGNED DEFAULT NULL, -- NULL for selling prices (website & paas)
  `price_type` ENUM(
    'cost_register', 'cost_renewal', 'cost_transfer',
    'selling_website_register', 'selling_website_renewal', 'selling_website_transfer',
    'selling_paas_register', 'selling_paas_renewal', 'selling_paas_transfer'
  ) NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `original_value` DECIMAL(15, 4) NOT NULL,
  `usd_value` DECIMAL(15, 4) NOT NULL,
  `idr_value` DECIMAL(15, 2) NOT NULL,
  `calculated_from_kurs` DECIMAL(10, 2) NOT NULL,
  `is_lowest` TINYINT(1) NOT NULL DEFAULT 0,
  `comparison_status` VARCHAR(50) DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain_price_entry_unique` (`month_id`, `tld_id`, `source_id`, `price_type`),
  CONSTRAINT `fk_domain_price_entries_month` FOREIGN KEY (`month_id`) REFERENCES `domain_price_months` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_domain_price_entries_tld` FOREIGN KEY (`tld_id`) REFERENCES `domain_price_tlds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_domain_price_entries_source` FOREIGN KEY (`source_id`) REFERENCES `domain_price_sources` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Finalized Monthly Stats Summary Per TLD Table
CREATE TABLE IF NOT EXISTS `domain_price_summaries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `month_id` INT UNSIGNED NOT NULL,
  `tld_id` INT UNSIGNED NOT NULL,
  `lowest_register_source_id` INT UNSIGNED DEFAULT NULL,
  `lowest_renewal_source_id` INT UNSIGNED DEFAULT NULL,
  `lowest_transfer_source_id` INT UNSIGNED DEFAULT NULL,
  `lowest_register_cost` DECIMAL(15, 2) DEFAULT NULL,
  `lowest_renewal_cost` DECIMAL(15, 2) DEFAULT NULL,
  `lowest_transfer_cost` DECIMAL(15, 2) DEFAULT NULL,
  `website_register_price` DECIMAL(15, 2) DEFAULT NULL,
  `website_renewal_price` DECIMAL(15, 2) DEFAULT NULL,
  `website_transfer_price` DECIMAL(15, 2) DEFAULT NULL,
  `paas_register_price` DECIMAL(15, 2) DEFAULT NULL,
  `paas_renewal_price` DECIMAL(15, 2) DEFAULT NULL,
  `paas_transfer_price` DECIMAL(15, 2) DEFAULT NULL,
  `website_margin_register` DECIMAL(15, 2) DEFAULT NULL,
  `website_margin_renewal` DECIMAL(15, 2) DEFAULT NULL,
  `website_margin_transfer` DECIMAL(15, 2) DEFAULT NULL,
  `paas_margin_register` DECIMAL(15, 2) DEFAULT NULL,
  `paas_margin_renewal` DECIMAL(15, 2) DEFAULT NULL,
  `paas_margin_transfer` DECIMAL(15, 2) DEFAULT NULL,
  `website_margin_register_pct` DECIMAL(5, 2) DEFAULT NULL,
  `website_margin_renewal_pct` DECIMAL(5, 2) DEFAULT NULL,
  `website_margin_transfer_pct` DECIMAL(5, 2) DEFAULT NULL,
  `paas_margin_register_pct` DECIMAL(5, 2) DEFAULT NULL,
  `paas_margin_renewal_pct` DECIMAL(5, 2) DEFAULT NULL,
  `paas_margin_transfer_pct` DECIMAL(5, 2) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain_price_summaries_month_tld` (`month_id`, `tld_id`),
  CONSTRAINT `fk_domain_price_summaries_month` FOREIGN KEY (`month_id`) REFERENCES `domain_price_months` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_domain_price_summaries_tld` FOREIGN KEY (`tld_id`) REFERENCES `domain_price_tlds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_domain_price_summaries_lowest_reg` FOREIGN KEY (`lowest_register_source_id`) REFERENCES `domain_price_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_domain_price_summaries_lowest_ren` FOREIGN KEY (`lowest_renewal_source_id`) REFERENCES `domain_price_sources` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_domain_price_summaries_lowest_tra` FOREIGN KEY (`lowest_transfer_source_id`) REFERENCES `domain_price_sources` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Scoped Operational Audit Log Table
CREATE TABLE IF NOT EXISTS `domain_price_audit_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `month_id` INT UNSIGNED NOT NULL,
  `actor_user_id` INT UNSIGNED NOT NULL,
  `actor_name` VARCHAR(150) NOT NULL,
  `action` VARCHAR(80) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_domain_price_audit_month` (`month_id`),
  INDEX `idx_domain_price_audit_actor` (`actor_user_id`),
  CONSTRAINT `fk_domain_price_audit_logs_month` FOREIGN KEY (`month_id`) REFERENCES `domain_price_months` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Register Permissions
INSERT INTO `tracs_permissions` (`permission_key`, `category`, `description`)
VALUES
  ('domain_price.view', 'Domain Price', 'View domain price crosscheck panel'),
  ('domain_price.manage', 'Domain Price', 'Create, update, and manage domain price drafts'),
  ('domain_price.approve', 'Domain Price', 'Review, lock, and approve domain price snapshots')
ON DUPLICATE KEY UPDATE
  `category` = VALUES(`category`),
  `description` = VALUES(`description`);

-- 8. Assign Permissions to Roles
-- super_admin gets all permissions
INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN ('domain_price.view', 'domain_price.manage', 'domain_price.approve')
WHERE r.slug = 'super_admin';

-- admin gets all permissions
INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN ('domain_price.view', 'domain_price.manage', 'domain_price.approve')
WHERE r.slug = 'admin';

-- agent gets view and manage
INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN ('domain_price.view', 'domain_price.manage')
WHERE r.slug = 'agent';

-- supervisor and viewer get view only
INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN ('domain_price.view')
WHERE r.slug IN ('supervisor', 'viewer');

-- 9. Seed Default TLDs
INSERT INTO `domain_price_tlds` (`tld_name`, `tld_category`, `is_active`, `sort_order`)
VALUES
  ('.com', 'gtld', 1, 10),
  ('.id', 'gtld', 1, 20),
  ('.net', 'gtld', 1, 30),
  ('.org', 'gtld', 1, 40),
  ('.xyz', 'gtld', 1, 50),
  ('.info', 'gtld', 1, 60),
  ('.biz', 'gtld', 1, 70),
  ('.co', 'gtld', 1, 80),
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

-- 10. Seed Default Pricing Sources
INSERT INTO `domain_price_sources` (`source_name`, `source_type`, `is_active`, `sort_order`)
VALUES
  ('Liquid Registrar', 'registrar', 1, 10),
  ('Webnic Registrar', 'registrar', 1, 20),
  ('IDCH Internal Pricing', 'internal', 1, 30),
  ('PANDI Registry Pricing', 'registry', 1, 401),
  ('IDCH ccTLD Pricing', 'internal', 1, 402)
ON DUPLICATE KEY UPDATE
  `source_type` = VALUES(`source_type`),
  `is_active` = VALUES(`is_active`),
  `sort_order` = VALUES(`sort_order`);

-- Clean up helper procedures if they were created
DROP PROCEDURE IF EXISTS tracs_dpc_add_index_if_missing;
DROP PROCEDURE IF EXISTS tracs_dpc_add_column_if_missing;
