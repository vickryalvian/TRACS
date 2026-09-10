CREATE TABLE IF NOT EXISTS tracs_configurator_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    service_type VARCHAR(100) NOT NULL,
    category VARCHAR(100) NOT NULL,
    name VARCHAR(500) NOT NULL,
    description TEXT NULL,
    price DOUBLE NOT NULL,
    billing_period ENUM('monthly','annual','one_time') NOT NULL DEFAULT 'monthly',
    unit_quantity TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    source_file VARCHAR(255) NULL,
    source_sheet VARCHAR(100) NULL,
    source_reference VARCHAR(100) NULL,
    source_key CHAR(64) NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_configurator_source (source_key),
    INDEX idx_configurator_selection (active, service_type, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tracs_configurator_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    tax_rate DECIMAL(7,6) NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
