-- Papelera / Restaurar eliminados
-- Archiva una copia completa (JSON) de cada registro eliminado para poder
-- restaurarlo despues. Cubre: Clientes, Articulos, Marcas, Proveedores,
-- Kits y Simulador de kits (incluye sus filas hijas).

CREATE TABLE IF NOT EXISTS `deleted_records` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module` VARCHAR(60) NOT NULL,            -- nombre visible: "Clientes", "Articulos", etc.
  `table_name` VARCHAR(60) NOT NULL,        -- tabla original: clients, articles, ...
  `record_id` INT NOT NULL,                 -- id original del registro
  `label` VARCHAR(255) NULL,               -- texto para mostrar en la lista
  `data` MEDIUMTEXT NOT NULL,               -- JSON {parent:{...}, children:{tabla:[...]}}
  `deleted_by` INT NULL,
  `deleted_by_name` VARCHAR(150) NULL,
  `deleted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `restored` TINYINT(1) NOT NULL DEFAULT 0,
  `restored_at` DATETIME NULL,
  `restored_by_name` VARCHAR(150) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_del_module` (`module`),
  KEY `idx_del_restored` (`restored`),
  KEY `idx_del_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
