-- Align the cases.delete permission record with the hardcoded supervisor-tier
-- delete check in core/access_control.php::tracs_user_can_delete_cases().
-- Enforcement is role-slug based (super_admin/admin/supervisor), not driven
-- by this table, but the permission grant is kept in sync for the admin UI.

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key = 'cases.delete'
WHERE r.slug = 'supervisor';
