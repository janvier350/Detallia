-- Migracion 34: 3 decimales en los calculos de compras
--
-- Amplia la precision de cantidad, precio unitario y costos a 3 decimales,
-- y el movimiento de inventario para no truncar cantidades compradas.

ALTER TABLE `purchase_invoice_items`
  MODIFY COLUMN `quantity`   DECIMAL(10,3) NOT NULL DEFAULT 1.000,
  MODIFY COLUMN `unit_price` DECIMAL(12,3) NOT NULL,
  MODIFY COLUMN `subtotal`   DECIMAL(14,3) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED;

ALTER TABLE `purchase_invoices`
  MODIFY COLUMN `shipping_cost`        DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  MODIFY COLUMN `personalization_cost` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  MODIFY COLUMN `total_amount`         DECIMAL(14,3) NOT NULL DEFAULT 0.000;

-- El inventario tambien a 3 decimales para no truncar lo comprado.
ALTER TABLE `stock_movements`
  MODIFY COLUMN `quantity` DECIMAL(10,3) NOT NULL COMMENT 'Positivo = entrada, negativo = salida';
