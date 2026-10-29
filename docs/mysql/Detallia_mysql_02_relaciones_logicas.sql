-- =====================================================================
-- Detallia - Relaciones LOGICAS (opcional)
-- =====================================================================
-- Estas relaciones existen por nombre y uso en la aplicacion, pero el
-- proyecto NO las declara como llaves foraneas en la base de datos.
-- Ejecutar este script despues del 01 es OPCIONAL: sirve para que el DER
-- de MySQL Workbench muestre tambien estas conexiones.
--
-- Cada relacion va en su propio ALTER TABLE para que puedas OMITIR
-- cualquiera (por ejemplo, si tus datos actuales no cumplen la integridad).
--
-- IMPORTANTE: si una columna tiene valores que no existen en la tabla
-- referenciada, el ALTER fallara. En ese caso, limpia los datos o
-- simplemente no agregues esa relacion.
-- =====================================================================

USE `detallia`;

-- 1) Papelera: quien elimino el registro (se deja sin FK en produccion
--    para conservar el historial aunque se borre el usuario).
ALTER TABLE `deleted_records`
  ADD CONSTRAINT `lfk_deleted_by_user`
  FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 2) Simulador de kits: proveedor elegido al costear un producto.
--    Es opcional en la app (puede guardarse solo el nombre del proveedor).
ALTER TABLE `kit_simulation_items`
  ADD CONSTRAINT `lfk_kitsimitem_provider`
  FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE SET NULL;

-- =====================================================================
-- NO declarables como una sola FK (se documentan, no se crean):
--
-- * stock_movements.reference_id -> purchase_invoices.id / kit_deliveries.id /
--   returns.id  (referencia POLIMORFICA; el destino depende de
--   stock_movements.reference_type = 'compra' | 'entrega' | 'devolucion').
--   No se puede expresar con una unica llave foranea.
--
-- * pending_clients -> clients : cuando un contacto pendiente se "importa",
--   la aplicacion crea una fila en `clients`, pero no se guarda un id de
--   enlace entre ambas tablas, asi que no hay relacion declarable.
-- =====================================================================
