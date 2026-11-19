-- Migracion 30: salida de inventario al marcar una entrega de ruta como "Entregado"
--
-- Al pasar una entrega (modulo Rutas) a estado 'entregado' se descuenta del
-- inventario el kit que corresponde a la clasificacion + marca del cliente.
-- Se agrega el tipo de movimiento 'ruta' para registrar esa salida.

ALTER TABLE `stock_movements`
  MODIFY COLUMN `movement_type`
  ENUM('compra','entrega','ajuste','devolucion','solicitud','ruta') NOT NULL;
