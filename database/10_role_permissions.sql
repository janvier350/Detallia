-- Migracion 10: nuevo rol "Consultor de Inventario" + sistema de permisos por rol/modulo

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(5, 'Consultor de Inventario', 'Acceso de solo consulta a dashboard, inventario, entregas, articulos, compras, kits y devoluciones; puede gestionar marcas y clientes')
ON DUPLICATE KEY UPDATE `name` = `name`;

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `module` varchar(50) NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT 0,
  `can_create` tinyint(1) NOT NULL DEFAULT 0,
  `can_edit` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_module` (`role_id`, `module`),
  CONSTRAINT `fk_roleperm_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- El rol Administrador (1) siempre tiene acceso total (regla fija en el codigo,
-- no depende de esta tabla). Se cargan valores por defecto para el resto de roles,
-- reproduciendo el comportamiento que ya tenian antes de existir esta tabla.

-- Rol 2: Jefe (acceso amplio, igual que antes)
INSERT INTO `role_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(2, 'dashboard', 1, 0, 0, 0),
(2, 'usuarios', 0, 0, 0, 0),
(2, 'proveedores', 1, 1, 1, 1),
(2, 'articulos', 1, 1, 1, 1),
(2, 'compras', 1, 1, 1, 1),
(2, 'marcas', 1, 1, 1, 1),
(2, 'clientes', 1, 1, 1, 1),
(2, 'kits', 1, 1, 1, 1),
(2, 'entregas', 1, 1, 0, 0),
(2, 'devoluciones', 1, 1, 0, 0),
(2, 'inventario', 1, 0, 0, 0),
(2, 'solicitudes', 1, 1, 0, 0)
ON DUPLICATE KEY UPDATE `can_view` = VALUES(`can_view`);

-- Rol 3: Asistente (registra compras/entregas/devoluciones, resto solo consulta)
INSERT INTO `role_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(3, 'dashboard', 1, 0, 0, 0),
(3, 'usuarios', 0, 0, 0, 0),
(3, 'proveedores', 1, 0, 0, 0),
(3, 'articulos', 1, 0, 0, 0),
(3, 'compras', 1, 1, 1, 0),
(3, 'marcas', 1, 0, 0, 0),
(3, 'clientes', 1, 0, 0, 0),
(3, 'kits', 1, 0, 0, 0),
(3, 'entregas', 1, 1, 0, 0),
(3, 'devoluciones', 1, 1, 0, 0),
(3, 'inventario', 1, 0, 0, 0),
(3, 'solicitudes', 1, 1, 0, 0)
ON DUPLICATE KEY UPDATE `can_view` = VALUES(`can_view`);

-- Rol 4: Solicitante (unicamente su propio modulo de solicitudes)
INSERT INTO `role_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(4, 'solicitudes', 1, 1, 0, 0)
ON DUPLICATE KEY UPDATE `can_view` = VALUES(`can_view`);

-- Rol 5: Consultor de Inventario (segun especificacion solicitada)
INSERT INTO `role_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(5, 'dashboard', 1, 0, 0, 0),
(5, 'usuarios', 0, 0, 0, 0),
(5, 'proveedores', 1, 0, 0, 0),
(5, 'articulos', 1, 0, 0, 0),
(5, 'compras', 1, 0, 0, 0),
(5, 'marcas', 1, 1, 0, 0),
(5, 'clientes', 1, 1, 1, 1),
(5, 'kits', 1, 0, 0, 0),
(5, 'entregas', 1, 0, 0, 0),
(5, 'devoluciones', 1, 0, 0, 0),
(5, 'inventario', 1, 0, 0, 0),
(5, 'solicitudes', 0, 0, 0, 0)
ON DUPLICATE KEY UPDATE `can_view` = VALUES(`can_view`);
