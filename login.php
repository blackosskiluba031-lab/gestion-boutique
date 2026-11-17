<?php
// login.php — v2 : connexion par username (ou email)

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['current_store_id'])) {
    header('Location: dashboard.php'); exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Store.php';

$pdo        = require __DIR__ . '/config/database.php';
$storeModel = new Store($pdo);

$error   = null;
$blocked = isset($_GET['blocked']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = trim($_POST['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        $error = "Veuillez renseigner votre identifiant et votre mot de passe.";
    } else {
        $store = $storeModel->authenticate($identifier, $password);
        if ($store) {
            if (!$store['is_active']) {
                $error = "Votre espace boutique a été suspendu. Contactez l'administrateur.";
            } else {
                $_SESSION['current_store_id'] = (int) $store['id'];
                $_SESSION['store_name']       = $store['name'];
                $_SESSION['store_username']   = $store['username'];
                header('Location: dashboard.php'); exit;
            }
        } else {
            $error = "Identifiant ou mot de passe incorrect. Vérifiez et réessayez.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Connexion | POSSIBILITY TECH</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="assets/images/logo_icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { brand: { 50:'#eff6ff',100:'#dbeafe',500:'#3b82f6',600:'#2563eb',700:'#1d4ed8',800:'#1e40af',900:'#1e3a8a' } } } }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #f0f4ff 0%, #e8f0fe 50%, #f0f4ff 100%); }
        .glass-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
        input:focus { outline: none; }

        /* PWA install banner */
        #pwa-banner { transition: transform 0.4s ease, opacity 0.4s ease; }
        #pwa-banner.hidden { transform: translateY(-100%); opacity: 0; }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased text-slate-800">

    <!-- ===== BANNIÈRE PWA INSTALL ===== -->
    <div id="pwa-banner" class="hidden fixed top-0 left-0 right-0 z-50 bg-brand-700 text-white px-4 py-3 flex items-center justify-between gap-3 shadow-lg">
        <div class="flex items-center gap-3">
            <img src="assets/images/logo_icon.png" alt="" class="w-8 h-8 rounded-lg">
            <div>
                <p class="text-xs font-black">📱 Installer l'application</p>
                <p class="text-[11px] opacity-80">Accédez à POSSIBILITY TECH depuis votre écran d'accueil</p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <button id="pwa-install-btn" class="px-3 py-1.5 bg-white text-brand-700 text-xs font-black rounded-lg shadow-sm active:scale-95 transition-transform">
                Installer
            </button>
            <button id="pwa-dismiss-btn" class="p-1.5 opacity-70 hover:opacity-100">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>
    </div>

    <!-- ===== CORPS DE LA PAGE ===== -->
    <div class="flex-1 flex flex-col items-center justify-center px-4 py-10 safe-area-inset">

        <!-- Logo -->
        <div class="text-center mb-6">
            <div class="inline-block p-3 bg-white rounded-3xl shadow-xl border border-slate-200/80 mb-4 hover:scale-105 transition-transform duration-300">
                <img src="assets/images/logo_icon.png" alt="POSSIBILITY TECH" class="h-20 w-20 mx-auto object-contain">
            </div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">POSSIBILITY TECH</h1>
            <p class="mt-1 text-xs text-slate-500 font-semibold tracking-widest uppercase">Gestion Multi-Boutique & Point de Vente</p>
        </div>

        <!-- Card de connexion -->
        <div class="glass-card w-full max-w-sm rounded-3xl shadow-2xl border border-slate-200/80 p-6 sm:p-8">

            <?php if ($blocked): ?>
            <div class="mb-5 p-3.5 bg-orange-50 border border-orange-200 rounded-2xl flex items-start gap-2.5 text-xs font-semibold text-orange-800">
                <i class="fa-solid fa-ban text-orange-500 mt-0.5 flex-shrink-0"></i>
                <span>Votre compte a été suspendu. Contactez le support POSSIBILITY TECH.</span>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="mb-5 p-3.5 bg-red-50 border border-red-200 rounded-2xl flex items-start gap-2.5 text-xs font-semibold text-red-700">
                <i class="fa-solid fa-triangle-exclamation text-red-500 mt-0.5 flex-shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <h2 class="text-sm font-black text-slate-800 mb-5 text-center">Accéder à votre Espace Boutique</h2>

            <form method="POST" action="login.php" class="space-y-4">

                <!-- Identifiant (username) -->
                <div>
                    <label for="identifier" class="block text-xs font-bold text-slate-700 mb-1.5">
                        <i class="fa-solid fa-user text-brand-500 mr-1"></i>
                        Identifiant de boutique
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-at text-sm"></i>
                        </div>
                        <input
                            id="identifier"
                            name="identifier"
                            type="text"
                            required
                            autofocus
                            autocomplete="username"
                            autocapitalize="none"
                            placeholder="ex: prestige"
                            value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                            class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:border-brand-400 focus:ring-2 focus:ring-brand-200 transition-all"
                        >
                    </div>
                    <p class="mt-1 text-[10px] text-slate-400">Votre identifiant sans espace (ex : ma_boutique)</p>
                </div>

                <!-- Mot de passe -->
                <div>
                    <label for="password" class="block text-xs font-bold text-slate-700 mb-1.5">
                        <i class="fa-solid fa-lock text-brand-500 mr-1"></i>
                        Mot de passe
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock text-sm"></i>
                        </div>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="w-full pl-10 pr-11 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:border-brand-400 focus:ring-2 focus:ring-brand-200 transition-all"
                        >
                        <button type="button" onclick="togglePwd()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                            <i id="pwd-eye" class="fa-solid fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <!-- Bouton connexion -->
                <button
                    type="submit"
                    class="w-full py-3.5 px-4 bg-brand-600 hover:bg-brand-700 active:scale-[0.98] text-white text-sm font-black rounded-xl shadow-lg shadow-brand-600/30 flex items-center justify-center gap-2 transition-all mt-2"
                >
                    <i class="fa-solid fa-right-to-bracket"></i>
                    Connexion
                </button>
            </form>

            <!-- Lien inscription -->
            <div class="mt-6 pt-5 border-t border-slate-100 text-center">
                <p class="text-xs text-slate-500">
                    Pas encore de compte boutique ?
                    <a href="register.php" class="font-black text-brand-600 hover:text-brand-800 hover:underline ml-1">Créer une boutique (Essai 15j)</a>
                </p>
            </div>
        </div>

        <p class="mt-6 text-[10px] text-slate-400 text-center">
            &copy; <?= date('Y') ?> POSSIBILITY TECH — Solutions Logicielles &amp; Technologiques
        </p>
    </div>

    <!-- Service Worker + PWA -->
    <script>
        // Toggle password visibility
        function togglePwd() {
            const inp = document.getElementById('password');
            const eye = document.getElementById('pwd-eye');
            if (inp.type === 'password') {
                inp.type = 'text';
                eye.className = 'fa-solid fa-eye-slash text-sm';
            } else {
                inp.type = 'password';
                eye.className = 'fa-solid fa-eye text-sm';
            }
        }

        // ── PWA Install Prompt ──
        let deferredPrompt = null;
        const banner = document.getElementById('pwa-banner');
        const installBtn = document.getElementById('pwa-install-btn');
        const dismissBtn = document.getElementById('pwa-dismiss-btn');

        // Show banner if not dismissed before
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            if (!localStorage.getItem('pwa_dismissed')) {
                banner.classList.remove('hidden');
            }
        });

        installBtn?.addEventListener('click', async () => {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            if (outcome === 'accepted') banner.classList.add('hidden');
            deferredPrompt = null;
        });

        dismissBtn?.addEventListener('click', () => {
            banner.classList.add('hidden');
            localStorage.setItem('pwa_dismissed', '1');
        });

        // Register service worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js').catch(() => {});
        }
    </script>
</body>
</html>
