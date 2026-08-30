-- Add a distinct 'pending' status to domain_transfers, separate from the existing
-- 'pending transfer' status, per CS/Ops request.
ALTER TABLE `domain_transfers`
  MODIFY `transfer_status` ENUM('pending','pending transfer','locked','error epp code','move domain','done','cancelled','retransferred','transferred away','pending verification','renew period')
    NOT NULL DEFAULT 'pending transfer';
