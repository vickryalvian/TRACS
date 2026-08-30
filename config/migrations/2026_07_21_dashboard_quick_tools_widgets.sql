-- Dashboard "Quick Tools" — Website Screenshot history and Currency Converter
-- history persistence, so both widgets can show meaningful data on load
-- instead of an empty panel.

-- Stores each PageFleets capture (one row per region when "All regions" is
-- used) so the Website Screenshot widget can show the most recent captures
-- without re-hitting the external API. Images are stored on disk under
-- public/uploads/screenshot_history/ (see public/api/screenshot-history-lib.php);
-- this table only holds filenames/metadata, never image bytes.
CREATE TABLE IF NOT EXISTS `screenshot_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `raw_input` VARCHAR(2048) NOT NULL,
  `host` VARCHAR(255) NOT NULL,
  `region` VARCHAR(32) NOT NULL DEFAULT '',
  `region_label` VARCHAR(64) NOT NULL DEFAULT '',
  `stored_filename` VARCHAR(255) DEFAULT NULL,
  `thumbnail_filename` VARCHAR(255) DEFAULT NULL,
  `width` SMALLINT UNSIGNED DEFAULT NULL,
  `height` SMALLINT UNSIGNED DEFAULT NULL,
  `file_size_bytes` INT UNSIGNED DEFAULT NULL,
  `load_ms` INT UNSIGNED DEFAULT NULL,
  `dns_ms` INT UNSIGNED DEFAULT NULL,
  `tcp_ms` INT UNSIGNED DEFAULT NULL,
  `ssl_ms` INT UNSIGNED DEFAULT NULL,
  `ttfb_ms` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('success','failed') NOT NULL DEFAULT 'success',
  `error_message` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_screenshot_history_created` (`created_at`),
  KEY `idx_screenshot_history_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Formalizes the currency-conversion history table that
-- modules/currency/service.php previously created ad hoc on every request
-- via an inline `CREATE TABLE IF NOT EXISTS`. Schema unchanged from what was
-- already live in production; this migration just gives it a proper home
-- and adds an index the ad hoc version never had. service.php's inline
-- CREATE TABLE call is removed in this same change.
CREATE TABLE IF NOT EXISTS `tracs_currency_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `from_currency` VARCHAR(10),
  `to_currency` VARCHAR(10),
  `amount` DECIMAL(15,2),
  `result` DECIMAL(15,2),
  `rate` DECIMAL(15,6),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_tracs_currency_history_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
