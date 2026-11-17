<?php
// products.php - Gestion Complète du Stock et du Catalogue Produits

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/store_context.php';
require_once __DIR__ . '/models/Product.php';

$pdo = require __DIR__ . '/config/database.php';
$productModel = new Product($pdo);

$storeId = $currentStoreId;

// Messages Flash
$feedbackMessage = $_SESSION['flash_message'] ?? null;
$feedbackType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Catégories disponibles
$categories = [
    1 => 'Épicerie',
    2 => 'Hygiène & Entretien',
    3 => 'Boissons',
    4 => 'Petits Déjeuners'
];

// ================= GESTION DES ACTIONS (POST) =================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. AJOUT D'UN NOUVEAU PRODUIT
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $categoryId = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
            $barcode = trim($_POST['barcode'] ?? '') ?: null;
            $purchasePrice = (float) ($_POST['purchase_price'] ?? 0);
            $unitPrice = (float) ($_POST['unit_price'] ?? 0);
            $stockQuantity = (int) ($_POST['stock_quantity'] ?? 0);
            $minStockAlert = (int) ($_POST['min_stock_alert'] ?? 5);

            if (empty($name)) {
                throw new InvalidArgumentException("Le nom du produit est obligatoire.");
            }
            if ($unitPrice <= 0) {
                throw new InvalidArgumentException("Le prix de vente doit être supérieur à 0.");
            }

            $newId = $productModel->create(
                $storeId,
                $categoryId,
                $name,
                $barcode,
                $purchasePrice,
                $unitPrice,
                $stockQuantity,
                $minStockAlert
            );

            $_SESSION['flash_message'] = "Le produit \"{$name}\" a été créé avec succès (ID: #{$newId}).";
            $_SESSION['flash_type'] = "success";
            header("Location: products.php");
            exit;
        }

        // 2. MODIFICATION D'UN PRODUIT EXISTANT
        if ($action === 'update') {
            $productId = (int) ($_POST['product_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $categoryId = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null;
            $barcode = trim($_POST['barcode'] ?? '') ?: null;
            $purchasePrice = (float) ($_POST['purchase_price'] ?? 0);
            $unitPrice = (float) ($_POST['unit_price'] ?? 0);
            $minStockAlert = (int) ($_POST['min_stock_alert'] ?? 5);

            if ($productId <= 0 || empty($name)) {
                throw new InvalidArgumentException("Informations de produit invalides.");
            }

            $productModel->update(
                $productId,
                $categoryId,
                $name,
                $barcode,
                $purchasePrice,
                $unitPrice,
                $minStockAlert
            );

            $_SESSION['flash_message'] = "Le produit \"{$name}\" a été mis à jour avec succès.";
            $_SESSION['flash_type'] = "success";
            header("Location: products.php");
            exit;
        }

        // 3. RÉAPPROVISIONNEMENT RAPIDE DE STOCK
        if ($action === 'restock') {
            $productId = (int) ($_POST['product_id'] ?? 0);
            $quantityToAdd = (int) ($_POST['quantity_to_add'] ?? 0);

            if ($productId <= 0 || $quantityToAdd <= 0) {
                throw new InvalidArgumentException("Veuillez renseigner une quantité valide à ajouter.");
            }

            $productModel->addStock($productId, $quantityToAdd);
            $p = $productModel->getById($productId);

            $_SESSION['flash_message'] = "Stock approvisionné : +{$quantityToAdd} unité(s) pour \"{$p['name']}\". Nouveau stock : {$p['stock_quantity']}.";
            $_SESSION['flash_type'] = "success";
            header("Location: products.php");
            exit;
        }

        // 4. SUPPRESSION D'UN PRODUIT
        if ($action === 'delete') {
            $productId = (int) ($_POST['product_id'] ?? 0);
            if ($productId <= 0) {
                throw new InvalidArgumentException("Produit introuvable.");
            }

            $productModel->delete($productId);

            $_SESSION['flash_message'] = "Le produit a été supprimé du catalogue.";
            $_SESSION['flash_type'] = "success";
            header("Location: products.php");
            exit;
        }

    } catch (Exception $e) {
        $_SESSION['flash_message'] = $e->getMessage();
        $_SESSION['flash_type'] = "error";
        header("Location: products.php");
        exit;
    }
}

// Chargement des données pour l'affichage
$allProducts = $productModel->getAllByStore($storeId);

// Métriques résumées
$totalReferences = count($allProducts);
$totalStockUnits = 0;
$totalStockCost = 0.0;
$totalLowStockCount = 0;

foreach ($allProducts as $p) {
    $qty = (int) $p['stock_quantity'];
    $totalStockUnits += $qty;
    $totalStockCost += $qty * (float) $p['purchase_price'];
    if ($qty <= (int) $p['min_stock_alert']) {
        $totalLowStockCount++;
    }
}

function fmtMoney(float $amount): string
{
    return number_format($amount, 2, ',', ' ') . ' FCFA';
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-50">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stocks & Produits | <?= htmlspecialchars($appName) ?> • <?= htmlspecialchars($currentStoreName) ?></title>
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
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="min-h-full flex flex-col font-sans text-slate-800 antialiased pb-20 md:pb-0">

    <!-- Top Navigation Bar Partagée -->
    <?php
    $activePage = 'products';
    require __DIR__ . '/includes/header_nav.php';
    ?>

    <!-- Contenu Principal -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- Notification Flash -->
        <?php if ($feedbackMessage): ?>
            <div
                class="p-4 rounded-xl text-xs font-semibold flex items-center justify-between border shadow-sm <?= $feedbackType === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200' ?>">
                <div class="flex items-center gap-2.5">
                    <i
                        class="fa-solid <?= $feedbackType === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-red-600' ?> text-base"></i>
                    <span><?= htmlspecialchars($feedbackMessage) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- ================= STATISTIQUES RÉSUMÉES DU STOCK ================= -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Total
                        Références</span>
                    <span class="text-2xl font-black text-slate-900 mt-1 block"><?= $totalReferences ?></span>
                    <span class="text-[11px] text-slate-500">Produits au catalogue</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-base">
                    <i class="fa-solid fa-barcode"></i>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Articles en
                        Stock</span>
                    <span
                        class="text-2xl font-black text-slate-900 mt-1 block"><?= number_format($totalStockUnits, 0, ',', ' ') ?></span>
                    <span class="text-[11px] text-slate-500">Unités physiques disponibles</span>
                </div>
                <div
                    class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-base">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Valeur d'Achat
                        Stock</span>
                    <span class="text-2xl font-black text-slate-900 mt-1 block"><?= fmtMoney($totalStockCost) ?></span>
                    <span class="text-[11px] text-slate-500">Coût immobilisé fournisseur</span>
                </div>
                <div
                    class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-base">
                    <i class="fa-solid fa-coins"></i>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Alertes Réappro</span>
                    <span
                        class="text-2xl font-black <?= $totalLowStockCount > 0 ? 'text-amber-600' : 'text-emerald-600' ?> mt-1 block"><?= $totalLowStockCount ?></span>
                    <span class="text-[11px] text-slate-500">Produits en stock critique</span>
                </div>
                <div
                    class="w-10 h-10 rounded-xl <?= $totalLowStockCount > 0 ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600' ?> flex items-center justify-center text-base">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
            </div>

        </section>


        <!-- ================= FILTRES ET RECHERCHE ================= -->
        <section
            class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex flex-col md:flex-row gap-3 items-center justify-between">

            <!-- Recherche textuelle / Code-barres -->
            <div class="relative w-full md:w-96">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <input type="text" id="tableSearchInput" placeholder="Filtrer par nom ou code-barres..."
                    class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all"
                    oninput="filterTable()">
            </div>

            <!-- Filtres statut et catégorie -->
            <div class="flex items-center gap-2 w-full md:w-auto overflow-x-auto pb-1 md:pb-0">
                <select id="categoryFilter" onchange="filterTable()"
                    class="py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500 font-medium">
                    <option value="all">Toutes les catégories</option>
                    <?php foreach ($categories as $id => $name): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="stockStatusFilter" onchange="filterTable()"
                    class="py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500 font-medium">
                    <option value="all">Tous les états de stock</option>
                    <option value="in_stock">En stock normal</option>
                    <option value="low_stock">Stock critique / faible</option>
                    <option value="out_of_stock">Rupture de stock (0)</option>
                </select>

                <button type="button" onclick="openCreateModal()"
                    class="py-2 px-3.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 transition-colors shadow-sm flex-shrink-0">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Nouveau Produit</span>
                </button>
            </div>
        </section>


        <!-- ================= TABLEAU DU CATALOGUE PRODUITS ================= -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead
                        class="bg-slate-50 border-b border-slate-200 uppercase font-bold text-slate-400 text-[10px] tracking-wider">
                        <tr>
                            <th class="py-3.5 px-4">Produit & Réf</th>
                            <th class="py-3.5 px-4">Catégorie</th>
                            <th class="py-3.5 px-4 text-right">Prix Achat</th>
                            <th class="py-3.5 px-4 text-right">Prix Vente</th>
                            <th class="py-3.5 px-4 text-right">Marge Unitaire</th>
                            <th class="py-3.5 px-4 text-center">Niveau de Stock</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productsTableBody" class="divide-y divide-slate-100 font-normal">
                        <?php foreach ($allProducts as $p): ?>
                            <?php
                            $stock = (int) $p['stock_quantity'];
                            $minAlert = (int) $p['min_stock_alert'];
                            $uPrice = (float) $p['unit_price'];
                            $pPrice = (float) $p['purchase_price'];
                            $unitMargin = $uPrice - $pPrice;
                            $marginPercent = $uPrice > 0 ? round(($unitMargin / $uPrice) * 100, 1) : 0;
                            $catName = $categories[$p['category_id']] ?? 'Général';

                            $stockState = 'in_stock';
                            if ($stock <= 0) {
                                $stockState = 'out_of_stock';
                            } elseif ($stock <= $minAlert) {
                                $stockState = 'low_stock';
                            }
                            ?>
                            <tr class="hover:bg-slate-50/80 transition-colors product-row"
                                data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>"
                                data-barcode="<?= strtolower(htmlspecialchars($p['barcode'] ?? '')) ?>"
                                data-category="<?= $p['category_id'] ?>" data-status="<?= $stockState ?>">
                                <!-- Produit & Code-barres -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-xs flex-shrink-0">
                                            <?= strtoupper(substr($p['name'], 0, 2)) ?>
                                        </div>
                                        <div class="min-w-0">
                                            <strong class="text-slate-900 block truncate font-bold text-xs"
                                                title="<?= htmlspecialchars($p['name']) ?>">
                                                <?= htmlspecialchars($p['name']) ?>
                                            </strong>
                                            <span class="text-[10px] text-slate-400 font-mono">
                                                <i
                                                    class="fa-solid fa-barcode text-[9px] mr-1"></i><?= htmlspecialchars($p['barcode'] ?? 'Non renseigné') ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Catégorie -->
                                <td class="py-3.5 px-4">
                                    <span
                                        class="px-2.5 py-1 rounded-lg text-[10px] font-semibold bg-slate-100 text-slate-700">
                                        <?= htmlspecialchars($catName) ?>
                                    </span>
                                </td>

                                <!-- Prix Achat -->
                                <td class="py-3.5 px-4 text-right font-mono text-slate-500">
                                    <?= fmtMoney($pPrice) ?>
                                </td>

                                <!-- Prix Vente -->
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900">
                                    <?= fmtMoney($uPrice) ?>
                                </td>

                                <!-- Marge Unitaire -->
                                <td class="py-3.5 px-4 text-right">
                                    <span
                                        class="font-bold text-emerald-600 block font-mono">+<?= fmtMoney($unitMargin) ?></span>
                                    <span class="text-[10px] text-emerald-500 font-medium">(<?= $marginPercent ?>%)</span>
                                </td>

                                <!-- Statut & Niveau Stock -->
                                <td class="py-3.5 px-4 text-center">
                                    <div class="inline-flex flex-col items-center">
                                        <?php if ($stockState === 'out_of_stock'): ?>
                                            <span
                                                class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-red-700 border border-red-200">
                                                Rupture (0)
                                            </span>
                                        <?php elseif ($stockState === 'low_stock'): ?>
                                            <span
                                                class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                                Critique (<?= $stock ?>)
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                En stock (<?= $stock ?>)
                                            </span>
                                        <?php endif; ?>
                                        <span class="text-[9px] text-slate-400 mt-0.5">Seuil alerte :
                                            <?= $minAlert ?></span>
                                    </div>
                                </td>

                                <!-- Boutons d'Action -->
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">

                                        <!-- Approvisionnement rapide -->
                                        <button type="button"
                                            onclick='openRestockModal(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                            class="px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-semibold flex items-center gap-1 transition-colors"
                                            title="Réapprovisionner le stock">
                                            <i class="fa-solid fa-plus text-[10px]"></i>
                                            <span>Stock</span>
                                        </button>

                                        <!-- Modification -->
                                        <button type="button"
                                            onclick='openEditModal(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                            class="w-7 h-7 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg flex items-center justify-center transition-colors"
                                            title="Modifier le produit">
                                            <i class="fa-regular fa-pen-to-square"></i>
                                        </button>

                                        <!-- Suppression -->
                                        <form method="POST"
                                            onsubmit="return confirm('Confirmez-vous la suppression de ce produit ?');"
                                            class="inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <button type="submit"
                                                class="w-7 h-7 bg-slate-100 hover:bg-red-50 hover:text-red-600 text-slate-400 rounded-lg flex items-center justify-center transition-colors"
                                                title="Supprimer">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Message si aucun produit trouvé -->
            <div id="noProductsFoundNotice" class="hidden py-12 text-center text-slate-400">
                <i class="fa-solid fa-box-open text-4xl mb-2 text-slate-300"></i>
                <p class="text-sm font-semibold text-slate-600">Aucun produit ne correspond aux filtres</p>
                <p class="text-xs text-slate-400">Modifiez votre recherche ou ajoutez un nouveau produit.</p>
            </div>
        </section>

    </main>

    <!-- ================= MODALE 1 : NOUVEAU PRODUIT ================= -->
    <div id="createProductModal"
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div
                        class="w-8 h-8 rounded-lg bg-brand-100 text-brand-600 flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-plus"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">Nouveau Produit au Catalogue</h3>
                </div>
                <button type="button" onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>

            <form method="POST" class="p-6 space-y-4 overflow-y-auto">
                <input type="hidden" name="action" value="create">

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Désignation du Produit *</label>
                    <input type="text" name="name" required placeholder="Ex: Huile d'Olive Vierge 1L"
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Catégorie</label>
                        <select name="category_id"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <?php foreach ($categories as $id => $name): ?>
                                <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Code-barres / EAN</label>
                        <input type="text" name="barcode" placeholder="Ex: 301234567890"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Prix d'Achat Fournisseur
                            (FCFA)</label>
                        <input type="number" step="0.01" min="0" name="purchase_price" required placeholder="0.00"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Prix de Vente Client (FCFA) *</label>
                        <input type="number" step="0.01" min="0" name="unit_price" required placeholder="0.00"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Stock Initial en Rayon</label>
                        <input type="number" min="0" name="stock_quantity" value="10" required
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Seuil Alerte Réappro</label>
                        <input type="number" min="1" name="min_stock_alert" value="5" required
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeCreateModal()"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
                        Annuler
                    </button>
                    <button type="submit"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-600/20 transition-colors">
                        Enregistrer le Produit
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- ================= MODALE 2 : MODIFICATION DU PRODUIT ================= -->
    <div id="editProductModal"
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div
                        class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center text-sm font-bold">
                        <i class="fa-regular fa-pen-to-square"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">Modifier le Produit</h3>
                </div>
                <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>

            <form method="POST" class="p-6 space-y-4 overflow-y-auto">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="product_id" id="editProductId">

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Désignation du Produit *</label>
                    <input type="text" name="name" id="editProductName" required
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Catégorie</label>
                        <select name="category_id" id="editProductCategory"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <?php foreach ($categories as $id => $name): ?>
                                <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Code-barres / EAN</label>
                        <input type="text" name="barcode" id="editProductBarcode"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Prix d'Achat (FCFA)</label>
                        <input type="number" step="0.01" min="0" name="purchase_price" id="editProductPurchasePrice"
                            required
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Prix de Vente (FCFA) *</label>
                        <input type="number" step="0.01" min="0" name="unit_price" id="editProductUnitPrice" required
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Seuil Alerte Stock Faible</label>
                    <input type="number" min="1" name="min_stock_alert" id="editProductMinAlert" required
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeEditModal()"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
                        Annuler
                    </button>
                    <button type="submit"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 shadow-md shadow-amber-600/20 transition-colors">
                        Mettre à Jour
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- ================= MODALE 3 : RÉAPPROVISIONNEMENT RAPIDE ================= -->
    <div id="restockModal"
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full overflow-hidden flex flex-col">
            <div class="px-6 py-4 bg-emerald-50 border-b border-emerald-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div
                        class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                    </div>
                    <h3 class="text-sm font-bold text-emerald-950">Réapprovisionner</h3>
                </div>
                <button type="button" onclick="closeRestockModal()" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>

            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="restock">
                <input type="hidden" name="product_id" id="restockProductId">

                <div>
                    <span class="text-xs text-slate-400 block">Produit sélectionné :</span>
                    <strong class="text-sm font-bold text-slate-900 block mt-0.5" id="restockProductName">--</strong>
                    <div class="mt-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs flex justify-between">
                        <span class="text-slate-500">Stock actuel en rayon :</span>
                        <strong class="font-bold text-slate-800" id="restockCurrentStock">0</strong>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Quantité livrée à ajouter (+)</label>
                    <input type="number" min="1" step="1" name="quantity_to_add" id="restockQuantityInput" value="10"
                        required oninput="updateRestockPreview()"
                        class="w-full px-3 py-2.5 bg-emerald-50/50 border border-emerald-300 rounded-xl text-base font-black text-emerald-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <div
                    class="p-2.5 bg-emerald-50 rounded-xl border border-emerald-200 text-xs flex justify-between items-center text-emerald-900">
                    <span>Nouveau stock prévu :</span>
                    <strong class="text-sm font-black text-emerald-700" id="restockNewStockPreview">0</strong>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeRestockModal()"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
                        Annuler
                    </button>
                    <button type="submit"
                        class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition-colors">
                        Valider l'Approvisionnement
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 mt-12 py-5 text-center text-xs text-slate-400">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <p>&copy; <?= date('Y') ?> <strong>Gestion Boutique</strong> - Système Point de Vente & Gestion des Stocks
            </p>
            <p class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-slate-600">Base MySQL connectée (127.0.0.1)</span>
            </p>
        </div>
    </footer>

    <!-- Script de filtrage et de gestion des modales -->
    <script>
        // Filtrage en direct dans le tableau
        function filterTable() {
            const searchVal = document.getElementById('tableSearchInput').value.trim().toLowerCase();
            const catVal = document.getElementById('categoryFilter').value;
            const stockVal = document.getElementById('stockStatusFilter').value;
            const rows = document.querySelectorAll('.product-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                const barcode = row.getAttribute('data-barcode') || '';
                const category = row.getAttribute('data-category');
                const status = row.getAttribute('data-status');

                const matchesSearch = !searchVal || name.includes(searchVal) || barcode.includes(searchVal);
                const matchesCat = (catVal === 'all' || category === catVal);
                const matchesStock = (stockVal === 'all' || status === stockVal);

                if (matchesSearch && matchesCat && matchesStock) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const notice = document.getElementById('noProductsFoundNotice');
            notice.classList.toggle('hidden', visibleCount > 0);
        }

        // ================= MODALE CRÉATION =================
        function openCreateModal() {
            document.getElementById('createProductModal').classList.remove('hidden');
        }
        function closeCreateModal() {
            document.getElementById('createProductModal').classList.add('hidden');
        }

        // ================= MODALE MODIFICATION =================
        function openEditModal(product) {
            document.getElementById('editProductId').value = product.id;
            document.getElementById('editProductName').value = product.name;
            document.getElementById('editProductCategory').value = product.category_id || 1;
            document.getElementById('editProductBarcode').value = product.barcode || '';
            document.getElementById('editProductPurchasePrice').value = product.purchase_price;
            document.getElementById('editProductUnitPrice').value = product.unit_price;
            document.getElementById('editProductMinAlert').value = product.min_stock_alert;

            document.getElementById('editProductModal').classList.remove('hidden');
        }
        function closeEditModal() {
            document.getElementById('editProductModal').classList.add('hidden');
        }

        // ================= MODALE RÉAPPROVISIONNEMENT =================
        let currentRestockBaseStock = 0;

        function openRestockModal(product) {
            currentRestockBaseStock = parseInt(product.stock_quantity);
            document.getElementById('restockProductId').value = product.id;
            document.getElementById('restockProductName').textContent = product.name;
            document.getElementById('restockCurrentStock').textContent = `${currentRestockBaseStock} unité(s)`;
            document.getElementById('restockQuantityInput').value = '10';
            updateRestockPreview();

            document.getElementById('restockModal').classList.remove('hidden');
            document.getElementById('restockQuantityInput').focus();
        }
        function closeRestockModal() {
            document.getElementById('restockModal').classList.add('hidden');
        }
        function updateRestockPreview() {
            const addQty = parseInt(document.getElementById('restockQuantityInput').value) || 0;
            const newTotal = currentRestockBaseStock + addQty;
            document.getElementById('restockNewStockPreview').textContent = `${newTotal} unité(s)`;
        }

        // Fermeture des modales avec Echap
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeCreateModal();
                closeEditModal();
                closeRestockModal();
            }
        });
    </script>
</body>

</html>