-- Migracion 18: simulacion / costeo de kits
--
-- Permite armar un kit de prueba (simulacro) con productos, precios, IVA y cantidad,
-- por tipo de cliente (clasificacion), guardarlo e imprimirlo en PDF para comparar costos.

CREATE TABLE `kit_simulations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `sim_date` date NOT NULL,
  `classification_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_kitsim_classification` (`classification_id`),
  KEY `fk_kitsim_user` (`created_by`),
  CONSTRAINT `fk_kitsim_classification` FOREIGN KEY (`classification_id`) REFERENCES `client_classifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kitsim_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `kit_simulation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `simulation_id` int(11) NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `iva_rate` decimal(5,2) NOT NULL DEFAULT 15.00,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_kitsimitem_sim` (`simulation_id`),
  CONSTRAINT `fk_kitsimitem_sim` FOREIGN KEY (`simulation_id`) REFERENCES `kit_simulations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
