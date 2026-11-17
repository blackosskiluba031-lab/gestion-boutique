<?php
// init_db_auto.php
// Script d'auto-initialisation idempotent pour déploiement Cloud (Render / TiDB / MySQL)
// Crée automatiquement les tables et le compte administrateur s'ils n'existent pas encore.

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    $pdo = require __DIR__ . '/config/database.php';

    // 1. Table des boutiques
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `stores` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(150) NOT NULL,
            `username` VARCHAR(80) NOT NULL UNIQUE,
            `email` VARCHAR(150) NULL,
            `password` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(50) NULL,
            `address` VARCHAR(255) NULL,
            `logo` VARCHAR(255) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `trial_ends_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_username` (`username`),
            INDEX `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Table des produits
    $pdo->exec("
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
    ");

    // 3. Table des ventes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `sales` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `store_id` INT NOT NULL,
            `user_id` INT NOT NULL DEFAULT 1,
            `total_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            `total_profit` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            `payment_method` VARCHAR(50) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_store_sale` (`store_id`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 4. Table des articles de vente
    $pdo->exec("
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
    ");

    // 5. Table des administrateurs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `admins` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(80) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 6. Créer le compte admin par défaut si aucun administrateur n'existe
    $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    if ($adminCount === 0) {
        $adminHash = password_hash('admin2024', PASSWORD_DEFAULT);
        $stmtAdmin = $pdo->prepare("INSERT INTO admins (username, password, created_at) VALUES ('admin', ?, NOW())");
        $stmtAdmin->execute([$adminHash]);
        echo "[INIT DB] Compte administrateur initialisé (login: admin / admin2024).\n";
    }

    echo "[INIT DB] Base de données et tables opérationnelles (compatible TiDB / MySQL).\n";
} catch (Exception $e) {
    echo "[INIT DB WARNING] " . $e->getMessage() . "\n";
}
