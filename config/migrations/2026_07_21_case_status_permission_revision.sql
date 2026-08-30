-- TRACS Permission Revision: Case Workflow Board
--
-- Cases: view (and therefore board access, drag/drop status changes via
-- case-status.php/case-reorder.php, which are already open to any cases.view
-- holder per the drag & drop board spec) is widened to every authenticated
-- role. cases.manage (create/edit) and case deletion remain unaffected.
--
-- The intern role previously held no `cases.*` permission at all, so
-- cases.php page-guarded (404) Interns out entirely. Widened here so "every
-- authenticated user" holds true across all roles, matching the precedent in
-- 2026_07_01_task_monitoring_mom_permission_revision.sql.

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN (
  'cases.view'
)
WHERE r.slug = 'intern';
