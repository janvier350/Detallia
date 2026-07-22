-- Migracion 16: bitacora de envios de enlaces de validacion por correo

CREATE TABLE `validation_link_sends` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `sent_by` int(11) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `sent_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_linksends_link` (`link_id`),
  KEY `fk_linksends_user` (`sent_by`),
  CONSTRAINT `fk_linksends_link` FOREIGN KEY (`link_id`) REFERENCES `validation_links` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_linksends_user` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
