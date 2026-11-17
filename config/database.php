<?php
// config/database.php
// Configuration PDO MySQL compatible Local (Laragon), TiDB Cloud (Serverless) & Render

declare(strict_types=1);

// 1. Détection des variables d'environnement (TiDB Cloud, Render, Local)
$host = getenv('TIDB_HOST') ?: (getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: '127.0.0.1'));
$port = (int)(getenv('TIDB_PORT') ?: (getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: 3306)));
$dbname = getenv('TIDB_DATABASE') ?: (getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: (getenv('MYSQLDATABASE') ?: 'gestion_boutique_db')));
$user = getenv('TIDB_USER') ?: (getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: (getenv('MYSQLUSER') ?: 'root')));
$password = getenv('TIDB_PASSWORD') ?: (getenv('DB_PASSWORD') ?: (getenv('DB_PASS') ?: (getenv('MYSQLPASSWORD') ?: '')));
$charset = 'utf8mb4';

// 2. Prise en charge d'une URL de base de données complète (ex: mysql://user:pass@host:port/dbname)
$databaseUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
if ($databaseUrl) {
    $dbParts = parse_url($databaseUrl);
    if ($dbParts) {
        $host = $dbParts['host'] ?? $host;
        $port = isset($dbParts['port']) ? (int)$dbParts['port'] : $port;
        $user = isset($dbParts['user']) ? urldecode($dbParts['user']) : $user;
        $password = isset($dbParts['pass']) ? urldecode($dbParts['pass']) : $password;
        if (!empty($dbParts['path'])) {
            $dbname = ltrim($dbParts['path'], '/');
        }
    }
}

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}"
];

// 3. Détection et configuration SSL pour TiDB Cloud Serverless
$isTiDB = (
    !empty(getenv('TIDB_HOST')) || 
    str_contains($host, 'tidbcloud.com') || 
    $port === 4000 || 
    getenv('DB_SSL') === 'true'
);

if ($isTiDB) {
    // Chemins courants vers les certificats CA sous Linux / Docker / Windows
    $caPaths = [
        getenv('TIDB_CA_PATH'),
        getenv('MYSQL_ATTR_SSL_CA'),
        '/etc/ssl/certs/ca-certificates.crt', // Debian / Ubuntu / Docker officiel sur Render
        '/etc/pki/tls/certs/ca-bundle.crt',   // CentOS / RHEL
        'C:\laragon\etc\ssl\cacert.pem',
        'C:\laragon\bin\git\mingw64\etc\ssl\cert.pem',
        'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\extras\ssl\cacert.pem'
    ];

    $caFound = null;
    foreach ($caPaths as $path) {
        if (!empty($path) && file_exists($path)) {
            $caFound = $path;
            break;
        }
    }

    if ($caFound) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $caFound;
    } else {
        // Fallback sécurisé : autoriser SSL même si le bundle CA n'est pas explicité
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
    }
}

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    throw new PDOException("Erreur de connexion à la base de données ({$host}:{$port}/{$dbname}) : " . $e->getMessage(), (int) $e->getCode());
}

return $pdo;
