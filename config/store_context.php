<?php
// config/store_context.php
// Gestion du contexte et de l'authentification de la boutique active pour POSSIBILITY TECH

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$publicPages = ['login.php', 'register.php'];

// Redirection vers login.php si la boutique n'est pas authentifiée
if (!in_array($currentScript, $publicPages)) {
    if (!isset($_SESSION['current_store_id']) || empty($_SESSION['current_store_id'])) {
        header('Location: login.php');
        exit;
    }
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../models/Store.php';

$dbPdo = require __DIR__ . '/database.php';
$storeModel = new Store($dbPdo);

$appName = "POSSIBILITY TECH";
$logoPath = "assets/images/logo.png";

// Si authentifié
if (isset($_SESSION['current_store_id'])) {
    $currentStoreId = (int)$_SESSION['current_store_id'];
    $currentStore = $storeModel->getById($currentStoreId);

    if (!$currentStore) {
        // La boutique en session a été supprimée ou est introuvable
        session_destroy();
        header('Location: login.php');
        exit;
    }

    $currentStoreName = $currentStore['name'];
    $currentStoreEmail = $currentStore['email'] ?? '';
} else {
    $currentStoreId = 0;
    $currentStore = null;
    $currentStoreName = "Invité";
    $currentStoreEmail = "";
}

$availableStores = $storeModel->getAll();

// Mise à jour rapide du nom de la boutique via modal si soumis
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_update_store_name'])) {
    $storeIdToUpdate = (int)($_POST['store_id'] ?? $currentStoreId);
    $newName = trim($_POST['store_name'] ?? '');
    if (!empty($newName) && $storeIdToUpdate === $currentStoreId) {
        $storeModel->update($storeIdToUpdate, $newName);
        $_SESSION['store_name'] = $newName;
        $_SESSION['flash_message'] = "Nom de votre boutique mis à jour : \"{$newName}\"";
        $_SESSION['flash_type'] = "success";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}
