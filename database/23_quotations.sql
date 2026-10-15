-- Migracion 23: cotizaciones de productos por proveedor + enlace al simulador de kits
--
-- Una cotizacion registra el precio al que un proveedor ofrece un articulo del catalogo.
-- El simulador de kits puede tomar productos cotizados para ver costo y que proveedor lo vende.

CREATE TABLE `quotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `iva_rate` decimal(5,2) NOT NULL DEFAULT 15.00,
  `quote_date` date NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_quot_article` (`article_id`),
  KEY `fk_quot_provider` (`provider_id`),
  KEY `fk_quot_user` (`created_by`),
  CONSTRAINT `fk_quot_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quot_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quot_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enlazar el item del simulador con el proveedor/cotizacion elegido (opcional)
ALTER TABLE `kit_simulation_items`
  ADD COLUMN `provider_id` int(11) DEFAULT NULL AFTER `product_name`,
  ADD COLUMN `provider_name` varchar(150) DEFAULT NULL AFTER `provider_id`;
