-- TRACS UI/theme cleanup.
-- Safe to re-run: disables legacy TRACS V2 user preferences and adds On Hold
-- to the existing case status enum without deleting or archiving case data.

DELIMITER $$

DROP PROCEDURE IF EXISTS tracs_ui_disable_v2_theme $$
CREATE PROCEDURE tracs_ui_disable_v2_theme()
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tracs_user_preferences'
  ) THEN
    UPDATE `tracs_user_preferences`
    SET `preference_value` = 'default', `updated_at` = NOW()
    WHERE `preference_key` = 'visual_theme'
      AND LOWER(REPLACE(REPLACE(TRIM(`preference_value`), '-', '_'), ' ', '_')) IN ('tracs_v2', 'tracsv2', 'intercom_inspired');
  END IF;
END $$

DROP PROCEDURE IF EXISTS tracs_ui_add_case_on_hold $$
CREATE PROCEDURE tracs_ui_add_case_on_hold()
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tracs_cases'
      AND COLUMN_NAME = 'status'
      AND COLUMN_TYPE NOT LIKE '%on_hold%'
  ) THEN
    ALTER TABLE `tracs_cases`
      MODIFY `status` ENUM('active','pending','in_progress','stuck','on_hold','completed') NOT NULL DEFAULT 'active';
  END IF;
END $$

DELIMITER ;

CALL tracs_ui_disable_v2_theme();
CALL tracs_ui_add_case_on_hold();

DROP PROCEDURE IF EXISTS tracs_ui_disable_v2_theme;
DROP PROCEDURE IF EXISTS tracs_ui_add_case_on_hold;
