-- Migracion 35: plantillas de tarjetas (impresion de nombres sobre una imagen)
--
-- Guarda la imagen de fondo subida y la posicion/estilo del nombre del cliente
-- (posicion en % para que escale a cualquier tamano). La impresion arma una
-- cuadricula A4 con una tarjeta por cliente segun los filtros elegidos.

CREATE TABLE IF NOT EXISTS `card_templates` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `pos_x` DECIMAL(5,2) NOT NULL DEFAULT 50.00,   -- % desde la izquierda
  `pos_y` DECIMAL(5,2) NOT NULL DEFAULT 50.00,   -- % desde arriba
  `font_pct` DECIMAL(5,2) NOT NULL DEFAULT 6.00, -- tamano de letra como % del ancho de la tarjeta
  `font_color` VARCHAR(20) NOT NULL DEFAULT '#1b5e20',
  `text_align` VARCHAR(10) NOT NULL DEFAULT 'center',
  `grid_cols` INT NOT NULL DEFAULT 2,            -- tarjetas por fila en A4
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_cardtpl_user` (`created_by`),
  CONSTRAINT `fk_cardtpl_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
