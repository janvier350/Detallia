-- Migracion 13: validacion externa de contactos antes de pasarlos a Clientes

CREATE TABLE `validation_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL,
  `label` varchar(150) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_validation_token` (`token`),
  KEY `fk_validationlinks_user` (`created_by`),
  CONSTRAINT `fk_validationlinks_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pending_clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `classification_id` int(11) DEFAULT NULL,
  `status` enum('pendiente','confirmado','rechazado') NOT NULL DEFAULT 'pendiente',
  `validated_by_email` varchar(150) DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_pendingclients_link` (`link_id`),
  KEY `fk_pendingclients_brand` (`brand_id`),
  KEY `fk_pendingclients_classification` (`classification_id`),
  CONSTRAINT `fk_pendingclients_link` FOREIGN KEY (`link_id`) REFERENCES `validation_links` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pendingclients_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pendingclients_classification` FOREIGN KEY (`classification_id`) REFERENCES `client_classifications` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `validation_otp_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `code` varchar(6) NOT NULL,
  `session_token` varchar(64) DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_otp_link` (`link_id`),
  KEY `idx_otp_session` (`session_token`),
  CONSTRAINT `fk_otp_link` FOREIGN KEY (`link_id`) REFERENCES `validation_links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
