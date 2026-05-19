-- TRACS migration: User Management, divisions, roles, permissions, and audit log.
-- Safe to re-run on MySQL/MariaDB installations.

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_um_add_column_if_missing $$
CREATE PROCEDURE tracs_um_add_column_if_missing(
  IN p_table VARCHAR(128),
  IN p_column VARCHAR(128),
  IN p_definition TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = p_column
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DROP PROCEDURE IF EXISTS tracs_um_add_index_if_missing $$
CREATE PROCEDURE tracs_um_add_index_if_missing(
  IN p_table VARCHAR(128),
  IN p_index VARCHAR(128),
  IN p_columns TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND INDEX_NAME = p_index
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` ', p_columns);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DROP PROCEDURE IF EXISTS tracs_um_add_unique_if_missing $$
CREATE PROCEDURE tracs_um_add_unique_if_missing(
  IN p_table VARCHAR(128),
  IN p_index VARCHAR(128),
  IN p_columns TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND INDEX_NAME = p_index
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD UNIQUE KEY `', p_index, '` ', p_columns);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DROP PROCEDURE IF EXISTS tracs_um_add_fk_if_missing $$
CREATE PROCEDURE tracs_um_add_fk_if_missing(
  IN p_table VARCHAR(128),
  IN p_fk VARCHAR(128),
  IN p_definition TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
    SELECT 1
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = p_fk
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_fk, '` ', p_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DELIMITER ;

CREATE TABLE IF NOT EXISTS `tracs_roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(80) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `hierarchy_level` INT NOT NULL DEFAULT 40,
  `is_system_role` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracs_roles_slug` (`slug`),
  INDEX `idx_tracs_roles_level` (`hierarchy_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `permission_key` VARCHAR(120) NOT NULL,
  `category` VARCHAR(80) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracs_permissions_key` (`permission_key`),
  INDEX `idx_tracs_permissions_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_role_permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracs_role_permission` (`role_id`, `permission_id`),
  INDEX `idx_tracs_role_permissions_permission` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_divisions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `code` VARCHAR(40) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `supervisor_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('active','archived') NOT NULL DEFAULT 'active',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracs_divisions_code` (`code`),
  INDEX `idx_tracs_divisions_status` (`status`),
  INDEX `idx_tracs_divisions_supervisor` (`supervisor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_user_activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` INT UNSIGNED DEFAULT NULL,
  `target_type` VARCHAR(60) NOT NULL,
  `target_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `before_data` LONGTEXT DEFAULT NULL,
  `after_data` LONGTEXT DEFAULT NULL,
  `reason` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tracs_ual_actor` (`actor_user_id`, `created_at`),
  INDEX `idx_tracs_ual_target` (`target_type`, `target_id`, `created_at`),
  INDEX `idx_tracs_ual_action` (`action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_password_reset_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tracs_prt_user` (`user_id`, `expires_at`),
  INDEX `idx_tracs_prt_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_intern_profiles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `university_name` VARCHAR(160) NOT NULL,
  `study_program` VARCHAR(160) DEFAULT NULL,
  `internship_start_date` DATE NOT NULL,
  `internship_end_date` DATE NOT NULL,
  `mentor_user_id` INT UNSIGNED DEFAULT NULL,
  `internship_status` ENUM('upcoming','active','ending_soon','completed','extended','terminated') NOT NULL DEFAULT 'upcoming',
  `evaluation_status` ENUM('not_started','in_review','passed','needs_improvement','failed') NOT NULL DEFAULT 'not_started',
  `skill_level` ENUM('beginner','basic','intermediate','advanced') NOT NULL DEFAULT 'beginner',
  `allowed_task_scope` VARCHAR(80) DEFAULT NULL,
  `special_notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_intern_profiles_user` (`user_id`),
  INDEX `idx_user_intern_profiles_status` (`internship_status`),
  INDEX `idx_user_intern_profiles_start` (`internship_start_date`),
  INDEX `idx_user_intern_profiles_end` (`internship_end_date`),
  INDEX `idx_user_intern_profiles_mentor` (`mentor_user_id`),
  INDEX `idx_user_intern_profiles_university` (`university_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL tracs_um_add_column_if_missing('tracs_users', 'username', 'VARCHAR(80) NULL AFTER `name`');
CALL tracs_um_add_column_if_missing('tracs_users', 'phone', 'VARCHAR(50) NULL AFTER `email`');
CALL tracs_um_add_column_if_missing('tracs_users', 'position', 'VARCHAR(120) NULL AFTER `phone`');
CALL tracs_um_add_column_if_missing('tracs_users', 'role', 'ENUM(''admin'',''operator'',''viewer'') NOT NULL DEFAULT ''operator'' AFTER `username`');
CALL tracs_um_add_column_if_missing('tracs_users', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`');
CALL tracs_um_add_column_if_missing('tracs_users', 'status', 'ENUM(''active'',''inactive'',''suspended'') NOT NULL DEFAULT ''active'' AFTER `is_active`');
CALL tracs_um_add_column_if_missing('tracs_users', 'division_id', 'INT UNSIGNED NULL AFTER `status`');
CALL tracs_um_add_column_if_missing('tracs_users', 'role_id', 'INT UNSIGNED NULL AFTER `division_id`');
CALL tracs_um_add_column_if_missing('tracs_users', 'shift_preference', 'VARCHAR(60) NULL AFTER `role_id`');
CALL tracs_um_add_column_if_missing('tracs_users', 'avatar_path', 'VARCHAR(255) NULL AFTER `shift_preference`');
CALL tracs_um_add_column_if_missing('tracs_users', 'avatar_initials_color', 'VARCHAR(20) NULL AFTER `avatar_path`');
CALL tracs_um_add_column_if_missing('tracs_users', 'created_by', 'INT UNSIGNED NULL AFTER `avatar_initials_color`');
CALL tracs_um_add_column_if_missing('tracs_users', 'updated_by', 'INT UNSIGNED NULL AFTER `created_by`');
CALL tracs_um_add_column_if_missing('tracs_users', 'last_login_at', 'DATETIME NULL AFTER `updated_by`');
CALL tracs_um_add_column_if_missing('tracs_users', 'last_activity_at', 'DATETIME NULL AFTER `last_login_at`');
CALL tracs_um_add_column_if_missing('tracs_users', 'last_password_change_at', 'DATETIME NULL AFTER `last_activity_at`');
CALL tracs_um_add_column_if_missing('tracs_users', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');
CALL tracs_um_add_column_if_missing('tracs_activity_logs', 'ip_address', 'VARCHAR(45) NULL AFTER `reference_id`');

UPDATE `tracs_users`
SET `username` = CONCAT('user', `id`)
WHERE `username` IS NULL OR TRIM(`username`) = '';

UPDATE `tracs_users`
SET `status` = CASE WHEN `is_active` = 1 THEN 'active' ELSE 'inactive' END
WHERE `status` = 'active';

CALL tracs_um_add_unique_if_missing('tracs_users', 'uq_tracs_users_username', '(`username`)');
CALL tracs_um_add_index_if_missing('tracs_users', 'idx_tracs_users_status', '(`status`)');
CALL tracs_um_add_index_if_missing('tracs_users', 'idx_tracs_users_role_id', '(`role_id`)');
CALL tracs_um_add_index_if_missing('tracs_users', 'idx_tracs_users_division_id', '(`division_id`)');
CALL tracs_um_add_index_if_missing('tracs_users', 'idx_tracs_users_last_activity', '(`last_activity_at`)');

INSERT INTO `tracs_roles` (`name`, `slug`, `description`, `hierarchy_level`, `is_system_role`)
VALUES
  ('Super Admin', 'super_admin', 'Full access to every TRACS module, role, permission, and setting.', 100, 1),
  ('Admin', 'admin', 'Operational administrator with broad user and operations access.', 80, 1),
  ('Supervisor / Leader', 'supervisor', 'Division leader scoped to team operations and permitted user actions.', 60, 1),
  ('Agent', 'agent', 'Operational agent access without User Management privileges.', 40, 1),
  ('Intern', 'intern', 'Temporary internship user with minimal safe access and dedicated monitoring metadata.', 30, 1),
  ('Viewer / Auditor', 'viewer', 'Read-only auditor access.', 20, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `hierarchy_level` = VALUES(`hierarchy_level`),
  `is_system_role` = VALUES(`is_system_role`);

INSERT INTO `tracs_permissions` (`permission_key`, `category`, `description`)
VALUES
  ('dashboard.view', 'Dashboard', 'View operational dashboard'),
  ('users.view', 'Users', 'View users and team structure'),
  ('users.create', 'Users', 'Create new users'),
  ('users.update', 'Users', 'Update user identity and access fields'),
  ('users.delete', 'Users', 'Soft-delete or permanently remove users'),
  ('users.suspend', 'Users', 'Suspend user login access'),
  ('users.activate', 'Users', 'Restore user login access'),
  ('users.reset_password', 'Users', 'Reset user passwords'),
  ('users.view_activity', 'Users', 'View user activity records'),
  ('profile.view_own', 'Profile', 'View own profile'),
  ('profile.update_own', 'Profile', 'Update own profile'),
  ('profile.change_password_own', 'Profile', 'Change own password'),
  ('profile.update_preferences_own', 'Profile', 'Update own preferences'),
  ('divisions.view', 'Divisions', 'View divisions'),
  ('divisions.create', 'Divisions', 'Create divisions'),
  ('divisions.update', 'Divisions', 'Update divisions'),
  ('divisions.archive', 'Divisions', 'Archive divisions'),
  ('divisions.manage_members', 'Divisions', 'Move users between divisions'),
  ('roles.view', 'Roles', 'View roles and permission matrix'),
  ('roles.create', 'Roles', 'Create roles'),
  ('roles.update', 'Roles', 'Update roles'),
  ('roles.delete', 'Roles', 'Delete roles'),
  ('roles.manage_permissions', 'Roles', 'Change role permissions'),
  ('reports.view', 'Reports', 'View reports'),
  ('reports.create', 'Reports', 'Create reports'),
  ('reports.update', 'Reports', 'Update reports'),
  ('reports.export', 'Reports', 'Export reports'),
  ('cases.view', 'Cases', 'View cases'),
  ('cases.manage', 'Cases', 'Create and update cases'),
  ('reminders.view', 'Reminders', 'View reminders'),
  ('reminders.manage', 'Reminders', 'Create and update reminders'),
  ('checklist.view', 'Checklist', 'View checklist'),
  ('checklist.manage', 'Checklist', 'Create and update checklist items'),
  ('finance.view', 'Finance', 'View finance records'),
  ('finance.manage', 'Finance', 'Create and update finance records'),
  ('domains.view', 'Domains', 'View domain records'),
  ('domains.manage', 'Domains', 'Create and update domain records'),
  ('moms.view', 'MoM', 'View meeting minutes'),
  ('moms.manage', 'MoM', 'Create and update meeting minutes'),
  ('cancellation_feedback.view', 'Cancellation Feedback', 'View cancellation feedback'),
  ('cancellation_feedback.manage', 'Cancellation Feedback', 'Create and update cancellation feedback'),
  ('settings.manage', 'Settings', 'Manage sensitive system settings')
ON DUPLICATE KEY UPDATE
  `category` = VALUES(`category`),
  `description` = VALUES(`description`);

UPDATE `tracs_users` u
INNER JOIN `tracs_roles` r
  ON r.slug = CASE
    WHEN u.`role` = 'admin' THEN 'super_admin'
    WHEN u.`role` = 'viewer' THEN 'viewer'
    ELSE 'agent'
  END
SET u.`role_id` = r.`id`
WHERE u.`role_id` IS NULL;

SET @tracs_super_admin_count = (
  SELECT COUNT(*)
  FROM `tracs_users` u
  INNER JOIN `tracs_roles` r ON r.id = u.role_id
  WHERE r.slug = 'super_admin'
);

UPDATE `tracs_users` u
INNER JOIN `tracs_roles` r ON r.slug = 'super_admin'
SET u.`role_id` = r.`id`,
    u.`role` = 'admin',
    u.`is_active` = 1,
    u.`status` = 'active'
WHERE @tracs_super_admin_count = 0
  AND u.`email` = 'admin@tracs.local';

SET @tracs_super_admin_count = (
  SELECT COUNT(*)
  FROM `tracs_users` u
  INNER JOIN `tracs_roles` r ON r.id = u.role_id
  WHERE r.slug = 'super_admin'
);

UPDATE `tracs_users` u
INNER JOIN `tracs_roles` r ON r.slug = 'super_admin'
SET u.`role_id` = r.`id`,
    u.`role` = 'admin',
    u.`is_active` = 1,
    u.`status` = 'active'
WHERE @tracs_super_admin_count = 0
  AND u.`id` = (SELECT first_user_id FROM (SELECT MIN(id) AS first_user_id FROM `tracs_users`) x);

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p
WHERE r.slug = 'super_admin';

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN (
  'dashboard.view',
  'users.view','users.create','users.update','users.suspend','users.activate','users.reset_password','users.view_activity',
  'profile.view_own','profile.update_own','profile.change_password_own','profile.update_preferences_own',
  'divisions.view','divisions.create','divisions.update','divisions.archive','divisions.manage_members',
  'roles.view',
  'reports.view','reports.create','reports.update','reports.export',
  'cases.view','cases.manage','reminders.view','reminders.manage','checklist.view','checklist.manage',
  'finance.view','finance.manage','domains.view','domains.manage','moms.view','moms.manage',
  'cancellation_feedback.view','cancellation_feedback.manage'
)
WHERE r.slug = 'admin';

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN (
  'dashboard.view',
  'users.view','users.update','users.suspend','users.activate','users.reset_password','users.view_activity',
  'profile.view_own','profile.update_own','profile.change_password_own','profile.update_preferences_own',
  'divisions.view','divisions.manage_members',
  'reports.view','reports.create','reports.update','reports.export',
  'cases.view','cases.manage','reminders.view','reminders.manage','checklist.view','checklist.manage',
  'domains.view','domains.manage','moms.view','moms.manage',
  'cancellation_feedback.view','cancellation_feedback.manage'
)
WHERE r.slug = 'supervisor';

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN (
  'dashboard.view','profile.view_own','profile.update_own','profile.change_password_own','profile.update_preferences_own',
  'cases.view','cases.manage','reminders.view','reminders.manage','checklist.view','checklist.manage',
  'domains.view','domains.manage','moms.view','moms.manage','reports.view',
  'cancellation_feedback.view','cancellation_feedback.manage'
)
WHERE r.slug = 'agent';

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN (
  'dashboard.view','users.view','users.view_activity',
  'profile.view_own','profile.update_own','profile.change_password_own','profile.update_preferences_own',
  'divisions.view','roles.view',
  'reports.view','cases.view','reminders.view','checklist.view','finance.view','domains.view','moms.view',
  'cancellation_feedback.view'
)
WHERE r.slug = 'viewer';

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN (
  'dashboard.view',
  'profile.view_own','profile.update_own','profile.change_password_own','profile.update_preferences_own',
  'checklist.view'
)
WHERE r.slug = 'intern';

CALL tracs_um_add_fk_if_missing('tracs_role_permissions', 'fk_tracs_role_permissions_role', 'FOREIGN KEY (`role_id`) REFERENCES `tracs_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
CALL tracs_um_add_fk_if_missing('tracs_role_permissions', 'fk_tracs_role_permissions_permission', 'FOREIGN KEY (`permission_id`) REFERENCES `tracs_permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
CALL tracs_um_add_fk_if_missing('tracs_users', 'fk_tracs_users_role', 'FOREIGN KEY (`role_id`) REFERENCES `tracs_roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
CALL tracs_um_add_fk_if_missing('tracs_users', 'fk_tracs_users_division', 'FOREIGN KEY (`division_id`) REFERENCES `tracs_divisions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');

-- User-id foreign keys are intentionally not forced in this migration because
-- legacy TRACS installations may have a signed INT tracs_users.id while newer
-- schema files use INT UNSIGNED. The indexed columns remain relationally clean,
-- and fresh installs can use config/schema/users.sql for strict FK creation.

DROP PROCEDURE IF EXISTS tracs_um_add_fk_if_missing;
DROP PROCEDURE IF EXISTS tracs_um_add_unique_if_missing;
DROP PROCEDURE IF EXISTS tracs_um_add_index_if_missing;
DROP PROCEDURE IF EXISTS tracs_um_add_column_if_missing;
