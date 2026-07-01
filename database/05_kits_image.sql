-- Migracion 05: agregar imagen de referencia a kits
ALTER TABLE `kits`
    ADD COLUMN `image_path` varchar(255) DEFAULT NULL AFTER `description`;
