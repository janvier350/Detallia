-- =====================================================================
-- Detallia - Esquema de base de datos (MySQL / MariaDB)
-- Gestion de compras de merchandising, proveedores, kits por marca
-- y entregas a clientes, con control de roles (Administrador, Jefe, Asistente)
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4;
START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1. ROLES Y USUARIOS
-- ---------------------------------------------------------------------

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Administrador', 'Acceso total a todos los modulos del sistema'),
(2, 'Jefe', 'Gestiona compras, proveedores, kits y clientes'),
(3, 'Asistente', 'Registra compras y entregas, acceso limitado');

-- Tabla `users` extendida (compatible con la tabla original del template Minia)
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `useremail` varchar(255) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `token` varchar(255) DEFAULT NULL,
  `role_id` int(11) NOT NULL DEFAULT 3,
  `status` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `useremail` (`useremail`),
  KEY `fk_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`id`, `useremail`, `username`, `full_name`, `password`, `token`, `role_id`, `status`, `created_at`) VALUES
(1, 'henry@gmail.com', 'Henry', 'Henry', '$2y$10$vC41AOMLc.nfBlZFOwukkuN/44tpQIlIjnGdRMMOVdlzOTf5fT5zq', NULL, 1, 'activo', '2020-09-24 17:53:37');

-- ---------------------------------------------------------------------
-- 2. PROVEEDORES
-- ---------------------------------------------------------------------

CREATE TABLE `providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_providers_created_by` (`created_by`),
  CONSTRAINT `fk_providers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. ARTICULOS (catalogo de articulos de merchandising)
-- ---------------------------------------------------------------------

CREATE TABLE `article_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `article_categories` (`name`) VALUES
('Papeleria'), ('Tecnologia'), ('Promocional'), ('Navidad'), ('Otros');

CREATE TABLE `articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'unidad',
  `description` text DEFAULT NULL,
  `status` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  KEY `fk_articles_category` (`category_id`),
  CONSTRAINT `fk_articles_category` FOREIGN KEY (`category_id`) REFERENCES `article_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4. COMPRAS / FACTURAS A PROVEEDORES
--    (cabecera de factura + detalle con precio unitario al momento de
--     la compra, para poder comparar precios entre periodos)
-- ---------------------------------------------------------------------

CREATE TABLE `purchase_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `purchase_date` date NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `attachment_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `registered_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_provider_invoice` (`provider_id`,`invoice_number`),
  KEY `fk_invoice_registered_by` (`registered_by`),
  CONSTRAINT `fk_invoice_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`),
  CONSTRAINT `fk_invoice_registered_by` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `purchase_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  PRIMARY KEY (`id`),
  KEY `fk_item_invoice` (`invoice_id`),
  KEY `fk_item_article` (`article_id`),
  CONSTRAINT `fk_item_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indice para comparativos de precio por articulo a traves del tiempo
CREATE INDEX `idx_items_article_price_history` ON `purchase_invoice_items` (`article_id`);

-- ---------------------------------------------------------------------
-- 5. MARCAS, TIPOS DE GESTION Y CLASIFICACION DE CLIENTE
-- ---------------------------------------------------------------------

-- Marcas/empresas de los clientes (ej: Overclocking)
CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tipo de gestion/ocasion (cliente nuevo, visita networking, pasante, regalo navidad, etc.)
CREATE TABLE `management_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `management_types` (`name`, `description`) VALUES
('Cliente nuevo', 'Bienvenida a un cliente que inicia relacion comercial'),
('Visita networking', 'Entrega en eventos o visitas de networking'),
('Pasante', 'Obsequio para pasantes'),
('Regalo navidad', 'Caja de regalo de fin de ano'),
('Renovacion de contrato', 'Entrega por renovacion o fidelizacion'),
('Otro', 'Otra ocasion no listada');

-- Clasificacion del cliente (VIP, frecuente, normal)
CREATE TABLE `client_classifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `client_classifications` (`name`, `description`) VALUES
('VIP', 'Cliente de alto valor / prioridad maxima'),
('Frecuente', 'Cliente con relacion comercial recurrente'),
('Normal', 'Cliente estandar');

-- ---------------------------------------------------------------------
-- 6. CLIENTES
-- ---------------------------------------------------------------------

CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `classification_id` int(11) NOT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_clients_brand` (`brand_id`),
  KEY `fk_clients_classification` (`classification_id`),
  CONSTRAINT `fk_clients_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_clients_classification` FOREIGN KEY (`classification_id`) REFERENCES `client_classifications` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7. KITS (plantillas de kit por marca / tipo de gestion / clasificacion)
-- ---------------------------------------------------------------------

CREATE TABLE `kits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `management_type_id` int(11) DEFAULT NULL,
  `classification_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_kits_brand` (`brand_id`),
  KEY `fk_kits_management_type` (`management_type_id`),
  KEY `fk_kits_classification` (`classification_id`),
  KEY `fk_kits_created_by` (`created_by`),
  CONSTRAINT `fk_kits_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kits_management_type` FOREIGN KEY (`management_type_id`) REFERENCES `management_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kits_classification` FOREIGN KEY (`classification_id`) REFERENCES `client_classifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_kits_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `kit_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kit_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kit_article` (`kit_id`,`article_id`),
  KEY `fk_kititems_article` (`article_id`),
  CONSTRAINT `fk_kititems_kit` FOREIGN KEY (`kit_id`) REFERENCES `kits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kititems_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 8. ENTREGAS DE KITS A CLIENTES
-- ---------------------------------------------------------------------

CREATE TABLE `kit_deliveries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kit_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `management_type_id` int(11) DEFAULT NULL,
  `delivery_date` date NOT NULL,
  `delivered_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_delivery_kit` (`kit_id`),
  KEY `fk_delivery_client` (`client_id`),
  KEY `fk_delivery_management_type` (`management_type_id`),
  KEY `fk_delivery_user` (`delivered_by`),
  CONSTRAINT `fk_delivery_kit` FOREIGN KEY (`kit_id`) REFERENCES `kits` (`id`),
  CONSTRAINT `fk_delivery_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_delivery_management_type` FOREIGN KEY (`management_type_id`) REFERENCES `management_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_delivery_user` FOREIGN KEY (`delivered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

-- =====================================================================
-- Notas de uso de roles:
--   Administrador (role_id = 1): acceso total (CRUD en todos los modulos,
--     gestion de usuarios y roles).
--   Jefe (role_id = 2): gestiona proveedores, articulos, compras, kits,
--     marcas y clientes; sin gestion de usuarios.
--   Asistente (role_id = 3): registra compras y entregas de kits;
--     solo lectura sobre catalogos (articulos, proveedores, kits).
-- La logica de permisos por rol se aplica en la capa de aplicacion (PHP)
-- usando `users.role_id`.
-- =====================================================================
