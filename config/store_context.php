<?php
// config/store_context.php
// Gestion du contexte de la boutique active pour POSSYBILITY TECH

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../models/Store.php';

$dbPdo = require __DIR__ . '/database.php';
$storeModel = new Store($dbPdo);

// Changement de boutique active (via paramètre GET ?switch_store=ID)
if (isset($_GET['switch_store'])) {
    $switchedId = (int)$_GET['switch_store'];
    if ($storeModel->getById($switchedId)) {
        $_SESSION['current_store_id'] = $switchedId;
    }
    // Nettoyer l'URL en redirigeant vers la même page sans switch_store
    $redirectUrl = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: " . $redirectUrl);
    exit;
}

// Mise à jour rapide du nom de la boutique via modal si soumis
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_update_store_name'])) {
    $storeIdToUpdate = (int)($_POST['store_id'] ?? 1);
    $newName = trim($_POST['store_name'] ?? '');
    if (!empty($newName)) {
        $storeModel->update($storeIdToUpdate, $newName);
        $_SESSION['flash_message'] = "Nom de la boutique mis à jour avec succès : \"{$newName}\"";
        $_SESSION['flash_type'] = "success";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Déterminer la boutique courante
$currentStoreId = (int)($_SESSION['current_store_id'] ?? 1);
$currentStore = $storeModel->getById($currentStoreId);

// Si introuvable, prendre la première boutique disponible
if (!$currentStore) {
    $allStores = $storeModel->getAll();
    if (!empty($allStores)) {
        $currentStore = $allStores[0];
        $currentStoreId = (int)$currentStore['id'];
        $_SESSION['current_store_id'] = $currentStoreId;
    } else {
        // Fallback de sécurité
        $currentStore = [
            'id' => 1,
            'name' => 'Boutique Principale',
            'phone' => '',
            'address' => ''
        ];
    }
}

$availableStores = $storeModel->getAll();
$currentStoreName = $currentStore['name'];
$appName = "POSSYBILITY TECH";
