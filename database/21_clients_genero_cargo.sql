-- Migracion 21: genero y cargo del contacto que recibe el regalo, en clientes

ALTER TABLE `clients`
  ADD COLUMN `genero` varchar(20) DEFAULT NULL AFTER `contact_name`,
  ADD COLUMN `cargo` varchar(120) DEFAULT NULL AFTER `genero`;
