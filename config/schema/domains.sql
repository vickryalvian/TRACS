-- TRACS domain schema.

CREATE TABLE IF NOT EXISTS `tracs_domains` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_by_name` VARCHAR(150) DEFAULT NULL,
  `domain` VARCHAR(253) NOT NULL,
  `registrar` VARCHAR(200) DEFAULT NULL,
  `expires_at` DATE DEFAULT NULL,
  `ssl_active` TINYINT(1) NOT NULL DEFAULT 0,
  `auto_renew` TINYINT(1) NOT NULL DEFAULT 0,
  `notes` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dom_user_domain` (`user_id`, `domain`),
  INDEX `idx_dom_expiry` (`user_id`, `expires_at`, `auto_renew`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `domain_transfers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain_name` VARCHAR(255) NOT NULL,
  `transfer_status` ENUM('pending transfer','locked','error epp code','move domain','done','cancelled','retransferred','transferred away','pending verification','renew period') NOT NULL DEFAULT 'pending transfer',
  `process_start_date` DATE DEFAULT NULL,
  `process_end_date` DATE DEFAULT NULL,
  `webnic_reseller_transfer` ENUM('Webnic','Resellercamp') DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_by_name` VARCHAR(150) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_transfer_status` (`transfer_status`),
  INDEX `idx_domain_transfers_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_feed` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_type` VARCHAR(50) NOT NULL,
  `activity_message` VARCHAR(255) NOT NULL,
  `related_domain` VARCHAR(255) DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_by_name` VARCHAR(150) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_activity_type` (`activity_type`),
  INDEX `idx_activity_feed_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
