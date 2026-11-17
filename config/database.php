<?php
// config/database.php
// Configuration PDO MySQL compatible Local (Laragon) & Cloud (Render / Aiven / Railway / etc.)

$host = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: '127.0.0.1');
$port = getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: '3306');
$dbname = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: (getenv('MYSQLDATABASE') ?: 'gestion_boutique_db'));
$user = getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: (getenv('MYSQLUSER') ?: 'root'));
$password = getenv('DB_PASSWORD') ?: (getenv('DB_PASS') ?: (getenv('MYSQLPASSWORD') ?: ''));
$charset = 'utf8mb4';

// Prise en charge optionnelle d'une URL de base de données complète (ex: mysql://user:pass@host:port/dbname)
$databaseUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
if ($databaseUrl) {
    $dbParts = parse_url($databaseUrl);
    if ($dbParts) {
        $host = $dbParts['host'] ?? $host;
        $port = $dbParts['port'] ?? $port;
        $user = $dbParts['user'] ?? $user;
        $password = $dbParts['pass'] ?? $password;
        if (!empty($dbParts['path'])) {
            $dbname = ltrim($dbParts['path'], '/');
        }
    }
}

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}"
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    throw new PDOException("Erreur de connexion à la base de données ({$host}:{$port}) : " . $e->getMessage(), (int) $e->getCode());
}

return $pdo;
