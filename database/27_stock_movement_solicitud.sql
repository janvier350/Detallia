-- Migracion 27: permitir movimientos de inventario por despacho de solicitudes
--
-- Hasta ahora, marcar una solicitud como "despachada" NO descontaba el stock.
-- Se agrega el tipo de movimiento 'solicitud' para registrar la salida de
-- inventario cuando se despacha una solicitud (igual que 'entrega' descuenta
-- al entregar un kit a un cliente).

ALTER TABLE `stock_movements`
  MODIFY COLUMN `movement_type`
  ENUM('compra','entrega','ajuste','devolucion','solicitud') NOT NULL;
