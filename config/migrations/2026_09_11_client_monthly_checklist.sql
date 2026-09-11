-- TRACS migration: Client monthly checklist status support.
-- Existing client followups remain intact; overdue is computed from open due dates.

ALTER TABLE `tracs_client_followups`
  MODIFY `status` ENUM('open','completed','cancelled','not_applicable') NOT NULL DEFAULT 'open';
