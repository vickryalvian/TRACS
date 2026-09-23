-- Supports bounded age-based retention for the production notification log.
-- Run during a reviewed maintenance window after confirming free disk space.
ALTER TABLE `tracs_notification_logs`
    ADD INDEX IF NOT EXISTS `idx_tracs_notification_logs_created_at` (`created_at`),
    ALGORITHM=INPLACE,
    LOCK=NONE;
