-- Migracion 17: modo de enlace de validacion
--   'validacion'  = flujo existente (se importan contactos desde Excel y el CEO los confirma)
--   'recoleccion' = el CEO ingresa los contactos desde cero en un formulario

ALTER TABLE `validation_links`
  ADD COLUMN `mode` enum('validacion','recoleccion') NOT NULL DEFAULT 'validacion' AFTER `label`;
