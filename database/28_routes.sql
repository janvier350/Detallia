-- Migracion 28: Modulo RUTAS (estado de entrega de regalos por cliente)
--
-- - carriers: catalogo simple de transportadores (sin login).
-- - route_deliveries: un estado de entrega por cliente (campaña actual).
--   Estados: pendiente, entregado, devuelto, cancelado (este ultimo exige
--   comentario de cancelacion, validado en la aplicacion).

CREATE TABLE IF NOT EXISTS `carriers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `route_deliveries` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `client_id` INT NOT NULL,
  `status` ENUM('pendiente','entregado','devuelto','cancelado') NOT NULL DEFAULT 'pendiente',
  `delivery_date` DATE DEFAULT NULL,
  `carrier_id` INT DEFAULT NULL,
  `cancel_comment` TEXT DEFAULT NULL,
  `updated_by` INT DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_route_client` (`client_id`),
  KEY `fk_route_carrier` (`carrier_id`),
  KEY `fk_route_user` (`updated_by`),
  KEY `idx_route_status` (`status`),
  CONSTRAINT `fk_route_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_route_carrier` FOREIGN KEY (`carrier_id`) REFERENCES `carriers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_route_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
