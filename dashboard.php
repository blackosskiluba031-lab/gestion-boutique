<?php
// dashboard.php - Tableau de bord analytique et statistiques

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/store_context.php';

$pdo = require __DIR__ . '/config/database.php';
$storeId = $currentStoreId; // Boutique active courante

// ================= 1. RÉCUPÉRATION DES KPI FINANCIERS =================

// CA Aujourd'hui & Bénéfice Aujourd'hui
$stmtToday = $pdo->prepare("
    SELECT 
        COALESCE(SUM(total_amount), 0) AS ca_today,
        COALESCE(SUM(total_profit), 0) AS profit_today,
        COUNT(*) AS count_today
    FROM sales 
    WHERE store_id = :store_id AND DATE(created_at) = CURDATE()
");
$stmtToday->execute(['store_id' => $storeId]);
$todayStats = $stmtToday->fetch();

// CA du Mois & Bénéfice du Mois
$stmtMonth = $pdo->prepare("
    SELECT 
        COALESCE(SUM(total_amount), 0) AS ca_month,
        COALESCE(SUM(total_profit), 0) AS profit_month,
        COUNT(*) AS count_month
    FROM sales 
    WHERE store_id = :store_id 
      AND MONTH(created_at) = MONTH(CURDATE()) 
      AND YEAR(created_at) = YEAR(CURDATE())
");
$stmtMonth->execute(['store_id' => $storeId]);
$monthStats = $stmtMonth->fetch();

// Statistiques globales (Total ventes, CA global, Bénéfice global)
$stmtGlobal = $pdo->prepare("
    SELECT 
        COALESCE(SUM(total_amount), 0) AS ca_global,
        COALESCE(SUM(total_profit), 0) AS profit_global,
        COUNT(*) AS count_global
    FROM sales 
    WHERE store_id = :store_id
");
$stmtGlobal->execute(['store_id' => $storeId]);
$globalStats = $stmtGlobal->fetch();

// Valeur totale du stock
$stmtStock = $pdo->prepare("
    SELECT 
        COALESCE(SUM(stock_quantity * purchase_price), 0) AS stock_cost_value,
        COALESCE(SUM(stock_quantity * unit_price), 0) AS stock_selling_value,
        COALESCE(SUM(stock_quantity), 0) AS total_units,
        COUNT(*) AS total_references
    FROM products 
    WHERE store_id = :store_id
");
$stmtStock->execute(['store_id' => $storeId]);
$stockStats = $stmtStock->fetch();

// Calcul du taux de marge moyen global
$globalMarginPercent = ($globalStats['ca_global'] > 0) 
    ? round(($globalStats['profit_global'] / $globalStats['ca_global']) * 100, 1) 
    : 0;

// ================= 2. DONNÉES POUR LES GRAPHIQUES (CHART.JS) =================

// Graphique 1 : Évolution du CA et des Bénéfices sur les 7 derniers jours
$stmtChart7Days = $pdo->prepare("
    SELECT 
        DATE(created_at) AS sale_date,
        COALESCE(SUM(total_amount), 0) AS daily_ca,
        COALESCE(SUM(total_profit), 0) AS daily_profit
    FROM sales
    WHERE store_id = :store_id 
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
    ORDER BY sale_date ASC
");
$stmtChart7Days->execute(['store_id' => $storeId]);
$raw7Days = $stmtChart7Days->fetchAll();

// Indexation des 7 derniers jours pour garantir que chaque jour ait une entrée (même à 0)
$last7DaysLabels = [];
$last7DaysCA = [];
$last7DaysProfit = [];

for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    // Label français (ex: Lun 16/11)
    $formattedLabel = date('d/m', strtotime($d));
    $last7DaysLabels[] = $formattedLabel;

    $found = false;
    foreach ($raw7Days as $row) {
        if ($row['sale_date'] === $d) {
            $last7DaysCA[] = (float) $row['daily_ca'];
            $last7DaysProfit[] = (float) $row['daily_profit'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $last7DaysCA[] = 0.0;
        $last7DaysProfit[] = 0.0;
    }
}

// Graphique 2 : Répartition des modes de paiement
$stmtPayment = $pdo->prepare("
    SELECT 
        payment_method,
        COALESCE(SUM(total_amount), 0) AS total_amount,
        COUNT(*) AS count_transactions
    FROM sales
    WHERE store_id = :store_id
    GROUP BY payment_method
");
$stmtPayment->execute(['store_id' => $storeId]);
$paymentStats = $stmtPayment->fetchAll();

$paymentLabels = [];
$paymentAmounts = [];
$methodNames = [
    'cash'         => 'Espèces',
    'mobile_money' => 'Mobile Money',
    'card'         => 'Carte Bancaire',
    'credit'       => 'Crédit Client'
];

foreach ($paymentStats as $p) {
    $paymentLabels[] = $methodNames[$p['payment_method']] ?? ucfirst($p['payment_method']);
    $paymentAmounts[] = (float) $p['total_amount'];
}

// Graphique 3 : Top 5 des produits les plus vendus
$stmtTopProducts = $pdo->prepare("
    SELECT 
        p.name,
        COALESCE(SUM(si.quantity), 0) AS total_qty,
        COALESCE(SUM(si.quantity * si.unit_price), 0) AS total_revenue
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN sales s ON si.sale_id = s.id
    WHERE s.store_id = :store_id
    GROUP BY p.id, p.name
    ORDER BY total_qty DESC
    LIMIT 5
");
$stmtTopProducts->execute(['store_id' => $storeId]);
$topProducts = $stmtTopProducts->fetchAll();

$topProductNames = [];
$topProductQuantities = [];
foreach ($topProducts as $tp) {
    $topProductNames[] = $tp['name'];
    $topProductQuantities[] = (int) $tp['total_qty'];
}

// ================= 3. ALERTES STOCKS FAIBLES =================
$stmtAlerts = $pdo->prepare("
    SELECT id, name, barcode, unit_price, stock_quantity, min_stock_alert
    FROM products
    WHERE store_id = :store_id AND stock_quantity <= min_stock_alert
    ORDER BY stock_quantity ASC
    LIMIT 6
");
$stmtAlerts->execute(['store_id' => $storeId]);
$lowStockProducts = $stmtAlerts->fetchAll();

// ================= 4. DERNIÈRES TRANSACTIONS =================
$stmtRecent = $pdo->prepare("
    SELECT id, total_amount, total_profit, payment_method, created_at
    FROM sales
    WHERE store_id = :store_id
    ORDER BY id DESC
    LIMIT 6
");
$stmtRecent->execute(['store_id' => $storeId]);
$recentSales = $stmtRecent->fetchAll();

// Fonction d'aide pour formater les devises
function fmtMoney(float $amount): string {
    return number_format($amount, 2, ',', ' ') . ' FCFA';
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?= htmlspecialchars($appName) ?> • <?= htmlspecialchars($currentStoreName) ?></title>
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
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="min-h-full flex flex-col font-sans text-slate-800 antialiased">

    <!-- Top Navigation Bar Partagée -->
    <?php 
        $activePage = 'dashboard';
        require __DIR__ . '/includes/header_nav.php'; 
    ?>

    <!-- Contenu Principal -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- ================= LIGNE 1 : KPI CARDS (MÉTRIQUES CLÉS) ================= -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            
            <!-- KPI 1 : CA Aujourd'hui -->
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Ventes du Jour</span>
                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-black text-slate-900">
                        <?= fmtMoney((float)$todayStats['ca_today']) ?>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs">
                        <span class="text-slate-500">
                            <strong><?= (int)$todayStats['count_today'] ?></strong> vente(s) aujourd'hui
                        </span>
                        <span class="font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full text-[11px]">
                            +<?= fmtMoney((float)$todayStats['profit_today']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- KPI 2 : CA du Mois -->
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Chiffre d'Affaires Mois</span>
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-black text-slate-900">
                        <?= fmtMoney((float)$monthStats['ca_month']) ?>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs">
                        <span class="text-slate-500">
                            <strong><?= (int)$monthStats['count_month'] ?></strong> transactions ce mois
                        </span>
                        <span class="font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full text-[11px]">
                            <?= date('F Y') ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- KPI 3 : Bénéfice Net Réalisé & Marge -->
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Bénéfice Net Global</span>
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-sack-dollar"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-black text-emerald-700">
                        +<?= fmtMoney((float)$globalStats['profit_global']) ?>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs">
                        <span class="text-slate-500">Marge brute globale</span>
                        <span class="font-bold text-emerald-700 bg-emerald-100/70 px-2.5 py-0.5 rounded-full text-[11px]">
                            <i class="fa-solid fa-arrow-trend-up mr-1 text-[9px]"></i><?= $globalMarginPercent ?>%
                        </span>
                    </div>
                </div>
            </div>

            <!-- KPI 4 : Valeur Financière du Stock -->
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Valeur du Stock Actuel</span>
                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="text-2xl font-black text-slate-900">
                        <?= fmtMoney((float)$stockStats['stock_cost_value']) ?>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs">
                        <span class="text-slate-500">
                            <strong><?= (int)$stockStats['total_units'] ?></strong> articles (<?= (int)$stockStats['total_references'] ?> réf)
                        </span>
                        <span class="text-slate-400 text-[11px]" title="Valeur au prix de vente">
                            Vente: <?= fmtMoney((float)$stockStats['stock_selling_value']) ?>
                        </span>
                    </div>
                </div>
            </div>

        </section>


        <!-- ================= LIGNE 2 : GRAPHIQUES CHART.JS ================= -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Graphique Principal (2/3 largeur) : Évolution CA vs Bénéfice sur 7 jours -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-4 border-b border-slate-100 gap-2">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Évolution des Ventes & Bénéfices</h2>
                        <p class="text-xs text-slate-400">Performances quotidiennes sur les 7 derniers jours</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-blue-600"></span>
                            <span class="text-slate-600 font-medium">Chiffre d'Affaires</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                            <span class="text-slate-600 font-medium">Bénéfice Net</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 h-72 sm:h-80 w-full relative">
                    <canvas id="salesTrendsChart"></canvas>
                </div>
            </div>

            <!-- Graphique Secondaire (1/3 largeur) : Modes de Paiement -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                <div class="pb-4 border-b border-slate-100">
                    <h2 class="text-base font-bold text-slate-900">Modes de Règlement</h2>
                    <p class="text-xs text-slate-400">Répartition des encaissements par canal</p>
                </div>

                <div class="my-4 h-56 relative flex items-center justify-center">
                    <canvas id="paymentMethodsChart"></canvas>
                </div>

                <!-- Légende détaillée -->
                <div class="pt-3 border-t border-slate-100 grid grid-cols-2 gap-2 text-xs">
                    <?php foreach ($paymentStats as $p): ?>
                        <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                            <span class="text-slate-400 block text-[10px]"><?= htmlspecialchars($methodNames[$p['payment_method']] ?? $p['payment_method']) ?></span>
                            <strong class="text-slate-800 text-xs"><?= fmtMoney((float)$p['total_amount']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </section>


        <!-- ================= LIGNE 3 : TOP PRODUITS & ALERTES STOCKS & DERNIÈRES VENTES ================= -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Bloc 1 : Top 5 des produits les plus vendus (Chart Horizontal) -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                <div class="pb-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Top 5 Meilleures Ventes</h2>
                        <p class="text-xs text-slate-400">Articles les plus demandés en volume</p>
                    </div>
                    <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xs">
                        <i class="fa-solid fa-trophy"></i>
                    </span>
                </div>

                <div class="mt-4 h-64 relative">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>

            <!-- Bloc 2 : Alertes Stocks Faibles (Priorité Réapprovisionnement) -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col">
                <div class="pb-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Alertes de Stock</h2>
                        <p class="text-xs text-slate-400">Articles sous le seuil critique</p>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold <?= count($lowStockProducts) > 0 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' ?>">
                        <?= count($lowStockProducts) ?> alerte(s)
                    </span>
                </div>

                <div class="mt-4 divide-y divide-slate-100 flex-1 overflow-y-auto max-h-64 space-y-2">
                    <?php if (empty($lowStockProducts)): ?>
                        <div class="h-full flex flex-col items-center justify-center text-center py-8 text-slate-400">
                            <i class="fa-solid fa-circle-check text-emerald-500 text-3xl mb-2"></i>
                            <p class="text-xs font-medium text-slate-700">Tous les stocks sont optimaux !</p>
                            <p class="text-[10px] text-slate-400">Aucun produit sous le seuil d'alerte.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($lowStockProducts as $p): ?>
                            <?php 
                                $isZero = $p['stock_quantity'] <= 0;
                                $percent = $p['min_stock_alert'] > 0 ? min(100, round(($p['stock_quantity'] / $p['min_stock_alert']) * 100)) : 0;
                            ?>
                            <div class="pt-2 flex items-center justify-between">
                                <div class="min-w-0 pr-2">
                                    <h4 class="text-xs font-bold text-slate-800 truncate" title="<?= htmlspecialchars($p['name']) ?>">
                                        <?= htmlspecialchars($p['name']) ?>
                                    </h4>
                                    <div class="flex items-center gap-2 mt-1">
                                        <div class="w-20 bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                            <div class="h-full <?= $isZero ? 'bg-red-600' : 'bg-amber-500' ?>" style="width: <?= $percent ?>%"></div>
                                        </div>
                                        <span class="text-[10px] text-slate-400">Seuil: <?= $p['min_stock_alert'] ?></span>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <span class="px-2 py-0.5 rounded-md text-[11px] font-bold <?= $isZero ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800' ?>">
                                        <?= $p['stock_quantity'] ?> restant(s)
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bloc 3 : Dernières Transactions Récentes -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col">
                <div class="pb-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Dernières Ventes</h2>
                        <p class="text-xs text-slate-400">Flux d'encaissement récent</p>
                    </div>
                    <a href="cashier.php" class="text-xs text-brand-600 hover:text-brand-800 font-semibold flex items-center gap-1">
                        <span>Caisse</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>

                <div class="mt-4 divide-y divide-slate-100 flex-1 overflow-y-auto max-h-64 space-y-2">
                    <?php if (empty($recentSales)): ?>
                        <div class="h-full flex flex-col items-center justify-center text-center py-8 text-slate-400">
                            <i class="fa-solid fa-receipt text-3xl mb-2 text-slate-300"></i>
                            <p class="text-xs text-slate-500">Aucune vente enregistrée pour le moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <div class="pt-2 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center text-xs font-mono font-bold">
                                        #<?= str_pad((string)$sale['id'], 3, '0', STR_PAD_LEFT) ?>
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-800 block"><?= fmtMoney((float)$sale['total_amount']) ?></span>
                                        <span class="text-[10px] text-slate-400"><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 uppercase">
                                        <?= htmlspecialchars($methodNames[$sale['payment_method']] ?? $sale['payment_method']) ?>
                                    </span>
                                    <span class="block text-[10px] text-emerald-600 font-bold mt-0.5">
                                        +<?= fmtMoney((float)$sale['total_profit']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </section>

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 mt-12 py-5 text-center text-xs text-slate-400">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <p>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars($appName) ?></strong> • <?= htmlspecialchars($currentStoreName) ?> - Système Point de Vente & Gestion des Stocks</p>
            <p class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-slate-600">Base MySQL connectée (127.0.0.1)</span>
            </p>
        </div>
    </footer>

    <!-- ================= SCRIPTS CHART.JS ================= -->
    <script>
        // Données transmises depuis PHP
        const last7DaysLabels = <?= json_encode($last7DaysLabels) ?>;
        const last7DaysCA = <?= json_encode($last7DaysCA) ?>;
        const last7DaysProfit = <?= json_encode($last7DaysProfit) ?>;

        const paymentLabels = <?= json_encode($paymentLabels) ?>;
        const paymentAmounts = <?= json_encode($paymentAmounts) ?>;

        const topProductNames = <?= json_encode($topProductNames) ?>;
        const topProductQuantities = <?= json_encode($topProductQuantities) ?>;

        // Configuration globale Chart.js
        Chart.defaults.font.family = 'ui-sans-serif, system-ui, sans-serif';
        Chart.defaults.color = '#64748b';

        // 1. Graphique d'Évolution (CA vs Bénéfice sur 7 jours)
        const ctxTrends = document.getElementById('salesTrendsChart').getContext('2d');
        
        // Dégradé pour le CA
        const gradientCA = ctxTrends.createLinearGradient(0, 0, 0, 300);
        gradientCA.addColorStop(0, 'rgba(37, 99, 235, 0.25)');
        gradientCA.addColorStop(1, 'rgba(37, 99, 235, 0.0)');

        // Dégradé pour le Profit
        const gradientProfit = ctxTrends.createLinearGradient(0, 0, 0, 300);
        gradientProfit.addColorStop(0, 'rgba(16, 185, 129, 0.25)');
        gradientProfit.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

        new Chart(ctxTrends, {
            type: 'line',
            data: {
                labels: last7DaysLabels,
                datasets: [
                    {
                        label: "Chiffre d'Affaires (FCFA)",
                        data: last7DaysCA,
                        borderColor: '#2563eb',
                        backgroundColor: gradientCA,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#2563eb',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Bénéfice Net (FCFA)',
                        data: last7DaysProfit,
                        borderColor: '#10b981',
                        backgroundColor: gradientProfit,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 11 },
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + new Intl.NumberFormat('fr-FR').format(context.raw) + ' FCFA';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            callback: function(value) {
                                return value >= 1000 ? (value / 1000) + 'k' : value;
                            }
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // 2. Graphique Camembert / Doughnut des Modes de Paiement
        const ctxPayment = document.getElementById('paymentMethodsChart').getContext('2d');
        new Chart(ctxPayment, {
            type: 'doughnut',
            data: {
                labels: paymentLabels.length > 0 ? paymentLabels : ['Aucune vente'],
                datasets: [{
                    data: paymentAmounts.length > 0 ? paymentAmounts : [1],
                    backgroundColor: [
                        '#10b981', // Espèces (vert)
                        '#f59e0b', // Mobile Money (orange)
                        '#3b82f6', // Carte (bleu)
                        '#8b5cf6'  // Crédit (violet)
                    ],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            padding: 14,
                            font: { size: 11, weight: '500' }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + new Intl.NumberFormat('fr-FR').format(context.raw) + ' FCFA';
                            }
                        }
                    }
                }
            }
        });

        // 3. Graphique Top 5 Produits (Barres Horizontales)
        const ctxTop = document.getElementById('topProductsChart').getContext('2d');
        new Chart(ctxTop, {
            type: 'bar',
            data: {
                labels: topProductNames.length > 0 ? topProductNames : ['Aucun article'],
                datasets: [{
                    label: 'Quantité vendue',
                    data: topProductQuantities.length > 0 ? topProductQuantities : [0],
                    backgroundColor: '#3b82f6',
                    borderRadius: 6,
                    barPercentage: 0.65
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Quantité vendue : ' + context.raw + ' unité(s)';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { stepSize: 1 }
                    },
                    y: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 11 }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
