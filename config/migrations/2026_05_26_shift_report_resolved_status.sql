-- Add informational resolved handover support and on-hold monitoring status.

ALTER TABLE `tracs_shift_reports`
  MODIFY COLUMN `status` ENUM('active','on_hold','resolved') NOT NULL DEFAULT 'active';

ALTER TABLE `tracs_shift_reports`
  ADD COLUMN IF NOT EXISTS `resolution_note` TEXT NULL AFTER `status`;

ALTER TABLE `tracs_shift_reports`
  ADD COLUMN IF NOT EXISTS `visible_to_next_shift` TINYINT(1) NOT NULL DEFAULT 1 AFTER `resolved_at`;

UPDATE `tracs_shift_reports`
SET `visible_to_next_shift` = 1
WHERE `status` IN ('active', 'on_hold', 'resolved');
