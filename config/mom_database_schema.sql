-- ═════════════════════════════════════════════════════════════════
-- TRACS — MOM (Minutes of Meeting) Database Schema
-- Complete table structure for meeting documentation and tracking
-- ═════════════════════════════════════════════════════════════════

-- Main meetings table
CREATE TABLE IF NOT EXISTS `tracs_moms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL COMMENT 'Meeting title',
  `type` ENUM('weekly','training','coordination','urgent') NOT NULL DEFAULT 'weekly' COMMENT 'Meeting type',
  `objective` TEXT COMMENT 'Meeting objective/purpose',
  `participants` TEXT COMMENT 'Comma-separated participant names',
  `meeting_at` DATETIME DEFAULT NULL COMMENT 'Planned meeting date and time',
  `meeting_url` VARCHAR(500) DEFAULT NULL COMMENT 'Meeting URL such as Google Meet or Zoom',
  `status` ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming' COMMENT 'Meeting lifecycle status',
  `created_by` INT NOT NULL COMMENT 'User ID who created meeting',
  `scheduled_reminder_id` INT UNSIGNED DEFAULT NULL COMMENT 'Reminder created for scheduled meeting',
  `ops_status_id` INT UNSIGNED DEFAULT NULL COMMENT 'Ops window status entry for meeting',
  `started_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,
  `summary` LONGTEXT DEFAULT NULL COMMENT 'Post-meeting MOM summary',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `idx_meeting_at` (`meeting_at`),
  KEY `idx_lifecycle` (`created_by`, `status`, `meeting_at`),
  KEY `idx_scheduled_reminder` (`scheduled_reminder_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agenda items for meetings
CREATE TABLE IF NOT EXISTS `tracs_mom_agenda` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mom_id` INT UNSIGNED NOT NULL,
  `topic` VARCHAR(255) NOT NULL COMMENT 'Agenda topic',
  `notes` TEXT COMMENT 'Notes on the topic',
  `status` ENUM('pending','completed','skipped') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mom_id` (`mom_id`),
  CONSTRAINT `fk_agenda_mom` FOREIGN KEY (`mom_id`) REFERENCES `tracs_moms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Discussion notes and observations
CREATE TABLE IF NOT EXISTS `tracs_mom_notes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mom_id` INT UNSIGNED NOT NULL,
  `content` LONGTEXT NOT NULL COMMENT 'Note content',
  `note_type` ENUM('discussion','decision','action','insight','risk') NOT NULL DEFAULT 'discussion',
  `created_by` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mom_id` (`mom_id`),
  KEY `idx_note_type` (`note_type`),
  CONSTRAINT `fk_notes_mom` FOREIGN KEY (`mom_id`) REFERENCES `tracs_moms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Decisions made in meeting
CREATE TABLE IF NOT EXISTS `tracs_mom_decisions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mom_id` INT UNSIGNED NOT NULL,
  `decision` TEXT NOT NULL COMMENT 'Decision text',
  `rationale` TEXT COMMENT 'Why this decision was made',
  `owner` VARCHAR(255) COMMENT 'Person responsible for decision',
  `status` ENUM('pending','approved','implemented','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mom_id` (`mom_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_decisions_mom` FOREIGN KEY (`mom_id`) REFERENCES `tracs_moms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Action items to be completed
CREATE TABLE IF NOT EXISTS `tracs_mom_actions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mom_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL COMMENT 'Action title',
  `description` TEXT COMMENT 'Detailed description',
  `assigned_to` VARCHAR(255) COMMENT 'Name of person assigned',
  `priority` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` ENUM('pending','in_progress','completed','cancelled','blocked') NOT NULL DEFAULT 'pending',
  `due_date` DATETIME COMMENT 'When action should be completed',
  `linked_reminder_id` INT COMMENT 'Linked reminder ID for tracking',
  `linked_case_id` INT COMMENT 'Linked case ID if created as operational case',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mom_id` (`mom_id`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_priority` (`priority`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_linked_reminder` (`linked_reminder_id`),
  CONSTRAINT `fk_actions_mom` FOREIGN KEY (`mom_id`) REFERENCES `tracs_moms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Linking MOMs to operational cases
CREATE TABLE IF NOT EXISTS `tracs_mom_case_links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mom_id` INT UNSIGNED NOT NULL,
  `case_id` INT NOT NULL,
  `link_context` VARCHAR(255) COMMENT 'Why case is linked (discussed, created, related)',
  `linked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mom_case` (`mom_id`, `case_id`),
  KEY `idx_case_id` (`case_id`),
  CONSTRAINT `fk_links_mom` FOREIGN KEY (`mom_id`) REFERENCES `tracs_moms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Screenshot attachments for discussions
CREATE TABLE IF NOT EXISTS `tracs_mom_screenshots` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mom_id` INT UNSIGNED NOT NULL,
  `filename` VARCHAR(255) NOT NULL COMMENT 'Stored filename',
  `attached_to_type` ENUM('discussion','action','decision','general') NOT NULL DEFAULT 'general',
  `attached_to_id` INT UNSIGNED COMMENT 'ID of related item (note, action, decision)',
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mom_id` (`mom_id`),
  KEY `idx_attached_to` (`attached_to_type`, `attached_to_id`),
  CONSTRAINT `fk_screenshots_mom` FOREIGN KEY (`mom_id`) REFERENCES `tracs_moms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meeting action log for audit trail
CREATE TABLE IF NOT EXISTS `tracs_mom_audit_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mom_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(100) NOT NULL COMMENT 'Action type (created, updated, decision_added, etc)',
  `details` TEXT COMMENT 'Action details',
  `user_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mom_id` (`mom_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  CONSTRAINT `fk_audit_mom` FOREIGN KEY (`mom_id`) REFERENCES `tracs_moms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════
-- DATABASE VIEWS FOR REPORTING
-- ═════════════════════════════════════════════════════════════════

-- View: Active MOMs with action counts
CREATE OR REPLACE VIEW `vw_mom_summary` AS
SELECT 
  m.id,
  m.title,
  m.type,
  m.status,
  m.created_by,
  m.created_at,
  COUNT(DISTINCT CASE WHEN a.status != 'completed' THEN a.id END) as pending_actions,
  COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END) as completed_actions,
  COUNT(DISTINCT d.id) as total_decisions,
  COUNT(DISTINCT n.id) as total_notes
FROM tracs_moms m
LEFT JOIN tracs_mom_actions a ON m.id = a.mom_id
LEFT JOIN tracs_mom_decisions d ON m.id = d.mom_id
LEFT JOIN tracs_mom_notes n ON m.id = n.mom_id
GROUP BY m.id, m.title, m.type, m.status, m.created_by, m.created_at;

-- View: Overdue actions from MOMs
CREATE OR REPLACE VIEW `vw_mom_overdue_actions` AS
SELECT 
  a.id,
  a.mom_id,
  a.title,
  a.assigned_to,
  a.priority,
  a.due_date,
  m.title as mom_title,
  DATEDIFF(NOW(), a.due_date) as days_overdue
FROM tracs_mom_actions a
INNER JOIN tracs_moms m ON a.mom_id = m.id
WHERE a.status NOT IN ('completed', 'cancelled')
  AND a.due_date < NOW()
ORDER BY a.priority DESC, a.due_date ASC;

-- ═════════════════════════════════════════════════════════════════
-- SAMPLE STORED PROCEDURE FOR MOM COMPLETION
-- ═════════════════════════════════════════════════════════════════

DELIMITER $$

DROP PROCEDURE IF EXISTS `proc_complete_mom`$$

CREATE PROCEDURE `proc_complete_mom` (
  IN p_mom_id INT UNSIGNED
)
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Failed to complete MOM';
  END;

  START TRANSACTION;

  -- Update MOM status
  UPDATE tracs_moms 
  SET status = 'completed', updated_at = NOW()
  WHERE id = p_mom_id;

  -- Mark incomplete actions as blocked/deferred
  UPDATE tracs_mom_actions 
  SET status = 'in_progress'
  WHERE mom_id = p_mom_id 
    AND status = 'pending'
    AND due_date IS NOT NULL;

  -- Log completion
  INSERT INTO tracs_mom_audit_log (mom_id, action, user_id, created_at)
  SELECT id, 'meeting_completed', created_by, NOW()
  FROM tracs_moms
  WHERE id = p_mom_id;

  COMMIT;
END$$

DELIMITER ;

-- Composite indexes and enum integrity are declared in the table definitions
-- above so the migration remains repeatable on both MySQL and MariaDB.
