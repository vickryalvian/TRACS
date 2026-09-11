-- TRACS migration: Client portfolio attachments.
-- Additive only: stores quotations, tax invoices, screenshots, and supporting documents.

CREATE TABLE IF NOT EXISTS `tracs_client_attachments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` INT UNSIGNED NOT NULL,
  `document_type` ENUM('quotation','tax_invoice','invoice','contract','screenshot','document','other') NOT NULL DEFAULT 'document',
  `original_filename` VARCHAR(255) NOT NULL,
  `stored_filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(120) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `uploaded_by` INT UNSIGNED DEFAULT NULL,
  `uploaded_by_name` VARCHAR(150) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_client_attachments_client` (`client_id`, `created_at`),
  INDEX `idx_client_attachments_type` (`document_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
