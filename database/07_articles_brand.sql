-- Migracion 07: permitir asignar una marca a cada articulo
ALTER TABLE `articles`
    ADD COLUMN `brand_id` int(11) DEFAULT NULL AFTER `category_id`,
    ADD KEY `fk_articles_brand` (`brand_id`),
    ADD CONSTRAINT `fk_articles_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL;
