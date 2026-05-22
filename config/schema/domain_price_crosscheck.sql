-- config/schema/domain_price_crosscheck.sql
-- Table structures for Domain Price Crosscheck module
-- Vickryalvian TRACS Project

-- 1. Monthly Records Metadata
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

-- 2. TLD Extensions Registry
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

-- 3. Registrar / Registry / Internal Sources Registry
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

-- 4. Price Entry Record
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

-- 5. Finalized Monthly Stats Summary Per TLD
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

-- 6. Scoped Operational Audit Log
CREATE TABLE IF NOT EXISTS `domain_price_audit_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `month_id` INT UNSIGNED NOT NULL,
  `tld_id` INT UNSIGNED DEFAULT NULL,
  `source_id` INT UNSIGNED DEFAULT NULL,
  `actor_user_id` INT UNSIGNED NOT NULL,
  `actor_name` VARCHAR(150) NOT NULL,
  `action` VARCHAR(80) NOT NULL,
  `field_name` VARCHAR(80) DEFAULT NULL,
  `old_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `change_reason` TEXT DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_domain_price_audit_month` (`month_id`),
  INDEX `idx_domain_price_audit_actor` (`actor_user_id`),
  CONSTRAINT `fk_domain_price_audit_logs_month` FOREIGN KEY (`month_id`) REFERENCES `domain_price_months` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
