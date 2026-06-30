-- =====================================================================
-- Detallia - Migracion: agrega columnas de rol/estado a `users` existente
-- Ejecutar SOLO si la tabla `users` ya existia antes (template Minia)
-- y el CREATE TABLE original de detallia_schema.sql no se aplico.
-- No borra ni modifica los datos actuales de los usuarios.
-- =====================================================================

ALTER TABLE `users`
  ADD COLUMN `full_name` varchar(150) DEFAULT NULL AFTER `username`,
  ADD COLUMN `role_id` int(11) NOT NULL DEFAULT 3 AFTER `token`,
  ADD COLUMN `status` enum('activo','inactivo') NOT NULL DEFAULT 'activo' AFTER `role_id`,
  ADD COLUMN `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_at`;

ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

-- Asigna rol Administrador al usuario existente (henry@gmail.com)
UPDATE `users` SET `role_id` = 1, `full_name` = `username` WHERE `useremail` = 'henry@gmail.com';
