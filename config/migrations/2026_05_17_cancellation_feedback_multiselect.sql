-- TRACS — Cancellation Feedback multi-select storage
-- Run once before saving records with multiple services/reasons.

DELIMITER //

DROP PROCEDURE IF EXISTS tracs_cf_drop_index_if_exists//
CREATE PROCEDURE tracs_cf_drop_index_if_exists(IN p_index_name VARCHAR(64))
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'tracs_cancellation_feedback'
      AND index_name = p_index_name
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `tracs_cancellation_feedback` DROP INDEX `', p_index_name, '`');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END//

DROP PROCEDURE IF EXISTS tracs_cf_add_index_if_missing//
CREATE PROCEDURE tracs_cf_add_index_if_missing(IN p_index_name VARCHAR(64), IN p_index_sql TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'tracs_cancellation_feedback'
      AND index_name = p_index_name
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `tracs_cancellation_feedback` ADD INDEX `', p_index_name, '` ', p_index_sql);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END//

DELIMITER ;

CALL tracs_cf_drop_index_if_exists('idx_cf_analytics');
CALL tracs_cf_drop_index_if_exists('idx_cf_filter');
CALL tracs_cf_drop_index_if_exists('idx_cf_service');
CALL tracs_cf_drop_index_if_exists('idx_cf_reason');

ALTER TABLE `tracs_cancellation_feedback`
  MODIFY `cancelled_service` TEXT NOT NULL,
  MODIFY `cancellation_reason` TEXT NOT NULL;

CALL tracs_cf_add_index_if_missing('idx_cf_service', '(`cancelled_service`(100))');
CALL tracs_cf_add_index_if_missing('idx_cf_reason', '(`cancellation_reason`(150))');
CALL tracs_cf_add_index_if_missing('idx_cf_analytics', '(`created_at`, `cancelled_service`(100), `cancellation_reason`(150), `payment_resolution`)');
CALL tracs_cf_add_index_if_missing('idx_cf_filter', '(`cancelled_service`(100), `cancellation_reason`(150), `payment_resolution`)');

DROP PROCEDURE IF EXISTS tracs_cf_drop_index_if_exists;
DROP PROCEDURE IF EXISTS tracs_cf_add_index_if_missing;
