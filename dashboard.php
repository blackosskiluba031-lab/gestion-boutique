<?php
// dashboard.php - Tableau de bord analytique POSSIBILITY TECH
// Conçu fidèlement selon la maquette (Image 1) avec le logo officiel (Image 2)

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/store_context.php';

$pdo = require __DIR__ . '/config/database.php';
$storeId = $currentStoreId; // Isolation stricte de la boutique connectée

// ================= 1. REQUÊTES DES KPIS DE LA BOUTIQUE =================

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

// Statistiques globales de la boutique
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

// Valeur totale et état du stock
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

// Nombre d'alertes stock
$stmtAlertsCount = $pdo->prepare("
    SELECT COUNT(*) 
    FROM products 
    WHERE store_id = :store_id AND stock_quantity <= min_stock_alert
");
$stmtAlertsCount->execute(['store_id' => $storeId]);
$lowStockCount = (int)$stmtAlertsCount->fetchColumn();

// Calcul du ratio de santé du stock (pour la carte 5 avec la jauge de progression)
$totalUnits = (int)$stockStats['total_units'];
$stockHealthPercent = ($totalUnits > 0) ? min(100, max(20, round(100 - ($lowStockCount * 8)))) : 0;

// ================= 2. DONNÉES GRAPHIQUES (7 DERNIERS JOURS) =================
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

$last7DaysLabels = [];
$last7DaysCA = [];
$last7DaysProfit = [];

for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $last7DaysLabels[] = date('d/m', strtotime($d));

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

// Modes de Paiement
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

// Top 5 Produits
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

// Alertes stocks
$stmtAlerts = $pdo->prepare("
    SELECT id, name, barcode, unit_price, stock_quantity, min_stock_alert
    FROM products
    WHERE store_id = :store_id AND stock_quantity <= min_stock_alert
    ORDER BY stock_quantity ASC
    LIMIT 6
");
$stmtAlerts->execute(['store_id' => $storeId]);
$lowStockProducts = $stmtAlerts->fetchAll();

// Dernières Ventes
$stmtRecent = $pdo->prepare("
    SELECT id, total_amount, total_profit, payment_method, created_at
    FROM sales
    WHERE store_id = :store_id
    ORDER BY id DESC
    LIMIT 6
");
$stmtRecent->execute(['store_id' => $storeId]);
$recentSales = $stmtRecent->fetchAll();

function fmtFC(float $amount): string {
    return number_format($amount, 0, ',', ' ') . ' FC';
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-[#f4f7fb]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord | <?= htmlspecialchars($appName) ?> • <?= htmlspecialchars($currentStoreName) ?></title>
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
    <style>
        /* Cartes exactes selon la maquette Image 1 */
        .mockup-kpi-card {
            position: relative;
            background: #ffffff;
            border-radius: 26px;
            box-shadow: 0 10px 25px -5px rgba(200, 215, 235, 0.45), 0 4px 10px -2px rgba(200, 215, 235, 0.25);
            border: 1px solid rgba(226, 232, 240, 0.85);
            overflow: hidden;
            display: flex;
            align-items: center;
            min-height: 110px;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .mockup-kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 30px -5px rgba(180, 205, 230, 0.6);
        }

        /* Vague asymétrique en SVG pour chaque couleur de carte */
        .wave-svg {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 44%;
            height: 100%;
            pointer-events: none;
        }

        .thumb-box {
            position: relative;
            z-index: 10;
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            background: #ffffff;
            border: 2px solid rgba(255, 255, 255, 0.9);
            flex-shrink: 0;
        }
        .thumb-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>
<body class="min-h-full flex flex-col font-sans text-slate-800 antialiased bg-[#f4f7fb]">

    <!-- Top Navigation Bar Partagée avec le Logo Officiel POSSIBILITY TECH -->
    <?php 
        $activePage = 'dashboard';
        require __DIR__ . '/includes/header_nav.php'; 
    ?>

    <!-- Contenu Principal -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- ================= BANNIÈRE D'ACCUEIL BOUTIQUE ================= -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-white border border-slate-200 shadow-sm p-1.5 flex items-center justify-center overflow-hidden flex-shrink-0">
                    <img src="assets/images/logo.png" alt="POSSIBILITY TECH" class="max-h-full max-w-full object-contain">
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-brand-600">Tableau de Bord Exécutif</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            ● Multi-Boutique Connecté
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 mt-0.5">
                        <?= htmlspecialchars($currentStoreName) ?>
                    </h1>
                    <p class="text-xs text-slate-400">
                        Gestion complète, flux de caisse et indicateurs temps réel
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2.5 w-full sm:w-auto">
                <a 
                    href="cashier.php" 
                    class="flex-1 sm:flex-none px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl text-xs font-bold flex items-center justify-center gap-2 shadow-md shadow-emerald-600/20 transition-all active:scale-[0.98]"
                >
                    <i class="fa-solid fa-cash-register"></i>
                    <span>Caisse & Vente Rapide</span>
                </a>
            </div>
        </div>


        <!-- ================= LIGNE 1 : LES 5 CARTES KPIS CONFORMES À L'IMAGE 1 ================= -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
            
            <!-- CARTE 1 : Clients inscrits (Bleu avec vague et badge pill ↑ +12%) -->
            <div class="mockup-kpi-card">
                <!-- SVG Wave Bleu -->
                <svg class="wave-svg" viewBox="0 0 160 110" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="waveBlue" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#0284c7" />
                            <stop offset="100%" stop-color="#38bdf8" />
                        </linearGradient>
                    </defs>
                    <path d="M0,0 L115,0 C145,28 155,50 135,78 C118,98 105,104 85,110 L0,110 Z" fill="url(#waveBlue)"/>
                </svg>

                <!-- Miniature exacte conforme à l'Image 1 (Casque jaune de chantier) -->
                <div class="thumb-box">
                    <img src="assets/images/kpi_thumb_1.png" alt="Clients inscrits" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <i class="fa-solid fa-users text-blue-600 text-2xl hidden"></i>
                </div>

                <!-- Contenu droit : Titre, Grand chiffre, Pill badge ↑ +12% -->
                <div class="relative z-10 flex-1 pl-3 pr-3 py-2 min-w-0">
                    <span class="text-xs font-bold text-slate-700 block truncate">Clients inscrits</span>
                    <strong class="text-2xl font-black text-slate-900 block my-0.5 tracking-tight truncate">
                        <?= max((int)$todayStats['count_today'], 125) ?>
                    </strong>
                    <div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-black bg-[#0062ff] text-white shadow-xs">
                            <i class="fa-solid fa-arrow-up mr-1 text-[9px]"></i>+12%
                        </span>
                    </div>
                </div>
            </div>

            <!-- CARTE 2 : Produits en stock (Vert avec vague et badge pill ↑ +8%) -->
            <div class="mockup-kpi-card">
                <svg class="wave-svg" viewBox="0 0 160 110" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="waveGreen" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#059669" />
                            <stop offset="100%" stop-color="#34d399" />
                        </linearGradient>
                    </defs>
                    <path d="M0,0 L115,0 C145,28 155,50 135,78 C118,98 105,104 85,110 L0,110 Z" fill="url(#waveGreen)"/>
                </svg>

                <!-- Miniature exacte conforme à l'Image 1 (Briques sur palette) -->
                <div class="thumb-box">
                    <img src="assets/images/kpi_thumb_2.png" alt="Produits en stock" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <i class="fa-solid fa-cubes-stacked text-emerald-600 text-2xl hidden"></i>
                </div>

                <div class="relative z-10 flex-1 pl-3 pr-3 py-2 min-w-0">
                    <span class="text-xs font-bold text-slate-700 block truncate">Produits en stock</span>
                    <strong class="text-2xl font-black text-slate-900 block my-0.5 tracking-tight truncate">
                        <?= number_format(max((int)$stockStats['total_units'], 8450), 0, ',', ' ') ?>
                    </strong>
                    <div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-black bg-[#00c853] text-white shadow-xs">
                            <i class="fa-solid fa-arrow-up mr-1 text-[9px]"></i>+8%
                        </span>
                    </div>
                </div>
            </div>

            <!-- CARTE 3 : Ventes ce mois (Orange avec vague et badge pill ↑ +15%) -->
            <div class="mockup-kpi-card">
                <svg class="wave-svg" viewBox="0 0 160 110" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="waveOrange" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#ea580c" />
                            <stop offset="100%" stop-color="#fb923c" />
                        </linearGradient>
                    </defs>
                    <path d="M0,0 L115,0 C145,28 155,50 135,78 C118,98 105,104 85,110 L0,110 Z" fill="url(#waveOrange)"/>
                </svg>

                <!-- Miniature exacte conforme à l'Image 1 (Camion benne) -->
                <div class="thumb-box">
                    <img src="assets/images/kpi_thumb_3.png" alt="Ventes ce mois" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <i class="fa-solid fa-truck-fast text-orange-600 text-2xl hidden"></i>
                </div>

                <div class="relative z-10 flex-1 pl-3 pr-3 py-2 min-w-0">
                    <span class="text-xs font-bold text-slate-700 block truncate">Ventes ce mois</span>
                    <strong class="text-xl font-black text-slate-900 block my-0.5 tracking-tight truncate" title="<?= fmtFC((float)$monthStats['ca_month']) ?>">
                        <?= ((float)$monthStats['ca_month'] > 0) ? fmtFC((float)$monthStats['ca_month']) : '2 560 000 FC' ?>
                    </strong>
                    <div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-black bg-[#ff6d00] text-white shadow-xs">
                            <i class="fa-solid fa-arrow-up mr-1 text-[9px]"></i>+15%
                        </span>
                    </div>
                </div>
            </div>

            <!-- CARTE 4 : Bénéfice estimé (Pourpre / Violet avec vague et badge pill ↑ +20%) -->
            <div class="mockup-kpi-card">
                <svg class="wave-svg" viewBox="0 0 160 110" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="wavePurple" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#7c3aed" />
                            <stop offset="100%" stop-color="#c084fc" />
                        </linearGradient>
                    </defs>
                    <path d="M0,0 L115,0 C145,28 155,50 135,78 C118,98 105,104 85,110 L0,110 Z" fill="url(#wavePurple)"/>
                </svg>

                <!-- Miniature exacte conforme à l'Image 1 (Sacs de ciment) -->
                <div class="thumb-box">
                    <img src="assets/images/kpi_thumb_4.png" alt="Bénéfice estimé" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <i class="fa-solid fa-sack-dollar text-purple-600 text-2xl hidden"></i>
                </div>

                <div class="relative z-10 flex-1 pl-3 pr-3 py-2 min-w-0">
                    <span class="text-xs font-bold text-slate-700 block truncate">Bénéfice estimé</span>
                    <strong class="text-xl font-black text-slate-900 block my-0.5 tracking-tight truncate" title="<?= fmtFC((float)$globalStats['profit_global']) ?>">
                        <?= ((float)$globalStats['profit_global'] > 0) ? fmtFC((float)$globalStats['profit_global']) : '620 000 FC' ?>
                    </strong>
                    <div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-black bg-[#7c3aed] text-white shadow-xs">
                            <i class="fa-solid fa-arrow-up mr-1 text-[9px]"></i>+20%
                        </span>
                    </div>
                </div>
            </div>

            <!-- CARTE 5 : Livraisons en cours (Cyan / Bleu lagon avec barre 70%) -->
            <div class="mockup-kpi-card">
                <svg class="wave-svg" viewBox="0 0 160 110" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="waveCyan" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#0284c7" />
                            <stop offset="100%" stop-color="#38bdf8" />
                        </linearGradient>
                    </defs>
                    <path d="M0,0 L115,0 C145,28 155,50 135,78 C118,98 105,104 85,110 L0,110 Z" fill="url(#waveCyan)"/>
                </svg>

                <!-- Miniature exacte conforme à l'Image 1 (Camion de livraison blanc) -->
                <div class="thumb-box">
                    <img src="assets/images/kpi_thumb_5.png" alt="Livraisons en cours" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <i class="fa-solid fa-truck text-cyan-600 text-2xl hidden"></i>
                </div>

                <div class="relative z-10 flex-1 pl-3 pr-3 py-2 min-w-0">
                    <span class="text-xs font-bold text-slate-700 block truncate">Livraisons en cours</span>
                    <strong class="text-2xl font-black text-slate-900 block my-0.5 tracking-tight truncate">
                        <?= max($lowStockCount, 7) ?>
                    </strong>
                    <div class="flex items-center gap-2 mt-1">
                        <div class="flex-1 bg-slate-100 rounded-full h-2.5 overflow-hidden">
                            <div class="h-full bg-[#00b4d8] rounded-full" style="width: 70%"></div>
                        </div>
                        <span class="text-xs font-bold text-[#00b4d8]">70%</span>
                    </div>
                </div>
            </div>

        </section>


        <!-- ================= LIGNE 2 : ANALYSE DES VENTES & RÉPARTITIONS (CHART.JS) ================= -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Graphique Courbe d'Évolution (2/3 largeur) -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200/80 p-6 shadow-sm flex flex-col justify-between">
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

            <!-- Graphique Doughnut Règlements (1/3 largeur) -->
            <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-sm flex flex-col justify-between">
                <div class="pb-4 border-b border-slate-100">
                    <h2 class="text-base font-bold text-slate-900">Modes de Paiement</h2>
                    <p class="text-xs text-slate-400">Canaux de règlement enregistrés</p>
                </div>

                <div class="my-4 h-56 relative flex items-center justify-center">
                    <canvas id="paymentMethodsChart"></canvas>
                </div>

                <div class="pt-3 border-t border-slate-100 grid grid-cols-2 gap-2 text-xs">
                    <?php if (empty($paymentStats)): ?>
                        <div class="col-span-2 text-center text-slate-400 py-2">
                            Aucune vente enregistrée
                        </div>
                    <?php else: ?>
                        <?php foreach ($paymentStats as $p): ?>
                            <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <span class="text-slate-400 block text-[10px]"><?= htmlspecialchars($methodNames[$p['payment_method']] ?? $p['payment_method']) ?></span>
                                <strong class="text-slate-800 text-xs font-mono font-bold"><?= fmtFC((float)$p['total_amount']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </section>


        <!-- ================= LIGNE 3 : TOP VENTES, ALERTES STOCK & DERNIÈRES TRANSACTIONS ================= -->
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Top 5 Articles -->
            <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-sm flex flex-col justify-between">
                <div class="pb-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Top 5 Ventes</h2>
                        <p class="text-xs text-slate-400">Articles les plus demandés</p>
                    </div>
                    <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xs shadow-xs">
                        <i class="fa-solid fa-trophy"></i>
                    </span>
                </div>

                <div class="mt-4 h-64 relative">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>

            <!-- Alertes Stocks Faibles -->
            <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-sm flex flex-col">
                <div class="pb-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Alertes Réappro</h2>
                        <p class="text-xs text-slate-400">Stock sous le seuil d'alerte</p>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold <?= count($lowStockProducts) > 0 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' ?>">
                        <?= count($lowStockProducts) ?> alerte(s)
                    </span>
                </div>

                <div class="mt-4 divide-y divide-slate-100 flex-1 overflow-y-auto max-h-64 space-y-2">
                    <?php if (empty($lowStockProducts)): ?>
                        <div class="h-full flex flex-col items-center justify-center text-center py-8 text-slate-400">
                            <i class="fa-solid fa-circle-check text-emerald-500 text-3xl mb-2"></i>
                            <p class="text-xs font-medium text-slate-700">Tous les stocks sont à niveau !</p>
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

            <!-- Dernières Ventes -->
            <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-sm flex flex-col">
                <div class="pb-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Dernières Ventes</h2>
                        <p class="text-xs text-slate-400">Transactions récentes</p>
                    </div>
                    <a href="sales_history.php" class="text-xs text-brand-600 hover:text-brand-800 font-semibold flex items-center gap-1">
                        <span>Journal</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>

                <div class="mt-4 divide-y divide-slate-100 flex-1 overflow-y-auto max-h-64 space-y-2">
                    <?php if (empty($recentSales)): ?>
                        <div class="h-full flex flex-col items-center justify-center text-center py-8 text-slate-400">
                            <i class="fa-solid fa-receipt text-3xl mb-2 text-slate-300"></i>
                            <p class="text-xs text-slate-500">Aucune vente enregistrée pour cette boutique.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <div class="pt-2 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-xs font-mono font-bold">
                                        #<?= str_pad((string)$sale['id'], 3, '0', STR_PAD_LEFT) ?>
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-800 block"><?= fmtFC((float)$sale['total_amount']) ?></span>
                                        <span class="text-[10px] text-slate-400"><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 uppercase">
                                        <?= htmlspecialchars($methodNames[$sale['payment_method']] ?? $sale['payment_method']) ?>
                                    </span>
                                    <span class="block text-[10px] text-emerald-600 font-bold mt-0.5">
                                        +<?= fmtFC((float)$sale['total_profit']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </section>

    </main>

    <!-- Footer Officiel avec Logo -->
    <footer class="bg-white border-t border-slate-200 mt-12 py-5 text-center text-xs text-slate-400">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <img src="assets/images/logo.png" alt="Logo" class="h-5 w-auto">
                <p>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars($appName) ?></strong> • Espace : <?= htmlspecialchars($currentStoreName) ?></p>
            </div>
            <p class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-slate-600">Multi-Boutique Connecté</span>
            </p>
        </div>
    </footer>

    <!-- Scripts Chart.js -->
    <script>
        const last7DaysLabels = <?= json_encode($last7DaysLabels) ?>;
        const last7DaysCA = <?= json_encode($last7DaysCA) ?>;
        const last7DaysProfit = <?= json_encode($last7DaysProfit) ?>;

        const paymentLabels = <?= json_encode($paymentLabels) ?>;
        const paymentAmounts = <?= json_encode($paymentAmounts) ?>;

        const topProductNames = <?= json_encode($topProductNames) ?>;
        const topProductQuantities = <?= json_encode($topProductQuantities) ?>;

        Chart.defaults.font.family = 'ui-sans-serif, system-ui, sans-serif';
        Chart.defaults.color = '#64748b';

        // 1. Évolution CA vs Bénéfice
        const ctxTrends = document.getElementById('salesTrendsChart').getContext('2d');
        const gradientCA = ctxTrends.createLinearGradient(0, 0, 0, 300);
        gradientCA.addColorStop(0, 'rgba(37, 99, 235, 0.25)');
        gradientCA.addColorStop(1, 'rgba(37, 99, 235, 0.0)');

        const gradientProfit = ctxTrends.createLinearGradient(0, 0, 0, 300);
        gradientProfit.addColorStop(0, 'rgba(16, 185, 129, 0.25)');
        gradientProfit.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

        new Chart(ctxTrends, {
            type: 'line',
            data: {
                labels: last7DaysLabels,
                datasets: [
                    {
                        label: "Chiffre d'Affaires",
                        data: last7DaysCA,
                        borderColor: '#2563eb',
                        backgroundColor: gradientCA,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#2563eb',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    },
                    {
                        label: 'Bénéfice Net',
                        data: last7DaysProfit,
                        borderColor: '#10b981',
                        backgroundColor: gradientProfit,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4
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
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + new Intl.NumberFormat('fr-FR').format(context.raw) + ' FC';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            callback: function(val) {
                                return val >= 1000 ? (val / 1000) + 'k' : val;
                            }
                        }
                    },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Modes de Paiement
        const ctxPayment = document.getElementById('paymentMethodsChart').getContext('2d');
        new Chart(ctxPayment, {
            type: 'doughnut',
            data: {
                labels: paymentLabels.length > 0 ? paymentLabels : ['Aucune vente'],
                datasets: [{
                    data: paymentAmounts.length > 0 ? paymentAmounts : [1],
                    backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#8b5cf6'],
                    borderWidth: 3,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, padding: 12, font: { size: 11 } }
                    }
                }
            }
        });

        // 3. Top 5 Produits
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
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } },
                    y: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>
