-- TRACS user-adjacent schema.
-- Auth accounts live in auth.sql; user preferences live in preferences.sql.
-- Apply config/migrations/2026_05_17_user_management.sql to upgrade legacy
-- installations safely without recreating tracs_users.
-- User profile pictures are stored as optimized public URL paths in
-- tracs_users.avatar_path by config/migrations/2026_05_18_user_avatars.sql.

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
  INDEX `idx_tracs_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_tracs_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `tracs_roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tracs_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `tracs_permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
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
  INDEX `idx_tracs_divisions_supervisor` (`supervisor_id`),
  CONSTRAINT `fk_tracs_divisions_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `tracs_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
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
  INDEX `idx_tracs_ual_action` (`action`, `created_at`),
  CONSTRAINT `fk_tracs_ual_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `tracs_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
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
  INDEX `idx_tracs_prt_token` (`token_hash`),
  CONSTRAINT `fk_tracs_prt_user` FOREIGN KEY (`user_id`) REFERENCES `tracs_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
