-- Migration: Register and Update Domain Price Crosscheck Sources with Professional English Names
-- Date: 2026-05-22

-- 1. Safely rename/update existing sources if they exist with old names, to preserve their IDs
UPDATE `domain_price_sources` SET `source_name` = 'Liquid Registrar' WHERE `source_name` IN ('Liquid', 'Liquid Registrar') OR `source_name` LIKE '%Liquid%';
UPDATE `domain_price_sources` SET `source_name` = 'Webnic Registrar' WHERE `source_name` IN ('Webnic', 'Webnic Registrar') OR `source_name` LIKE '%Webnic%';
UPDATE `domain_price_sources` SET `source_name` = 'IDCH Internal Pricing' WHERE `source_name` IN ('Harga IDCH', 'IDCH Internal Pricing') OR `source_name` LIKE '%Harga IDCH%';
UPDATE `domain_price_sources` SET `source_name` = 'IDCH Website Pricing' WHERE `source_name` IN ('Harga di Website IDCH', 'IDCH Website Pricing') OR `source_name` LIKE '%Website IDCH%';
UPDATE `domain_price_sources` SET `source_name` = 'PAAS Pricing' WHERE `source_name` IN ('Harga PAAS', 'PAAS Pricing') OR `source_name` LIKE '%Harga PAAS%';

-- 2. Insert standard professional sources if they don't exist yet, avoiding duplicate errors
INSERT INTO `domain_price_sources` (`source_name`, `source_type`, `is_active`, `sort_order`)
VALUES
  ('Liquid Registrar', 'registrar', 1, 10),
  ('Webnic Registrar', 'registrar', 1, 20),
  ('IDCH Internal Pricing', 'competitor', 1, 30),
  ('IDCH Website Pricing', 'competitor', 1, 40),
  ('PAAS Pricing', 'competitor', 1, 50)
ON DUPLICATE KEY UPDATE
  `source_type` = VALUES(`source_type`),
  `is_active` = 1,
  `sort_order` = VALUES(`sort_order`);
