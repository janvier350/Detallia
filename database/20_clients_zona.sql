-- Migracion 20: campo Zona en clientes

ALTER TABLE `clients`
  ADD COLUMN `zona` varchar(100) DEFAULT NULL AFTER `ciudad`;
