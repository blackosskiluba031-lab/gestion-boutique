<?php
// models/Store.php — v2 : username login + trial + is_active + admin

class Store
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT id, name, username, email, phone, address, logo, is_active, trial_ends_at, created_at FROM stores ORDER BY id DESC");
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, name, username, email, phone, address, logo, is_active, trial_ends_at, created_at FROM stores WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function getByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM stores WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public function getByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM stores WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => strtolower(trim($username))]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Authentifie par username OU email + mot de passe
     */
    public function authenticate(string $identifier, string $password): ?array
    {
        // Try username first, then email
        $store = $this->getByUsername($identifier);
        if (!$store) {
            $store = $this->getByEmail($identifier);
        }
        if (!$store) return null;

        if (!password_verify($password, $store['password'])) return null;

        unset($store['password']);
        return $store;
    }

    /**
     * Vérifie si la période d'essai est expirée
     */
    public function isTrialExpired(array $store): bool
    {
        if (empty($store['trial_ends_at'])) return false;
        return new DateTime() > new DateTime($store['trial_ends_at']);
    }

    /**
     * Retourne les jours restants dans la période d'essai (0 si expiré)
     */
    public function trialDaysLeft(array $store): int
    {
        if (empty($store['trial_ends_at'])) return 0;
        $now = new DateTime();
        $end = new DateTime($store['trial_ends_at']);
        if ($now >= $end) return 0;
        return (int) $now->diff($end)->days;
    }

    /**
     * Crée une nouvelle boutique avec période d'essai de 15 jours
     */
    public function register(string $name, string $username, string $password, ?string $phone = null, ?string $address = null, ?string $email = null): int
    {
        $username = strtolower(trim($username));
        if ($this->getByUsername($username)) {
            throw new InvalidArgumentException("Ce nom d'utilisateur est déjà pris. Choisissez-en un autre.");
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("
            INSERT INTO stores (name, username, email, password, phone, address, is_active, trial_ends_at, created_at)
            VALUES (:name, :username, :email, :password, :phone, :address, 1, DATE_ADD(NOW(), INTERVAL 15 DAY), NOW())
        ");

        $stmt->execute([
            'name'     => trim($name),
            'username' => $username,
            'email'    => $email ? strtolower(trim($email)) : null,
            'password' => $hash,
            'phone'    => $phone ? trim($phone) : null,
            'address'  => $address ? trim($address) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $name, ?string $phone = null, ?string $address = null): bool
    {
        $stmt = $this->db->prepare("UPDATE stores SET name = :name, phone = :phone, address = :address WHERE id = :id");
        return $stmt->execute(['name' => trim($name), 'phone' => $phone, 'address' => $address, 'id' => $id]);
    }

    public function setActive(int $id, bool $active): bool
    {
        $stmt = $this->db->prepare("UPDATE stores SET is_active = :active WHERE id = :id");
        return $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    public function extendTrial(int $id, int $days = 15): bool
    {
        $stmt = $this->db->prepare("UPDATE stores SET trial_ends_at = DATE_ADD(NOW(), INTERVAL :days DAY) WHERE id = :id");
        return $stmt->execute(['days' => $days, 'id' => $id]);
    }

    public function count(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM stores")->fetchColumn();
    }

    public function countActive(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM stores WHERE is_active = 1")->fetchColumn();
    }
}
