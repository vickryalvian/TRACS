-- Make Cases CSV download assignable from User Management.
INSERT INTO `tracs_permissions` (`permission_key`, `category`, `description`)
VALUES ('cases.export', 'Cases', 'Download cases as CSV')
ON DUPLICATE KEY UPDATE
  `category` = VALUES(`category`),
  `description` = VALUES(`description`);

-- Preserve existing exporter access while allowing lower roles to be granted
-- the scoped permission explicitly from the role matrix.
INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key = 'cases.export'
WHERE r.slug IN ('super_admin', 'admin', 'supervisor');
