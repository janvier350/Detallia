-- Migracion 11: estado de despacho de las solicitudes
ALTER TABLE `requests`
    ADD COLUMN `status` enum('pendiente','despachado') NOT NULL DEFAULT 'pendiente' AFTER `notes`;
