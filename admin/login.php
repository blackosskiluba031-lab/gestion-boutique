<?php
// admin/login.php — Connexion administrateur POSSIBILITY TECH

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php'); exit;
}

require_once __DIR__ . '/../config/database.php';

$pdo   = require __DIR__ . '/../config/database.php';
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Identifiant et mot de passe requis.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: dashboard.php'); exit;
        } else {
            $error = "Identifiants administrateur incorrects.";
            // Simuler un délai anti-brute-force
            sleep(1);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Admin | POSSIBILITY TECH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 60%, #0f172a 100%); }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center font-sans text-slate-200 p-4">

    <div class="w-full max-w-sm">
        <!-- Logo + titre -->
        <div class="text-center mb-8">
            <div class="inline-block p-3 bg-white/10 rounded-2xl border border-white/20 mb-4">
                <img src="../assets/images/logo_icon.png" alt="" class="w-14 h-14 object-contain">
            </div>
            <h1 class="text-xl font-black text-white tracking-tight">POSSIBILITY TECH</h1>
            <p class="text-xs text-slate-400 font-semibold mt-1 uppercase tracking-widest">Espace Administrateur</p>
        </div>

        <!-- Card de connexion -->
        <div class="bg-white/10 backdrop-blur-xl rounded-3xl border border-white/20 p-6 shadow-2xl">

            <?php if ($error): ?>
            <div class="mb-4 p-3.5 bg-red-500/20 border border-red-400/30 rounded-2xl flex items-center gap-2.5 text-xs font-semibold text-red-300">
                <i class="fa-solid fa-shield-halved flex-shrink-0"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">Identifiant Admin</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                            <i class="fa-solid fa-user-shield text-sm"></i>
                        </div>
                        <input name="username" type="text" required autofocus
                            placeholder="admin"
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            class="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/20 rounded-xl text-sm font-medium text-white placeholder-slate-400 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400/30 transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">Mot de passe</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
                            <i class="fa-solid fa-lock text-sm"></i>
                        </div>
                        <input id="pwd" name="password" type="password" required
                            placeholder="••••••••"
                            class="w-full pl-10 pr-11 py-3 bg-white/10 border border-white/20 rounded-xl text-sm font-medium text-white placeholder-slate-400 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400/30 transition-all">
                        <button type="button" onclick="const i=document.getElementById('pwd');i.type=i.type==='password'?'text':'password';"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-200">
                            <i class="fa-solid fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <button type="submit"
                    class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white text-sm font-black rounded-xl shadow-lg shadow-blue-500/30 flex items-center justify-center gap-2 transition-all">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    Accéder au panneau Admin
                </button>
            </form>

            <div class="mt-5 pt-4 border-t border-white/10 text-center">
                <a href="../login.php" class="text-xs text-slate-400 hover:text-slate-200 font-semibold">
                    <i class="fa-solid fa-arrow-left mr-1"></i>Retour à l'accueil boutique
                </a>
            </div>
        </div>

        <p class="text-center text-xs text-slate-500 mt-6">Accès réservé aux administrateurs POSSIBILITY TECH</p>
    </div>
</body>
</html>
