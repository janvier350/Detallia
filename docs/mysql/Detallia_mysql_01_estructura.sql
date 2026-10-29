-- =====================================================================
-- Detallia - Estructura COMPLETA y ACTUAL de la base de datos (solo DDL)
-- Reconstruida a partir de database/detallia_schema.sql + migraciones 01..26
-- Motor InnoDB, charset utf8mb4 / utf8mb4_unicode_ci.
--
-- Uso en MySQL Workbench:
--   1) File > Open SQL Script... y abre este archivo.
--   2) Ejecuta con el primer rayo (Execute, Ctrl+Shift+Enter).
--   3) Database > Reverse Engineer... y elige el esquema `detallia`
--      para generar el diagrama entidad-relacion (DER).
--
-- Requiere MySQL 8.0 o superior (por las columnas GENERATED STORED).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `detallia`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE `detallia`;

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- MODULO 1: ROLES, USUARIOS Y PERMISOS
-- =====================================================================

CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `useremail` VARCHAR(255) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `full_name` VARCHAR(150) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) DEFAULT NULL,
  `role_id` INT NOT NULL DEFAULT 3,
  `status` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_useremail` (`useremail`),
  KEY `fk_users_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `role_id` INT NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `can_view` TINYINT(1) NOT NULL DEFAULT 0,
  `can_create` TINYINT(1) NOT NULL DEFAULT 0,
  `can_edit` TINYINT(1) NOT NULL DEFAULT 0,
  `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_module` (`role_id`,`module`),
  KEY `fk_roleperm_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MODULO 2: PROVEEDORES, CATALOGO DE ARTICULOS Y MARCAS
-- =====================================================================

CREATE TABLE IF NOT EXISTS `providers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `contact_name` VARCHAR(150) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_providers_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `brands` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_brands_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `article_categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_article_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `articles` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `sku` VARCHAR(50) DEFAULT NULL,
  `category_id` INT DEFAULT NULL,
  `brand_id` INT DEFAULT NULL,
  `unit` VARCHAR(30) NOT NULL DEFAULT 'unidad',
  `description` TEXT DEFAULT NULL,
  `image_path` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_articles_sku` (`sku`),
  KEY `fk_articles_category` (`category_id`),
  KEY `fk_articles_brand` (`brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MODULO 3: COMPRAS / FACTURAS A PROVEEDORES
-- =====================================================================

CREATE TABLE IF NOT EXISTS `purchase_invoices` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `provider_id` INT NOT NULL,
  `invoice_number` VARCHAR(50) NOT NULL,
  `purchase_date` DATE NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `attachment_path` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `registered_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_provider_invoice` (`provider_id`,`invoice_number`),
  KEY `fk_invoice_registered_by` (`registered_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_invoice_items` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `invoice_id` INT NOT NULL,
  `article_id` INT NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(12,2) NOT NULL,
  `iva_rate` DECIMAL(5,2) NOT NULL DEFAULT 15.00,
  `subtotal` DECIMAL(12,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  PRIMARY KEY (`id`),
  KEY `fk_item_invoice` (`invoice_id`),
  KEY `fk_item_article` (`article_id`),
  KEY `idx_items_article_price_history` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cotizaciones: precio al que un proveedor ofrece un articulo del catalogo.
CREATE TABLE IF NOT EXISTS `quotations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `article_id` INT NOT NULL,
  `provider_id` INT NOT NULL,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `iva_rate` DECIMAL(5,2) NOT NULL DEFAULT 15.00,
  `quote_date` DATE NOT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_quot_article` (`article_id`),
  KEY `fk_quot_provider` (`provider_id`),
  KEY `fk_quot_user` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MODULO 4: CLASIFICACIONES, TIPOS DE GESTION Y CLIENTES
-- =====================================================================

CREATE TABLE IF NOT EXISTS `management_types` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_management_types_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `client_classifications` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client_classifications_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clients` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `brand_id` INT DEFAULT NULL,
  `classification_id` INT DEFAULT NULL,
  `contact_name` VARCHAR(150) DEFAULT NULL,
  `genero` VARCHAR(20) DEFAULT NULL,
  `cargo` VARCHAR(120) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `ciudad` VARCHAR(100) DEFAULT NULL,
  `zona` VARCHAR(100) DEFAULT NULL,
  `provincia` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client_name` (`name`),
  KEY `fk_clients_brand` (`brand_id`),
  KEY `fk_clients_classification` (`classification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MODULO 5: KITS Y SUS ARTICULOS
-- =====================================================================

CREATE TABLE IF NOT EXISTS `kits` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `brand_id` INT DEFAULT NULL,
  `management_type_id` INT DEFAULT NULL,
  `classification_id` INT DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `image_path` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_kits_brand` (`brand_id`),
  KEY `fk_kits_management_type` (`management_type_id`),
  KEY `fk_kits_classification` (`classification_id`),
  KEY `fk_kits_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kit_items` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `kit_id` INT NOT NULL,
  `article_id` INT NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kit_article` (`kit_id`,`article_id`),
  KEY `fk_kititems_article` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MODULO 6: ENTREGAS, DEVOLUCIONES E INVENTARIO
-- =====================================================================

CREATE TABLE IF NOT EXISTS `kit_deliveries` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `kit_id` INT NOT NULL,
  `client_id` INT NOT NULL,
  `management_type_id` INT DEFAULT NULL,
  `delivery_date` DATE NOT NULL,
  `delivered_by` INT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_delivery_kit` (`kit_id`),
  KEY `fk_delivery_client` (`client_id`),
  KEY `fk_delivery_management_type` (`management_type_id`),
  KEY `fk_delivery_user` (`delivered_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `article_id` INT NOT NULL,
  `movement_type` ENUM('compra','entrega','ajuste','devolucion') NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL COMMENT 'Positivo = entrada, negativo = salida',
  `reference_type` VARCHAR(30) DEFAULT NULL,
  `reference_id` INT DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_stockmov_article` (`article_id`),
  KEY `idx_stockmov_reference` (`reference_type`,`reference_id`),
  KEY `fk_stockmov_user` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `returns` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `delivery_id` INT NOT NULL,
  `client_id` INT NOT NULL,
  `return_date` DATE NOT NULL,
  `reason` TEXT NOT NULL,
  `registered_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_returns_delivery` (`delivery_id`),
  KEY `fk_returns_client` (`client_id`),
  KEY `fk_returns_user` (`registered_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `return_items` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `return_id` INT NOT NULL,
  `article_id` INT NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_returnitems_return` (`return_id`),
  KEY `fk_returnitems_article` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MODULO 7: SOLICITUDES INTERNAS
-- =====================================================================

CREATE TABLE IF NOT EXISTS `requests` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `request_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `requested_by` INT NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `status` ENUM('pendiente','despachado') NOT NULL DEFAULT 'pendiente',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_requests_user` (`requested_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `request_items` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `request_id` INT NOT NULL,
  `item_type` ENUM('articulo','kit') NOT NULL,
  `article_id` INT DEFAULT NULL,
  `kit_id` INT DEFAULT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`id`),
  KEY `fk_requestitems_request` (`request_id`),
  KEY `fk_requestitems_article` (`article_id`),
  KEY `fk_requestitems_kit` (`kit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MODULO 8: VALIDACION EXTERNA DE CONTACTOS (pre-clientes)
-- =====================================================================

CREATE TABLE IF NOT EXISTS `validation_links` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `token` VARCHAR(64) NOT NULL,
  `label` VARCHAR(150) NOT NULL,
  `mode` ENUM('validacion','recoleccion') NOT NULL DEFAULT 'validacion',
  `created_by` INT DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `finished_at` DATETIME DEFAULT NULL,
  `finished_by` VARCHAR(150) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_validation_token` (`token`),
  KEY `fk_validationlinks_user` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pending_clients` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `link_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `contact_name` VARCHAR(150) DEFAULT NULL,
  `oficina` VARCHAR(100) DEFAULT NULL,
  `zona` VARCHAR(100) DEFAULT NULL,
  `contacto_interno` VARCHAR(150) DEFAULT NULL,
  `ruc_ci` VARCHAR(20) DEFAULT NULL,
  `meses_fact` VARCHAR(20) DEFAULT NULL,
  `detalle_meses` TEXT DEFAULT NULL,
  `estatus_excel` VARCHAR(50) DEFAULT NULL,
  `alerta` VARCHAR(150) DEFAULT NULL,
  `ciudad` VARCHAR(100) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `brand_id` INT DEFAULT NULL,
  `classification_id` INT DEFAULT NULL,
  `status` ENUM('pendiente','confirmado','rechazado') NOT NULL DEFAULT 'pendiente',
  `validated_by_email` VARCHAR(150) DEFAULT NULL,
  `validated_at` DATETIME DEFAULT NULL,
  `imported` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pendingclients_link` (`link_id`),
  KEY `fk_pendingclients_brand` (`brand_id`),
  KEY `fk_pendingclients_classification` (`classification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `validation_otp_codes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `link_id` INT NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `code` VARCHAR(6) NOT NULL,
  `session_token` VARCHAR(64) DEFAULT NULL,
  `verified` TINYINT(1) NOT NULL DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_otp_link` (`link_id`),
  KEY `idx_otp_session` (`session_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `validation_link_sends` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `link_id` INT NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `sent_by` INT DEFAULT NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 1,
  `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_linksends_link` (`link_id`),
  KEY `fk_linksends_user` (`sent_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MODULO 9: SIMULADOR / COSTEO DE KITS
-- =====================================================================

CREATE TABLE IF NOT EXISTS `kit_simulations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `sim_date` DATE NOT NULL,
  `classification_id` INT DEFAULT NULL,
  `kit_quantity` INT NOT NULL DEFAULT 1,
  `image_path` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_kitsim_classification` (`classification_id`),
  KEY `fk_kitsim_user` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kit_simulation_items` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `simulation_id` INT NOT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `provider_id` INT DEFAULT NULL,
  `provider_name` VARCHAR(150) DEFAULT NULL,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `iva_rate` DECIMAL(5,2) NOT NULL DEFAULT 15.00,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_kitsimitem_sim` (`simulation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MODULO 10: BITACORA / AUDITORIA Y PAPELERA
-- =====================================================================

CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT DEFAULT NULL,
  `username` VARCHAR(100) DEFAULT NULL,
  `role_name` VARCHAR(50) DEFAULT NULL,
  `module` VARCHAR(50) NOT NULL,
  `reference` VARCHAR(200) DEFAULT NULL,
  `action` VARCHAR(30) NOT NULL,
  `detail` TEXT DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `device` VARCHAR(30) DEFAULT NULL,
  `os` VARCHAR(50) DEFAULT NULL,
  `browser` VARCHAR(50) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_user` (`user_id`),
  KEY `idx_log_module` (`module`),
  KEY `idx_log_action` (`action`),
  KEY `idx_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Papelera: copia (JSON) de cada registro eliminado para poder restaurarlo.
CREATE TABLE IF NOT EXISTS `deleted_records` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module` VARCHAR(60) NOT NULL,
  `table_name` VARCHAR(60) NOT NULL,
  `record_id` INT NOT NULL,
  `label` VARCHAR(255) DEFAULT NULL,
  `data` MEDIUMTEXT NOT NULL,
  `deleted_by` INT DEFAULT NULL,
  `deleted_by_name` VARCHAR(150) DEFAULT NULL,
  `deleted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `restored` TINYINT(1) NOT NULL DEFAULT 0,
  `restored_at` DATETIME DEFAULT NULL,
  `restored_by_name` VARCHAR(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_del_module` (`module`),
  KEY `idx_del_restored` (`restored`),
  KEY `idx_del_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- LLAVES FORANEAS (declaradas en el proyecto)
-- =====================================================================

ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_roleperm_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

ALTER TABLE `providers`
  ADD CONSTRAINT `fk_providers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `articles`
  ADD CONSTRAINT `fk_articles_category` FOREIGN KEY (`category_id`) REFERENCES `article_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_articles_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL;

ALTER TABLE `purchase_invoices`
  ADD CONSTRAINT `fk_invoice_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`),
  ADD CONSTRAINT `fk_invoice_registered_by` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `purchase_invoice_items`
  ADD CONSTRAINT `fk_item_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_item_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`);

ALTER TABLE `quotations`
  ADD CONSTRAINT `fk_quot_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quot_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quot_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `clients`
  ADD CONSTRAINT `fk_clients_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_clients_classification` FOREIGN KEY (`classification_id`) REFERENCES `client_classifications` (`id`);

ALTER TABLE `kits`
  ADD CONSTRAINT `fk_kits_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_kits_management_type` FOREIGN KEY (`management_type_id`) REFERENCES `management_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_kits_classification` FOREIGN KEY (`classification_id`) REFERENCES `client_classifications` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_kits_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `kit_items`
  ADD CONSTRAINT `fk_kititems_kit` FOREIGN KEY (`kit_id`) REFERENCES `kits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_kititems_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`);

ALTER TABLE `kit_deliveries`
  ADD CONSTRAINT `fk_delivery_kit` FOREIGN KEY (`kit_id`) REFERENCES `kits` (`id`),
  ADD CONSTRAINT `fk_delivery_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `fk_delivery_management_type` FOREIGN KEY (`management_type_id`) REFERENCES `management_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_delivery_user` FOREIGN KEY (`delivered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stockmov_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `fk_stockmov_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `returns`
  ADD CONSTRAINT `fk_returns_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `kit_deliveries` (`id`),
  ADD CONSTRAINT `fk_returns_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `fk_returns_user` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `return_items`
  ADD CONSTRAINT `fk_returnitems_return` FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_returnitems_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`);

ALTER TABLE `requests`
  ADD CONSTRAINT `fk_requests_user` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`);

ALTER TABLE `request_items`
  ADD CONSTRAINT `fk_requestitems_request` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_requestitems_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`),
  ADD CONSTRAINT `fk_requestitems_kit` FOREIGN KEY (`kit_id`) REFERENCES `kits` (`id`);

ALTER TABLE `validation_links`
  ADD CONSTRAINT `fk_validationlinks_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `pending_clients`
  ADD CONSTRAINT `fk_pendingclients_link` FOREIGN KEY (`link_id`) REFERENCES `validation_links` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pendingclients_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pendingclients_classification` FOREIGN KEY (`classification_id`) REFERENCES `client_classifications` (`id`) ON DELETE SET NULL;

ALTER TABLE `validation_otp_codes`
  ADD CONSTRAINT `fk_otp_link` FOREIGN KEY (`link_id`) REFERENCES `validation_links` (`id`) ON DELETE CASCADE;

ALTER TABLE `validation_link_sends`
  ADD CONSTRAINT `fk_linksends_link` FOREIGN KEY (`link_id`) REFERENCES `validation_links` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_linksends_user` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `kit_simulations`
  ADD CONSTRAINT `fk_kitsim_classification` FOREIGN KEY (`classification_id`) REFERENCES `client_classifications` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_kitsim_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `kit_simulation_items`
  ADD CONSTRAINT `fk_kitsimitem_sim` FOREIGN KEY (`simulation_id`) REFERENCES `kit_simulations` (`id`) ON DELETE CASCADE;

ALTER TABLE `activity_log`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- NOTAS
-- =====================================================================
-- * Todas las tablas tienen llave primaria propia (`id`).
-- * `purchase_invoice_items.subtotal` es una columna generada (STORED):
--   requiere MySQL 5.7+ / 8.0. El total con IVA se calcula en la aplicacion.
-- * `deleted_records` (Papelera) NO declara FK hacia `users` a proposito:
--   conserva el nombre de quien elimino aunque el usuario ya no exista.
--   La relacion logica deleted_by -> users se incluye en el script 02.
-- * `stock_movements.reference_id` es una referencia polimorfica
--   (segun `reference_type` apunta a purchase_invoices / kit_deliveries /
--   returns), por lo que no se declara como FK.
-- * `kit_simulation_items.provider_id` guarda el proveedor elegido al costear;
--   es opcional y no se declara como FK en el proyecto (relacion logica en 02).
-- =====================================================================
