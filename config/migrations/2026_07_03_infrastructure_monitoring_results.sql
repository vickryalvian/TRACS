-- Historical ICMP check samples, written by bin/tracs-infrastructure-monitor.php
-- (cron worker) and by the manual "check now" path in
-- public/api/infrastructure-ping.php. infrastructure_servers only ever
-- holds the latest cached result; this table is what makes trend graphs
-- (Grafana-style) possible instead of the client-side fabricated wave.

CREATE TABLE IF NOT EXISTS `infrastructure_monitoring_results` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `server_code` VARCHAR(8) NOT NULL,
  `status` VARCHAR(16) NOT NULL,
  `latency_ms` DECIMAL(8,2) DEFAULT NULL,
  `packet_loss_percent` DECIMAL(5,2) DEFAULT NULL,
  `checked_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_infra_results_code_time` (`server_code`, `checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
