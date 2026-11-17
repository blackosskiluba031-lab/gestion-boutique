<?php
// includes/header_nav.php
// Barre de navigation supérieure partagée pour POSSYBILITY TECH

if (!isset($appName)) {
    $appName = "POSSYBILITY TECH";
}
if (!isset($currentStoreName)) {
    $currentStoreName = "Boutique Prestige";
}
if (!isset($activePage)) {
    $activePage = 'dashboard';
}
if (!isset($availableStores)) {
    $availableStores = [];
}
?>
<!-- Top Navigation Bar -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm no-print">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            
            <!-- Logo & Nom de l'application + Nom de la boutique -->
            <div class="flex items-center space-x-3">
                <a href="dashboard.php" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-brand-700 via-brand-600 to-indigo-600 text-white flex items-center justify-center shadow-md shadow-brand-500/20 group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-store text-lg"></i>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:gap-2">
                        <span class="text-sm sm:text-base font-black tracking-tight text-slate-900 group-hover:text-brand-600 transition-colors uppercase">
                            <?= htmlspecialchars($appName) ?>
                        </span>
                        <div class="flex items-center gap-1.5">
                            <span class="hidden sm:inline text-slate-300 font-light">•</span>
                            <!-- Nom de la Boutique bien visible -->
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-xs font-bold bg-brand-50 text-brand-700 border border-brand-200 shadow-sm" title="Boutique actuellement active">
                                <i class="fa-solid fa-shop text-[10px] text-brand-500"></i>
                                <span><?= htmlspecialchars($currentStoreName) ?></span>
                            </span>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Navigation Tabs (Dashboard, Caisse, Stocks, Historique) -->
            <nav class="hidden md:flex items-center space-x-1.5">
                <a href="dashboard.php" class="px-3 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?= $activePage === 'dashboard' ? 'bg-brand-50 text-brand-700 border border-brand-200 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                    <i class="fa-solid fa-chart-line text-[11px]"></i>
                    <span>Dashboard</span>
                </a>
                <a href="cashier.php" class="px-3 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?= $activePage === 'cashier' ? 'bg-brand-50 text-brand-700 border border-brand-200 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                    <i class="fa-solid fa-cash-register text-[11px]"></i>
                    <span>Caisse & Vente</span>
                </a>
                <a href="products.php" class="px-3 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?= $activePage === 'products' ? 'bg-brand-50 text-brand-700 border border-brand-200 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                    <i class="fa-solid fa-boxes-stacked text-[11px]"></i>
                    <span>Stocks & Produits</span>
                </a>
                <a href="sales_history.php" class="px-3 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 <?= $activePage === 'sales_history' ? 'bg-brand-50 text-brand-700 border border-brand-200 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                    <i class="fa-solid fa-clock-rotate-left text-[11px]"></i>
                    <span>Historique</span>
                </a>
            </nav>

            <!-- Menu de droite : Sélecteur de Boutique & Accès Caisse -->
            <div class="flex items-center space-x-2.5">
                
                <!-- Sélecteur de Boutique (si plusieurs boutiques ou modification du nom) -->
                <?php if (!empty($availableStores) && count($availableStores) > 1): ?>
                    <div class="relative inline-block text-left">
                        <select 
                            onchange="window.location.href = '?switch_store=' + this.value;"
                            class="text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-xl px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-500 cursor-pointer transition-colors"
                            title="Changer de boutique"
                        >
                            <?php foreach ($availableStores as $st): ?>
                                <option value="<?= $st['id'] ?>" <?= ($st['id'] == ($currentStore['id'] ?? 1)) ? 'selected' : '' ?>>
                                    🏪 <?= htmlspecialchars($st['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Bouton modifier le nom de la boutique -->
                <button 
                    type="button" 
                    onclick="openStoreRenameModal()"
                    class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-xs transition-colors"
                    title="Renommer la boutique"
                >
                    <i class="fa-solid fa-pen-to-square"></i>
                </button>

                <!-- Bouton action : Caisse Rapide (sauf si déjà sur la page caisse) -->
                <?php if ($activePage !== 'cashier'): ?>
                    <a 
                        href="cashier.php" 
                        class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 flex items-center gap-2 transition-all active:scale-[0.98]"
                    >
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span class="hidden sm:inline">Caisse</span>
                    </a>
                <?php endif; ?>

            </div>
        </div>
    </div>
</header>

<!-- Modale de personnalisation du nom de la boutique -->
<div id="storeRenameModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 no-print">
    <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full overflow-hidden flex flex-col animate-in fade-in zoom-in duration-150">
        <div class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-brand-100 text-brand-600 flex items-center justify-center text-xs font-bold">
                    <i class="fa-solid fa-store"></i>
                </div>
                <h3 class="text-xs font-bold text-slate-900">Renommer la Boutique</h3>
            </div>
            <button type="button" onclick="closeStoreRenameModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action_update_store_name" value="1">
            <input type="hidden" name="store_id" value="<?= $currentStore['id'] ?? 1 ?>">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nom affiché de votre boutique :</label>
                <input 
                    type="text" 
                    name="store_name" 
                    value="<?= htmlspecialchars($currentStoreName) ?>" 
                    required 
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                    placeholder="Ex: Boutique Prestige, Supermarché Étoile..."
                >
                <p class="text-[10px] text-slate-400 mt-1">Ce nom apparaîtra en haut sur la barre de navigation et sur les tickets de caisse.</p>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="closeStoreRenameModal()" class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 shadow-md shadow-brand-600/20 transition-colors">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openStoreRenameModal() {
        document.getElementById('storeRenameModal').classList.remove('hidden');
    }
    function closeStoreRenameModal() {
        document.getElementById('storeRenameModal').classList.add('hidden');
    }
</script>
