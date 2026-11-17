<?php
// login.php - Connexion pour chaque boutique à son espace dédié

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si déjà connecté, rediriger vers le tableau de bord
if (isset($_SESSION['current_store_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Store.php';

$pdo = require __DIR__ . '/config/database.php';
$storeModel = new Store($pdo);

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Veuillez saisir votre email et votre mot de passe.";
    } else {
        $store = $storeModel->authenticate($email, $password);
        if ($store) {
            $_SESSION['current_store_id'] = (int)$store['id'];
            $_SESSION['store_name'] = $store['name'];
            $_SESSION['store_email'] = $store['email'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = "Identifiants invalides. Vérifiez l'email et le mot de passe.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Espace Boutique | POSSIBILITY TECH</title>
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
<body class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8 font-sans antialiased text-slate-800 bg-gradient-to-br from-slate-100 via-brand-50/30 to-slate-200">

    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <!-- Logo Officiel Possibility Tech -->
        <div class="inline-block p-3 bg-white rounded-3xl shadow-lg border border-slate-200/80 mb-4 hover:scale-105 transition-transform duration-200">
            <img src="assets/images/logo.png" alt="POSSIBILITY TECH Logo" class="h-16 w-auto mx-auto object-contain">
        </div>
        
        <h2 class="text-2xl font-black tracking-tight text-slate-900">
            POSSIBILITY TECH
        </h2>
        <p class="mt-1 text-xs text-slate-500 font-semibold tracking-wide uppercase">
            Système Multi-Boutique & Point de Vente
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-6 shadow-xl rounded-3xl sm:px-10 border border-slate-200/80">
            
            <div class="mb-6 text-center border-b border-slate-100 pb-4">
                <h3 class="text-base font-bold text-slate-800">Accéder à votre Espace Boutique</h3>
                <p class="text-xs text-slate-400 mt-1">Connectez-vous avec vos identifiants pour gérer votre stock et votre caisse.</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-5 p-3.5 bg-red-50 border border-red-200 rounded-2xl flex items-center gap-2.5 text-xs font-semibold text-red-700 animate-in fade-in">
                    <i class="fa-solid fa-triangle-exclamation text-red-500 text-sm"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4">
                
                <div>
                    <label for="email" class="block text-xs font-bold text-slate-700 mb-1">
                        Adresse Email de la boutique :
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
                            autofocus
                            placeholder="ex: prestige@possibility.tech"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            class="w-full pl-10 pr-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all"
                        >
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-slate-700 mb-1">
                        Mot de passe :
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
                            placeholder="••••••••"
                            class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 transition-all"
                        >
                        <button 
                            type="button" 
                            onclick="togglePasswordVisibility()" 
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 text-xs"
                        >
                            <i id="eyeIcon" class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="pt-2">
                    <button 
                        type="submit" 
                        class="w-full py-3 px-4 bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-brand-500/25 flex items-center justify-center gap-2 transition-all active:scale-[0.98]"
                    >
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <span>Se Connecter à la Boutique</span>
                    </button>
                </div>
            </form>

            <!-- Démo Rapide Local : Comptes de test -->
            <div class="mt-6 pt-5 border-t border-slate-100">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block text-center mb-2">Comptes de Test Local</span>
                <div class="grid grid-cols-2 gap-2 text-left">
                    <button 
                        type="button"
                        onclick="fillDemo('prestige@possibility.tech', 'password123')"
                        class="p-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-[11px] transition-colors"
                    >
                        <strong class="block text-slate-800 font-bold truncate">🏪 Boutique 1</strong>
                        <span class="text-[10px] text-slate-500 block truncate">prestige@...</span>
                        <span class="text-[9px] text-brand-600 font-mono">password123</span>
                    </button>
                    <button 
                        type="button"
                        onclick="fillDemo('express@possibility.tech', 'password123')"
                        class="p-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-[11px] transition-colors"
                    >
                        <strong class="block text-slate-800 font-bold truncate">🏪 Boutique 2</strong>
                        <span class="text-[10px] text-slate-500 block truncate">express@...</span>
                        <span class="text-[9px] text-brand-600 font-mono">password123</span>
                    </button>
                </div>
            </div>

            <!-- Inscription Nouvelle Boutique -->
            <div class="mt-6 text-center">
                <p class="text-xs text-slate-500">
                    Vous gérez une nouvelle boutique ? 
                    <a href="register.php" class="font-bold text-brand-600 hover:text-brand-800 hover:underline">
                        Créer un compte boutique
                    </a>
                </p>
            </div>

        </div>

        <p class="mt-6 text-center text-xs text-slate-400">
            &copy; <?= date('Y') ?> <strong>POSSIBILITY TECH</strong> • Tous droits réservés.
        </p>
    </div>

    <script>
        function fillDemo(email, pass) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = pass;
        }

        function togglePasswordVisibility() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fa-solid fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fa-solid fa-eye';
            }
        }
    </script>
</body>
</html>
