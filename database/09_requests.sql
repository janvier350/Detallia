-- Migracion 09: modulo de solicitudes (pedidos internos de articulos/kits)
-- Nuevo rol "Solicitante": usuarios que solo pueden crear y ver sus propias solicitudes.

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(4, 'Solicitante', 'Solo puede crear y consultar sus propias solicitudes de articulos o kits')
ON DUPLICATE KEY UPDATE `name` = `name`;

CREATE TABLE `requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_date` datetime NOT NULL DEFAULT current_timestamp(),
  `requested_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_requests_user` (`requested_by`),
  CONSTRAINT `fk_requests_user` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `request_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `item_type` enum('articulo','kit') NOT NULL,
  `article_id` int(11) DEFAULT NULL,
  `kit_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`id`),
  KEY `fk_requestitems_request` (`request_id`),
  KEY `fk_requestitems_article` (`article_id`),
  KEY `fk_requestitems_kit` (`kit_id`),
  CONSTRAINT `fk_requestitems_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_requestitems_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  CONSTRAINT `fk_requestitems_kit` FOREIGN KEY (`kit_id`) REFERENCES `kits` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
