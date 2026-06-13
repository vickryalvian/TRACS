-- TRACS shifting assignment audit implementation.
-- Safe to re-run after 2026_06_08_shifting_assignment.sql.

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_shift_add_column_if_missing $$
CREATE PROCEDURE tracs_shift_add_column_if_missing(
  IN p_table VARCHAR(128),
  IN p_column VARCHAR(128),
  IN p_definition TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DROP PROCEDURE IF EXISTS tracs_shift_add_index_if_missing $$
CREATE PROCEDURE tracs_shift_add_index_if_missing(
  IN p_table VARCHAR(128),
  IN p_index VARCHAR(128),
  IN p_definition TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table
  ) AND NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` ', p_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DELIMITER ;

CALL tracs_shift_add_column_if_missing(
  'shift_assignments',
  'is_cross_day',
  'TINYINT(1) NOT NULL DEFAULT 0 AFTER `end_datetime`'
);
CALL tracs_shift_add_column_if_missing(
  'shift_assignments',
  'source',
  'ENUM(''manual'',''monthly_template'',''copy'',''replacement'') NOT NULL DEFAULT ''manual'' AFTER `approval_status`'
);
CALL tracs_shift_add_column_if_missing(
  'shift_assignments',
  'monthly_template_id',
  'BIGINT UNSIGNED DEFAULT NULL AFTER `source`'
);
CALL tracs_shift_add_index_if_missing(
  'shift_assignments',
  'idx_shift_assignments_source',
  '(`source`, `monthly_template_id`)'
);

UPDATE shift_assignments
SET is_cross_day = CASE WHEN DATE(end_datetime) > assignment_date THEN 1 ELSE 0 END;

CALL tracs_shift_add_column_if_missing(
  'shift_monthly_templates',
  'applied_at',
  'DATETIME DEFAULT NULL AFTER `archived_at`'
);
CALL tracs_shift_add_column_if_missing(
  'shift_monthly_templates',
  'applied_by',
  'INT UNSIGNED DEFAULT NULL AFTER `applied_at`'
);

ALTER TABLE shift_monthly_templates
  MODIFY COLUMN status ENUM('draft','previewed','active','applied','archived') NOT NULL DEFAULT 'draft';
UPDATE shift_monthly_templates SET status='applied' WHERE status='active';
ALTER TABLE shift_monthly_templates
  MODIFY COLUMN status ENUM('draft','previewed','applied','archived') NOT NULL DEFAULT 'draft';

CALL tracs_shift_add_column_if_missing(
  'shift_warnings',
  'warning_key',
  'VARCHAR(190) DEFAULT NULL AFTER `id`'
);
CALL tracs_shift_add_column_if_missing(
  'shift_warnings',
  'affected_date',
  'DATE DEFAULT NULL AFTER `user_id`'
);
CALL tracs_shift_add_column_if_missing(
  'shift_warnings',
  'resolved_by',
  'INT UNSIGNED DEFAULT NULL AFTER `is_resolved`'
);
CALL tracs_shift_add_column_if_missing(
  'shift_warnings',
  'resolved_at',
  'DATETIME DEFAULT NULL AFTER `resolved_by`'
);
CALL tracs_shift_add_column_if_missing(
  'shift_warnings',
  'resolution_note',
  'VARCHAR(500) DEFAULT NULL AFTER `resolved_at`'
);
CALL tracs_shift_add_index_if_missing(
  'shift_warnings',
  'idx_shift_warnings_key',
  '(`warning_key`, `is_resolved`)'
);

ALTER TABLE shift_warnings
  MODIFY COLUMN warning_type ENUM(
    'conflict','jumpshift','overtime','under_target','over_target','coverage_gap',
    'holiday_missing_coverage','availability','duration','rest_day_violation',
    'duplicate_assignment','overlapping_assignment','agent_without_schedule',
    'approval_pending','last_minute_change','cross_day_shift_risk'
  ) NOT NULL;

CREATE TABLE IF NOT EXISTS `assignment_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` BIGINT UNSIGNED DEFAULT NULL,
  `action` ENUM(
    'created','updated','deleted','approved','rejected','replaced',
    'warning_dismissed','template_applied'
  ) NOT NULL,
  `changed_by` INT UNSIGNED DEFAULT NULL,
  `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `before_snapshot` JSON DEFAULT NULL,
  `after_snapshot` JSON DEFAULT NULL,
  `note` VARCHAR(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_assignment_audit_assignment` (`assignment_id`, `changed_at`),
  INDEX `idx_assignment_audit_action` (`action`, `changed_at`),
  CONSTRAINT `fk_assignment_audit_assignment`
    FOREIGN KEY (`assignment_id`) REFERENCES `shift_assignments` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS tracs_shift_add_index_if_missing;
DROP PROCEDURE IF EXISTS tracs_shift_add_column_if_missing;
