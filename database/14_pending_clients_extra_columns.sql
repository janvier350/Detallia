-- Migracion 14: columnas adicionales del Excel en pending_clients + cierre manual del lote

ALTER TABLE `pending_clients`
  ADD COLUMN `oficina` varchar(100) DEFAULT NULL AFTER `contact_name`,
  ADD COLUMN `zona` varchar(100) DEFAULT NULL AFTER `oficina`,
  ADD COLUMN `contacto_interno` varchar(150) DEFAULT NULL AFTER `zona`,
  ADD COLUMN `ruc_ci` varchar(20) DEFAULT NULL AFTER `contacto_interno`;

ALTER TABLE `validation_links`
  ADD COLUMN `finished_at` datetime DEFAULT NULL,
  ADD COLUMN `finished_by` varchar(150) DEFAULT NULL;
