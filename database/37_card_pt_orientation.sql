-- Migracion 37: tamano de fuente en puntos (pt) y orientacion de la hoja A4
ALTER TABLE `card_templates`
  ADD COLUMN `font_pt` DECIMAL(6,2) NOT NULL DEFAULT 24.00 AFTER `font_pct`,
  ADD COLUMN `orientation` VARCHAR(10) NOT NULL DEFAULT 'portrait' AFTER `font_family`;
