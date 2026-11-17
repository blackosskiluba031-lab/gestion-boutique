<?php
// includes/header_nav.php
// Barre de navigation supérieure partagée pour POSSIBILITY TECH

if (!isset($appName)) {
    $appName = "POSSIBILITY TECH";
}
if (!isset($currentStoreName)) {
    $currentStoreName = "Boutique";
}
if (!isset($activePage)) {
    $activePage = 'dashboard';
}
?>
<!-- Top Navigation Bar -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm no-print">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            
            <!-- Logo & Nom de l'application + Nom de la boutique -->
            <div class="flex items-center space-x-3">
                <a href="dashboard.php" class="flex items-center gap-3 group">
                    <!-- Logo Officiel Possibility Tech -->
                    <div class="w-10 h-10 rounded-xl bg-white p-0.5 border border-slate-200 shadow-xs flex items-center justify-center group-hover:scale-105 transition-transform overflow-hidden">
                        <img src="assets/images/logo_icon.png" alt="POSSIBILITY TECH" class="h-9 w-9 object-contain">
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:gap-2">
                        <span class="text-sm sm:text-base font-black tracking-tight text-slate-900 group-hover:text-brand-600 transition-colors uppercase">
                            <?= htmlspecialchars($appName) ?>
                        </span>
                        <div class="flex items-center gap-1.5">
                            <span class="hidden sm:inline text-slate-300 font-light">•</span>
                            <!-- Nom de la Boutique connectée bien visible -->
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-xs font-bold bg-brand-50 text-brand-700 border border-brand-200 shadow-xs" title="Boutique connectée">
                                <i class="fa-solid fa-store text-[10px] text-brand-500"></i>
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

            <!-- Menu de droite : Espace Boutique & Déconnexion -->
            <div class="flex items-center space-x-2.5">
                
                <!-- Bouton modifier les informations de la boutique -->
                <button 
                    type="button" 
                    onclick="openStoreRenameModal()"
                    class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center gap-1.5 text-xs font-semibold transition-colors"
                    title="Paramètres boutique"
                >
                    <i class="fa-solid fa-gear text-slate-500"></i>
                    <span class="hidden lg:inline">Paramètres</span>
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

                <!-- Bouton Déconnexion du compte boutique -->
                <a 
                    href="logout.php" 
                    onclick="return confirm('Voulez-vous vous déconnecter de votre espace boutique ?');"
                    class="w-9 h-9 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 flex items-center justify-center text-xs transition-colors"
                    title="Se déconnecter"
                >
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>

            </div>
        </div>
    </div>
</header>

<!-- Modale de personnalisation du nom de la boutique -->
<div id="storeRenameModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 no-print">
    <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full overflow-hidden flex flex-col animate-in fade-in zoom-in duration-150">
        <div class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-brand-100 text-brand-600 flex items-center justify-center text-xs font-bold">
                    <i class="fa-solid fa-store"></i>
                </div>
                <h3 class="text-xs font-bold text-slate-900">Paramètres de la Boutique</h3>
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
                    placeholder="Ex: Boutique Prestige..."
                >
                <p class="text-[10px] text-slate-400 mt-1">Ce nom s'affiche dans l'en-tête et sur tous les tickets de caisse imprimés.</p>
            </div>

            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1">
                <div class="flex justify-between text-slate-500">
                    <span>Identifiant / Email :</span>
                    <strong class="text-slate-800"><?= htmlspecialchars($currentStoreEmail) ?></strong>
                </div>
                <div class="flex justify-between text-slate-500">
                    <span>ID Boutique :</span>
                    <span class="font-mono text-brand-600 font-bold">#<?= $currentStoreId ?></span>
                </div>
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
