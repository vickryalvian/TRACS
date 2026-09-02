-- TRACS migration: Client Portfolio MVP.
-- Additive only: client portfolio tables plus permission catalog entries.

CREATE TABLE IF NOT EXISTS `tracs_clients` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_code` VARCHAR(40) DEFAULT NULL,
  `company_name` VARCHAR(190) NOT NULL,
  `client_area_id` INT UNSIGNED DEFAULT NULL,
  `owner_user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('active','monitoring','inactive') NOT NULL DEFAULT 'active',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tracs_clients_code` (`client_code`),
  INDEX `idx_tracs_clients_owner` (`owner_user_id`, `status`),
  INDEX `idx_tracs_clients_status` (`status`),
  INDEX `idx_tracs_clients_company` (`company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_client_contacts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) DEFAULT NULL,
  `phone` VARCHAR(80) DEFAULT NULL,
  `role_title` VARCHAR(120) DEFAULT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_client_contacts_client` (`client_id`, `is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_client_services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` INT UNSIGNED NOT NULL,
  `service_name` VARCHAR(190) NOT NULL,
  `service_type` VARCHAR(80) DEFAULT NULL,
  `service_reference` VARCHAR(120) DEFAULT NULL,
  `billing_cycle` ENUM('monthly','quarterly','semiannual','annual','custom') NOT NULL DEFAULT 'monthly',
  `price` DECIMAL(14,2) DEFAULT NULL,
  `start_date` DATE DEFAULT NULL,
  `billing_day` TINYINT UNSIGNED DEFAULT NULL,
  `renewal_date` DATE DEFAULT NULL,
  `status` ENUM('active','monitoring','inactive') NOT NULL DEFAULT 'active',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_client_services_client` (`client_id`, `status`),
  INDEX `idx_client_services_renewal` (`renewal_date`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_client_billing_records` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED DEFAULT NULL,
  `period_start` DATE DEFAULT NULL,
  `period_end` DATE DEFAULT NULL,
  `invoice_number` VARCHAR(120) DEFAULT NULL,
  `invoice_date` DATE DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `amount` DECIMAL(14,2) DEFAULT NULL,
  `invoice_status` ENUM('upcoming','sent','cancelled') NOT NULL DEFAULT 'upcoming',
  `payment_status` ENUM('waiting','paid','overdue') NOT NULL DEFAULT 'waiting',
  `payment_date` DATE DEFAULT NULL,
  `tax_invoice_required` TINYINT(1) NOT NULL DEFAULT 0,
  `tax_invoice_number` VARCHAR(120) DEFAULT NULL,
  `tax_invoice_sent_at` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_client_billing_client` (`client_id`, `due_date`),
  INDEX `idx_client_billing_service` (`service_id`),
  INDEX `idx_client_billing_attention` (`payment_status`, `invoice_status`, `due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_client_followups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED DEFAULT NULL,
  `billing_record_id` INT UNSIGNED DEFAULT NULL,
  `reminder_id` INT UNSIGNED DEFAULT NULL,
  `action_type` ENUM('send_invoice','check_payment','send_tax_invoice','renewal','general_followup') NOT NULL DEFAULT 'general_followup',
  `title` VARCHAR(220) NOT NULL,
  `due_at` DATETIME DEFAULT NULL,
  `priority` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` ENUM('open','completed','cancelled') NOT NULL DEFAULT 'open',
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_client_followups_client` (`client_id`, `status`, `due_at`),
  INDEX `idx_client_followups_assigned` (`assigned_to`, `status`, `due_at`),
  INDEX `idx_client_followups_reminder` (`reminder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tracs_client_activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `actor_name` VARCHAR(150) DEFAULT NULL,
  `event_type` VARCHAR(80) NOT NULL,
  `summary` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_client_activity_client` (`client_id`, `created_at`),
  INDEX `idx_client_activity_type` (`event_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tracs_permissions` (`permission_key`, `category`, `description`)
VALUES
  ('clients.view', 'Clients', 'View owned client portfolio records'),
  ('clients.manage', 'Clients', 'Create and update client portfolio records'),
  ('clients.view_all', 'Clients', 'View all client portfolio records')
ON DUPLICATE KEY UPDATE
  `category` = VALUES(`category`),
  `description` = VALUES(`description`);

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p
WHERE r.slug = 'super_admin';

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN ('clients.view','clients.manage','clients.view_all')
WHERE r.slug IN ('admin','supervisor');

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key IN ('clients.view','clients.manage')
WHERE r.slug = 'agent';

INSERT IGNORE INTO `tracs_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `tracs_roles` r
JOIN `tracs_permissions` p ON p.permission_key = 'clients.view'
WHERE r.slug = 'viewer';
