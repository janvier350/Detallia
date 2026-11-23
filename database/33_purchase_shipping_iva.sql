-- Migracion 33: IVA editable para el costo de envio (0 o 15)
-- El envio pasa a sumarse al total con su propio IVA: shipping_cost * (1 + shipping_iva/100).

ALTER TABLE `purchase_invoices`
  ADD COLUMN `shipping_iva` DECIMAL(5,2) NOT NULL DEFAULT 15.00 AFTER `shipping_cost`;
