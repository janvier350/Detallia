-- Migracion 15: columnas de facturacion/alerta del Excel en pending_clients

ALTER TABLE `pending_clients`
  ADD COLUMN `meses_fact` varchar(20) DEFAULT NULL AFTER `ruc_ci`,
  ADD COLUMN `detalle_meses` text DEFAULT NULL AFTER `meses_fact`,
  ADD COLUMN `estatus_excel` varchar(50) DEFAULT NULL AFTER `detalle_meses`,
  ADD COLUMN `alerta` varchar(150) DEFAULT NULL AFTER `estatus_excel`;
