<?php
// sales_history.php - Historique & Journal des Ventes

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/store_context.php';
require_once __DIR__ . '/models/Sale.php';

$pdo = require __DIR__ . '/config/database.php';
$saleModel = new Sale($pdo);

$storeId = $currentStoreId;

// --- 1. ENDPOINT AJAX : DÉTAIL D'UNE VENTE / TICKET ---
if (isset($_GET['action']) && $_GET['action'] === 'get_details') {
    header('Content-Type: application/json; charset=utf-8');
    $saleId = (int)($_GET['sale_id'] ?? 0);

    try {
        $details = $saleModel->getSaleDetails($saleId);
        if (!$details) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => "Vente introuvable."]);
            exit;
        }
        echo json_encode(['success' => true, 'sale' => $details]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- 2. GESTION DES FILTRES DE RECHERCHE ---
$dateStart = $_GET['date_start'] ?? null;
$dateEnd = $_GET['date_end'] ?? null;
$paymentMethod = $_GET['payment_method'] ?? 'all';

// Normalisation des dates si vides
if ($dateStart === '') $dateStart = null;
if ($dateEnd === '') $dateEnd = null;

// Récupération des ventes filtrées
$sales = $saleModel->getAllWithFilters($storeId, $dateStart, $dateEnd, $paymentMethod);

// --- 3. EXPORT CSV DU JOURNAL DES VENTES ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "journal_ventes_" . date('Y-m-d_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM pour compatibilité Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // En-têtes CSV
    fputcsv($output, ['N° Ticket', 'Date & Heure', 'Mode de Paiement', 'Nombre Articles', 'Quantite Totale', 'Total Vente (FCFA)', 'Benefice Net (FCFA)'], ';');

    foreach ($sales as $s) {
        fputcsv($output, [
            '#' . str_pad((string)$s['id'], 5, '0', STR_PAD_LEFT),
            $s['created_at'],
            strtoupper($s['payment_method']),
            $s['items_count'],
            $s['total_quantity'],
            number_format((float)$s['total_amount'], 2, '.', ''),
            number_format((float)$s['total_profit'], 2, '.', '')
        ], ';');
    }

    fclose($output);
    exit;
}

// Calcul des statistiques résumées de la sélection filtrée
$totalSalesCount = count($sales);
$totalRevenue = 0.0;
$totalProfit = 0.0;
$totalUnitsSold = 0;

foreach ($sales as $s) {
    $totalRevenue += (float)$s['total_amount'];
    $totalProfit += (float)$s['total_profit'];
    $totalUnitsSold += (int)$s['total_quantity'];
}

$averageBasket = $totalSalesCount > 0 ? ($totalRevenue / $totalSalesCount) : 0;
$globalMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0;

$paymentNames = [
    'cash'         => ['label' => 'Espèces', 'icon' => 'fa-money-bill-wave', 'color' => 'bg-emerald-100 text-emerald-800 border-emerald-200'],
    'mobile_money' => ['label' => 'Mobile Money', 'icon' => 'fa-mobile-screen-button', 'color' => 'bg-amber-100 text-amber-800 border-amber-200'],
    'card'         => ['label' => 'Carte Bancaire', 'icon' => 'fa-credit-card', 'color' => 'bg-blue-100 text-blue-800 border-blue-200'],
    'credit'       => ['label' => 'Crédit Client', 'icon' => 'fa-file-invoice-dollar', 'color' => 'bg-purple-100 text-purple-800 border-purple-200']
];

function fmtMoney(float $amount): string {
    return number_format($amount, 2, ',', ' ') . ' FCFA';
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Ventes | <?= htmlspecialchars($appName) ?> • <?= htmlspecialchars($currentStoreName) ?></title>
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
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            #receiptPrintZone, #receiptPrintZone * {
                visibility: visible;
            }
            #receiptPrintZone {
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
    </style>
</head>
<body class="min-h-full flex flex-col font-sans text-slate-800 antialiased">

    <!-- Top Navigation Bar Partagée -->
    <?php 
        $activePage = 'sales_history';
        require __DIR__ . '/includes/header_nav.php'; 
    ?>

    <!-- Contenu Principal -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6 no-print">

        <!-- ================= STATISTIQUES RÉSUMÉES DU JOURNAL ================= -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Ventes Enregistrées</span>
                    <span class="text-2xl font-black text-slate-900 mt-1 block"><?= $totalSalesCount ?></span>
                    <span class="text-[11px] text-slate-500"><?= $totalUnitsSold ?> article(s) écoulé(s)</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-base">
                    <i class="fa-solid fa-file-invoice"></i>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Chiffre d'Affaires</span>
                    <span class="text-2xl font-black text-slate-900 mt-1 block"><?= fmtMoney($totalRevenue) ?></span>
                    <span class="text-[11px] text-slate-500">Total sélectionné</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-base">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Bénéfice Net</span>
                    <span class="text-2xl font-black text-emerald-700 mt-1 block">+<?= fmtMoney($totalProfit) ?></span>
                    <span class="text-[11px] text-emerald-600 font-semibold">Taux de marge : <?= $globalMargin ?>%</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-base">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Panier Moyen</span>
                    <span class="text-2xl font-black text-slate-900 mt-1 block"><?= fmtMoney($averageBasket) ?></span>
                    <span class="text-[11px] text-slate-500">Par transaction</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-base">
                    <i class="fa-solid fa-basket-shopping"></i>
                </div>
            </div>

        </section>


        <!-- ================= FORMULAIRE DE FILTRES ================= -->
        <section class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <form method="GET" class="flex flex-col lg:flex-row gap-4 items-end justify-between">
                
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full lg:w-3/4">
                    <!-- Date de début -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Du :</label>
                        <input 
                            type="date" 
                            name="date_start" 
                            value="<?= htmlspecialchars($dateStart ?? '') ?>" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        >
                    </div>

                    <!-- Date de fin -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Au :</label>
                        <input 
                            type="date" 
                            name="date_end" 
                            value="<?= htmlspecialchars($dateEnd ?? '') ?>" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        >
                    </div>

                    <!-- Mode de Paiement -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Mode de Paiement :</label>
                        <select 
                            name="payment_method" 
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-brand-500"
                        >
                            <option value="all" <?= $paymentMethod === 'all' ? 'selected' : '' ?>>Tous les modes</option>
                            <option value="cash" <?= $paymentMethod === 'cash' ? 'selected' : '' ?>>Espèces</option>
                            <option value="mobile_money" <?= $paymentMethod === 'mobile_money' ? 'selected' : '' ?>>Mobile Money</option>
                            <option value="card" <?= $paymentMethod === 'card' ? 'selected' : '' ?>>Carte Bancaire</option>
                            <option value="credit" <?= $paymentMethod === 'credit' ? 'selected' : '' ?>>Crédit Client</option>
                        </select>
                    </div>
                </div>

                <!-- Boutons d'Action Filtre -->
                <div class="flex items-center gap-2 w-full lg:w-auto">
                    <button 
                        type="submit" 
                        class="px-5 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-colors shadow-sm"
                    >
                        <i class="fa-solid fa-filter"></i>
                        <span>Filtrer</span>
                    </button>
                    <a 
                        href="sales_history.php" 
                        class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition-colors"
                        title="Réinitialiser les filtres"
                    >
                        Réinitialiser
                    </a>
                    <?php 
                        $exportUrl = "sales_history.php?export=csv" 
                            . ($dateStart ? "&date_start=" . urlencode($dateStart) : "")
                            . ($dateEnd ? "&date_end=" . urlencode($dateEnd) : "")
                            . ($paymentMethod !== 'all' ? "&payment_method=" . urlencode($paymentMethod) : "");
                    ?>
                    <a 
                        href="<?= $exportUrl ?>" 
                        class="px-4 py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-colors"
                        title="Télécharger l'export CSV"
                    >
                        <i class="fa-solid fa-file-csv text-emerald-600 text-sm"></i>
                        <span>Export CSV</span>
                    </a>
                </div>

            </form>
        </section>


        <!-- ================= TABLEAU DE L'HISTORIQUE DES VENTES ================= -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row gap-3 items-center justify-between">
                <div class="relative w-full sm:w-80">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <input 
                        type="text" 
                        id="historySearchInput" 
                        placeholder="Rechercher par N° ticket ou date..." 
                        class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        oninput="filterHistoryTable()"
                    >
                </div>
                <span class="text-xs text-slate-400 font-medium">
                    Affichage de <strong class="text-slate-800"><?= $totalSalesCount ?></strong> transaction(s)
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 border-b border-slate-200 uppercase font-bold text-slate-400 text-[10px] tracking-wider">
                        <tr>
                            <th class="py-3.5 px-4">N° Ticket</th>
                            <th class="py-3.5 px-4">Date & Heure</th>
                            <th class="py-3.5 px-4">Règlement</th>
                            <th class="py-3.5 px-4 text-center">Articles</th>
                            <th class="py-3.5 px-4 text-right">Montant Total</th>
                            <th class="py-3.5 px-4 text-right">Bénéfice Net</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody" class="divide-y divide-slate-100">
                        <?php if (empty($sales)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <i class="fa-solid fa-receipt text-4xl mb-2 text-slate-300 block"></i>
                                    <span class="font-bold text-slate-700">Aucune vente enregistrée pour ces critères.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sales as $sale): ?>
                                <?php 
                                    $pInfo = $paymentNames[$sale['payment_method']] ?? [
                                        'label' => ucfirst($sale['payment_method']),
                                        'icon'  => 'fa-wallet',
                                        'color' => 'bg-slate-100 text-slate-700 border-slate-200'
                                    ];
                                    $formattedId = '#' . str_pad((string)$sale['id'], 5, '0', STR_PAD_LEFT);
                                ?>
                                <tr 
                                    class="hover:bg-slate-50/80 transition-colors history-row"
                                    data-id="<?= strtolower($formattedId) ?>"
                                    data-date="<?= date('d/m/Y', strtotime($sale['created_at'])) ?>"
                                >
                                    <!-- N° Ticket -->
                                    <td class="py-3.5 px-4">
                                        <button 
                                            type="button" 
                                            onclick="viewSaleDetails(<?= $sale['id'] ?>)"
                                            class="font-mono font-bold text-brand-600 hover:text-brand-800 hover:underline flex items-center gap-1.5"
                                        >
                                            <i class="fa-solid fa-receipt text-[11px]"></i>
                                            <span><?= $formattedId ?></span>
                                        </button>
                                    </td>

                                    <!-- Date & Heure -->
                                    <td class="py-3.5 px-4">
                                        <strong class="text-slate-800 block text-xs">
                                            <?= date('d/m/Y', strtotime($sale['created_at'])) ?>
                                        </strong>
                                        <span class="text-[10px] text-slate-400">
                                            <?= date('H:i:s', strtotime($sale['created_at'])) ?>
                                        </span>
                                    </td>

                                    <!-- Mode de Paiement -->
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold border <?= $pInfo['color'] ?>">
                                            <i class="fa-solid <?= $pInfo['icon'] ?> text-[10px]"></i>
                                            <span><?= $pInfo['label'] ?></span>
                                        </span>
                                    </td>

                                    <!-- Nombre d'articles -->
                                    <td class="py-3.5 px-4 text-center">
                                        <span class="font-semibold text-slate-700">
                                            <?= $sale['items_count'] ?> réf
                                        </span>
                                        <span class="text-[10px] text-slate-400 block">
                                            (<?= $sale['total_quantity'] ?> unité<?= $sale['total_quantity'] > 1 ? 's' : '' ?>)
                                        </span>
                                    </td>

                                    <!-- Total TTC -->
                                    <td class="py-3.5 px-4 text-right font-mono font-black text-slate-900 text-xs">
                                        <?= fmtMoney((float)$sale['total_amount']) ?>
                                    </td>

                                    <!-- Bénéfice Net -->
                                    <td class="py-3.5 px-4 text-right">
                                        <span class="font-bold text-emerald-600 block font-mono">
                                            +<?= fmtMoney((float)$sale['total_profit']) ?>
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button 
                                                type="button" 
                                                onclick="viewSaleDetails(<?= $sale['id'] ?>)"
                                                class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors"
                                                title="Voir le ticket et les détails"
                                            >
                                                <i class="fa-regular fa-eye"></i>
                                                <span>Détails</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- ================= MODALE DE DÉTAIL & RÉIMPRESSION DU TICKET ================= -->
    <div id="saleDetailsModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden flex flex-col max-h-[90vh]">
            
            <!-- En-tête Modale -->
            <div class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between no-print">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-brand-100 text-brand-600 flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">Détail du Ticket <span id="modalTicketNumber" class="font-mono text-brand-600">#00000</span></h3>
                </div>
                <button type="button" onclick="closeSaleModal()" class="text-slate-400 hover:text-slate-600 text-base">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Contenu du Reçu / Zone Imprimable -->
            <div id="receiptPrintZone" class="p-6 overflow-y-auto text-xs font-mono text-slate-800 space-y-4">
                <div class="text-center border-b border-dashed border-slate-300 pb-3">
                    <h4 class="font-bold text-sm tracking-wider uppercase font-sans">GESTION BOUTIQUE</h4>
                    <p class="text-[11px] text-slate-500">Boutique Principale - Caisse Centrale</p>
                    <p class="text-[10px] text-slate-400 mt-1">Ticket N° <span id="receiptNumberDisplay" class="font-bold text-slate-800">#00000</span></p>
                    <p class="text-[10px] text-slate-400" id="receiptDateDisplay">--/--/---- --:--</p>
                </div>

                <!-- Articles de la vente -->
                <div class="space-y-2 border-b border-dashed border-slate-300 pb-3" id="modalItemsContainer">
                    <!-- Généré dynamiquement en JS -->
                </div>

                <!-- Récapitulatif Financier -->
                <div class="space-y-1 font-sans text-xs">
                    <div class="flex justify-between font-bold text-sm pt-1">
                        <span>TOTAL TTC :</span>
                        <span id="modalTotalAmount">0.00 FCFA</span>
                    </div>
                    <div class="flex justify-between text-slate-600 text-[11px]">
                        <span>Mode de Règlement :</span>
                        <span id="modalPaymentMethod" class="font-bold uppercase text-slate-800">ESPÈCES</span>
                    </div>
                    <div class="flex justify-between text-emerald-700 bg-emerald-50 px-2 py-1 rounded text-[11px] font-semibold mt-2">
                        <span>Bénéfice Réalisé sur cette vente :</span>
                        <span id="modalTotalProfit">+0.00 FCFA</span>
                    </div>
                </div>

                <div class="text-center text-[10px] text-slate-400 pt-2">
                    <p>Merci pour votre visite !</p>
                    <p>Ce document fait office de ticket de caisse.</p>
                </div>
            </div>

            <!-- Actions Modale (No-print) -->
            <div class="p-4 bg-slate-50 border-t border-slate-200 flex gap-2 no-print">
                <button 
                    type="button" 
                    onclick="window.print()" 
                    class="flex-1 py-2.5 px-3 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-colors shadow-sm"
                >
                    <i class="fa-solid fa-print"></i>
                    <span>Imprimer Ticket</span>
                </button>
                <button 
                    type="button" 
                    onclick="closeSaleModal()" 
                    class="flex-1 py-2.5 px-3 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-bold transition-colors"
                >
                    Fermer
                </button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 mt-12 py-5 text-center text-xs text-slate-400 no-print">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <p>&copy; <?= date('Y') ?> <strong>Gestion Boutique</strong> - Système Point de Vente & Gestion des Stocks</p>
            <p class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-slate-600">Base MySQL connectée (127.0.0.1)</span>
            </p>
        </div>
    </footer>

    <!-- Script JavaScript pour le détail et l'impression -->
    <script>
        // Filtrage rapide dans le tableau HTML
        function filterHistoryTable() {
            const query = document.getElementById('historySearchInput').value.trim().toLowerCase();
            const rows = document.querySelectorAll('.history-row');

            rows.forEach(row => {
                const id = row.getAttribute('data-id') || '';
                const date = row.getAttribute('data-date') || '';
                if (!query || id.includes(query) || date.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Chargement du détail d'une vente via l'API AJAX
        async function viewSaleDetails(saleId) {
            try {
                const response = await fetch(`sales_history.php?action=get_details&sale_id=${saleId}`);
                const data = await response.json();

                if (!data.success || !data.sale) {
                    alert("Impossible de charger les détails de cette vente.");
                    return;
                }

                const sale = data.sale;
                const formattedId = '#' + String(sale.id).padStart(5, '0');

                document.getElementById('modalTicketNumber').textContent = formattedId;
                document.getElementById('receiptNumberDisplay').textContent = formattedId;
                document.getElementById('receiptDateDisplay').textContent = new Date(sale.created_at).toLocaleString('fr-FR');
                document.getElementById('modalTotalAmount').textContent = new Intl.NumberFormat('fr-FR').format(sale.total_amount) + ' FCFA';
                document.getElementById('modalTotalProfit').textContent = '+' + new Intl.NumberFormat('fr-FR').format(sale.total_profit) + ' FCFA';
                document.getElementById('modalPaymentMethod').textContent = sale.payment_method;

                // Rendu des articles
                const itemsContainer = document.getElementById('modalItemsContainer');
                itemsContainer.innerHTML = sale.items.map(item => `
                    <div class="flex justify-between items-start py-0.5">
                        <div class="pr-2">
                            <span class="font-bold text-slate-800 block">${item.quantity}x ${item.product_name}</span>
                            <span class="text-[10px] text-slate-400">${new Intl.NumberFormat('fr-FR').format(item.unit_price)} FCFA / u</span>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <strong class="font-bold text-slate-900 block">${new Intl.NumberFormat('fr-FR').format(item.line_total)} FCFA</strong>
                            <span class="text-[9px] text-emerald-600">(+${new Intl.NumberFormat('fr-FR').format(item.line_profit)})</span>
                        </div>
                    </div>
                `).join('');

                document.getElementById('saleDetailsModal').classList.remove('hidden');

            } catch (err) {
                console.error(err);
                alert("Erreur réseau lors de la récupération des détails.");
            }
        }

        function closeSaleModal() {
            document.getElementById('saleDetailsModal').classList.add('hidden');
        }

        // Fermeture avec Échap
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeSaleModal();
            }
        });
    </script>
</body>
</html>
