-- Add active investigation state for operational cases.

ALTER TABLE `tracs_cases`
  MODIFY COLUMN `status` ENUM('active','pending','in_progress','stuck','on_hold','completed') NOT NULL DEFAULT 'active';

INSERT IGNORE INTO `tracs_permissions` (`permission_key`, `category`, `description`)
VALUES ('cases.delete', 'Cases', 'Delete operational cases');

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key = 'cases.delete'
WHERE r.slug IN ('super_admin', 'admin');
