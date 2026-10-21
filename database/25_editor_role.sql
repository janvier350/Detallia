-- Migracion 25: rol Editor (6)
--
-- El rol Editor puede VER/CREAR/EDITAR en los modulos operativos, pero NO eliminar
-- en Clientes ni Articulos. En el Simulador de kits tiene acceso completo (incluye
-- eliminar), al igual que los demas roles operativos. El acceso a las paginas se
-- controla por rol en el codigo; esta tabla alimenta el modulo de Permisos.
--
-- NOTA: los usuarios ya existen. Despues de correr esto, entra a Usuarios y cambia
-- el rol de L. Rosero y F. Astudillo a "Editor".

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

-- Opcional: asignar el rol Editor directamente por SQL (en vez de hacerlo desde Usuarios).
-- Descomenta las dos lineas siguientes si prefieres aplicarlo aqui:
-- UPDATE `users` SET `role_id` = 6 WHERE `username` = 'lrosero';
-- UPDATE `users` SET `role_id` = 6 WHERE `username` = 'francisco.astudillo';
