-- TRACS — Migration: Cancellation Feedback Table
CREATE TABLE IF NOT EXISTS `tracs_cancellation_feedback` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `submitter_name` VARCHAR(100) NOT NULL,
  `cancelled_service` VARCHAR(100) NOT NULL,
  `cancellation_reason` VARCHAR(150) NOT NULL,
  `additional_details` TEXT NULL,
  `whmcs_reference` VARCHAR(255) NULL,
  `email_address` VARCHAR(150) NULL,
  `payment_resolution` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_cf_service` (`cancelled_service`),
  INDEX `idx_cf_reason` (`cancellation_reason`),
  INDEX `idx_cf_email` (`email_address`),
  INDEX `idx_cf_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
