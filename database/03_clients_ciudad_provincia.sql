-- Migracion 03: agregar ciudad y provincia a clients, y restriccion de nombre unico
ALTER TABLE `clients`
    ADD COLUMN `ciudad`   varchar(100) DEFAULT NULL AFTER `address`,
    ADD COLUMN `provincia` varchar(100) DEFAULT NULL AFTER `ciudad`,
    ADD UNIQUE KEY `uq_client_name` (`name`);
