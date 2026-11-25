-- Migracion 36: tamano fisico exacto (cm) y fuente para las tarjetas
-- card_w_mm / card_h_mm: tamano exacto de cada tarjeta en milimetros (NULL = auto por "tarjetas por fila").
-- font_family: tipografia del nombre.

ALTER TABLE `card_templates`
  ADD COLUMN `card_w_mm` DECIMAL(6,2) DEFAULT NULL AFTER `grid_cols`,
  ADD COLUMN `card_h_mm` DECIMAL(6,2) DEFAULT NULL AFTER `card_w_mm`,
  ADD COLUMN `font_family` VARCHAR(80) NOT NULL DEFAULT 'Arial' AFTER `card_h_mm`;
