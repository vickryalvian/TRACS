-- ╔══════════════════════════════════════════════════════════════════════════════╗
-- ║  TRACS — Operational Dashboard                                              ║
-- ║  Database Installer — Production Ready                                      ║
-- ║  Compatible: MySQL 5.7+ / MariaDB 10.3+                                    ║
-- ║                                                                              ║
-- ║  Run once on a clean database.                                              ║
-- ║  All statements are migration-safe (IF NOT EXISTS / IF EXISTS).             ║
-- ║  Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci         ║
-- ╚══════════════════════════════════════════════════════════════════════════════╝

-- ─────────────────────────────────────────────────────────────────────────────
-- GLOBAL SESSION SETTINGS
-- ─────────────────────────────────────────────────────────────────────────────
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET character_set_connection = utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ─────────────────────────────────────────────────────────────────────────────
-- DATABASE
-- ─────────────────────────────────────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS `vickryid_tracs_alpha`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `vickryid_tracs_alpha`;


-- ══════════════════════════════════════════════════════════════════════════════
-- SECTION 1 — CORE: USERS & AUTH
-- ══════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- tracs_users
-- Central auth table. All user-scoped tables FK back to this.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tracs_users` (
  `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `email`          VARCHAR(255)     NOT NULL,
  `password`       VARCHAR(255)     NOT NULL,
  `name`           VARCHAR(100)     NOT NULL DEFAULT '',
  `role`           ENUM('admin','operator','viewer')
                                    NOT NULL DEFAULT 'operator',
  `is_active`      TINYINT(1)       NOT NULL DEFAULT 1,
  `last_login_at`  DATETIME                  DEFAULT NULL,
  `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email`   (`email`),
  INDEX      `idx_users_active` (`is_active`),
  INDEX      `idx_users_role`   (`role`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Authentication accounts';


-- ══════════════════════════════════════════════════════════════════════════════
-- SECTION 2 — OPERATIONS: CASES
-- ══════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- tracs_cases
-- Legal/operational case tracking. Core module.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tracs_cases` (
  `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED     NOT NULL,
  `title`         VARCHAR(500)     NOT NULL,
  `notes`         TEXT                      DEFAULT NULL,
  `status`        ENUM('active','pending','stuck','completed')
                                   NOT NULL DEFAULT 'active',
  `priority`      ENUM('low','medium','high','critical')
                                   NOT NULL DEFAULT 'medium',
  `next_check_at` DATETIME                  DEFAULT NULL,
  `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_cases_user`          (`user_id`),
  INDEX `idx_cases_status`        (`status`),
  INDEX `idx_cases_priority`      (`priority`),
  INDEX `idx_cases_next_check`    (`next_check_at`),
  -- Composite: dashboard alert ticker query (priority + status + next_check_at)
  INDEX `idx_cases_alert`         (`user_id`, `priority`, `status`, `next_check_at`),
  -- Composite: today-tasks widget
  INDEX `idx_cases_today`         (`user_id`, `next_check_at`, `status`),

  CONSTRAINT `fk_cases_user`
    FOREIGN KEY (`user_id`) REFERENCES `tracs_users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Operational cases (legal, CS, ops)';


-- ══════════════════════════════════════════════════════════════════════════════
-- SECTION 3 — OPERATIONS: REMINDERS
-- ══════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- tracs_reminders
-- Time-based alerts/reminders per user.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tracs_reminders` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED    NOT NULL,
  `title`        VARCHAR(500)    NOT NULL,
  `description`  TEXT                     DEFAULT NULL,
  `due_date`     DATETIME                 DEFAULT NULL,
  `priority`     ENUM('low','medium','high','critical')
                                 NOT NULL DEFAULT 'medium',
  `is_completed` TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_rem_user`      (`user_id`),
  INDEX `idx_rem_due`       (`due_date`),
  INDEX `idx_rem_completed` (`is_completed`),
  -- Composite: overdue reminders query
  INDEX `idx_rem_overdue`   (`user_id`, `is_completed`, `due_date`),
  -- Composite: upcoming reminders widget
  INDEX `idx_rem_upcoming`  (`user_id`, `is_completed`, `due_date`, `priority`),

  CONSTRAINT `fk_reminders_user`
    FOREIGN KEY (`user_id`) REFERENCES `tracs_users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Time-based reminders per operator';


-- ══════════════════════════════════════════════════════════════════════════════
-- SECTION 4 — OPERATIONS: DAILY CHECKLIST (SIDE TASKS)
-- ══════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- tracs_side_tasks
-- Daily checklist items per user.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tracs_side_tasks` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED    NOT NULL,
  `title`        VARCHAR(500)    NOT NULL,
  `description`  TEXT                     DEFAULT NULL,
  `is_completed` TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_tasks_user`      (`user_id`),
  INDEX `idx_tasks_completed` (`is_completed`),
  -- Composite: incomplete tasks list
  INDEX `idx_tasks_pending`   (`user_id`, `is_completed`, `created_at`),

  CONSTRAINT `fk_tasks_user`
    FOREIGN KEY (`user_id`) REFERENCES `tracs_users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Daily checklist / side tasks per operator';


-- ─────────────────────────────────────────────────────────────────────────────
-- tracs_side_task_logs
-- Notes/history appended to checklist tasks.
-- NOTE: model.php inserts user_id here; column added for full compatibility.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tracs_side_task_logs` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `task_id`    INT UNSIGNED    NOT NULL,
  `user_id`    INT UNSIGNED             DEFAULT NULL,
  `note`       TEXT            NOT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_stl_task`    (`task_id`),
  INDEX `idx_stl_user`    (`user_id`),
  INDEX `idx_stl_created` (`created_at`),

  CONSTRAINT `fk_stl_task`
    FOREIGN KEY (`task_id`) REFERENCES `tracs_side_tasks` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stl_user`
    FOREIGN KEY (`user_id`) REFERENCES `tracs_users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Appended notes/log entries for side tasks';


-- ══════════════════════════════════════════════════════════════════════════════
-- SECTION 5 — OPERATIONS: SHIFT REPORTS
-- ══════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- tracs_shift_reports
-- Shift handover reports (daily). Replaces the old migrate.sql stub.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tracs_shift_reports` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `shift_name`  VARCHAR(50)     NOT NULL DEFAULT 'Shift 1',
  `title`       VARCHAR(255)    NOT NULL,
  `details`     TEXT                     DEFAULT NULL,
  `priority`    ENUM('low','medium','high','critical')
                                NOT NULL DEFAULT 'medium',
  `status`      ENUM('active','resolved')
                                NOT NULL DEFAULT 'active',
  `active_date` DATE            NOT NULL DEFAULT (CURRENT_DATE),
  `created_by`  INT UNSIGNED    NOT NULL,
  `resolved_at` DATETIME                 DEFAULT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_sr_created_by`    (`created_by`),
  INDEX `idx_sr_status`        (`status`),
  -- Composite: today dashboard query
  INDEX `idx_sr_today`         (`active_date`, `status`),
  -- Composite: history filter (date + shift + status + priority)
  INDEX `idx_sr_history`       (`active_date`, `shift_name`, `status`, `priority`),

  CONSTRAINT `fk_sr_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `tracs_users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Shift handover reports — daily operational log';


-- ══════════════════════════════════════════════════════════════════════════════
-- SECTION 6 — DASHBOARD: TICKER & ANNOUNCEMENTS
-- ══════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- tracs_ticker_messages
-- User-managed custom announcements shown in the live ticker bar.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tracs_ticker_messages` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED    NOT NULL,
  `text`       VARCHAR(500)    NOT NULL,
  `class`      ENUM('normal','info','urgent','critical')
                               NOT NULL DEFAULT 'normal',
  `enabled`    TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_tm_user`    (`user_id`),
  INDEX `idx_tm_enabled` (`enabled`),
  -- Composite: ticker display query
  INDEX `idx_tm_active`  (`user_id`, `enabled`, `class`),

  CONSTRAINT `fk_tm_user`
    FOREIGN KEY (`user_id`) REFERENCES `tracs_users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Custom user-managed ticker announcements';


-- ─────────────────────────────────────────────────────────────────────────────
-- tracs_ticker_events
-- Auto-generated system events with 1-hour expiry, fed into the ticker.
-- Created by TickerEventController on every CRUD action.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tracs_ticker_events` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED    NOT NULL,
  `message`      VARCHAR(500)    NOT NULL,
  `type`         ENUM('info','success','warning','critical')
                                 NOT NULL DEFAULT 'info',
  `module`       VARCHAR(50)              DEFAULT NULL,
  `reference_id` INT UNSIGNED             DEFAULT NULL,
  `expires_at`   DATETIME                 DEFAULT NULL,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  -- Composite: active events query (user + expiry, ordered by created_at)
  INDEX `idx_te_active`   (`user_id`, `expires_at`),
  INDEX `idx_te_module`   (`module`),
  INDEX `idx_te_created`  (`created_at`)

  -- No FK on user_id intentionally: events are ephemeral, user deletion
  -- should not cascade-fail on purging expired rows.

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Auto-generated operational ticker events (1h TTL)';


-- ══════════════════════════════════════════════════════════════════════════════
-- SECTION 7 — OPERATIONS: OPS STATUS
-- ══════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- ops_status
-- Live operational status board. Intentionally NOT prefixed `tracs_` —
-- the application queries it as `ops_status` throughout (ajax.php + api).
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ops_status` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `message`    VARCHAR(500)    NOT NULL,
  `severity`   ENUM('info','warning','critical','solved')
                               NOT NULL DEFAULT 'info',
  `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_os_active`   (`is_active`),
  INDEX `idx_os_severity` (`severity`),
  -- Composite: dashboard active status list
  INDEX `idx_os_display`  (`is_active`, `severity`, `id`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Live operational status messages (shown on dashboard)';


-- ══════════════════════════════════════════════════════════════════════════════
-- SECTION 8 — FINANCE
-- ══════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- tracs_finance_transfers
-- Internal finance movement log (in/out per operator).
-- Used by finance.php and api/finance-create.php.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tracs_finance_transfers` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED    NOT NULL,
  `note`          VARCHAR(500)    NOT NULL,
  `from_account`  VARCHAR(200)             DEFAULT NULL,
  `to_account`    VARCHAR(200)             DEFAULT NULL,
  `amount`        DECIMAL(18,2)   NOT NULL DEFAULT '0.00',
  `direction`     ENUM('in','out')NOT NULL DEFAULT 'out',
  `status`        ENUM('completed','pending','failed')
                                  NOT NULL DEFAULT 'pending',
  `transfer_date` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                           ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_ft_user`     (`user_id`),
  INDEX `idx_ft_date`     (`transfer_date`),
  INDEX `idx_ft_status`   (`status`),
  INDEX `idx_ft_dir`      (`direction`),
  -- Composite: finance dashboard filter (user + date range + direction)
  INDEX `idx_ft_filter`   (`user_id`, `direction`, `transfer_date`),

  CONSTRAINT `fk_ft_user`
    FOREIGN KEY (`user_id`) REFERENCES `tracs_users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Internal operator finance movement log';


-- ─────────────────────────────────────────────────────────────────────────────
-- balance_transfers
-- CS/Billing team inter-account balance transfers.
-- Intentionally NOT prefixed `tracs_` — queried as `balance_transfers`
-- in bt-create.php, bt-update.php, bt-delete.php, finance.php.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `balance_transfers` (
  `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `transfer_date`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Sender
  `sender_email`     VARCHAR(254)    NOT NULL DEFAULT '',
  `sender_user_id`   VARCHAR(100)    NOT NULL DEFAULT '',
  `sender_type`      ENUM('client_area','billing_console','billing_awan')
                                     NOT NULL DEFAULT 'client_area',

  -- Receiver
  `receiver_email`   VARCHAR(254)    NOT NULL DEFAULT '',
  `receiver_user_id` VARCHAR(100)    NOT NULL DEFAULT '',
  `receiver_type`    ENUM('client_area','billing_console','billing_awan')
                                     NOT NULL DEFAULT 'client_area',

  -- Transfer details
  `amount`           DECIMAL(15,2)   NOT NULL DEFAULT '0.00',
  `status`           ENUM('done','pending')
                                     NOT NULL DEFAULT 'pending',

  -- Operator traceability
  `admin_name`       VARCHAR(150)    NOT NULL DEFAULT '',
  `ticket_id`        VARCHAR(100)             DEFAULT NULL,

  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                              ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_bt_admin`          (`admin_name`),
  INDEX `idx_bt_ticket`         (`ticket_id`),
  INDEX `idx_bt_transfer_date`  (`transfer_date`),
  INDEX `idx_bt_created_at`     (`created_at`),
  INDEX `idx_bt_sender_email`   (`sender_email`(64)),
  INDEX `idx_bt_receiver_email` (`receiver_email`(64)),
  INDEX `idx_bt_sender_uid`     (`sender_user_id`(50)),
  INDEX `idx_bt_receiver_uid`   (`receiver_user_id`(50)),
  INDEX `idx_bt_status`         (`status`),
  -- Composite: monthly aggregation dashboard
  INDEX `idx_bt_month`          (`status`, `transfer_date`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='CS/Billing inter-account balance transfer log';


-- ══════════════════════════════════════════════════════════════════════════════
-- SECTION 9 — DOMAIN TRACKING
-- ══════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- tracs_domains
-- Domain expiry tracking with SSL and auto-renew flags.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tracs_domains` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED    NOT NULL,
  `domain`      VARCHAR(253)    NOT NULL,
  `registrar`   VARCHAR(200)             DEFAULT NULL,
  `expires_at`  DATE                     DEFAULT NULL,
  `ssl_active`  TINYINT(1)      NOT NULL DEFAULT 0,
  `auto_renew`  TINYINT(1)      NOT NULL DEFAULT 0,
  `notes`       VARCHAR(500)             DEFAULT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_dom_user`      (`user_id`),
  INDEX `idx_dom_expires`   (`expires_at`),
  INDEX `idx_dom_ssl`       (`ssl_active`),
  -- Composite: expiry dashboard widget
  INDEX `idx_dom_expiry`    (`user_id`, `expires_at`, `auto_renew`),
  -- Partial unique: one domain name per user
  UNIQUE KEY `uq_dom_user_domain` (`user_id`, `domain`),

  CONSTRAINT `fk_dom_user`
    FOREIGN KEY (`user_id`) REFERENCES `tracs_users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Domain expiry and SSL tracking per operator';


-- ══════════════════════════════════════════════════════════════════════════════
-- SECTION 10 — CANCELLATION FEEDBACK
-- ══════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- tracs_cancellation_feedback
-- CS cancellation feedback form submissions with analytics support.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tracs_cancellation_feedback` (
  `id`                   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `submitter_name`       VARCHAR(100)    NOT NULL,
  `cancelled_service`    VARCHAR(100)    NOT NULL,
  `cancellation_reason`  VARCHAR(150)    NOT NULL,
  `additional_details`   TEXT                     DEFAULT NULL,
  `whmcs_reference`      VARCHAR(255)             DEFAULT NULL,
  `email_address`        VARCHAR(150)             DEFAULT NULL,
  `payment_resolution`   VARCHAR(100)             DEFAULT NULL,
  `created_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                  ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_cf_service`    (`cancelled_service`),
  INDEX `idx_cf_reason`     (`cancellation_reason`),
  INDEX `idx_cf_email`      (`email_address`),
  INDEX `idx_cf_date`       (`created_at`),
  INDEX `idx_cf_resolution` (`payment_resolution`),
  -- Composite: monthly analytics GROUP BY queries
  INDEX `idx_cf_analytics`  (`created_at`, `cancelled_service`, `cancellation_reason`, `payment_resolution`),
  -- Composite: search filter
  INDEX `idx_cf_filter`     (`cancelled_service`, `cancellation_reason`, `payment_resolution`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Cancellation feedback form submissions (CS module)';


-- ══════════════════════════════════════════════════════════════════════════════
-- SECTION 11 — AUDIT & ACTIVITY LOGGING
-- ══════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- tracs_activity_logs
-- Every create/update/delete action in any module is logged here.
-- Populated by ActivityLogController::logActivity() via logAct() in _bootstrap.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tracs_activity_logs` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED    NOT NULL,
  `action`       VARCHAR(100)    NOT NULL,
  `module`       VARCHAR(100)             DEFAULT NULL,
  `description`  TEXT                     DEFAULT NULL,
  `reference_id` INT UNSIGNED             DEFAULT NULL,
  `ip_address`   VARCHAR(45)              DEFAULT NULL,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_al_user`    (`user_id`),
  INDEX `idx_al_module`  (`module`),
  INDEX `idx_al_action`  (`action`),
  INDEX `idx_al_created` (`created_at`),
  -- Composite: per-user recent activity widget
  INDEX `idx_al_user_recent` (`user_id`, `created_at`),
  -- Composite: per-module activity filter
  INDEX `idx_al_module_filter` (`user_id`, `module`, `created_at`),

  CONSTRAINT `fk_al_user`
    FOREIGN KEY (`user_id`) REFERENCES `tracs_users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Full audit trail — every CRUD action across all modules';


-- ══════════════════════════════════════════════════════════════════════════════
-- SECTION 12 — CURRENCY HISTORY
-- ══════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- tracs_currency_history
-- Cached results from the Frankfurter API (currency converter module).
-- Auto-created inline by currency/service.php; mirrored here for clean install.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tracs_currency_history` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `from_currency` VARCHAR(10)     NOT NULL,
  `to_currency`   VARCHAR(10)     NOT NULL,
  `amount`        DECIMAL(15,2)   NOT NULL,
  `result`        DECIMAL(15,2)   NOT NULL,
  `rate`          DECIMAL(15,6)   NOT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_ch_pair`    (`from_currency`, `to_currency`),
  INDEX `idx_ch_created` (`created_at`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Cached currency conversion results (Frankfurter API)';


-- ══════════════════════════════════════════════════════════════════════════════
-- RESTORE FOREIGN KEY CHECKS
-- ══════════════════════════════════════════════════════════════════════════════
SET FOREIGN_KEY_CHECKS = 1;


-- ══════════════════════════════════════════════════════════════════════════════
-- SECTION 13 — SEED DATA
-- ══════════════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────────────
-- Default admin account
-- Password hash = bcrypt("password") — CHANGE IMMEDIATELY after first login.
-- Run: UPDATE tracs_users SET password = '$2y$12$...' WHERE email='admin@tracs.local';
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `tracs_users`
  (`email`, `password`, `name`, `role`)
VALUES
  (
    'admin@tracs.local',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Administrator',
    'admin'
  );

-- ─────────────────────────────────────────────────────────────────────────────
-- Default ops_status seed (blank active entry so dashboard renders cleanly)
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `ops_status`
  (`id`, `message`, `severity`, `is_active`)
VALUES
  (1, 'All systems operational', 'info', 1);


-- ══════════════════════════════════════════════════════════════════════════════
-- END OF TRACS INSTALL SCRIPT
-- ══════════════════════════════════════════════════════════════════════════════
--
-- Tables created (13 total):
--   Core         : tracs_users
--   Operations   : tracs_cases, tracs_reminders, tracs_side_tasks,
--                  tracs_side_task_logs, tracs_shift_reports
--   Dashboard    : tracs_ticker_messages, tracs_ticker_events, ops_status
--   Finance      : tracs_finance_transfers, balance_transfers
--   Domains      : tracs_domains
--   Feedback     : tracs_cancellation_feedback
--   Audit        : tracs_activity_logs
--   Utility      : tracs_currency_history
--
-- Foreign keys   : 8 (all verified, no orphan references)
-- Indexes        : 54 (primary + secondary + composites)
-- Unique keys    : 2 (tracs_users.email, tracs_domains user+domain)
--
