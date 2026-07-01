-- Migracion 08: devoluciones de articulos ligadas a una entrega
-- Repone el stock (movimiento tipo 'devolucion') y deja motivo obligatorio.

ALTER TABLE `stock_movements`
    MODIFY COLUMN `movement_type` enum('compra','entrega','ajuste','devolucion') NOT NULL;

CREATE TABLE `returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `reason` text NOT NULL,
  `registered_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_returns_delivery` (`delivery_id`),
  KEY `fk_returns_client` (`client_id`),
  KEY `fk_returns_user` (`registered_by`),
  CONSTRAINT `fk_returns_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `kit_deliveries` (`id`),
  CONSTRAINT `fk_returns_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_returns_user` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `return_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_returnitems_return` (`return_id`),
  KEY `fk_returnitems_article` (`article_id`),
  CONSTRAINT `fk_returnitems_return` FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_returnitems_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
