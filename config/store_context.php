<?php
// config/store_context.php — v2 : username auth + trial check + is_active

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$publicPages = ['login.php', 'register.php'];

// Pages admin indépendantes — ne pas rediriger
if (str_contains($_SERVER['PHP_SELF'] ?? '', '/admin/')) {
    // Pas de redirect ici, les pages admin gèrent elles-mêmes
    return;
}

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

$appName  = "POSSIBILITY TECH";
$logoPath = "assets/images/logo.png";
$waContact = "0901247908"; // Contact WhatsApp pour les abonnements

// Si authentifié
if (isset($_SESSION['current_store_id'])) {
    $currentStoreId = (int) $_SESSION['current_store_id'];
    $currentStore   = $storeModel->getById($currentStoreId);

    if (!$currentStore) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // ── Vérification : compte désactivé par l'admin ──
    if (!$currentStore['is_active']) {
        session_destroy();
        header('Location: login.php?blocked=1');
        exit;
    }

    // ── Vérification : période d'essai expirée ──
    $trialExpired   = $storeModel->isTrialExpired($currentStore);
    $trialDaysLeft  = $storeModel->trialDaysLeft($currentStore);

    // Si expiré, bloquer toutes les pages sauf le dashboard (qui affiche le message de paiement)
    if ($trialExpired && $currentScript !== 'dashboard.php') {
        header('Location: dashboard.php?expired=1');
        exit;
    }

    $currentStoreName  = $currentStore['name'];
    $currentStoreEmail = $currentStore['email'] ?? '';
    $currentUsername   = $currentStore['username'] ?? '';
    $trialEndsAt       = $currentStore['trial_ends_at'] ?? null;

} else {
    $currentStoreId   = 0;
    $currentStore     = null;
    $currentStoreName = "Invité";
    $currentStoreEmail = "";
    $currentUsername  = "";
    $trialExpired     = false;
    $trialDaysLeft    = 15;
    $trialEndsAt      = null;
}

$availableStores = $storeModel->getAll();

// Mise à jour rapide du nom via modal
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action_update_store_name'])) {
    $storeIdToUpdate = (int) ($_POST['store_id'] ?? $currentStoreId);
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
