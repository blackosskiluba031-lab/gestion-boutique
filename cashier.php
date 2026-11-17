<?php
// cashier.php - Interface de Caisse et Vente Rapide

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/store_context.php';
require_once __DIR__ . '/models/Product.php';
require_once __DIR__ . '/models/Sale.php';

$pdo = require __DIR__ . '/config/database.php';
$productModel = new Product($pdo);
$saleModel = new Sale($pdo);

$storeId = $currentStoreId; // Boutique active
$userId = 1;  // Utilisateur caissier connecté (par défaut 1)

// --- GESTION DES REQUÊTES AJAX / API ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    // 1. Récupération des produits disponibles à jour
    if ($_GET['action'] === 'get_products') {
        try {
            $products = $productModel->getAllByStore($storeId);
            echo json_encode(['success' => true, 'products' => $products]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // 2. Traitement de la vente rapide (Checkout)
    if ($_GET['action'] === 'checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                throw new InvalidArgumentException("Données de requête invalides.");
            }

            $paymentMethod = trim($input['payment_method'] ?? 'cash');
            $cartItems = $input['items'] ?? [];

            if (empty($cartItems)) {
                throw new InvalidArgumentException("Le panier est vide.");
            }

            // Validation et formatage des articles pour le modèle Sale
            $saleItems = [];
            foreach ($cartItems as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $purchasePrice = (float) ($item['purchase_price'] ?? 0);

                if ($productId <= 0 || $quantity <= 0) {
                    throw new InvalidArgumentException("Article invalide dans le panier.");
                }

                // Vérification du stock actuel en base avant validation
                $stmtCheck = $pdo->prepare("SELECT stock_quantity, name FROM products WHERE id = ?");
                $stmtCheck->execute([$productId]);
                $productInDb = $stmtCheck->fetch();

                if (!$productInDb) {
                    throw new RuntimeException("Produit introuvable (ID: {$productId}).");
                }

                if ($productInDb['stock_quantity'] < $quantity) {
                    throw new RuntimeException("Stock insuffisant pour \"{$productInDb['name']}\" (Restant : {$productInDb['stock_quantity']}).");
                }

                $saleItems[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'purchase_price' => $purchasePrice
                ];
            }

            // Exécution de la transaction via le modèle Sale
            $saleId = $saleModel->createSale($storeId, $userId, $saleItems, $paymentMethod);

            // Calcul du total et du bénéfice pour le reçu
            $totalAmount = 0.0;
            $totalProfit = 0.0;
            foreach ($saleItems as $sItem) {
                $totalAmount += $sItem['unit_price'] * $sItem['quantity'];
                $totalProfit += ($sItem['unit_price'] - $sItem['purchase_price']) * $sItem['quantity'];
            }

            // Récupération de la liste mise à jour des produits pour synchronisation immédiate
            $updatedProducts = $productModel->getAllByStore($storeId);

            echo json_encode([
                'success' => true,
                'message' => 'Vente enregistrée avec succès !',
                'sale_id' => $saleId,
                'total_amount' => $totalAmount,
                'total_profit' => $totalProfit,
                'payment_method' => $paymentMethod,
                'items_count' => count($saleItems),
                'products' => $updatedProducts
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// Chargement initial des produits pour affichage
$products = $productModel->getAllByStore($storeId);
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-100">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caisse & Vente Rapide | <?= htmlspecialchars($appName) ?> • <?= htmlspecialchars($currentStoreName) ?>
    </title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Optimisation pour impression du ticket de caisse */
        @media print {
            body * {
                visibility: hidden;
            }

            #receiptModalContent,
            #receiptModalContent * {
                visibility: visible;
            }

            #receiptModalContent {
                position: absolute;
                left: 0;
                top: 0;
                width: 80mm;
                padding: 10px;
                background: white;
                color: black;
                font-family: 'Courier New', Courier, monospace;
            }

            .no-print {
                display: none !important;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background-color: #94a3b8;
        }
    </style>
</head>

<body class="h-full flex flex-col font-sans text-slate-800 antialiased overflow-hidden">

    <!-- En-tête Topbar -->
    <header class="bg-white border-b border-slate-200 shadow-sm z-20 flex-shrink-0">
        <div class="px-6 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="dashboard.php" class="flex items-center gap-3 group">
                    <div
                        class="w-10 h-10 rounded-xl bg-white p-1 border border-slate-200 shadow-xs flex items-center justify-center group-hover:scale-105 transition-transform overflow-hidden">
                        <img src="assets/images/logo.png" alt="POSSIBILITY TECH" class="h-8 w-auto object-contain">
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:gap-2">
                        <span
                            class="text-sm sm:text-base font-black tracking-tight text-slate-900 group-hover:text-brand-600 transition-colors uppercase">
                            <?= htmlspecialchars($appName) ?>
                        </span>
                        <div class="flex items-center gap-1.5">
                            <span class="hidden sm:inline text-slate-300 font-light">•</span>
                            <!-- Nom de la Boutique -->
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-xs font-bold bg-brand-50 text-brand-700 border border-brand-200 shadow-sm"
                                title="Boutique actuellement active">
                                <i class="fa-solid fa-shop text-[10px] text-brand-500"></i>
                                <span><?= htmlspecialchars($currentStoreName) ?></span>
                            </span>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Liens & Navigation -->
            <div class="flex items-center space-x-2">
                <a href="dashboard.php"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors flex items-center gap-1.5"
                    title="Accéder au Tableau de Bord">
                    <i class="fa-solid fa-chart-line text-brand-600"></i>
                    <span class="hidden sm:inline">Dashboard</span>
                </a>
                <a href="products.php"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors flex items-center gap-1.5"
                    title="Gérer les Stocks et Produits">
                    <i class="fa-solid fa-boxes-stacked text-brand-600"></i>
                    <span class="hidden sm:inline">Stocks</span>
                </a>
                <a href="sales_history.php"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors flex items-center gap-1.5"
                    title="Consulter l'Historique des Ventes">
                    <i class="fa-solid fa-clock-rotate-left text-brand-600"></i>
                    <span class="hidden sm:inline">Historique</span>
                </a>

                <?php if (!empty($availableStores) && count($availableStores) > 1): ?>
                    <select onchange="window.location.href = '?switch_store=' + this.value;"
                        class="text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-xl px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer transition-colors"
                        title="Changer de boutique">
                        <?php foreach ($availableStores as $st): ?>
                            <option value="<?= $st['id'] ?>" <?= ($st['id'] == $currentStoreId) ? 'selected' : '' ?>>
                                🏪 <?= htmlspecialchars($st['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <div class="hidden md:flex flex-col text-right pl-2 border-l border-slate-200">
                    <span id="currentTime" class="text-xs font-bold text-slate-700">--:--:--</span>
                    <span id="currentDate" class="text-[10px] text-slate-400">--/--/----</span>
                </div>

                <!-- Bouton Déconnexion -->
                <a href="logout.php"
                    onclick="return confirm('Voulez-vous vous déconnecter de votre espace boutique ?');"
                    class="w-8 h-8 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 flex items-center justify-center text-xs transition-colors ml-1"
                    title="Se déconnecter">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- Conteneur Principal : Layout 2 colonnes (Catalogue à gauche, Panier/Encaissement à droite) -->
    <main class="flex-1 flex overflow-hidden">

        <!-- ================= Colonne Gauche : Recherche & Catalogue de Produits ================= -->
        <section class="flex-1 flex flex-col bg-slate-50 overflow-hidden border-r border-slate-200">

            <!-- Barre d'outils / Recherche rapide -->
            <div
                class="p-4 bg-white border-b border-slate-200 flex flex-col sm:flex-row gap-3 items-center justify-between">

                <!-- Champ de recherche produit / code-barre -->
                <div class="relative w-full sm:w-96">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-barcode text-lg"></i>
                    </div>
                    <input type="text" id="searchInput" autofocus
                        placeholder="Rechercher par nom ou scanner code-barre [Entrée]..."
                        class="w-full pl-11 pr-10 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all">
                    <button id="clearSearchBtn"
                        class="hidden absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>

                <!-- Filtres Catégories -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0" id="categoryPills">
                    <button onclick="filterCategory('all')" data-cat="all"
                        class="cat-pill px-3 py-1.5 rounded-lg text-xs font-semibold bg-brand-600 text-white transition-all shadow-sm">
                        Tous
                    </button>
                    <button onclick="filterCategory(1)" data-cat="1"
                        class="cat-pill px-3 py-1.5 rounded-lg text-xs font-medium bg-white text-slate-600 hover:bg-slate-100 border border-slate-200 transition-all">
                        Épicerie
                    </button>
                    <button onclick="filterCategory(2)" data-cat="2"
                        class="cat-pill px-3 py-1.5 rounded-lg text-xs font-medium bg-white text-slate-600 hover:bg-slate-100 border border-slate-200 transition-all">
                        Hygiène
                    </button>
                    <button onclick="filterCategory(3)" data-cat="3"
                        class="cat-pill px-3 py-1.5 rounded-lg text-xs font-medium bg-white text-slate-600 hover:bg-slate-100 border border-slate-200 transition-all">
                        Boissons
                    </button>
                    <button onclick="filterCategory(4)" data-cat="4"
                        class="cat-pill px-3 py-1.5 rounded-lg text-xs font-medium bg-white text-slate-600 hover:bg-slate-100 border border-slate-200 transition-all">
                        Petits Déjeuners
                    </button>
                </div>
            </div>

            <!-- Grille des Cartes Produits -->
            <div class="flex-1 overflow-y-auto p-4 md:p-6">
                <div id="productsGrid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3.5">
                    <!-- Généré dynamiquement en JS -->
                </div>

                <!-- Message si aucun résultat -->
                <div id="noProductsFound"
                    class="hidden flex-col items-center justify-center py-16 text-center text-slate-400">
                    <i class="fa-solid fa-box-open text-5xl mb-3 text-slate-300"></i>
                    <p class="text-base font-medium text-slate-600">Aucun produit ne correspond à votre recherche</p>
                    <p class="text-xs text-slate-400 mt-1">Vérifiez l'orthographe ou le code-barre saisi.</p>
                </div>
            </div>

            <!-- Barre de statistiques / raccourcis en bas -->
            <div
                class="px-5 py-2.5 bg-white border-t border-slate-200 text-xs text-slate-500 flex justify-between items-center flex-shrink-0">
                <div class="flex items-center gap-4">
                    <span><i class="fa-solid fa-boxes-stacked mr-1.5 text-brand-500"></i>Total références : <strong
                            id="totalProductsCount" class="text-slate-800">0</strong></span>
                    <span><i class="fa-solid fa-triangle-exclamation mr-1.5 text-amber-500"></i>Alerte stock : <strong
                            id="lowStockCount" class="text-slate-800">0</strong></span>
                </div>
                <div class="hidden sm:flex items-center gap-2 text-slate-400">
                    <kbd
                        class="px-2 py-0.5 bg-slate-100 border border-slate-300 rounded text-[10px] text-slate-600 font-mono">Entrée</kbd>
                    <span>Ajout rapide</span>
                    <span class="mx-1">•</span>
                    <kbd
                        class="px-2 py-0.5 bg-slate-100 border border-slate-300 rounded text-[10px] text-slate-600 font-mono">Echap</kbd>
                    <span>Effacer</span>
                </div>
            </div>
        </section>


        <!-- ================= Colonne Droite : Panier & Encaissement ================= -->
        <section class="w-full md:w-[420px] lg:w-[460px] bg-white flex flex-col shadow-xl z-10 flex-shrink-0">

            <!-- En-tête Panier -->
            <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <div class="flex items-center space-x-2">
                    <div
                        class="w-7 h-7 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center text-xs font-bold">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>
                    <h2 class="text-sm font-bold text-slate-800">Panier en cours</h2>
                    <span id="cartBadgeCount"
                        class="px-2 py-0.5 bg-brand-600 text-white text-[11px] font-bold rounded-full">0</span>
                </div>
                <button onclick="clearCart()" title="Vider le panier"
                    class="text-xs text-slate-400 hover:text-red-600 transition-colors flex items-center gap-1 font-medium">
                    <i class="fa-regular fa-trash-can"></i>
                    <span>Vider</span>
                </button>
            </div>

            <!-- Liste des articles du panier -->
            <div id="cartItemsContainer" class="flex-1 overflow-y-auto p-4 space-y-2.5 divide-y divide-slate-100">
                <!-- État vide par défaut -->
                <div id="emptyCartView"
                    class="h-full flex flex-col items-center justify-center text-center py-12 text-slate-400">
                    <div
                        class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center text-slate-300 mb-3 text-2xl">
                        <i class="fa-solid fa-basket-shopping"></i>
                    </div>
                    <p class="text-sm font-medium text-slate-600">Le panier est vide</p>
                    <p class="text-xs text-slate-400 mt-1 max-w-[220px]">Cliquez sur un produit à gauche ou scannez un
                        code-barre.</p>
                </div>

                <!-- Articles dynamiques injectés ici -->
                <div id="cartList" class="space-y-2.5"></div>
            </div>

            <!-- Résumé Financier & Calculs en temps réel -->
            <div class="p-4 bg-slate-50 border-t border-slate-200 space-y-3 flex-shrink-0">

                <!-- Décomposition des montants -->
                <div class="space-y-1.5 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>Sous-total articles :</span>
                        <span id="subTotalAmount" class="font-medium text-slate-700">0.00 FCFA</span>
                    </div>

                    <!-- Marge bénéficiaire estimée demandée -->
                    <div
                        class="flex justify-between items-center py-1 px-2.5 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800">
                        <span class="flex items-center gap-1.5 font-medium">
                            <i class="fa-solid fa-chart-line text-emerald-600"></i>
                            Bénéfice estimé :
                        </span>
                        <div class="text-right">
                            <span id="totalProfitAmount" class="font-bold text-emerald-700">+0.00 FCFA</span>
                            <span id="profitMarginPercent"
                                class="text-[10px] text-emerald-600 font-semibold ml-1">(0%)</span>
                        </div>
                    </div>
                </div>

                <!-- Grand Total -->
                <div class="pt-2 border-t border-slate-200 flex items-baseline justify-between">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block">Total à
                            Payer</span>
                        <span class="text-[11px] text-slate-400" id="itemsCountLabel">0 article</span>
                    </div>
                    <div class="text-right">
                        <span id="grandTotalAmount"
                            class="text-2xl font-black text-slate-900 tracking-tight">0.00</span>
                        <span class="text-xs font-bold text-slate-500 ml-1">FCFA</span>
                    </div>
                </div>

                <!-- Formulaire Mode de Paiement -->
                <div class="pt-1">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Mode de
                        paiement</label>
                    <div class="grid grid-cols-2 gap-2" id="paymentMethodsGrid">

                        <!-- Espèces -->
                        <button type="button" onclick="selectPaymentMethod('cash')" data-method="cash"
                            class="payment-btn active flex items-center gap-2.5 p-2.5 border-2 border-brand-600 bg-brand-50 rounded-xl text-left transition-all">
                            <div
                                class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs">
                                <i class="fa-solid fa-money-bill-wave"></i>
                            </div>
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">Espèces</span>
                                <span class="text-[10px] text-slate-500">Cash direct</span>
                            </div>
                        </button>

                        <!-- Mobile Money -->
                        <button type="button" onclick="selectPaymentMethod('mobile_money')" data-method="mobile_money"
                            class="payment-btn flex items-center gap-2.5 p-2.5 border-2 border-slate-200 bg-white hover:border-slate-300 rounded-xl text-left transition-all">
                            <div
                                class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-xs">
                                <i class="fa-solid fa-mobile-screen-button"></i>
                            </div>
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">Mobile Money</span>
                                <span class="text-[10px] text-slate-500">Orange/Wave/MTN</span>
                            </div>
                        </button>

                        <!-- Carte Bancaire -->
                        <button type="button" onclick="selectPaymentMethod('card')" data-method="card"
                            class="payment-btn flex items-center gap-2.5 p-2.5 border-2 border-slate-200 bg-white hover:border-slate-300 rounded-xl text-left transition-all">
                            <div
                                class="w-7 h-7 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center text-xs">
                                <i class="fa-solid fa-credit-card"></i>
                            </div>
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">Carte</span>
                                <span class="text-[10px] text-slate-500">Visa / TPE</span>
                            </div>
                        </button>

                        <!-- Crédit Client -->
                        <button type="button" onclick="selectPaymentMethod('credit')" data-method="credit"
                            class="payment-btn flex items-center gap-2.5 p-2.5 border-2 border-slate-200 bg-white hover:border-slate-300 rounded-xl text-left transition-all">
                            <div
                                class="w-7 h-7 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center text-xs">
                                <i class="fa-solid fa-file-invoice-dollar"></i>
                            </div>
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">Crédit</span>
                                <span class="text-[10px] text-slate-500">À terme / Compte</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Montant Reçu & Calcul Monnaie (pour espèces) -->
                <div id="cashChangeBlock" class="p-2.5 bg-white border border-slate-200 rounded-xl space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <label for="amountReceivedInput" class="text-xs text-slate-600 font-medium">Montant Reçu
                            :</label>
                        <div class="relative w-36">
                            <input type="number" id="amountReceivedInput" min="0" step="100" placeholder="0"
                                class="w-full text-right pr-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold focus:outline-none focus:ring-1 focus:ring-brand-500"
                                oninput="calculateChange()">
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-100">
                        <span class="text-slate-500">Monnaie à rendre :</span>
                        <strong id="changeReturnAmount" class="font-bold text-brand-600">0.00 FCFA</strong>
                    </div>
                </div>

                <!-- Bouton d'action principal : Valider la Vente -->
                <button type="button" id="submitSaleBtn" onclick="processCheckout()" disabled
                    class="w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-bold rounded-xl shadow-lg shadow-emerald-600/20 flex items-center justify-center gap-2 transition-all text-sm active:scale-[0.99]">
                    <i class="fa-solid fa-check text-base"></i>
                    <span id="btnSubmitText">Valider la Vente</span>
                </button>
            </div>
        </section>
    </main>

    <!-- ================= MODALE DE SUCCÈS & REÇU DE CAISSE ================= -->
    <div id="receiptModal"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div
            class="bg-white rounded-2xl shadow-2xl max-w-sm w-full overflow-hidden flex flex-col max-h-[90vh] animate-in fade-in zoom-in duration-200">

            <!-- En-tête de modale -->
            <div class="bg-emerald-600 text-white p-4 text-center relative no-print">
                <button onclick="closeReceiptModal()"
                    class="absolute top-3 right-3 text-white/80 hover:text-white text-lg">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-2 text-2xl">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h3 class="text-base font-bold">Vente Validée avec Succès !</h3>
                <p class="text-xs text-white/80">Stock décrémenté & bénéfice comptabilisé</p>
            </div>

            <!-- Contenu du Reçu / Ticket (Zone imprimable) -->
            <div id="receiptModalContent" class="p-6 overflow-y-auto text-xs font-mono text-slate-800 space-y-4">
                <div class="text-center border-b border-dashed border-slate-300 pb-3">
                    <h4 class="font-bold text-sm tracking-wider uppercase font-sans"><?= htmlspecialchars($appName) ?>
                    </h4>
                    <p class="text-[11px] text-slate-700 font-semibold"><?= htmlspecialchars($currentStoreName) ?></p>
                    <p class="text-[10px] text-slate-400 mt-1">Ticket N° <span id="receiptSaleId"
                            class="font-bold text-slate-700">#0000</span></p>
                    <p class="text-[10px] text-slate-400" id="receiptDateTime">--/--/---- --:--</p>
                </div>

                <!-- Articles du ticket -->
                <div class="space-y-1.5 border-b border-dashed border-slate-300 pb-3" id="receiptItemsList">
                    <!-- Généré dynamiquement -->
                </div>

                <!-- Totaux du ticket -->
                <div class="space-y-1 font-sans">
                    <div class="flex justify-between text-xs font-bold">
                        <span>TOTAL TTC :</span>
                        <span id="receiptTotalAmount">0.00 FCFA</span>
                    </div>
                    <div class="flex justify-between text-[11px] text-slate-600">
                        <span>Mode de paiement :</span>
                        <span id="receiptPaymentMethod" class="font-semibold uppercase">ESPÈCES</span>
                    </div>
                    <div class="flex justify-between text-[11px] text-slate-600" id="receiptReceivedRow">
                        <span>Montant Reçu :</span>
                        <span id="receiptAmountReceived">0.00 FCFA</span>
                    </div>
                    <div class="flex justify-between text-[11px] text-slate-600 font-semibold" id="receiptChangeRow">
                        <span>Monnaie Rendu :</span>
                        <span id="receiptChange">0.00 FCFA</span>
                    </div>
                </div>

                <div class="text-center text-[10px] text-slate-400 pt-2">
                    <p>Merci pour votre visite !</p>
                    <p>À bientôt dans notre boutique.</p>
                </div>
            </div>

            <!-- Actions de bas de modale -->
            <div class="p-3 bg-slate-50 border-t border-slate-200 flex gap-2 no-print">
                <button onclick="window.print()"
                    class="flex-1 py-2.5 px-3 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-colors">
                    <i class="fa-solid fa-print"></i>
                    <span>Imprimer Ticket</span>
                </button>
                <button onclick="closeReceiptModal()"
                    class="flex-1 py-2.5 px-3 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-colors">
                    <i class="fa-solid fa-plus"></i>
                    <span>Nouvelle Vente</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Notification Toast -->
    <div id="toastNotification"
        class="fixed bottom-5 right-5 z-50 transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none">
        <div
            class="bg-slate-900 text-white text-xs px-4 py-3 rounded-xl shadow-xl flex items-center gap-2.5 border border-slate-700">
            <i id="toastIcon" class="fa-solid fa-circle-check text-emerald-400 text-sm"></i>
            <span id="toastMessage">Action effectuée</span>
        </div>
    </div>

    <!-- Logique JavaScript Réactive -->
    <script>
        // Données d'état
        let productsData = <?php echo json_encode($products, JSON_UNESCAPED_UNICODE); ?>;
        let cart = []; // Array d'articles : { product_id, name, barcode, unit_price, purchase_price, quantity, max_stock }
        let currentCategory = 'all';
        let currentPaymentMethod = 'cash';
        let lastCompletedSale = null;

        // Formateur de devise
        function formatMoney(amount) {
            return new Intl.NumberFormat('fr-FR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount) + ' FCFA';
        }

        // Horloge temps réel
        function updateClock() {
            const now = new Date();
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('fr-FR');
            document.getElementById('currentDate').textContent = now.toLocaleDateString('fr-FR', {
                weekday: 'short', day: '2-digit', month: 'short', year: 'numeric'
            });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Affichage Toast
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toastNotification');
            const toastMsg = document.getElementById('toastMessage');
            const toastIcon = document.getElementById('toastIcon');

            toastMsg.textContent = message;
            if (type === 'error') {
                toastIcon.className = "fa-solid fa-circle-xmark text-red-400 text-sm";
            } else if (type === 'warning') {
                toastIcon.className = "fa-solid fa-triangle-exclamation text-amber-400 text-sm";
            } else {
                toastIcon.className = "fa-solid fa-circle-check text-emerald-400 text-sm";
            }

            toast.classList.remove('translate-y-20', 'opacity-0');
            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 3000);
        }

        // Rendu de la grille de produits
        function renderProducts() {
            const grid = document.getElementById('productsGrid');
            const searchVal = document.getElementById('searchInput').value.trim().toLowerCase();
            const noFound = document.getElementById('noProductsFound');

            // Filtrage par texte et catégorie
            const filtered = productsData.filter(p => {
                const matchesCat = (currentCategory === 'all' || p.category_id == currentCategory);
                const matchesSearch = !searchVal ||
                    p.name.toLowerCase().includes(searchVal) ||
                    (p.barcode && p.barcode.toLowerCase().includes(searchVal));
                return matchesCat && matchesSearch;
            });

            // Mise à jour des compteurs
            document.getElementById('totalProductsCount').textContent = productsData.length;
            const lowStockCount = productsData.filter(p => p.stock_quantity <= p.min_stock_alert).length;
            document.getElementById('lowStockCount').textContent = lowStockCount;

            if (filtered.length === 0) {
                grid.innerHTML = '';
                noFound.classList.remove('hidden');
                noFound.classList.add('flex');
                return;
            }

            noFound.classList.add('hidden');
            noFound.classList.remove('flex');

            grid.innerHTML = filtered.map(product => {
                const stock = parseInt(product.stock_quantity);
                const minAlert = parseInt(product.min_stock_alert || 5);
                const isOutOfStock = stock <= 0;

                // Calcul de la marge estimée unitaire
                const unitPrice = parseFloat(product.unit_price);
                const purchasePrice = parseFloat(product.purchase_price);
                const unitProfit = unitPrice - purchasePrice;

                // Badge de stock
                let stockBadge = '';
                if (isOutOfStock) {
                    stockBadge = `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700 border border-red-200">Rupture (0)</span>`;
                } else if (stock <= minAlert) {
                    stockBadge = `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">Stock Faible (${stock})</span>`;
                } else {
                    stockBadge = `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200">En stock (${stock})</span>`;
                }

                return `
                    <div 
                        onclick="${isOutOfStock ? '' : `addToCart(${product.id})`}"
                        class="group relative bg-white border border-slate-200 rounded-xl p-3.5 flex flex-col justify-between transition-all duration-150 ${isOutOfStock
                        ? 'opacity-60 cursor-not-allowed bg-slate-50'
                        : 'cursor-pointer hover:shadow-md hover:border-brand-500 hover:-translate-y-0.5 active:scale-[0.98]'
                    }"
                    >
                        <!-- En-tête carte : Catégorie & Badge stock -->
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-mono text-slate-400 truncate max-w-[80px]" title="${product.barcode || ''}">
                                ${product.barcode || 'SANS CODE'}
                            </span>
                            ${stockBadge}
                        </div>

                        <!-- Nom du produit -->
                        <div class="mb-2">
                            <h3 class="text-xs font-bold text-slate-800 line-clamp-2 leading-tight group-hover:text-brand-600 transition-colors" title="${product.name}">
                                ${product.name}
                            </h3>
                        </div>

                        <!-- Prix & Action -->
                        <div class="pt-2 border-t border-slate-100 flex items-end justify-between">
                            <div>
                                <span class="text-xs text-slate-400 block text-[10px]">Prix Vente</span>
                                <span class="text-sm font-black text-slate-900">${formatMoney(unitPrice)}</span>
                            </div>
                            <button 
                                type="button" 
                                ${isOutOfStock ? 'disabled' : ''}
                                class="w-7 h-7 rounded-lg ${isOutOfStock ? 'bg-slate-200 text-slate-400' : 'bg-brand-50 text-brand-600 group-hover:bg-brand-600 group-hover:text-white'} flex items-center justify-center text-xs transition-colors shadow-sm"
                            >
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Filtre de catégorie
        function filterCategory(catId) {
            currentCategory = catId;
            document.querySelectorAll('.cat-pill').forEach(btn => {
                if (btn.getAttribute('data-cat') == catId) {
                    btn.className = "cat-pill px-3 py-1.5 rounded-lg text-xs font-semibold bg-brand-600 text-white transition-all shadow-sm";
                } else {
                    btn.className = "cat-pill px-3 py-1.5 rounded-lg text-xs font-medium bg-white text-slate-600 hover:bg-slate-100 border border-slate-200 transition-all";
                }
            });
            renderProducts();
        }

        // Recherche réactive
        const searchInput = document.getElementById('searchInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');

        searchInput.addEventListener('input', (e) => {
            const val = e.target.value.trim();
            clearSearchBtn.classList.toggle('hidden', val.length === 0);
            renderProducts();
        });

        clearSearchBtn.addEventListener('click', () => {
            searchInput.value = '';
            clearSearchBtn.classList.add('hidden');
            renderProducts();
            searchInput.focus();
        });

        // Scan code-barre ou validation directe avec Entrée
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = searchInput.value.trim().toLowerCase();
                if (!query) return;

                // Chercher d'abord une correspondance exacte par code-barre
                let match = productsData.find(p => p.barcode && p.barcode.toLowerCase() === query);

                // Sinon par nom exact ou premier produit filtré
                if (!match) {
                    const filtered = productsData.filter(p =>
                        p.name.toLowerCase().includes(query) ||
                        (p.barcode && p.barcode.toLowerCase().includes(query))
                    );
                    if (filtered.length > 0) {
                        match = filtered[0];
                    }
                }

                if (match) {
                    if (parseInt(match.stock_quantity) <= 0) {
                        showToast(`"${match.name}" est en rupture de stock !`, 'error');
                    } else {
                        addToCart(match.id);
                        searchInput.value = '';
                        clearSearchBtn.classList.add('hidden');
                        renderProducts();
                    }
                } else {
                    showToast("Aucun produit correspondant trouvé.", 'warning');
                }
            }
        });

        // ================= GESTION DU PANIER =================

        function addToCart(productId) {
            const product = productsData.find(p => p.id == productId);
            if (!product) return;

            const stockAvailable = parseInt(product.stock_quantity);
            if (stockAvailable <= 0) {
                showToast("Stock épuisé pour ce produit !", "error");
                return;
            }

            const existingItem = cart.find(item => item.product_id == productId);
            if (existingItem) {
                if (existingItem.quantity + 1 > stockAvailable) {
                    showToast(`Stock maximal atteint (${stockAvailable}) pour cet article.`, "warning");
                    return;
                }
                existingItem.quantity += 1;
            } else {
                cart.push({
                    product_id: product.id,
                    name: product.name,
                    barcode: product.barcode,
                    unit_price: parseFloat(product.unit_price),
                    purchase_price: parseFloat(product.purchase_price),
                    quantity: 1,
                    max_stock: stockAvailable
                });
            }

            updateCartUI();
        }

        function updateItemQuantity(productId, delta) {
            const item = cart.find(i => i.product_id == productId);
            if (!item) return;

            const newQty = item.quantity + delta;
            if (newQty <= 0) {
                removeFromCart(productId);
                return;
            }

            if (newQty > item.max_stock) {
                showToast(`Stock maximum disponible : ${item.max_stock}`, "warning");
                return;
            }

            item.quantity = newQty;
            updateCartUI();
        }

        function setItemQuantity(productId, explicitQty) {
            const item = cart.find(i => i.product_id == productId);
            if (!item) return;

            let qty = parseInt(explicitQty);
            if (isNaN(qty) || qty <= 0) {
                removeFromCart(productId);
                return;
            }

            if (qty > item.max_stock) {
                showToast(`Stock disponible limité à ${item.max_stock}.`, "warning");
                qty = item.max_stock;
            }

            item.quantity = qty;
            updateCartUI();
        }

        function removeFromCart(productId) {
            cart = cart.filter(i => i.product_id != productId);
            updateCartUI();
        }

        function clearCart() {
            if (cart.length === 0) return;
            cart = [];
            updateCartUI();
            showToast("Le panier a été vidé.");
        }

        function updateCartUI() {
            const list = document.getElementById('cartList');
            const emptyView = document.getElementById('emptyCartView');
            const badgeCount = document.getElementById('cartBadgeCount');
            const submitBtn = document.getElementById('submitSaleBtn');

            const totalItemsCount = cart.reduce((sum, item) => sum + item.quantity, 0);
            badgeCount.textContent = totalItemsCount;
            document.getElementById('itemsCountLabel').textContent = `${totalItemsCount} article${totalItemsCount > 1 ? 's' : ''}`;

            if (cart.length === 0) {
                emptyView.classList.remove('hidden');
                list.innerHTML = '';
                submitBtn.disabled = true;

                document.getElementById('subTotalAmount').textContent = '0.00 FCFA';
                document.getElementById('grandTotalAmount').textContent = '0.00';
                document.getElementById('totalProfitAmount').textContent = '+0.00 FCFA';
                document.getElementById('profitMarginPercent').textContent = '(0%)';
                document.getElementById('amountReceivedInput').value = '';
                calculateChange();
                return;
            }

            emptyView.classList.add('hidden');
            submitBtn.disabled = false;

            // Rendu des lignes d'articles du panier
            list.innerHTML = cart.map(item => {
                const lineTotal = item.unit_price * item.quantity;
                const lineProfit = (item.unit_price - item.purchase_price) * item.quantity;

                return `
                    <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3 flex flex-col gap-2 hover:border-slate-300 transition-colors">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="text-xs font-bold text-slate-800 leading-tight">${item.name}</h4>
                                <span class="text-[10px] text-slate-400 font-mono">${formatMoney(item.unit_price)} / unité</span>
                            </div>
                            <button 
                                onclick="removeFromCart(${item.product_id})" 
                                class="text-slate-400 hover:text-red-600 text-xs p-1 transition-colors" 
                                title="Supprimer"
                            >
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>

                        <div class="flex items-center justify-between pt-1 border-t border-slate-100">
                            <!-- Sélecteur de quantité -->
                            <div class="flex items-center border border-slate-200 rounded-lg bg-white overflow-hidden shadow-sm">
                                <button 
                                    onclick="updateItemQuantity(${item.product_id}, -1)" 
                                    class="w-7 h-7 flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors"
                                >
                                    <i class="fa-solid fa-minus text-[10px]"></i>
                                </button>
                                <input 
                                    type="number" 
                                    min="1" 
                                    max="${item.max_stock}" 
                                    value="${item.quantity}" 
                                    onchange="setItemQuantity(${item.product_id}, this.value)"
                                    class="w-10 text-center text-xs font-bold text-slate-800 focus:outline-none"
                                >
                                <button 
                                    onclick="updateItemQuantity(${item.product_id}, 1)" 
                                    class="w-7 h-7 flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors"
                                >
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                </button>
                            </div>

                            <!-- Sous-total et marge de la ligne -->
                            <div class="text-right">
                                <span class="text-xs font-black text-slate-900 block">${formatMoney(lineTotal)}</span>
                                <span class="text-[10px] text-emerald-600 font-medium" title="Bénéfice ligne">(+${formatMoney(lineProfit)})</span>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            // Calculs financiers globaux
            let totalAmount = 0.0;
            let totalProfit = 0.0;
            cart.forEach(item => {
                totalAmount += item.unit_price * item.quantity;
                totalProfit += (item.unit_price - item.purchase_price) * item.quantity;
            });

            const marginPercent = totalAmount > 0 ? ((totalProfit / totalAmount) * 100).toFixed(1) : 0;

            document.getElementById('subTotalAmount').textContent = formatMoney(totalAmount);
            document.getElementById('grandTotalAmount').textContent = new Intl.NumberFormat('fr-FR', {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            }).format(totalAmount);
            document.getElementById('totalProfitAmount').textContent = `+${formatMoney(totalProfit)}`;
            document.getElementById('profitMarginPercent').textContent = `(${marginPercent}%)`;

            calculateChange();
        }

        // ================= MODES DE PAIEMENT & ENCAISSEMENT =================

        function selectPaymentMethod(method) {
            currentPaymentMethod = method;
            document.querySelectorAll('.payment-btn').forEach(btn => {
                const btnMethod = btn.getAttribute('data-method');
                if (btnMethod === method) {
                    btn.className = "payment-btn active flex items-center gap-2.5 p-2.5 border-2 border-brand-600 bg-brand-50 rounded-xl text-left transition-all";
                } else {
                    btn.className = "payment-btn flex items-center gap-2.5 p-2.5 border-2 border-slate-200 bg-white hover:border-slate-300 rounded-xl text-left transition-all";
                }
            });

            // Afficher ou cacher le bloc monnaie selon le mode
            const cashBlock = document.getElementById('cashChangeBlock');
            if (method === 'cash') {
                cashBlock.classList.remove('hidden');
            } else {
                cashBlock.classList.add('hidden');
            }
        }

        function calculateChange() {
            if (currentPaymentMethod !== 'cash') return;

            let totalAmount = 0.0;
            cart.forEach(i => totalAmount += i.unit_price * i.quantity);

            const receivedInput = document.getElementById('amountReceivedInput');
            const received = parseFloat(receivedInput.value) || 0;
            const changeDisplay = document.getElementById('changeReturnAmount');

            if (received > 0 && received >= totalAmount) {
                const change = received - totalAmount;
                changeDisplay.textContent = formatMoney(change);
                changeDisplay.className = "font-bold text-emerald-600";
            } else if (received > 0 && received < totalAmount) {
                const missing = totalAmount - received;
                changeDisplay.textContent = `Manque : ${formatMoney(missing)}`;
                changeDisplay.className = "font-bold text-red-500";
            } else {
                changeDisplay.textContent = "0.00 FCFA";
                changeDisplay.className = "font-bold text-slate-400";
            }
        }

        // ================= SOUMISSION DE LA VENTE VIA AJAX =================

        async function processCheckout() {
            if (cart.length === 0) return;

            const submitBtn = document.getElementById('submitSaleBtn');
            const btnText = document.getElementById('btnSubmitText');

            let totalAmount = 0.0;
            cart.forEach(i => totalAmount += i.unit_price * i.quantity);

            // Validation espèces
            const receivedInput = document.getElementById('amountReceivedInput');
            const received = parseFloat(receivedInput.value) || 0;

            if (currentPaymentMethod === 'cash' && received > 0 && received < totalAmount) {
                showToast("Le montant reçu est inférieur au total de la vente !", "error");
                receivedInput.focus();
                return;
            }

            // Désactivation du bouton pendant la requête
            submitBtn.disabled = true;
            btnText.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-2"></i>Traitement en cours...`;

            try {
                const payload = {
                    store_id: 1,
                    user_id: 1,
                    payment_method: currentPaymentMethod,
                    items: cart.map(i => ({
                        product_id: i.product_id,
                        quantity: i.quantity,
                        unit_price: i.unit_price,
                        purchase_price: i.purchase_price
                    }))
                };

                const response = await fetch('cashier.php?action=checkout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.error || "Une erreur est survenue lors de l'enregistrement.");
                }

                // Succès : mise à jour des stocks locaux à partir de la réponse du serveur
                if (result.products) {
                    productsData = result.products;
                    renderProducts();
                }

                // Enregistrement des données du ticket
                lastCompletedSale = {
                    sale_id: result.sale_id,
                    items: [...cart],
                    total_amount: result.total_amount,
                    total_profit: result.total_profit,
                    payment_method: result.payment_method,
                    amount_received: received > 0 ? received : result.total_amount,
                    change: (received >= result.total_amount) ? (received - result.total_amount) : 0,
                    date: new Date()
                };

                // Vider le panier
                cart = [];
                updateCartUI();

                // Afficher le ticket de caisse
                showReceiptModal(lastCompletedSale);
                showToast("Vente enregistrée avec succès !", "success");

            } catch (err) {
                console.error(err);
                showToast(err.message, "error");
            } finally {
                submitBtn.disabled = false;
                btnText.textContent = "Valider la Vente";
            }
        }

        // ================= AFFICHAGE DU REÇU / TICKET =================

        function showReceiptModal(sale) {
            document.getElementById('receiptSaleId').textContent = `#${String(sale.sale_id).padStart(5, '0')}`;
            document.getElementById('receiptDateTime').textContent = sale.date.toLocaleString('fr-FR');
            document.getElementById('receiptTotalAmount').textContent = formatMoney(sale.total_amount);
            document.getElementById('receiptPaymentMethod').textContent = sale.payment_method;

            const itemsContainer = document.getElementById('receiptItemsList');
            itemsContainer.innerHTML = sale.items.map(item => `
                <div class="flex justify-between py-0.5">
                    <span class="truncate max-w-[170px]">${item.quantity}x ${item.name}</span>
                    <span class="font-bold">${formatMoney(item.unit_price * item.quantity)}</span>
                </div>
            `).join('');

            // Gestion rendu monnaie pour le ticket
            const receivedRow = document.getElementById('receiptReceivedRow');
            const changeRow = document.getElementById('receiptChangeRow');
            if (sale.payment_method === 'cash' && sale.amount_received > 0) {
                receivedRow.classList.remove('hidden');
                changeRow.classList.remove('hidden');
                document.getElementById('receiptAmountReceived').textContent = formatMoney(sale.amount_received);
                document.getElementById('receiptChange').textContent = formatMoney(sale.change);
            } else {
                receivedRow.classList.add('hidden');
                changeRow.classList.add('hidden');
            }

            document.getElementById('receiptModal').classList.remove('hidden');
        }

        function closeReceiptModal() {
            document.getElementById('receiptModal').classList.add('hidden');
            searchInput.focus();
        }

        // Raccourcis clavier (F10 = Valider vente, Echap = fermer modal / vider recherche)
        window.addEventListener('keydown', (e) => {
            if (e.key === 'F10') {
                e.preventDefault();
                processCheckout();
            } else if (e.key === 'Escape') {
                const modal = document.getElementById('receiptModal');
                if (!modal.classList.contains('hidden')) {
                    closeReceiptModal();
                } else if (searchInput.value) {
                    searchInput.value = '';
                    renderProducts();
                }
            }
        });

        // Rendu initial au chargement
        renderProducts();
        updateCartUI();
    </script>

<!-- Barre de navigation fixe en bas — mobile uniquement -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 shadow-[0_-2px_12px_rgba(0,0,0,0.08)] z-40 no-print">
    <div class="grid grid-cols-4 h-16 max-w-lg mx-auto px-2">

        <!-- Onglet 1 : Accueil / Dashboard -->
        <a href="dashboard.php" class="flex flex-col items-center justify-center py-1 group transition-all text-slate-400 hover:text-slate-700">
            <div class="w-8 h-8 flex items-center justify-center rounded-xl transition-transform active:scale-90">
                <i class="fa-solid fa-chart-line text-base"></i>
            </div>
            <span class="text-[10px] tracking-tight mt-0.5 font-bold">Accueil</span>
        </a>

        <!-- Onglet 2 : Caisse & Vente (actif) -->
        <a href="cashier.php" class="flex flex-col items-center justify-center py-1 group transition-all text-brand-600 font-black">
            <div class="w-8 h-8 flex items-center justify-center rounded-xl transition-transform active:scale-90 bg-brand-50 text-brand-600 shadow-xs">
                <i class="fa-solid fa-cash-register text-base"></i>
            </div>
            <span class="text-[10px] tracking-tight mt-0.5 font-bold">Caisse</span>
        </a>

        <!-- Onglet 3 : Stocks & Produits -->
        <a href="products.php" class="flex flex-col items-center justify-center py-1 group transition-all text-slate-400 hover:text-slate-700">
            <div class="w-8 h-8 flex items-center justify-center rounded-xl transition-transform active:scale-90">
                <i class="fa-solid fa-boxes-stacked text-base"></i>
            </div>
            <span class="text-[10px] tracking-tight mt-0.5 font-bold">Stocks</span>
        </a>

        <!-- Onglet 4 : Historique des Ventes -->
        <a href="sales_history.php" class="flex flex-col items-center justify-center py-1 group transition-all text-slate-400 hover:text-slate-700">
            <div class="w-8 h-8 flex items-center justify-center rounded-xl transition-transform active:scale-90">
                <i class="fa-solid fa-clock-rotate-left text-base"></i>
            </div>
            <span class="text-[10px] tracking-tight mt-0.5 font-bold">Historique</span>
        </a>

    </div>
</nav>

</body>

</html>