<?php
// seed_demo.php
$pdo = require __DIR__ . '/config/database.php';

$count = $pdo->query("SELECT COUNT(*) FROM products WHERE store_id = 1")->fetchColumn();

if ($count < 5) {
    $pdo->exec("DELETE FROM sale_items");
    $pdo->exec("DELETE FROM sales");
    $pdo->exec("DELETE FROM products");
    $products = [
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

    $stmt = $pdo->prepare("
        INSERT INTO products (store_id, category_id, name, barcode, purchase_price, unit_price, stock_quantity, min_stock_alert, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($products as $p) {
        $stmt->execute($p);
    }
    echo "Produits de démonstration insérés avec succès !\n";
} else {
    echo "Produits déjà existants ($count produits).\n";
}
