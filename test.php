<?php
// test.php
// Script de test pour la connexion et l'instanciation des modèles

$isCli = (php_sapi_name() === 'cli');

function outputMessage(string $title, string $status, string $message, bool $isCli): void
{
    if ($isCli) {
        $badge = $status === 'SUCCESS' ? '[OK]' : ($status === 'WARNING' ? '[WARN]' : '[ERROR]');
        echo "{$badge} {$title} : {$message}\n";
    } else {
        $color = $status === 'SUCCESS' ? '#155724' : ($status === 'WARNING' ? '#856404' : '#721c24');
        $bg = $status === 'SUCCESS' ? '#d4edda' : ($status === 'WARNING' ? '#fff3cd' : '#f8d7da');
        $border = $status === 'SUCCESS' ? '#c3e6cb' : ($status === 'WARNING' ? '#ffeeba' : '#f5c6cb');
        echo "<div style='background: {$bg}; color: {$color}; border: 1px solid {$border}; padding: 12px; margin-bottom: 10px; border-radius: 6px; font-family: sans-serif;'>";
        echo "<strong>{$title} :</strong> {$message}";
        echo "</div>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'><title>Test Gestion Boutique</title></head><body style='padding: 24px; max-width: 800px; margin: 0 auto; background: #f9f9fb;'>";
    echo "<h2 style='font-family: sans-serif; color: #333;'>Test de Configuration - Gestion Boutique</h2>";
}

$pdo = null;

// 1. Test de la connexion à la base de données
try {
    $pdo = require __DIR__ . '/config/database.php';

    if ($pdo instanceof PDO) {
        outputMessage(
            "Connexion PDO",
            "SUCCESS",
            "Connexion à la base de données réussie (gestion_boutique_db).",
            $isCli
        );
    } else {
        outputMessage(
            "Connexion PDO",
            "ERROR",
            "Le fichier config/database.php n'a pas retourné une instance de PDO valide.",
            $isCli
        );
    }
} catch (PDOException $e) {
    // Si la base spécifique n'existe pas encore, tentons de vérifier si le serveur MySQL est joignable
    $mysqlAvailable = false;
    try {
        $testPdo = new PDO("mysql:host=127.0.0.1;charset=utf8mb4", "root", "", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $mysqlAvailable = true;
    } catch (Exception $inner) {
        // MySQL non joignable
    }

    if ($mysqlAvailable) {
        outputMessage(
            "Connexion PDO",
            "WARNING",
            "Serveur MySQL joignable, mais la base 'gestion_boutique_db' n'a pas encore été créée ({$e->getMessage()}). Création d'une instance mock/temporaire pour tester l'instanciation des modèles.",
            $isCli
        );
        $pdo = $testPdo;
    } else {
        outputMessage(
            "Connexion PDO",
            "ERROR",
            "Impossible de se connecter au serveur MySQL (127.0.0.1:3306) : " . $e->getMessage(),
            $isCli
        );
    }
}

// 2. Test d'inclusion et d'instanciation du modèle Product
try {
    require_once __DIR__ . '/models/Product.php';

    if ($pdo instanceof PDO) {
        $productModel = new Product($pdo);
        outputMessage(
            "Modèle Product",
            "SUCCESS",
            "Classe Product chargée et instanciée avec succès.",
            $isCli
        );
    } else {
        outputMessage(
            "Modèle Product",
            "WARNING",
            "Classe Product chargée mais non instanciée (pas de connexion PDO active).",
            $isCli
        );
    }
} catch (Throwable $e) {
    outputMessage(
        "Modèle Product",
        "ERROR",
        "Erreur lors de l'instanciation de Product : " . $e->getMessage(),
        $isCli
    );
}

// 3. Test d'inclusion et d'instanciation du modèle Sale
try {
    require_once __DIR__ . '/models/Sale.php';

    if ($pdo instanceof PDO) {
        $saleModel = new Sale($pdo);
        outputMessage(
            "Modèle Sale",
            "SUCCESS",
            "Classe Sale chargée et instanciée avec succès.",
            $isCli
        );
    } else {
        outputMessage(
            "Modèle Sale",
            "WARNING",
            "Classe Sale chargée mais non instanciée (pas de connexion PDO active).",
            $isCli
        );
    }
} catch (Throwable $e) {
    outputMessage(
        "Modèle Sale",
        "ERROR",
        "Erreur lors de l'instanciation de Sale : " . $e->getMessage(),
        $isCli
    );
}

if (!$isCli) {
    echo "</body></html>";
}
