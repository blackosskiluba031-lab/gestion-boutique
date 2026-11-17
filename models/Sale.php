<?php
// models/Sale.php

class Sale
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Enregistre une vente avec ses articles et met à jour les stocks dans une transaction.
     *
     * @param int $store_id
     * @param int $user_id
     * @param array $items Liste d'articles, chaque article devant contenir :
     *                     - product_id (int)
     *                     - quantity (int)
     *                     - unit_price (float)
     *                     - purchase_price (float)
     * @param string $payment_method Ex: 'cash', 'card', 'mobile_money'
     * @return int L'identifiant de la vente créée
     * @throws Exception En cas d'erreur ou d'échec de la transaction
     */
    public function createSale(int $store_id, int $user_id, array $items, string $payment_method): int
    {
        if (empty($items)) {
            throw new InvalidArgumentException("La vente doit contenir au moins un article.");
        }

        $total_amount = 0.0;
        $total_profit = 0.0;

        // Calcul des totaux et du bénéfice total
        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unit_price = (float) $item['unit_price'];
            $purchase_price = (float) $item['purchase_price'];

            $line_total = $unit_price * $quantity;
            $line_profit = ($unit_price - $purchase_price) * $quantity;

            $total_amount += $line_total;
            $total_profit += $line_profit;
        }

        try {
            $this->db->beginTransaction();

            // 1. Insertion de la vente principale
            $stmtSale = $this->db->prepare("
                INSERT INTO sales (
                    store_id,
                    user_id,
                    total_amount,
                    total_profit,
                    payment_method,
                    created_at
                ) VALUES (
                    :store_id,
                    :user_id,
                    :total_amount,
                    :total_profit,
                    :payment_method,
                    NOW()
                )
            ");

            $stmtSale->execute([
                'store_id'       => $store_id,
                'user_id'        => $user_id,
                'total_amount'   => $total_amount,
                'total_profit'   => $total_profit,
                'payment_method' => $payment_method
            ]);

            $sale_id = (int) $this->db->lastInsertId();

            // Préparation des requêtes pour les lignes de vente et la décrémentation des stocks
            $stmtItem = $this->db->prepare("
                INSERT INTO sale_items (
                    sale_id,
                    product_id,
                    quantity,
                    unit_price,
                    purchase_price
                ) VALUES (
                    :sale_id,
                    :product_id,
                    :quantity,
                    :unit_price,
                    :purchase_price
                )
            ");

            $stmtStock = $this->db->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity - :quantity 
                WHERE id = :product_id
            ");

            // 2. Traitement de chaque article
            foreach ($items as $item) {
                $product_id = (int) $item['product_id'];
                $quantity = (int) $item['quantity'];
                $unit_price = (float) $item['unit_price'];
                $purchase_price = (float) $item['purchase_price'];

                // Insertion de la ligne dans sale_items
                $stmtItem->execute([
                    'sale_id'        => $sale_id,
                    'product_id'     => $product_id,
                    'quantity'       => $quantity,
                    'unit_price'     => $unit_price,
                    'purchase_price' => $purchase_price
                ]);

                // Décrémentation du stock
                $stmtStock->execute([
                    'quantity'   => $quantity,
                    'product_id' => $product_id
                ]);
            }

            // 3. Validation de la transaction
            $this->db->commit();

            return $sale_id;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Récupère la liste des ventes filtrées avec le décompte des articles.
     *
     * @param int $store_id
     * @param string|null $dateStart Format YYYY-MM-DD
     * @param string|null $dateEnd Format YYYY-MM-DD
     * @param string|null $paymentMethod
     * @return array
     */
    public function getAllWithFilters(int $store_id, ?string $dateStart = null, ?string $dateEnd = null, ?string $paymentMethod = null): array
    {
        $sql = "
            SELECT 
                s.id,
                s.store_id,
                s.user_id,
                s.total_amount,
                s.total_profit,
                s.payment_method,
                s.created_at,
                COUNT(si.id) AS items_count,
                COALESCE(SUM(si.quantity), 0) AS total_quantity
            FROM sales s
            LEFT JOIN sale_items si ON s.id = si.sale_id
            WHERE s.store_id = :store_id
        ";

        $params = ['store_id' => $store_id];

        if (!empty($dateStart)) {
            $sql .= " AND DATE(s.created_at) >= :date_start";
            $params['date_start'] = $dateStart;
        }

        if (!empty($dateEnd)) {
            $sql .= " AND DATE(s.created_at) <= :date_end";
            $params['date_end'] = $dateEnd;
        }

        if (!empty($paymentMethod) && $paymentMethod !== 'all') {
            $sql .= " AND s.payment_method = :payment_method";
            $params['payment_method'] = $paymentMethod;
        }

        $sql .= " GROUP BY s.id ORDER BY s.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Récupère une vente et l'ensemble de ses lignes d'articles détaillées.
     *
     * @param int $sale_id
     * @return array|null
     */
    public function getSaleDetails(int $sale_id): ?array
    {
        // 1. Informations de la vente
        $stmtSale = $this->db->prepare("SELECT * FROM sales WHERE id = :id LIMIT 1");
        $stmtSale->execute(['id' => $sale_id]);
        $sale = $stmtSale->fetch();

        if (!$sale) {
            return null;
        }

        // 2. Lignes d'articles avec jointure sur les produits
        $stmtItems = $this->db->prepare("
            SELECT 
                si.id AS item_id,
                si.product_id,
                si.quantity,
                si.unit_price,
                si.purchase_price,
                (si.unit_price * si.quantity) AS line_total,
                ((si.unit_price - si.purchase_price) * si.quantity) AS line_profit,
                COALESCE(p.name, 'Produit supprimé') AS product_name,
                p.barcode
            FROM sale_items si
            LEFT JOIN products p ON si.product_id = p.id
            WHERE si.sale_id = :sale_id
            ORDER BY si.id ASC
        ");
        $stmtItems->execute(['sale_id' => $sale_id]);
        $sale['items'] = $stmtItems->fetchAll();

        return $sale;
    }
}
