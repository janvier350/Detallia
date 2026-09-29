-- Migracion 19: IVA por item en las facturas de compra
--
-- Cada articulo comprado puede tener su propio % de IVA (15% por defecto, 0% permitido).
-- El subtotal generado sigue siendo cantidad * precio (sin IVA); el total de la factura
-- (purchase_invoices.total_amount) pasa a incluir el IVA, calculado desde la aplicacion.

ALTER TABLE `purchase_invoice_items`
  ADD COLUMN `iva_rate` decimal(5,2) NOT NULL DEFAULT 15.00 AFTER `unit_price`;
