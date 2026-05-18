-- TRACS migration: Intern role and intern profile metadata.
-- Safe to re-run on MySQL/MariaDB installations.

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

INSERT INTO `tracs_roles` (`name`, `slug`, `description`, `hierarchy_level`, `is_system_role`)
VALUES ('Intern', 'intern', 'Temporary internship user with minimal safe access and dedicated monitoring metadata.', 30, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `hierarchy_level` = VALUES(`hierarchy_level`),
  `is_system_role` = VALUES(`is_system_role`);

INSERT INTO `tracs_permissions` (`permission_key`, `category`, `description`)
VALUES ('dashboard.view', 'Dashboard', 'View operational dashboard')
ON DUPLICATE KEY UPDATE
  `category` = VALUES(`category`),
  `description` = VALUES(`description`);

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN (
  'dashboard.view',
  'profile.view_own',
  'profile.update_own',
  'profile.change_password_own',
  'profile.update_preferences_own',
  'checklist.view'
)
WHERE r.slug = 'intern';
