-- TRACS migration: safe user removal (archive + identity release) and dashboard
-- access repair. Idempotent and safe to re-run on MySQL/MariaDB installations.
--
-- Fixes two production defects in the User Management lifecycle:
--   1. Login 404: operational roles were missing `dashboard.view`, so every
--      non-super_admin user landed on index.php and hit a hard 404.
--   2. Email/username reuse conflict: removing a user kept the original email
--      and username on the row, so the UNIQUE keys blocked recreation. Removal
--      now archives the original identity and releases the reusable identifiers.

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_rr_add_column_if_missing $$
CREATE PROCEDURE tracs_rr_add_column_if_missing(
  IN p_table VARCHAR(128),
  IN p_column VARCHAR(128),
  IN p_definition TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DELIMITER ;

-- 1. Ensure the soft-delete status value exists (no-op if already present).
ALTER TABLE `tracs_users`
  MODIFY `status` ENUM('active','inactive','suspended','removed') NOT NULL DEFAULT 'active';

-- 2. Archive columns preserve the original identity for audit/history display
--    while the live email/username are released for reuse.
CALL tracs_rr_add_column_if_missing('tracs_users', 'archived_email', 'VARCHAR(255) NULL AFTER `email`');
CALL tracs_rr_add_column_if_missing('tracs_users', 'archived_username', 'VARCHAR(80) NULL AFTER `username`');
CALL tracs_rr_add_column_if_missing('tracs_users', 'removed_at', 'DATETIME NULL AFTER `last_login_at`');
CALL tracs_rr_add_column_if_missing('tracs_users', 'removed_by', 'INT UNSIGNED NULL AFTER `removed_at`');

DROP PROCEDURE IF EXISTS tracs_rr_add_column_if_missing;

-- 3. Repair the login 404: every operational role that lands on the dashboard
--    must hold `dashboard.view`. Intern already had it; this aligns the rest.
INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key = 'dashboard.view'
WHERE r.slug IN ('admin', 'supervisor', 'agent', 'viewer', 'intern');
