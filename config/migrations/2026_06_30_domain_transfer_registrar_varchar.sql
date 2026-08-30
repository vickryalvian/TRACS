-- Widen domain_transfers.webnic_reseller_transfer from a fixed ENUM to VARCHAR so any
-- active registrar source from Domain Price Crosscheck (domain_price_sources) can be used
-- in Domain Transfer Log, not just the original hardcoded Webnic/Resellercamp pair.
ALTER TABLE `domain_transfers`
  MODIFY `webnic_reseller_transfer` VARCHAR(100) DEFAULT NULL;
