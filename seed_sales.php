<?php
// seed_sales.php
$pdo = require __DIR__ . '/config/database.php';

$count = (int) $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();

if ($count < 8) {
    // Récupérer les produits existants
    $products = $pdo->query("SELECT id, name, unit_price, purchase_price, stock_quantity FROM products WHERE store_id = 1")->fetchAll();
    
    if (empty($products)) {
        echo "Aucun produit trouvé pour générer des ventes.\n";
        exit;
    }

    $methods = ['cash', 'mobile_money', 'card', 'cash', 'mobile_money'];
    
    // Générer 12 ventes réparties sur les 7 derniers jours
    for ($i = 0; $i < 12; $i++) {
        $daysAgo = rand(0, 6);
        $hoursAgo = rand(1, 10);
        $createdAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days -$hoursAgo hours"));
        $method = $methods[array_rand($methods)];
        
        // Choisir 1 à 3 produits aléatoires
        $numItems = rand(1, 3);
        $selectedKeys = (array) array_rand($products, min($numItems, count($products)));
        
        $totalAmount = 0.0;
        $totalProfit = 0.0;
        $itemsData = [];
        
        foreach ($selectedKeys as $k) {
            $p = $products[$k];
            $qty = rand(1, 3);
            $uPrice = (float) $p['unit_price'];
            $pPrice = (float) $p['purchase_price'];
            
            $lineTotal = $uPrice * $qty;
            $lineProfit = ($uPrice - $pPrice) * $qty;
            
            $totalAmount += $lineTotal;
            $totalProfit += $lineProfit;
            
            $itemsData[] = [
                'product_id' => $p['id'],
                'quantity' => $qty,
                'unit_price' => $uPrice,
                'purchase_price' => $pPrice
            ];
        }
        
        // Insérer la vente
        $stmt = $pdo->prepare("INSERT INTO sales (store_id, user_id, total_amount, total_profit, payment_method, created_at) VALUES (1, 1, ?, ?, ?, ?)");
        $stmt->execute([$totalAmount, $totalProfit, $method, $createdAt]);
        $saleId = $pdo->lastInsertId();
        
        // Insérer les items
        $stmtItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, purchase_price) VALUES (?, ?, ?, ?, ?)");
        foreach ($itemsData as $it) {
            $stmtItem->execute([$saleId, $it['product_id'], $it['quantity'], $it['unit_price'], $it['purchase_price']]);
        }
    }
    
    echo "12 ventes de démonstration générées avec succès pour enrichir les statistiques !\n";
} else {
    echo "Il y a déjà $count ventes enregistrées.\n";
}
