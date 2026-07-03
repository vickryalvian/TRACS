-- Persists the "real" targets added through Infrastructure Pulse's Server
-- Registry (public/infrastructure-pulse.php). Mock/demo entries stay
-- session-only by design (see public/assets/infrastructure-pulse.js) and are
-- never written here. Soft-delete via deleted_at, matching the non-destructive
-- removal pattern used elsewhere in TRACS (see AI_MEMORY.md User Lifecycle Rules).

CREATE TABLE IF NOT EXISTS `infrastructure_servers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(8) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `region` VARCHAR(255) NOT NULL DEFAULT '',
  `country` VARCHAR(255) NOT NULL DEFAULT '',
  `provider` VARCHAR(255) NOT NULL DEFAULT '',
  `method` ENUM('icmp','tcp','http') NOT NULL DEFAULT 'icmp',
  `target_host` VARCHAR(255) NOT NULL DEFAULT '',
  `target_port` SMALLINT UNSIGNED DEFAULT NULL,
  `health_url` VARCHAR(512) NOT NULL DEFAULT '',
  `expected_status` SMALLINT UNSIGNED DEFAULT NULL,
  `expected_keyword` VARCHAR(255) NOT NULL DEFAULT '',
  `packet_count` TINYINT UNSIGNED DEFAULT NULL,
  `timeout_seconds` TINYINT UNSIGNED DEFAULT NULL,
  `interval_seconds` SMALLINT UNSIGNED DEFAULT NULL,
  `last_status` VARCHAR(16) DEFAULT NULL,
  `last_latency_ms` DECIMAL(8,2) DEFAULT NULL,
  `last_packet_loss_percent` DECIMAL(5,2) DEFAULT NULL,
  `last_checked_at` DATETIME DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_infra_server_code` (`code`),
  INDEX `idx_infra_server_active` (`deleted_at`),
  INDEX `idx_infra_server_creator` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
