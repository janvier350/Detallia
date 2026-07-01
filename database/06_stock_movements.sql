-- Migracion 06: control de inventario (stock) por articulo
-- Las compras alimentan el stock, las entregas de kits lo descuentan.

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) NOT NULL,
  `movement_type` enum('compra','entrega','ajuste') NOT NULL,
  `quantity` decimal(10,2) NOT NULL COMMENT 'Positivo = entrada, negativo = salida',
  `reference_type` varchar(30) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_stockmov_article` (`article_id`),
  KEY `idx_stockmov_reference` (`reference_type`, `reference_id`),
  KEY `fk_stockmov_user` (`created_by`),
  CONSTRAINT `fk_stockmov_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  CONSTRAINT `fk_stockmov_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
