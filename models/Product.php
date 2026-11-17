<?php
// models/Product.php

class Product
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère tous les produits d'une boutique.
     *
     * @param int $store_id
     * @return array
     */
    public function getAllByStore(int $store_id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE store_id = :store_id ORDER BY id DESC");
        $stmt->execute(['store_id' => $store_id]);
        return $stmt->fetchAll();
    }

    /**
     * Crée un nouveau produit.
     *
     * @param int $store_id
     * @param int|null $category_id
     * @param string $name
     * @param string|null $barcode
     * @param float $purchase_price
     * @param float $unit_price
     * @param int $stock_quantity
     * @param int $min_stock_alert
     * @return int L'identifiant du produit créé
     */
    public function create(
        int $store_id,
        ?int $category_id,
        string $name,
        ?string $barcode,
        float $purchase_price,
        float $unit_price,
        int $stock_quantity,
        int $min_stock_alert
    ): int {
        $sql = "INSERT INTO products (
                    store_id,
                    category_id,
                    name,
                    barcode,
                    purchase_price,
                    unit_price,
                    stock_quantity,
                    min_stock_alert,
                    created_at
                ) VALUES (
                    :store_id,
                    :category_id,
                    :name,
                    :barcode,
                    :purchase_price,
                    :unit_price,
                    :stock_quantity,
                    :min_stock_alert,
                    NOW()
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'store_id'        => $store_id,
            'category_id'     => $category_id,
            'name'            => $name,
            'barcode'         => $barcode,
            'purchase_price'  => $purchase_price,
            'unit_price'      => $unit_price,
            'stock_quantity'  => $stock_quantity,
            'min_stock_alert' => $min_stock_alert
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour la quantité en stock d'un produit.
     *
     * @param int $product_id
     * @param int $new_quantity
     * @return bool
     */
    public function updateStock(int $product_id, int $new_quantity): bool
    {
        $stmt = $this->db->prepare("UPDATE products SET stock_quantity = :stock_quantity WHERE id = :id");
        return $stmt->execute([
            'stock_quantity' => $new_quantity,
            'id'             => $product_id
        ]);
    }

    /**
     * Récupère un produit par son ID.
     *
     * @param int $product_id
     * @return array|null
     */
    public function getById(int $product_id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $product_id]);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    /**
     * Met à jour les informations d'un produit existant.
     *
     * @param int $product_id
     * @param int|null $category_id
     * @param string $name
     * @param string|null $barcode
     * @param float $purchase_price
     * @param float $unit_price
     * @param int $min_stock_alert
     * @return bool
     */
    public function update(
        int $product_id,
        ?int $category_id,
        string $name,
        ?string $barcode,
        float $purchase_price,
        float $unit_price,
        int $min_stock_alert
    ): bool {
        $sql = "UPDATE products 
                SET category_id = :category_id,
                    name = :name,
                    barcode = :barcode,
                    purchase_price = :purchase_price,
                    unit_price = :unit_price,
                    min_stock_alert = :min_stock_alert
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'category_id'     => $category_id,
            'name'            => $name,
            'barcode'         => $barcode,
            'purchase_price'  => $purchase_price,
            'unit_price'      => $unit_price,
            'min_stock_alert' => $min_stock_alert,
            'id'              => $product_id
        ]);
    }

    /**
     * Approvisionne le stock en ajoutant une quantité à la quantité existante.
     *
     * @param int $product_id
     * @param int $quantityToAdd
     * @return bool
     */
    public function addStock(int $product_id, int $quantityToAdd): bool
    {
        if ($quantityToAdd <= 0) {
            throw new InvalidArgumentException("La quantité à ajouter doit être supérieure à zéro.");
        }

        $stmt = $this->db->prepare("UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :id");
        return $stmt->execute([
            'qty' => $quantityToAdd,
            'id'  => $product_id
        ]);
    }

    /**
     * Supprime un produit (ou empêche la suppression si lié à des ventes).
     *
     * @param int $product_id
     * @return bool
     */
    public function delete(int $product_id): bool
    {
        // Vérifier si le produit est lié à des lignes de vente
        $stmtCheck = $this->db->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id = :id");
        $stmtCheck->execute(['id' => $product_id]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            throw new RuntimeException("Impossible de supprimer ce produit car il est lié à des ventes enregistrées. Vous pouvez plutôt mettre son stock à 0.");
        }

        $stmt = $this->db->prepare("DELETE FROM products WHERE id = :id");
        return $stmt->execute(['id' => $product_id]);
    }
}
