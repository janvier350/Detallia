-- Migracion 03: actualizar tabla clients
-- 1. Agregar ciudad y provincia
-- 2. Hacer classification_id opcional (nullable)
-- 3. Restriccion de nombre unico

ALTER TABLE `clients`
    ADD COLUMN `ciudad`    varchar(100) DEFAULT NULL AFTER `address`,
    ADD COLUMN `provincia` varchar(100) DEFAULT NULL AFTER `ciudad`,
    MODIFY COLUMN `classification_id` int(11) DEFAULT NULL,
    ADD UNIQUE KEY `uq_client_name` (`name`);
