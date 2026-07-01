-- TRACS Workflow Board — manual drag & drop ordering
--
-- Adds a per-column manual ordering field to tracs_cases so the Kanban board
-- can persist Trello-style drag order. Each status column maintains its own
-- ordering (lower board_order = higher in the column).
--
-- This mirrors the self-healing helper tracs_ensure_case_board_order() in
-- core/creator_tracking.php; running this migration explicitly is optional —
-- the app adds/backfills the column on first request if it is missing.

ALTER TABLE `tracs_cases`
  ADD COLUMN IF NOT EXISTS `board_order` INT NOT NULL DEFAULT 0;

ALTER TABLE `tracs_cases`
  ADD INDEX IF NOT EXISTS `idx_cases_board_order` (`status`, `board_order`);

-- Backfill deterministic per-status ordering for existing rows.
SET @tracs_bo := 0, @tracs_bo_status := '';
UPDATE `tracs_cases` c
JOIN (
    SELECT id,
           (@tracs_bo := IF(@tracs_bo_status = status, @tracs_bo + 1, 0)) AS ord,
           (@tracs_bo_status := status) AS s
    FROM `tracs_cases`
    ORDER BY status, next_check_at IS NULL, next_check_at ASC, updated_at DESC, id DESC
) ranked ON ranked.id = c.id
SET c.board_order = ranked.ord;
