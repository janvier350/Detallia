-- Migracion 29: Ejecutivo de cuenta en clientes + confirmacion del transportador
--
-- 1) clients.account_executive: el "Ejecutivo de cuenta". Se toma de
--    pending_clients.contacto_interno emparejando por persona + empresa
--    (la colacion utf8mb4 ignora mayusculas y acentos). Lo que no empareje
--    queda en blanco y se puede llenar a mano desde Rutas.
-- 2) route_deliveries: visto del transportador y su comentario (incidencias).

ALTER TABLE `clients`
  ADD COLUMN `account_executive` VARCHAR(150) DEFAULT NULL AFTER `cargo`;

ALTER TABLE `route_deliveries`
  ADD COLUMN `carrier_checked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `cancel_comment`,
  ADD COLUMN `carrier_comment` TEXT DEFAULT NULL AFTER `carrier_checked`;

-- Relleno inicial del ejecutivo de cuenta desde pending_clients.
UPDATE `clients` c
JOIN (
    SELECT `name`, `contact_name`, MAX(`contacto_interno`) AS ci
    FROM `pending_clients`
    WHERE `contacto_interno` IS NOT NULL AND `contacto_interno` <> ''
    GROUP BY `name`, `contact_name`
) pc
  ON pc.`name`         COLLATE utf8mb4_unicode_ci = c.`name`         COLLATE utf8mb4_unicode_ci
 AND pc.`contact_name` COLLATE utf8mb4_unicode_ci = c.`contact_name` COLLATE utf8mb4_unicode_ci
SET c.`account_executive` = pc.ci
WHERE (c.`account_executive` IS NULL OR c.`account_executive` = '');
