-- TRACS Database Install Script
-- Run once on fresh database
-- Compatible: MySQL 5.7+ / MariaDB 10.3+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Users
CREATE TABLE IF NOT EXISTS `tracs_users` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(255) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `name`       VARCHAR(100),
  `created_at` DATETIME DEFAULT NOW(),
  INDEX idx_email (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cases
CREATE TABLE IF NOT EXISTS `tracs_cases` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT NOT NULL,
  `title`         VARCHAR(500) NOT NULL,
  `notes`         TEXT,
  `status`        ENUM('active','pending','stuck','completed') DEFAULT 'active',
  `priority`      ENUM('low','medium','high','critical') DEFAULT 'medium',
  `next_check_at` DATETIME NULL,
  `created_at`    DATETIME DEFAULT NOW(),
  `updated_at`    DATETIME DEFAULT NOW(),
  INDEX idx_user (`user_id`),
  INDEX idx_status (`status`),
  INDEX idx_priority (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reminders
CREATE TABLE IF NOT EXISTS `tracs_reminders` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT NOT NULL,
  `title`        VARCHAR(500) NOT NULL,
  `description`  TEXT,
  `due_date`     DATETIME NULL,
  `priority`     ENUM('low','medium','high','critical') DEFAULT 'medium',
  `is_completed` TINYINT(1) DEFAULT 0,
  `created_at`   DATETIME DEFAULT NOW(),
  `updated_at`   DATETIME DEFAULT NOW(),
  INDEX idx_user (`user_id`),
  INDEX idx_due (`due_date`),
  INDEX idx_completed (`is_completed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tasks (Checklist)
CREATE TABLE IF NOT EXISTS `tracs_side_tasks` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT NOT NULL,
  `title`        VARCHAR(500) NOT NULL,
  `description`  TEXT,
  `is_completed` TINYINT(1) DEFAULT 0,
  `created_at`   DATETIME DEFAULT NOW(),
  `updated_at`   DATETIME DEFAULT NOW(),
  INDEX idx_user (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Task Logs
CREATE TABLE IF NOT EXISTS `tracs_side_task_logs` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `task_id`    INT NOT NULL,
  `note`       TEXT,
  `created_at` DATETIME DEFAULT NOW(),
  INDEX idx_task (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity Log
CREATE TABLE IF NOT EXISTS `tracs_activity_logs` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT NOT NULL,
  `action`       VARCHAR(100) NOT NULL,
  `module`       VARCHAR(100),
  `description`  TEXT,
  `reference_id` INT NULL,
  `created_at`   DATETIME DEFAULT NOW(),
  INDEX idx_user (`user_id`),
  INDEX idx_module (`module`),
  INDEX idx_created (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Custom Ticker Messages
CREATE TABLE IF NOT EXISTS `tracs_ticker_messages` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `text`       VARCHAR(500) NOT NULL,
  `class`      ENUM('normal','info','urgent','critical') DEFAULT 'normal',
  `enabled`    TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT NOW(),
  INDEX idx_user (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Finance Transfers
CREATE TABLE IF NOT EXISTS `tracs_finance_transfers` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`       INT NOT NULL,
  `note`          VARCHAR(500) NOT NULL,
  `from_account`  VARCHAR(200),
  `to_account`    VARCHAR(200),
  `amount`        DECIMAL(18,2) NOT NULL DEFAULT 0,
  `direction`     ENUM('in','out') DEFAULT 'out',
  `status`        ENUM('completed','pending','failed') DEFAULT 'pending',
  `transfer_date` DATETIME DEFAULT NOW(),
  `created_at`    DATETIME DEFAULT NOW(),
  INDEX idx_user (`user_id`),
  INDEX idx_date (`transfer_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Domains
CREATE TABLE IF NOT EXISTS `tracs_domains` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT NOT NULL,
  `domain`      VARCHAR(253) NOT NULL,
  `registrar`   VARCHAR(200),
  `expires_at`  DATE NULL,
  `ssl_active`  TINYINT(1) DEFAULT 0,
  `auto_renew`  TINYINT(1) DEFAULT 0,
  `notes`       VARCHAR(500),
  `created_at`  DATETIME DEFAULT NOW(),
  `updated_at`  DATETIME DEFAULT NOW(),
  INDEX idx_user (`user_id`),
  INDEX idx_expires (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Default admin user (password: admin123 - CHANGE IMMEDIATELY)
INSERT IGNORE INTO `tracs_users` (`email`, `password`, `name`) VALUES
('admin@tracs.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator');

-- Note: Default password is "password" - change via: UPDATE tracs_users SET password=PASSWORD_HASH WHERE email='admin@tracs.local';
