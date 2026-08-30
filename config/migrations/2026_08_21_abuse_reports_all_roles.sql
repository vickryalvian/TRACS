-- TRACS Permission Revision: Abuse Reports
--
-- Abuse Reports view/update is available to every authenticated role. Delete
-- remains role-gated by core/access_control.php::tracs_user_can_delete_abuse_reports().

INSERT INTO `tracs_permissions` (`permission_key`, `category`, `description`)
VALUES
  ('abuse_reports.view', 'Abuse Reports', 'View abuse reports'),
  ('abuse_reports.manage', 'Abuse Reports', 'Create, update, and move abuse reports')
ON DUPLICATE KEY UPDATE
  `category` = VALUES(`category`),
  `description` = VALUES(`description`);

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN (
  'abuse_reports.view',
  'abuse_reports.manage'
);
