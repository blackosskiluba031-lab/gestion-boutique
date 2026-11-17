<?php
// models/Store.php

class Store
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère toutes les boutiques enregistrées.
     *
     * @return array
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM stores ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    /**
     * Récupère une boutique par son ID.
     *
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM stores WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $store = $stmt->fetch();
        return $store ?: null;
    }

    /**
     * Met à jour le nom et les coordonnées de la boutique.
     *
     * @param int $id
     * @param string $name
     * @param string|null $phone
     * @param string|null $address
     * @return bool
     */
    public function update(int $id, string $name, ?string $phone = null, ?string $address = null): bool
    {
        $stmt = $this->db->prepare("UPDATE stores SET name = :name, phone = :phone, address = :address WHERE id = :id");
        return $stmt->execute([
            'name'    => $name,
            'phone'   => $phone,
            'address' => $address,
            'id'      => $id
        ]);
    }
}
