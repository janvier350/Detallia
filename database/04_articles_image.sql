-- Migracion 04: agregar columna de imagen a articulos
ALTER TABLE `articles`
    ADD COLUMN `image_path` varchar(255) DEFAULT NULL AFTER `description`;
