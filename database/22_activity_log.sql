-- Migracion 22: bitacora de cambios / auditoria
--
-- Registra inicios de sesion y creaciones/ediciones/eliminaciones en los modulos,
-- con usuario, rol, dispositivo (SO + navegador) e IP.

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `role_name` varchar(50) DEFAULT NULL,
  `module` varchar(50) NOT NULL,
  `reference` varchar(200) DEFAULT NULL,
  `action` varchar(30) NOT NULL,
  `detail` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `device` varchar(30) DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_log_user` (`user_id`),
  KEY `idx_log_module` (`module`),
  KEY `idx_log_action` (`action`),
  KEY `idx_log_created` (`created_at`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
