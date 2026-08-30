-- Add 'removed' status for soft-deleted user accounts (preserves audit/FK history).
ALTER TABLE `tracs_users`
  MODIFY `status` ENUM('active','inactive','suspended','removed') NOT NULL DEFAULT 'active';
