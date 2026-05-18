-- TRACS shared database conventions and extension tables.
-- Use with MySQL 5.7+ / MariaDB 10.3+.

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET character_set_connection = utf8mb4;

CREATE TABLE IF NOT EXISTS `ops_status` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message` VARCHAR(500) NOT NULL,
  `severity` ENUM('info','warning','critical','solved') NOT NULL DEFAULT 'info',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_os_display` (`is_active`, `severity`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_currency_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_currency` VARCHAR(10) NOT NULL,
  `to_currency` VARCHAR(10) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `result` DECIMAL(15,2) NOT NULL,
  `rate` DECIMAL(15,6) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ch_pair` (`from_currency`, `to_currency`),
  INDEX `idx_ch_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
