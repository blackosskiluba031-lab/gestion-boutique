<?php
// register.php - Inscription d'une nouvelle boutique

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Store.php';
require_once __DIR__ . '/models/Product.php';

$pdo = require __DIR__ . '/config/database.php';
$storeModel = new Store($pdo);
$productModel = new Product($pdo);

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $error = "Veuillez renseigner le nom de la boutique, l'email et un mot de passe.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit comporter au moins 6 caractères.";
    } else {
        try {
            $newStoreId = $storeModel->register($name, $email, $password, $phone, $address);

            // Initialiser 4 produits de base pour cette nouvelle boutique
            $starterProducts = [
                [$newStoreId, 1, 'Produit Exemple A', '4000000000001', 1000.00, 1500.00, 20, 5],
                [$newStoreId, 1, 'Produit Exemple B', '4000000000002', 2000.00, 3000.00, 15, 5],
                [$newStoreId, 2, 'Article Hygiène X', '4000000000003', 800.00, 1200.00, 30, 8],
                [$newStoreId, 3, 'Boisson Rafraîchissante', '4000000000004', 500.00, 800.00, 50, 10]
            ];

            foreach ($starterProducts as $sp) {
                $productModel->create($sp[0], $sp[1], $sp[2], $sp[3], $sp[4], $sp[5], $sp[6], $sp[7]);
            }

            // Connecter automatiquement la nouvelle boutique
            $_SESSION['current_store_id'] = $newStoreId;
            $_SESSION['store_name'] = $name;
            $_SESSION['store_email'] = $email;

            header('Location: dashboard.php');
            exit;

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une Boutique | POSSIBILITY TECH</title>
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
<body class="min-h-full flex flex-col justify-center py-10 sm:px-6 lg:px-8 font-sans antialiased text-slate-800 bg-gradient-to-br from-slate-100 via-brand-50/30 to-slate-200">

    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div class="inline-block p-2.5 bg-white rounded-3xl shadow-lg border border-slate-200/80 mb-3 hover:scale-105 transition-transform duration-200">
            <img src="assets/images/logo.png" alt="POSSIBILITY TECH Logo" class="h-14 w-auto mx-auto object-contain">
        </div>
        <h2 class="text-2xl font-black tracking-tight text-slate-900">
            Créer un Espace Boutique
        </h2>
        <p class="mt-1 text-xs text-slate-500 font-semibold tracking-wide uppercase">
            POSSIBILITY TECH Multi-Boutiques
        </p>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-6 shadow-xl rounded-3xl sm:px-10 border border-slate-200/80">
            
            <?php if ($error): ?>
                <div class="mb-5 p-3.5 bg-red-50 border border-red-200 rounded-2xl flex items-center gap-2.5 text-xs font-semibold text-red-700 animate-in fade-in">
                    <i class="fa-solid fa-triangle-exclamation text-red-500 text-sm"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" class="space-y-3.5">
                
                <div>
                    <label for="name" class="block text-xs font-bold text-slate-700 mb-1">
                        Nom de votre Boutique * :
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-store text-xs"></i>
                        </div>
                        <input 
                            id="name" 
                            name="name" 
                            type="text" 
                            required 
                            autofocus
                            placeholder="ex: Supermarché Le Progrès"
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                            class="w-full pl-10 pr-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all"
                        >
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-xs font-bold text-slate-700 mb-1">
                        Adresse Email (identifiant de connexion) * :
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-envelope text-xs"></i>
                        </div>
                        <input 
                            id="email" 
                            name="email" 
                            type="email" 
                            required 
                            placeholder="ex: contact@leprogres.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            class="w-full pl-10 pr-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all"
                        >
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-slate-700 mb-1">
                        Mot de passe * :
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock text-xs"></i>
                        </div>
                        <input 
                            id="password" 
                            name="password" 
                            type="password" 
                            required 
                            minlength="6"
                            placeholder="Au moins 6 caractères"
                            class="w-full pl-10 pr-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label for="phone" class="block text-xs font-bold text-slate-700 mb-1">
                            Téléphone :
                        </label>
                        <input 
                            id="phone" 
                            name="phone" 
                            type="text" 
                            placeholder="+225 ..."
                            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all"
                        >
                    </div>
                    <div>
                        <label for="address" class="block text-xs font-bold text-slate-700 mb-1">
                            Adresse / Ville :
                        </label>
                        <input 
                            id="address" 
                            name="address" 
                            type="text" 
                            placeholder="Abidjan, Dakar..."
                            value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all"
                        >
                    </div>
                </div>

                <div class="pt-2">
                    <button 
                        type="submit" 
                        class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-500/25 flex items-center justify-center gap-2 transition-all active:scale-[0.98]"
                    >
                        <i class="fa-solid fa-check"></i>
                        <span>Créer la Boutique & Ouvrir l'Espace</span>
                    </button>
                </div>
            </form>

            <div class="mt-5 text-center border-t border-slate-100 pt-4">
                <p class="text-xs text-slate-500">
                    Vous possédez déjà un compte boutique ? 
                    <a href="login.php" class="font-bold text-brand-600 hover:text-brand-800 hover:underline">
                        Se connecter
                    </a>
                </p>
            </div>

        </div>
    </div>
</body>
</html>
