-- Migracion 24: cantidad de kits e imagen en el simulador

ALTER TABLE `kit_simulations`
  ADD COLUMN `kit_quantity` int(11) NOT NULL DEFAULT 1 AFTER `classification_id`,
  ADD COLUMN `image_path` varchar(255) DEFAULT NULL AFTER `kit_quantity`;
