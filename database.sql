-- database.sql
-- Structure de la base de données pour "gestion-boutique"

CREATE DATABASE IF NOT EXISTS `gestion_boutique_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gestion_boutique_db`;

-- Table des boutiques / comptes
CREATE TABLE IF NOT EXISTS `stores` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) NULL,
    `address` VARCHAR(255) NULL,
    `logo` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion de deux boutiques de démonstration (mot de passe par défaut: password123)
INSERT IGNORE INTO `stores` (`id`, `name`, `email`, `password`, `phone`, `address`, `created_at`) 
VALUES 
(1, 'Boutique Prestige', 'prestige@possibility.tech', '$2y$10$w85o38F1oMhH362l1.B1yOi4J8aV4mF/W5n4xWkQ147qJ2q/8lG2S', '+225 07 00 00 00 01', 'Plateau, Centre Commercial', NOW()),
(2, 'Boutique Express Sud', 'express@possibility.tech', '$2y$10$w85o38F1oMhH362l1.B1yOi4J8aV4mF/W5n4xWkQ147qJ2q/8lG2S', '+225 05 00 00 00 02', 'Avenue Commerciale, Zone Sud', NOW());

-- Table des produits
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `category_id` INT NULL,
    `name` VARCHAR(255) NOT NULL,
    `barcode` VARCHAR(100) NULL,
    `purchase_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `unit_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `min_stock_alert` INT NOT NULL DEFAULT 5,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_store` (`store_id`),
    INDEX `idx_barcode` (`barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des ventes principales
CREATE TABLE IF NOT EXISTS `sales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `total_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_profit` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `payment_method` VARCHAR(50) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_store_sale` (`store_id`),
    INDEX `idx_user_sale` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des détails des ventes (lignes d'articles)
CREATE TABLE IF NOT EXISTS `sale_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10, 2) NOT NULL,
    `purchase_price` DECIMAL(10, 2) NOT NULL,
    INDEX `idx_sale` (`sale_id`),
    INDEX `idx_product` (`product_id`),
    CONSTRAINT `fk_sale_items_sales` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
