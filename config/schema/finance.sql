-- TRACS finance schema.

CREATE TABLE IF NOT EXISTS `tracs_finance_transfers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_by_name` VARCHAR(150) DEFAULT NULL,
  `note` VARCHAR(500) NOT NULL,
  `from_account` VARCHAR(200) DEFAULT NULL,
  `to_account` VARCHAR(200) DEFAULT NULL,
  `amount` DECIMAL(18,2) NOT NULL DEFAULT '0.00',
  `direction` ENUM('in','out') NOT NULL DEFAULT 'out',
  `status` ENUM('completed','pending','failed') NOT NULL DEFAULT 'pending',
  `transfer_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ft_filter` (`user_id`, `direction`, `transfer_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `balance_transfers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_by_name` VARCHAR(150) DEFAULT NULL,
  `transfer_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sender_email` VARCHAR(254) NOT NULL DEFAULT '',
  `sender_user_id` VARCHAR(100) NOT NULL DEFAULT '',
  `sender_type` ENUM('client_area','billing_console','billing_awan') NOT NULL DEFAULT 'client_area',
  `receiver_email` VARCHAR(254) NOT NULL DEFAULT '',
  `receiver_user_id` VARCHAR(100) NOT NULL DEFAULT '',
  `receiver_type` ENUM('client_area','billing_console','billing_awan') NOT NULL DEFAULT 'client_area',
  `amount` DECIMAL(15,2) NOT NULL DEFAULT '0.00',
  `status` ENUM('done','pending') NOT NULL DEFAULT 'pending',
  `admin_name` VARCHAR(150) NOT NULL DEFAULT '',
  `ticket_id` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_bt_month` (`status`, `transfer_date`),
  INDEX `idx_balance_transfers_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
