-- Migration: Create Domain Price Task Links
-- Date: 2026-05-23
-- For Phase 7 Domain Price Crosscheck Task Integration

CREATE TABLE IF NOT EXISTS `domain_price_task_links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `month_id` INT UNSIGNED NOT NULL,
  `task_id` INT NOT NULL,
  `assigned_to` INT NOT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_domain_price_task_links_month` (`month_id`),
  INDEX `idx_dptl_assigned` (`assigned_to`),
  INDEX `idx_dptl_task` (`task_id`),
  CONSTRAINT `fk_dptl_month` FOREIGN KEY (`month_id`) REFERENCES `domain_price_months` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dptl_task` FOREIGN KEY (`task_id`) REFERENCES `tracs_reminders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dptl_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `tracs_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
