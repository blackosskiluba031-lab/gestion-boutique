<?php
// init_db_auto.php
// Script d'initialisation automatique des tables et données pour le déploiement Cloud (Render)

require_once __DIR__ . '/config/database.php';

try {
    $pdo = require __DIR__ . '/config/database.php';

    // 1. Table des boutiques
    $pdo->exec("
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
    ");

    // Vérifier si une boutique existe
    $storeCount = (int)$pdo->query("SELECT COUNT(*) FROM stores")->fetchColumn();
    if ($storeCount === 0) {
        $defaultHash = password_hash('password123', PASSWORD_DEFAULT);
        $stmtStore = $pdo->prepare("INSERT INTO stores (id, name, email, password, phone, address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmtStore->execute([1, 'Boutique Prestige', 'prestige@possibility.tech', $defaultHash, '+225 07 00 00 00 01', 'Plateau, Centre Commercial']);
        $stmtStore->execute([2, 'Boutique Express Sud', 'express@possibility.tech', $defaultHash, '+225 05 00 00 00 02', 'Avenue Commerciale, Zone Sud']);
    }

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
            `user_id` INT NOT NULL,
            `total_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            `total_profit` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            `payment_method` VARCHAR(50) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_store_sale` (`store_id`),
            INDEX `idx_user_sale` (`user_id`)
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

    // 5. Insertion de produits par défaut si la table est vide
    $prodCount = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    if ($prodCount === 0) {
        $defaultProducts = [
            [1, 1, 'Riz Parfumé 5kg', '3012345678901', 3500.00, 4800.00, 45, 10],
            [1, 1, 'Huile Végétale 1L', '3012345678902', 900.00, 1300.00, 30, 8],
            [1, 1, 'Sucre Roux 1kg', '3012345678903', 600.00, 850.00, 50, 15],
            [1, 2, 'Savon Liquide 500ml', '3012345678904', 750.00, 1200.00, 25, 5],
            [1, 2, 'Dentifrice Blancheur', '3012345678905', 500.00, 800.00, 40, 10],
            [1, 3, 'Pack Eau Minérale 6x1.5L', '3012345678906', 1500.00, 2200.00, 20, 5],
            [1, 3, 'Jus d\'Orange Pur 1L', '3012345678907', 800.00, 1350.00, 18, 5],
            [1, 4, 'Café Arabica Moulu 250g', '3012345678908', 1200.00, 1900.00, 35, 8],
            [1, 4, 'Boîte de Thé Vert (25 sachets)', '3012345678909', 450.00, 750.00, 60, 12],
            [1, 1, 'Lait Concentré Sucré 397g', '3012345678910', 650.00, 1000.00, 40, 10],
            [1, 3, 'Boisson Gazeuse 33cl', '3012345678911', 300.00, 500.00, 70, 20],
            [1, 2, 'Lessive Poudre 1kg', '3012345678912', 1100.00, 1650.00, 22, 6]
        ];

        $stmtP = $pdo->prepare("
            INSERT INTO products (store_id, category_id, name, barcode, purchase_price, unit_price, stock_quantity, min_stock_alert, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        foreach ($defaultProducts as $dp) {
            $stmtP->execute($dp);
        }
    }

    echo "[INIT DB] Base de données et tables vérifiées avec succès.\n";
} catch (Exception $e) {
    echo "[INIT DB WARNING] " . $e->getMessage() . "\n";
}
