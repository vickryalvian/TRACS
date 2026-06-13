-- Keep the active gTLD registrar matrix focused on the registrars currently used operationally.
-- Historical monthly entries remain linked to their original source IDs because this is a soft disable.

UPDATE `domain_price_sources`
SET `is_active` = 0
WHERE `source_type` = 'registrar'
  AND `source_name` NOT IN ('Liquid Registrar', 'Webnic Registrar');
