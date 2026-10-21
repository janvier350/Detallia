-- Migracion 25: rol Editor (6) y usuarios L. Rosero y F. Astudillo
--
-- El rol Editor puede VER/CREAR/EDITAR en los modulos operativos, pero NO eliminar
-- en Clientes ni Articulos. En el Simulador de kits tiene acceso completo (incluye
-- eliminar), al igual que los demas roles operativos. El acceso a las paginas se
-- controla por rol en el codigo; esta tabla alimenta el modulo de Permisos.

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(6, 'Editor', 'Agrega y edita en los modulos; no puede eliminar clientes ni articulos. Acceso completo al simulador de kits.')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- Permisos del rol Editor (crear/editar si, eliminar solo donde corresponde)
INSERT INTO `role_permissions` (`role_id`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`) VALUES
(6, 'dashboard',    1, 0, 0, 0),
(6, 'usuarios',     0, 0, 0, 0),
(6, 'proveedores',  1, 1, 1, 1),
(6, 'articulos',    1, 1, 1, 0),   -- NO eliminar
(6, 'compras',      1, 1, 1, 0),
(6, 'marcas',       1, 1, 1, 1),
(6, 'clientes',     1, 1, 1, 0),   -- NO eliminar
(6, 'kits',         1, 1, 1, 1),
(6, 'entregas',     1, 1, 1, 0),
(6, 'devoluciones', 1, 1, 1, 0),
(6, 'inventario',   1, 0, 0, 0),
(6, 'solicitudes',  1, 1, 1, 0)
ON DUPLICATE KEY UPDATE
  `can_view` = VALUES(`can_view`), `can_create` = VALUES(`can_create`),
  `can_edit` = VALUES(`can_edit`), `can_delete` = VALUES(`can_delete`);

-- Usuarios Editor. Contrasena temporal: Editor2026*  (cambiarla en el primer ingreso).
-- Ajusta el correo real desde el modulo Usuarios si es necesario.
INSERT INTO `users` (`useremail`, `username`, `full_name`, `password`, `role_id`, `status`) VALUES
('lrosero@buadnet.com.ec', 'lrosero', 'L. Rosero', '$2y$12$4EIgSHUcxFU2auvJyA3hjuDZu2cIhaoHH85x8n1b3FVmwDEUMqhQW', 6, 'activo'),
('fastudillo@buadnet.com.ec', 'fastudillo', 'F. Astudillo', '$2y$12$4EIgSHUcxFU2auvJyA3hjuDZu2cIhaoHH85x8n1b3FVmwDEUMqhQW', 6, 'activo')
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`), `status` = VALUES(`status`);
