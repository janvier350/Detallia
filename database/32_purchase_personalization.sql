-- Migracion 32: costo de personalizacion (con su propio IVA editable 0/15)
-- Se suma al total de la factura: personalization_cost * (1 + personalization_iva/100).

ALTER TABLE `purchase_invoices`
  ADD COLUMN `personalization_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `shipping_cost`,
  ADD COLUMN `personalization_iva` DECIMAL(5,2) NOT NULL DEFAULT 15.00 AFTER `personalization_cost`;
