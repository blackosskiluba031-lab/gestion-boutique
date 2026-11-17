<?php
// register.php — v2 : inscription par username (sans email obligatoire)

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Store.php';
require_once __DIR__ . '/models/Product.php';

$pdo          = require __DIR__ . '/config/database.php';
$storeModel   = new Store($pdo);
$productModel = new Product($pdo);

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $email    = trim($_POST['email'] ?? '') ?: null;

    if (empty($name) || empty($username) || empty($password)) {
        $error = "Le nom de boutique, l'identifiant et le mot de passe sont obligatoires.";
    } elseif (!preg_match('/^[a-z0-9_]{3,30}$/', strtolower($username))) {
        $error = "L'identifiant doit avoir 3 à 30 caractères : lettres minuscules, chiffres, underscore (_) uniquement.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit comporter au moins 6 caractères.";
    } elseif (!empty($confirm) && $password !== $confirm) {
        $error = "Les deux mots de passe ne correspondent pas.";
    } else {
        try {
            $newStoreId = $storeModel->register($name, $username, $password, $phone, $address, $email);

            $_SESSION['current_store_id'] = $newStoreId;
            $_SESSION['store_name']       = $name;
            $_SESSION['store_username']   = $username;
            header('Location: dashboard.php'); exit;

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Nettoyage de l'username saisi
$suggestedUsername = strtolower(preg_replace('/[^a-z0-9_]/i', '_', trim($_POST['name'] ?? '')));
$suggestedUsername = preg_replace('/_+/', '_', $suggestedUsername);
$suggestedUsername = trim($suggestedUsername, '_');
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Créer une Boutique | POSSIBILITY TECH</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2563eb">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{brand:{50:'#eff6ff',100:'#dbeafe',500:'#3b82f6',600:'#2563eb',700:'#1d4ed8'}}}}}</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #f0f4ff 0%, #e8f0fe 50%, #f0f4ff 100%); }
        .glass-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased text-slate-800">
<div class="flex-1 flex flex-col items-center justify-center px-4 py-8">

    <!-- Logo -->
    <div class="text-center mb-5">
        <div class="inline-block p-2.5 bg-white rounded-3xl shadow-xl border border-slate-200/80 mb-3 hover:scale-105 transition-transform">
            <img src="assets/images/logo_icon.png" alt="POSSIBILITY TECH" class="h-16 w-16 mx-auto object-contain">
        </div>
        <h1 class="text-xl font-black tracking-tight text-slate-900">Créer votre Espace Boutique</h1>
        <p class="mt-1 text-xs text-slate-500 font-semibold">🎉 15 jours d'essai gratuit — Sans carte bancaire</p>
    </div>

    <div class="glass-card w-full max-w-sm rounded-3xl shadow-2xl border border-slate-200/80 p-6">

        <?php if ($error): ?>
        <div class="mb-4 p-3.5 bg-red-50 border border-red-200 rounded-2xl flex items-start gap-2.5 text-xs font-semibold text-red-700">
            <i class="fa-solid fa-triangle-exclamation text-red-500 mt-0.5 flex-shrink-0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="space-y-3.5" id="regForm">

            <!-- Nom de la boutique -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    <i class="fa-solid fa-store text-brand-500 mr-1"></i>Nom de la boutique *
                </label>
                <input id="storeName" name="name" type="text" required autofocus
                    placeholder="ex: Supermarché Le Progrès"
                    oninput="suggestUsername(this.value)"
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:border-brand-400 focus:ring-2 focus:ring-brand-200 transition-all">
            </div>

            <!-- Identifiant (username) -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    <i class="fa-solid fa-at text-brand-500 mr-1"></i>Identifiant de connexion *
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-sm font-bold">@</div>
                    <input id="username" name="username" type="text" required
                        pattern="[a-z0-9_]{3,30}"
                        placeholder="ma_boutique"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        class="w-full pl-8 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:border-brand-400 focus:ring-2 focus:ring-brand-200 transition-all">
                </div>
                <p class="mt-1 text-[10px] text-slate-400">3–30 caractères : minuscules, chiffres, _ (sans espace ni accent)</p>
            </div>

            <!-- Mot de passe -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    <i class="fa-solid fa-lock text-brand-500 mr-1"></i>Mot de passe *
                </label>
                <div class="relative">
                    <input id="password" name="password" type="password" required minlength="6"
                        placeholder="Au moins 6 caractères"
                        class="w-full px-4 pr-11 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:border-brand-400 focus:ring-2 focus:ring-brand-200 transition-all">
                    <button type="button" onclick="togglePwd('password','eye1')" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400">
                        <i id="eye1" class="fa-solid fa-eye text-sm"></i>
                    </button>
                </div>
            </div>

            <!-- Confirmer mot de passe -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    <i class="fa-solid fa-lock text-brand-500 mr-1"></i>Confirmer le mot de passe
                </label>
                <div class="relative">
                    <input id="confirm" name="confirm" type="password" minlength="6"
                        placeholder="Répétez le mot de passe"
                        class="w-full px-4 pr-11 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:border-brand-400 focus:ring-2 focus:ring-brand-200 transition-all">
                    <button type="button" onclick="togglePwd('confirm','eye2')" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400">
                        <i id="eye2" class="fa-solid fa-eye text-sm"></i>
                    </button>
                </div>
            </div>

            <!-- Téléphone + Ville sur 2 colonnes -->
            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Téléphone</label>
                    <input name="phone" type="tel" placeholder="+225 ..."
                        value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                        class="w-full px-3 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:border-brand-400 focus:ring-2 focus:ring-brand-200 transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Ville / Adresse</label>
                    <input name="address" type="text" placeholder="Abidjan..."
                        value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                        class="w-full px-3 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:border-brand-400 focus:ring-2 focus:ring-brand-200 transition-all">
                </div>
            </div>

            <!-- Bouton -->
            <button type="submit"
                class="w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-sm font-black rounded-xl shadow-lg shadow-emerald-600/25 flex items-center justify-center gap-2 transition-all mt-1">
                <i class="fa-solid fa-rocket"></i>
                Créer la Boutique — Essai 15 jours
            </button>
        </form>

        <div class="mt-4 text-center border-t border-slate-100 pt-4">
            <p class="text-xs text-slate-500">
                Déjà un compte ?
                <a href="login.php" class="font-black text-brand-600 hover:underline ml-1">Se connecter</a>
            </p>
        </div>
    </div>

    <p class="mt-5 text-[10px] text-slate-400 text-center">&copy; <?= date('Y') ?> POSSIBILITY TECH</p>
</div>

<script>
function suggestUsername(name) {
    const u = document.getElementById('username');
    if (u.dataset.touched) return;
    // Auto-generate from name
    const slug = name.toLowerCase()
        .replace(/[àáâãäå]/g,'a').replace(/[èéêë]/g,'e')
        .replace(/[ìíîï]/g,'i').replace(/[òóôõö]/g,'o')
        .replace(/[ùúûü]/g,'u').replace(/[ç]/g,'c')
        .replace(/[^a-z0-9]/g,'_')
        .replace(/_+/g,'_').replace(/^_|_$/g,'')
        .substring(0, 30);
    u.value = slug;
}

document.getElementById('username')?.addEventListener('input', function() {
    this.dataset.touched = '1';
    this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
});

function togglePwd(fieldId, eyeId) {
    const inp = document.getElementById(fieldId);
    const eye = document.getElementById(eyeId);
    if (inp.type === 'password') {
        inp.type = 'text';
        eye.className = 'fa-solid fa-eye-slash text-sm';
    } else {
        inp.type = 'password';
        eye.className = 'fa-solid fa-eye text-sm';
    }
}

if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
</script>
</body>
</html>
