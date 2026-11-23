-- Migracion 31: costo de envio en las facturas de compra
-- Se suma al total de la factura (purchase_invoices.total_amount).

ALTER TABLE `purchase_invoices`
  ADD COLUMN `shipping_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `currency`;
