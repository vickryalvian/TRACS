-- Migration: Add precise structural logging columns to domain_price_audit_logs
-- Created: 2026-05-21

ALTER TABLE `domain_price_audit_logs`
  ADD COLUMN `tld_id` INT UNSIGNED DEFAULT NULL AFTER `month_id`,
  ADD COLUMN `source_id` INT UNSIGNED DEFAULT NULL AFTER `tld_id`,
  ADD COLUMN `field_name` VARCHAR(80) DEFAULT NULL AFTER `action`,
  ADD COLUMN `old_value` TEXT DEFAULT NULL AFTER `field_name`,
  ADD COLUMN `new_value` TEXT DEFAULT NULL AFTER `old_value`,
  ADD COLUMN `change_reason` TEXT DEFAULT NULL AFTER `new_value`;
