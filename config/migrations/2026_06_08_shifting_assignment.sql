-- TRACS Workforce Scheduling / Shifting Assignment
-- Safe to re-run on MySQL 8+.

CREATE TABLE IF NOT EXISTS `shift_assignment_types` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_name` VARCHAR(80) NOT NULL,
  `type_slug` VARCHAR(80) NOT NULL,
  `count_as_work_hour` TINYINT(1) NOT NULL DEFAULT 1,
  `count_as_overtime` TINYINT(1) NOT NULL DEFAULT 0,
  `count_as_holiday_hour` TINYINT(1) NOT NULL DEFAULT 0,
  `color_label` VARCHAR(20) NOT NULL DEFAULT '#4f46e5',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shift_assignment_types_slug` (`type_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shift_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shift_name` VARCHAR(120) NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `duration_minutes` INT UNSIGNED NOT NULL,
  `default_break_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_cross_day` TINYINT(1) NOT NULL DEFAULT 0,
  `color_label` VARCHAR(20) NOT NULL DEFAULT '#4f46e5',
  `default_assignment_type` VARCHAR(80) NOT NULL DEFAULT 'regular_shift',
  `count_as_work_hour` TINYINT(1) NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_shift_templates_active` (`is_active`, `shift_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shift_workload_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `division_id` INT UNSIGNED DEFAULT NULL,
  `weekly_target_minutes` INT UNSIGNED NOT NULL DEFAULT 2400,
  `daily_target_minutes` INT UNSIGNED NOT NULL DEFAULT 480,
  `min_weekly_minutes` INT UNSIGNED NOT NULL DEFAULT 2400,
  `max_weekly_minutes` INT UNSIGNED NOT NULL DEFAULT 2880,
  `max_daily_minutes` INT UNSIGNED NOT NULL DEFAULT 720,
  `overtime_threshold_minutes` INT UNSIGNED NOT NULL DEFAULT 2700,
  `normal_working_days_per_week` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `minimum_rest_between_shifts_minutes` INT UNSIGNED NOT NULL DEFAULT 480,
  `timeline_snap_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `minimum_shift_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  `count_standby_as_work_hour` TINYINT(1) NOT NULL DEFAULT 1,
  `holiday_minimum_agents` SMALLINT UNSIGNED NOT NULL DEFAULT 2,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shift_workload_settings_division` (`division_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `public_holidays` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `holiday_date` DATE NOT NULL,
  `holiday_name` VARCHAR(180) NOT NULL,
  `holiday_type` ENUM('national_holiday','collective_leave','company_holiday','custom') NOT NULL DEFAULT 'national_holiday',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_public_holidays_date_name` (`holiday_date`, `holiday_name`),
  INDEX `idx_public_holidays_active_date` (`is_active`, `holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shift_coverage_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `division_id` INT UNSIGNED DEFAULT NULL,
  `day_type` ENUM('weekday','weekend','public_holiday','custom') NOT NULL DEFAULT 'weekday',
  `custom_date` DATE DEFAULT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `minimum_agents` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `role_required` VARCHAR(80) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_shift_coverage_rules_lookup` (`division_id`, `day_type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shift_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `division_id` INT UNSIGNED DEFAULT NULL,
  `shift_template_id` INT UNSIGNED DEFAULT NULL,
  `assignment_date` DATE NOT NULL,
  `start_datetime` DATETIME NOT NULL,
  `end_datetime` DATETIME NOT NULL,
  `is_cross_day` TINYINT(1) NOT NULL DEFAULT 0,
  `break_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `calculated_duration_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `assignment_type` VARCHAR(80) NOT NULL DEFAULT 'regular_shift',
  `status` ENUM('assigned','confirmed','active','completed','cancelled','no_show','replaced') NOT NULL DEFAULT 'assigned',
  `is_overtime` TINYINT(1) NOT NULL DEFAULT 0,
  `is_holiday_assignment` TINYINT(1) NOT NULL DEFAULT 0,
  `is_manual_duration_override` TINYINT(1) NOT NULL DEFAULT 0,
  `approval_status` ENUM('not_required','pending','approved','rejected') NOT NULL DEFAULT 'not_required',
  `source` ENUM('manual','monthly_template','copy','replacement') NOT NULL DEFAULT 'manual',
  `monthly_template_id` BIGINT UNSIGNED DEFAULT NULL,
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_shift_assignments_range` (`start_datetime`, `end_datetime`, `status`),
  INDEX `idx_shift_assignments_user_range` (`user_id`, `start_datetime`, `end_datetime`),
  INDEX `idx_shift_assignments_division_date` (`division_id`, `assignment_date`),
  INDEX `idx_shift_assignments_type` (`assignment_type`, `status`),
  INDEX `idx_shift_assignments_source` (`source`, `monthly_template_id`),
  CONSTRAINT `fk_shift_assignments_user` FOREIGN KEY (`user_id`) REFERENCES `tracs_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shift_assignments_division` FOREIGN KEY (`division_id`) REFERENCES `tracs_divisions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_shift_assignments_template` FOREIGN KEY (`shift_template_id`) REFERENCES `shift_templates` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `holiday_coverage_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `holiday_id` INT UNSIGNED NOT NULL,
  `user_id` INT NOT NULL,
  `shift_assignment_id` BIGINT UNSIGNED NOT NULL,
  `assignment_type` VARCHAR(80) NOT NULL DEFAULT 'holiday_coverage',
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('assigned','confirmed','completed','cancelled') NOT NULL DEFAULT 'assigned',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_holiday_coverage_shift` (`shift_assignment_id`),
  INDEX `idx_holiday_coverage_holiday` (`holiday_id`, `status`),
  CONSTRAINT `fk_holiday_coverage_holiday` FOREIGN KEY (`holiday_id`) REFERENCES `public_holidays` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_holiday_coverage_user` FOREIGN KEY (`user_id`) REFERENCES `tracs_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_holiday_coverage_shift` FOREIGN KEY (`shift_assignment_id`) REFERENCES `shift_assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shift_agent_availability` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `availability_date` DATE NOT NULL,
  `availability_status` ENUM('available','unavailable','leave','sick','training','off_day') NOT NULL DEFAULT 'available',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shift_agent_availability` (`user_id`, `availability_date`),
  INDEX `idx_shift_agent_availability_date` (`availability_date`, `availability_status`),
  CONSTRAINT `fk_shift_agent_availability_user` FOREIGN KEY (`user_id`) REFERENCES `tracs_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shift_monthly_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `division_id` INT UNSIGNED NOT NULL,
  `target_month` DATE NOT NULL,
  `status` ENUM('draft','previewed','applied','archived') NOT NULL DEFAULT 'draft',
  `settings_json` JSON NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `updated_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `archived_at` DATETIME DEFAULT NULL,
  `applied_at` DATETIME DEFAULT NULL,
  `applied_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_shift_monthly_templates_month` (`target_month`, `status`),
  INDEX `idx_shift_monthly_templates_division` (`division_id`, `target_month`),
  CONSTRAINT `fk_shift_monthly_templates_division` FOREIGN KEY (`division_id`) REFERENCES `tracs_divisions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shift_monthly_template_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` BIGINT UNSIGNED NOT NULL,
  `agent_id` INT NOT NULL,
  `shift_template_id` INT UNSIGNED NOT NULL,
  `assignment_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `break_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `assignment_type` VARCHAR(80) NOT NULL DEFAULT 'regular_shift',
  `notes` VARCHAR(500) DEFAULT NULL,
  `generated_assignment_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shift_monthly_template_slot` (`template_id`, `agent_id`, `assignment_date`, `start_time`),
  INDEX `idx_shift_monthly_template_items_template` (`template_id`),
  INDEX `idx_shift_monthly_template_items_agent_date` (`agent_id`, `assignment_date`),
  INDEX `idx_shift_monthly_template_items_assignment` (`generated_assignment_id`),
  CONSTRAINT `fk_shift_monthly_template_items_template` FOREIGN KEY (`template_id`) REFERENCES `shift_monthly_templates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_shift_monthly_template_items_agent` FOREIGN KEY (`agent_id`) REFERENCES `tracs_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shift_monthly_template_items_shift` FOREIGN KEY (`shift_template_id`) REFERENCES `shift_templates` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_shift_monthly_template_items_assignment` FOREIGN KEY (`generated_assignment_id`) REFERENCES `shift_assignments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shift_warnings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `warning_key` VARCHAR(190) DEFAULT NULL,
  `shift_assignment_id` BIGINT UNSIGNED DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `affected_date` DATE DEFAULT NULL,
  `warning_type` ENUM(
    'conflict','jumpshift','overtime','under_target','over_target','coverage_gap',
    'holiday_missing_coverage','availability','duration','rest_day_violation',
    'duplicate_assignment','overlapping_assignment','agent_without_schedule',
    'approval_pending','last_minute_change','cross_day_shift_risk'
  ) NOT NULL,
  `warning_message` VARCHAR(500) NOT NULL,
  `severity` ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
  `is_resolved` TINYINT(1) NOT NULL DEFAULT 0,
  `resolved_by` INT UNSIGNED DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `resolution_note` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_shift_warnings_active` (`is_resolved`, `warning_type`, `created_at`),
  INDEX `idx_shift_warnings_key` (`warning_key`, `is_resolved`),
  INDEX `idx_shift_warnings_assignment` (`shift_assignment_id`, `is_resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assignment_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` BIGINT UNSIGNED DEFAULT NULL,
  `action` ENUM('created','updated','deleted','approved','rejected','replaced','warning_dismissed','template_applied') NOT NULL,
  `changed_by` INT UNSIGNED DEFAULT NULL,
  `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `before_snapshot` JSON DEFAULT NULL,
  `after_snapshot` JSON DEFAULT NULL,
  `note` VARCHAR(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_assignment_audit_assignment` (`assignment_id`, `changed_at`),
  INDEX `idx_assignment_audit_action` (`action`, `changed_at`),
  CONSTRAINT `fk_assignment_audit_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `shift_assignments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `shift_assignment_types`
  (`type_name`, `type_slug`, `count_as_work_hour`, `count_as_overtime`, `count_as_holiday_hour`, `color_label`, `is_active`)
VALUES
  ('Regular Shift', 'regular_shift', 1, 0, 0, '#4f46e5', 1),
  ('Middle Shift', 'middle_shift', 1, 0, 0, '#0ea5e9', 1),
  ('Lembur', 'lembur', 1, 1, 0, '#f59e0b', 1),
  ('Standby', 'standby', 1, 0, 0, '#14b8a6', 1),
  ('Replacement Shift', 'replacement_shift', 1, 0, 0, '#8b5cf6', 1),
  ('Holiday Coverage', 'holiday_coverage', 1, 1, 1, '#ec4899', 1),
  ('Emergency Coverage', 'emergency_coverage', 1, 1, 0, '#ef4444', 1),
  ('Training', 'training', 1, 0, 0, '#64748b', 1),
  ('Off / Leave', 'off_leave', 0, 0, 0, '#94a3b8', 1)
ON DUPLICATE KEY UPDATE
  `type_name` = VALUES(`type_name`),
  `count_as_work_hour` = VALUES(`count_as_work_hour`),
  `count_as_overtime` = VALUES(`count_as_overtime`),
  `count_as_holiday_hour` = VALUES(`count_as_holiday_hour`),
  `color_label` = VALUES(`color_label`);

INSERT INTO `shift_templates`
  (`shift_name`, `start_time`, `end_time`, `duration_minutes`, `default_break_minutes`, `is_cross_day`, `color_label`, `default_assignment_type`, `count_as_work_hour`, `is_active`, `notes`)
SELECT * FROM (
  SELECT 'Shift 1' AS shift_name, '08:00:00' AS start_time, '16:00:00' AS end_time, 480 AS duration_minutes,
         0 AS default_break_minutes, 0 AS is_cross_day, '#4f46e5' AS color_label,
         'regular_shift' AS default_assignment_type, 1 AS count_as_work_hour, 1 AS is_active,
         'Template only; times may be overridden' AS notes
  UNION ALL SELECT 'Shift 2', '14:00:00', '22:00:00', 480, 0, 0, '#0ea5e9', 'regular_shift', 1, 1, 'Template only; times may be overridden'
  UNION ALL SELECT 'Shift 3', '22:00:00', '06:00:00', 480, 0, 1, '#8b5cf6', 'regular_shift', 1, 1, 'Cross-day template'
  UNION ALL SELECT 'Middle Shift', '10:00:00', '14:00:00', 240, 0, 0, '#14b8a6', 'middle_shift', 1, 1, 'Flexible four-hour support shift'
  UNION ALL SELECT 'Weekend Standby', '09:00:00', '17:00:00', 480, 0, 0, '#f59e0b', 'standby', 1, 1, 'Weekend standby template'
  UNION ALL SELECT 'Holiday Lembur', '09:00:00', '17:00:00', 480, 0, 0, '#ec4899', 'holiday_coverage', 1, 1, 'Holiday coverage helper; override as needed'
) seed
WHERE NOT EXISTS (SELECT 1 FROM `shift_templates`);

INSERT INTO `shift_workload_settings` (`division_id`)
SELECT NULL
WHERE NOT EXISTS (SELECT 1 FROM `shift_workload_settings` WHERE `division_id` IS NULL);

INSERT INTO `shift_coverage_rules`
  (`division_id`, `day_type`, `start_time`, `end_time`, `minimum_agents`, `notes`, `is_active`)
SELECT * FROM (
  SELECT NULL AS division_id, 'weekday' AS day_type, '08:00:00' AS start_time, '12:00:00' AS end_time,
         4 AS minimum_agents, 'Default morning coverage' AS notes, 1 AS is_active
  UNION ALL SELECT NULL, 'weekday', '12:00:00', '16:00:00', 5, 'Default afternoon coverage', 1
  UNION ALL SELECT NULL, 'weekday', '16:00:00', '22:00:00', 3, 'Default evening coverage', 1
  UNION ALL SELECT NULL, 'weekday', '22:00:00', '06:00:00', 1, 'Default overnight coverage', 1
  UNION ALL SELECT NULL, 'weekend', '09:00:00', '17:00:00', 2, 'Default weekend standby coverage', 1
  UNION ALL SELECT NULL, 'public_holiday', '09:00:00', '17:00:00', 2, 'Default public holiday coverage', 1
) seed
WHERE NOT EXISTS (SELECT 1 FROM `shift_coverage_rules`);

INSERT INTO `tracs_permissions` (`permission_key`, `category`, `description`)
VALUES
  ('shifts.view', 'Workforce Schedule', 'View shifting assignments and workload recap'),
  ('shifts.manage', 'Workforce Schedule', 'Create and update shifting assignments'),
  ('shifts.settings', 'Workforce Schedule', 'Manage shift templates, holidays, coverage rules, and workload settings'),
  ('shifts.monthly_templates', 'Workforce Schedule', 'Create, duplicate, preview, apply, and archive monthly shift templates'),
  ('shifts.export', 'Workforce Schedule', 'Export shifting assignments and workload recap')
ON DUPLICATE KEY UPDATE
  `category` = VALUES(`category`),
  `description` = VALUES(`description`);

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p
WHERE r.slug IN ('super_admin','admin')
  AND p.permission_key IN ('shifts.view','shifts.manage','shifts.settings','shifts.monthly_templates','shifts.export');

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p
WHERE r.slug = 'supervisor'
  AND p.permission_key IN ('shifts.view','shifts.manage','shifts.monthly_templates','shifts.export');

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p
WHERE r.slug IN ('agent','intern','viewer')
  AND p.permission_key = 'shifts.view';
