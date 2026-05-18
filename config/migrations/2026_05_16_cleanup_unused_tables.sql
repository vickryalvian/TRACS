-- TRACS cleanup migration.
-- Intentionally non-destructive. Deprecated SQL files were archived under
-- config/archive/deprecated after backup; no live tables are dropped here.
--
-- If a future cleanup needs to remove a table, create a new dated migration
-- with an explicit backup/export step and a documented rollback path.

SELECT 'No destructive cleanup performed. Deprecated SQL files archived only.' AS cleanup_note;
