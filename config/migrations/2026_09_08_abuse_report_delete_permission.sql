-- Make abuse report deletion assignable through the existing role permissions.
INSERT INTO `tracs_permissions` (`permission_key`, `category`, `description`)
VALUES ('abuse_reports.delete', 'Abuse Reports', 'Delete abuse reports')
ON DUPLICATE KEY UPDATE
  `category` = VALUES(`category`),
  `description` = VALUES(`description`);

-- Preserve existing deletion access when upgrading.
INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key = 'abuse_reports.delete'
WHERE r.slug IN ('super_admin', 'admin', 'supervisor');
