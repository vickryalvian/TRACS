-- ═══════════════════════════════════════════════════════════════
-- TRACS — Balance Transfer Log
-- Table: balance_transfers
-- Migration-safe · UTF8MB4 · Prepared-statement compatible
-- ═══════════════════════════════════════════════════════════════

-- ── Drop old placeholder table if it exists ──────────────────
-- The original tracs_finance_transfers was a stub (auto-created).
-- This replaces it with a properly structured table.
-- Run this migration script ONCE on first deploy.

-- Step 1: Drop old stub table (only if you have no real data in it)
-- DROP TABLE IF EXISTS tracs_finance_transfers;

-- Step 2: Create the new structured table
CREATE TABLE IF NOT EXISTS `balance_transfers` (
  `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `transfer_date`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Sender
  `sender_email`     VARCHAR(254)     NOT NULL DEFAULT '',
  `sender_user_id`   VARCHAR(100)     NOT NULL DEFAULT '',
  `sender_type`      ENUM(
                       'client_area',
                       'billing_console',
                       'billing_awan'
                     )                NOT NULL DEFAULT 'client_area',

  -- Receiver
  `receiver_email`   VARCHAR(254)     NOT NULL DEFAULT '',
  `receiver_user_id` VARCHAR(100)     NOT NULL DEFAULT '',
  `receiver_type`    ENUM(
                       'client_area',
                       'billing_console',
                       'billing_awan'
                     )                NOT NULL DEFAULT 'client_area',

  -- Transfer details
  `amount`           DECIMAL(15,2)    NOT NULL DEFAULT '0.00',
  `status`           ENUM(
                       'done',
                       'pending'
                     )                NOT NULL DEFAULT 'pending',

  -- Operator / traceability
  `admin_name`       VARCHAR(150)     NOT NULL DEFAULT '',
  `ticket_id`        VARCHAR(100)     NULL     DEFAULT NULL,

  -- Timestamps
  `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  -- Lookup by operator
  INDEX `idx_admin`         (`admin_name`),

  -- Lookup by ticket
  INDEX `idx_ticket`        (`ticket_id`),

  -- Monthly aggregation (transfer_date is what users set; created_at is server-stamped)
  INDEX `idx_transfer_date` (`transfer_date`),
  INDEX `idx_created_at`    (`created_at`),

  -- Sender / receiver lookup
  INDEX `idx_sender_email`    (`sender_email`(64)),
  INDEX `idx_receiver_email`  (`receiver_email`(64)),
  INDEX `idx_sender_uid`      (`sender_user_id`(50)),
  INDEX `idx_receiver_uid`    (`receiver_user_id`(50)),

  -- Status for dashboard stats
  INDEX `idx_status`          (`status`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Balance transfer log — CS/Ops team';


-- ── Sample data (remove before production) ───────────────────
INSERT INTO `balance_transfers`
  (`transfer_date`, `sender_email`, `sender_user_id`, `sender_type`,
   `receiver_email`, `receiver_user_id`, `receiver_type`,
   `amount`, `status`, `admin_name`, `ticket_id`)
VALUES
  (NOW() - INTERVAL 1 DAY,
   'budi.santoso@gmail.com',   'USR-10042', 'client_area',
   'pt.maju@domain.co.id',     'USR-10088', 'billing_console',
   500000.00, 'done',    'Rina', 'TKT-2025-001'),

  (NOW() - INTERVAL 2 DAY,
   'ani.wijaya@yahoo.com',     'USR-10031', 'billing_awan',
   'siti.rahayu@gmail.com',    'USR-10055', 'client_area',
   1250000.00, 'done',   'Dian', 'TKT-2025-002'),

  (NOW() - INTERVAL 3 HOUR,
   'cv.berkah@hosting.id',     'USR-20011', 'billing_console',
   'hendro.purnomo@gmail.com', 'USR-10099', 'billing_awan',
   750000.00, 'pending', 'Rina', NULL),

  (NOW() - INTERVAL 5 DAY,
   'tokosaya.id@gmail.com',    'USR-10072', 'client_area',
   'pt.teknologi@company.com', 'USR-20031', 'billing_console',
   3000000.00, 'done',   'Bimo', 'TKT-2025-003');


-- ── Suggested future: foreign key to tracs_users ─────────────
-- If you add a foreign key to tracs_users in the future:
-- ALTER TABLE balance_transfers
--   ADD COLUMN `operator_user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `admin_name`,
--   ADD INDEX `idx_operator` (`operator_user_id`),
--   ADD CONSTRAINT `fk_bt_operator`
--     FOREIGN KEY (`operator_user_id`) REFERENCES `tracs_users` (`id`)
--     ON DELETE SET NULL ON UPDATE CASCADE;
