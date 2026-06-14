-- Manual-only events for the TRACS Calendar React pilot.
-- Existing cases, shifts, meetings, reminders, tasks, holidays, and notifications
-- remain in their source tables and are aggregated dynamically.

CREATE TABLE IF NOT EXISTS `calendar_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(180) NOT NULL,
  `event_type` VARCHAR(40) NOT NULL,
  `event_date` DATE NOT NULL,
  `start_time` TIME DEFAULT NULL,
  `end_time` TIME DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'upcoming',
  `assigned_user_id` INT UNSIGNED DEFAULT NULL,
  `source_module` VARCHAR(80) NOT NULL DEFAULT 'calendar',
  `source_id` BIGINT UNSIGNED DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `visibility` ENUM('private','team','all') NOT NULL DEFAULT 'team',
  `reminder_minutes` INT UNSIGNED DEFAULT NULL,
  `recurrence_rule` VARCHAR(80) NOT NULL DEFAULT 'none',
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_calendar_events_range` (`event_date`, `deleted_at`),
  INDEX `idx_calendar_events_assignee` (`assigned_user_id`, `event_date`),
  INDEX `idx_calendar_events_creator` (`created_by`, `event_date`),
  INDEX `idx_calendar_events_source` (`source_module`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
