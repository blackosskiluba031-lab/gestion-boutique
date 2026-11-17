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
        $stmt = $this->db->query("SELECT id, name, email, phone, address, logo, created_at FROM stores ORDER BY id ASC");
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
        $stmt = $this->db->prepare("SELECT id, name, email, phone, address, logo, created_at FROM stores WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $store = $stmt->fetch();
        return $store ?: null;
    }

    /**
     * Récupère une boutique par son email.
     *
     * @param string $email
     * @return array|null
     */
    public function getByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM stores WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => strtolower(trim($email))]);
        $store = $stmt->fetch();
        return $store ?: null;
    }

    /**
     * Authentifie une boutique par son email et son mot de passe.
     *
     * @param string $email
     * @param string $password
     * @return array|null Les données de la boutique si succès, null sinon
     */
    public function authenticate(string $email, string $password): ?array
    {
        $store = $this->getByEmail($email);
        if (!$store) {
            return null;
        }

        if (password_verify($password, $store['password'])) {
            unset($store['password']); // Ne jamais stocker le hash dans la session
            return $store;
        }

        return null;
    }

    /**
     * Crée une nouvelle boutique (inscription).
     *
     * @param string $name
     * @param string $email
     * @param string $password
     * @param string|null $phone
     * @param string|null $address
     * @return int L'identifiant de la nouvelle boutique
     */
    public function register(string $name, string $email, string $password, ?string $phone = null, ?string $address = null): int
    {
        $email = strtolower(trim($email));
        if ($this->getByEmail($email)) {
            throw new InvalidArgumentException("Cet email est déjà utilisé par une autre boutique.");
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("
            INSERT INTO stores (name, email, password, phone, address, created_at)
            VALUES (:name, :email, :password, :phone, :address, NOW())
        ");

        $stmt->execute([
            'name'     => trim($name),
            'email'    => $email,
            'password' => $hash,
            'phone'    => $phone ? trim($phone) : null,
            'address'  => $address ? trim($address) : null
        ]);

        return (int) $this->db->lastInsertId();
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
            'name'    => trim($name),
            'phone'   => $phone,
            'address' => $address,
            'id'      => $id
        ]);
    }
}
