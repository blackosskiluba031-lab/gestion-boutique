<?php
// admin/dashboard.php — Panneau de gestion des boutiques POSSIBILITY TECH

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

// Vérification auth admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php'); exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Store.php';

$pdo        = require __DIR__ . '/../config/database.php';
$storeModel = new Store($pdo);

$adminUsername = $_SESSION['admin_username'] ?? 'Admin';
$flash = null;

// ── Actions POST ──
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action  = $_POST['action'] ?? '';
    $storeId = (int) ($_POST['store_id'] ?? 0);

    if ($action === 'toggle_active' && $storeId > 0) {
        $store = $storeModel->getById($storeId);
        if ($store) {
            $storeModel->setActive($storeId, !$store['is_active']);
            $state = $store['is_active'] ? 'désactivée' : 'activée';
            $flash = ['type' => 'success', 'msg' => "Boutique \"{$store['name']}\" {$state} avec succès."];
        }
    }

    if ($action === 'extend_trial' && $storeId > 0) {
        $days = max(1, (int) ($_POST['days'] ?? 15));
        $store = $storeModel->getById($storeId);
        if ($store) {
            $storeModel->extendTrial($storeId, $days);
            $flash = ['type' => 'success', 'msg' => "Période d'essai de \"{$store['name']}\" prolongée de {$days} jours."];
        }
    }

    if ($action === 'delete' && $storeId > 0) {
        // Soft delete : désactiver uniquement (pas de suppression réelle en production)
        $storeModel->setActive($storeId, false);
        $flash = ['type' => 'warning', 'msg' => "Boutique #{$storeId} désactivée (pas supprimée définitivement)."];
    }

    // Redirect PRG
    $_SESSION['admin_flash'] = $flash;
    header('Location: dashboard.php'); exit;
}

if (isset($_SESSION['admin_flash'])) {
    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
}

// ── Données ──
$allStores    = $storeModel->getAll();
$totalStores  = count($allStores);
$activeCount  = count(array_filter($allStores, fn($s) => $s['is_active']));
$trialCount   = count(array_filter($allStores, fn($s) => !$storeModel->isTrialExpired($s) && !empty($s['trial_ends_at'])));
$expiredCount = count(array_filter($allStores, fn($s) => $storeModel->isTrialExpired($s)));

// Ventes globales
$totalRevenueRow = $pdo->query("SELECT COALESCE(SUM(total_amount),0) as rev, COALESCE(SUM(total_profit),0) as prof FROM sales")->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Admin Panel | POSSIBILITY TECH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{brand:{50:'#eff6ff',100:'#dbeafe',500:'#3b82f6',600:'#2563eb',700:'#1d4ed8'}}}}}</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f1f5f9; }
        .stat-card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; }
        .table-row:hover { background: #f8fafc; }
        input[type=number]::-webkit-inner-spin-button { opacity:1; }
    </style>
</head>
<body class="min-h-screen font-sans text-slate-800 antialiased">

    <!-- ── TOP NAV ADMIN ── -->
    <header class="bg-slate-900 text-white px-4 sm:px-6 py-3 flex items-center justify-between gap-3 shadow-lg sticky top-0 z-30">
        <div class="flex items-center gap-3">
            <img src="../assets/images/logo_icon.png" alt="" class="w-8 h-8 rounded-lg">
            <div>
                <span class="text-xs font-black tracking-tight text-white">POSSIBILITY TECH</span>
                <span class="ml-2 px-2 py-0.5 bg-blue-600 text-white text-[10px] font-black rounded-md uppercase">Admin</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs text-slate-400 hidden sm:block">
                <i class="fa-solid fa-user-shield mr-1"></i><?= htmlspecialchars($adminUsername) ?>
            </span>
            <a href="../login.php" target="_blank" class="p-2 bg-white/10 hover:bg-white/20 rounded-xl text-xs font-semibold transition-colors" title="Voir le site public">
                <i class="fa-solid fa-external-link"></i>
            </a>
            <a href="logout.php" class="p-2 bg-red-600/80 hover:bg-red-600 rounded-xl text-xs font-semibold transition-colors" title="Déconnexion">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-3 sm:px-6 py-6 space-y-6">

        <!-- Flash message -->
        <?php if ($flash): ?>
        <div class="p-4 rounded-2xl flex items-start gap-3 text-sm font-semibold <?= $flash['type'] === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-amber-50 border border-amber-200 text-amber-800' ?>">
            <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-500' : 'fa-triangle-exclamation text-amber-500' ?> mt-0.5 flex-shrink-0"></i>
            <?= htmlspecialchars($flash['msg']) ?>
        </div>
        <?php endif; ?>

        <!-- En-tête titre -->
        <div>
            <h1 class="text-2xl font-black text-slate-900">Panneau d'Administration</h1>
            <p class="text-xs text-slate-500 mt-1">Gérez les boutiques, les abonnements et les accès à la plateforme.</p>
        </div>

        <!-- ── STATISTIQUES GLOBALES ── -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <!-- Total boutiques -->
            <div class="stat-card p-5 flex flex-col gap-1">
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-2">
                    <i class="fa-solid fa-store text-lg"></i>
                </div>
                <strong class="text-3xl font-black text-slate-900"><?= $totalStores ?></strong>
                <span class="text-xs font-semibold text-slate-500">Boutiques total</span>
            </div>

            <!-- Boutiques actives -->
            <div class="stat-card p-5 flex flex-col gap-1">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2">
                    <i class="fa-solid fa-circle-check text-lg"></i>
                </div>
                <strong class="text-3xl font-black text-slate-900"><?= $activeCount ?></strong>
                <span class="text-xs font-semibold text-slate-500">Actives</span>
            </div>

            <!-- En période d'essai -->
            <div class="stat-card p-5 flex flex-col gap-1">
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-2">
                    <i class="fa-regular fa-clock text-lg"></i>
                </div>
                <strong class="text-3xl font-black text-slate-900"><?= $trialCount ?></strong>
                <span class="text-xs font-semibold text-slate-500">En période d'essai</span>
            </div>

            <!-- Essais expirés -->
            <div class="stat-card p-5 flex flex-col gap-1">
                <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center mb-2">
                    <i class="fa-solid fa-ban text-lg"></i>
                </div>
                <strong class="text-3xl font-black text-slate-900"><?= $expiredCount ?></strong>
                <span class="text-xs font-semibold text-slate-500">Essais expirés</span>
            </div>
        </div>

        <!-- Revenus globaux -->
        <div class="stat-card p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Chiffre d'affaires global (toutes boutiques)</p>
                <strong class="text-2xl sm:text-3xl font-black text-slate-900"><?= number_format((float)$totalRevenueRow['rev'], 0, ',', ' ') ?> FC</strong>
            </div>
            <div class="text-right">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Bénéfice global estimé</p>
                <strong class="text-2xl sm:text-3xl font-black text-emerald-700"><?= number_format((float)$totalRevenueRow['prof'], 0, ',', ' ') ?> FC</strong>
            </div>
        </div>

        <!-- ── TABLE DES BOUTIQUES ── -->
        <div class="stat-card overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between gap-3">
                <h2 class="text-base font-black text-slate-900">
                    <i class="fa-solid fa-list text-brand-500 mr-2"></i>Toutes les boutiques
                </h2>
                <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-bold rounded-full"><?= $totalStores ?> boutiques</span>
            </div>

            <!-- Responsive scroll -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[700px]">
                    <thead>
                        <tr class="bg-slate-50 text-left">
                            <th class="px-5 py-3 text-xs font-black text-slate-500 uppercase tracking-wide">ID</th>
                            <th class="px-5 py-3 text-xs font-black text-slate-500 uppercase tracking-wide">Boutique</th>
                            <th class="px-5 py-3 text-xs font-black text-slate-500 uppercase tracking-wide">Identifiant</th>
                            <th class="px-5 py-3 text-xs font-black text-slate-500 uppercase tracking-wide">Statut</th>
                            <th class="px-5 py-3 text-xs font-black text-slate-500 uppercase tracking-wide">Fin d'essai</th>
                            <th class="px-5 py-3 text-xs font-black text-slate-500 uppercase tracking-wide">Inscription</th>
                            <th class="px-5 py-3 text-xs font-black text-slate-500 uppercase tracking-wide text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($allStores as $store):
                            $isExpired = $storeModel->isTrialExpired($store);
                            $daysLeft  = $storeModel->trialDaysLeft($store);
                        ?>
                        <tr class="table-row transition-colors">
                            <td class="px-5 py-4 text-xs font-mono text-slate-400">#<?= $store['id'] ?></td>
                            <td class="px-5 py-4">
                                <div class="font-bold text-slate-900"><?= htmlspecialchars($store['name']) ?></div>
                                <?php if ($store['phone']): ?>
                                <div class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($store['phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4">
                                <span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-mono font-bold rounded-lg">
                                    @<?= htmlspecialchars($store['username']) ?>
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <?php if (!$store['is_active']): ?>
                                <span class="px-2.5 py-1 bg-red-50 text-red-700 text-xs font-black rounded-full border border-red-200">
                                    <i class="fa-solid fa-ban mr-1"></i>Suspendu
                                </span>
                                <?php elseif ($isExpired): ?>
                                <span class="px-2.5 py-1 bg-orange-50 text-orange-700 text-xs font-black rounded-full border border-orange-200">
                                    <i class="fa-solid fa-clock mr-1"></i>Essai expiré
                                </span>
                                <?php elseif ($daysLeft > 0): ?>
                                <span class="px-2.5 py-1 bg-amber-50 text-amber-700 text-xs font-black rounded-full border border-amber-200">
                                    <i class="fa-regular fa-clock mr-1"></i>Essai : <?= $daysLeft ?>j
                                </span>
                                <?php else: ?>
                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-black rounded-full border border-emerald-200">
                                    <i class="fa-solid fa-circle-check mr-1"></i>Abonné
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-500">
                                <?= $store['trial_ends_at'] ? date('d/m/Y', strtotime($store['trial_ends_at'])) : '—' ?>
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-500">
                                <?= date('d/m/Y', strtotime($store['created_at'])) ?>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-center gap-2 flex-wrap">

                                    <!-- Toggle actif/suspendu -->
                                    <form method="POST" action="dashboard.php" onsubmit="return confirm('Confirmer ?')">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="store_id" value="<?= $store['id'] ?>">
                                        <button type="submit"
                                            class="px-3 py-1.5 text-xs font-bold rounded-xl transition-colors <?= $store['is_active'] ? 'bg-red-50 text-red-700 border border-red-200 hover:bg-red-100' : 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' ?>">
                                            <?= $store['is_active'] ? '<i class="fa-solid fa-ban mr-1"></i>Suspendre' : '<i class="fa-solid fa-check mr-1"></i>Activer' ?>
                                        </button>
                                    </form>

                                    <!-- Prolonger essai -->
                                    <button type="button"
                                        onclick="showExtend(<?= $store['id'] ?>, '<?= htmlspecialchars($store['name'], ENT_QUOTES) ?>')"
                                        class="px-3 py-1.5 text-xs font-bold rounded-xl bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition-colors">
                                        <i class="fa-regular fa-calendar-plus mr-1"></i>Prolonger
                                    </button>

                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($allStores)): ?>
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-400">
                                Aucune boutique enregistrée pour l'instant.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Infos admin -->
        <div class="text-center text-xs text-slate-400 pb-4">
            <i class="fa-solid fa-shield-halved mr-1"></i>
            Panneau réservé aux administrateurs POSSIBILITY TECH — Connecté en tant que <strong><?= htmlspecialchars($adminUsername) ?></strong>
        </div>
    </div>

    <!-- ── MODAL : Prolonger l'essai ── -->
    <div id="extendModal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm p-6">
            <h3 class="text-base font-black text-slate-900 mb-1">Prolonger la période d'essai</h3>
            <p id="extendShopName" class="text-xs text-slate-500 mb-5"></p>

            <form method="POST" action="dashboard.php">
                <input type="hidden" name="action" value="extend_trial">
                <input type="hidden" id="extendStoreId" name="store_id" value="">

                <label class="block text-xs font-bold text-slate-700 mb-1.5">Nombre de jours à ajouter</label>
                <input type="number" name="days" value="15" min="1" max="365"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold mb-5 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-200 transition-all">

                <div class="flex gap-3">
                    <button type="button" onclick="hideExtend()"
                        class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold rounded-xl transition-colors">
                        Annuler
                    </button>
                    <button type="submit"
                        class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-600/25 transition-colors">
                        <i class="fa-regular fa-calendar-plus mr-1"></i>Prolonger
                    </button>
                </div>
            </form>
        </div>
    </div>

<script>
function showExtend(id, name) {
    document.getElementById('extendStoreId').value = id;
    document.getElementById('extendShopName').textContent = 'Boutique : ' + name;
    document.getElementById('extendModal').classList.remove('hidden');
}
function hideExtend() {
    document.getElementById('extendModal').classList.add('hidden');
}
document.getElementById('extendModal').addEventListener('click', function(e) {
    if (e.target === this) hideExtend();
});
</script>

</body>
</html>
