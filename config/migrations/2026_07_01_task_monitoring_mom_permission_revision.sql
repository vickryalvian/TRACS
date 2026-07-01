-- TRACS Permission Revision: Task Monitoring (Checklist/Reminder/Assignment) & MoM
--
-- Checklist: create/update open to every authenticated user; delete restricted
-- to the item's creator (enforced in public/api/task-delete.php via created_by,
-- not by this permission table).
-- Reminder: fully public view/create/update for every authenticated role.
-- MoM: create/update open to every authenticated user (enforced in
-- modules/mom/controller.php + core/access_control.php::tracs_can_view_mom).
--
-- The intern and viewer roles previously lacked checklist.manage,
-- reminders.view/manage, and moms.view/manage (viewer only had the .view
-- permissions; intern had none of these three modules at all). Both are
-- widened here so "every authenticated user" holds true across all roles.

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN (
  'checklist.manage',
  'reminders.view',
  'reminders.manage',
  'moms.view',
  'moms.manage'
)
WHERE r.slug = 'intern';

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN (
  'checklist.manage',
  'reminders.manage',
  'moms.manage'
)
WHERE r.slug = 'viewer';
