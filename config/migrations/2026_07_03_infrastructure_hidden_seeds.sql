-- Tracks removal of the built-in demo datacenters seeded in
-- public/assets/infrastructure-pulse-data.js (DCI, IDB, CY1, BCD, BTI, DR3,
-- SG3, EGH, NDS). Those are hardcoded client-side mock data re-seeded fresh
-- on every page load, so "removing" one previously only worked for the
-- current browser tab and reset on refresh. This table makes that removal
-- durable across sessions/users, matching how removal already works for
-- real (infrastructure_servers) targets. Ad-hoc "Demo Data" entries added
-- manually through the Add Server form remain intentionally session-only.

CREATE TABLE IF NOT EXISTS `infrastructure_hidden_seeds` (
  `code` VARCHAR(8) NOT NULL,
  `hidden_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `hidden_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
