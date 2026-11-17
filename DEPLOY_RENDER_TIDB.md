# 🚀 Guide de Déploiement : Render + TiDB Cloud (Serverless)

Ce guide vous explique étape par étape comment déployer **POSSIBILITY TECH** en production sur **Render** (hébergement Web Service gratuit/Docker) avec une base de données **TiDB Cloud** (MySQL Cloud gratuit et ultra-rapide).

---

## 📋 Prérequis

1. Un compte **GitHub** (pour héberger votre code source).
2. Un compte **TiDB Cloud** gratuit sur [tidbcloud.com](https://tidbcloud.com).
3. Un compte **Render** gratuit sur [render.com](https://render.com).

---

## Étape 1 : Créer votre base de données sur TiDB Cloud (Gratuit)

1. Connectez-vous sur [https://tidbcloud.com](https://tidbcloud.com).
2. Cliquez sur **"Create Cluster"** et choisissez **Serverless (Free)**.
3. Choisissez la région la plus proche (ex: *Frankfurt - eu-central-1* ou *US*).
4. Une fois le cluster prêt (en moins de 30 secondes), cliquez sur **"Connect"**.
5. Dans l'onglet de connexion, sélectionnez **Type : General** ou **PHP / PDO**.
6. Vous obtiendrez vos identifiants :
   - **Host** : ex: `gateway01.eu-central-1.prod.aws.tidbcloud.com`
   - **Port** : `4000`
   - **User** : ex: `3wX...root`
   - **Password** : *(le mot de passe que vous avez défini ou généré)*
   - **Database** : `test` (ou le nom de votre choix)

> 💡 **Initialisation automatique :** 
> Dès le premier démarrage sur Render, le script [init_db_auto.php](file:///c:/laragon/www/gestion-boutique/init_db_auto.php) créera automatiquement toutes les tables (`stores`, `products`, `sales`, `sale_items`, `admins`) ainsi que le compte administrateur sur TiDB. Vous n'avez aucune commande SQL manuelle à exécuter !
> 
> *(Si vous préférez créer les tables à l'avance, ouvrez l'onglet "SQL Editor" dans TiDB Cloud et collez le contenu du fichier [database.sql](file:///c:/laragon/www/gestion-boutique/database.sql)).*

---

## Étape 2 : Envoyer votre code sur GitHub

Ouvrez un terminal PowerShell dans votre dossier local `c:\laragon\www\gestion-boutique` :

```powershell
cd c:\laragon\www\gestion-boutique

# 1. Vérifier le statut git
git status

# 2. Lier à votre dépôt GitHub (si ce n'est pas déjà fait)
# Créez au préalable un dépôt vide sur GitHub nommé "possibility-tech"
git remote add origin https://github.com/VOTRE_NOM_UTILISATEUR/possibility-tech.git

# 3. Pousser vers GitHub
git branch -M master
git push -u origin master
```

---

## Étape 3 : Déployer sur Render

1. Rendez-vous sur votre tableau de bord [Render.com](https://dashboard.render.com).
2. Cliquez sur le bouton bleu **"New +"** puis choisissez **"Web Service"**.
3. Sélectionnez **"Build and deploy from a Git repository"** et choisissez votre dépôt `possibility-tech`.
4. Configurez les paramètres :
   - **Name** : `possibility-tech` (ou le nom de votre choix)
   - **Region** : Frankfurt (ou celle proche de votre cluster TiDB)
   - **Branch** : `master` (ou `main`)
   - **Runtime** : **Docker** *(Render détectera automatiquement le `Dockerfile` inclus)*
   - **Plan** : **Free**

5. Descendez jusqu'à la section **"Environment Variables"** et ajoutez les 5 variables fournies par TiDB Cloud :

| Variable | Valeur exemple | Description |
| :--- | :--- | :--- |
| `TIDB_HOST` | `gateway01.eu-central-1.prod.aws.tidbcloud.com` | L'hôte de votre cluster TiDB |
| `TIDB_PORT` | `4000` | Port TiDB (toujours 4000) |
| `TIDB_USER` | `xxxxxx.root` | Nom d'utilisateur TiDB |
| `TIDB_PASSWORD` | `votre_mot_de_passe_secret` | Mot de passe TiDB |
| `TIDB_DATABASE` | `test` | Nom de la base de données |

*(Alternative : Si TiDB vous a fourni une URL complète `mysql://user:pass@host:4000/db`, vous pouvez simplement définir la seule variable `DATABASE_URL`)*.

6. Cliquez sur **"Deploy Web Service"** ! 🚀

---

## Étape 4 : Utilisation et Administration en Production

Une fois le déploiement terminé (Render affiche *"Your service is live"*), votre application sera disponible sur votre URL Render (ex: `https://possibility-tech.onrender.com`) :

### 1. Espace Administrateur Plateforme
- **URL :** `https://votre-app.onrender.com/admin/login.php`
- **Identifiant :** `admin`
- **Mot de passe :** `admin2024`
- **Capacités :** Voir le nombre total de boutiques inscrites, activer/suspendre une boutique en 1 clic, prolonger les jours d'essai gratuit.

### 2. Inscription d'une Boutique
- **URL :** `https://votre-app.onrender.com/register.php`
- Chaque boutique s'inscrit avec son propre identifiant unique (sans obligation d'email) et bénéficie immédiatement de ses **15 jours d'essai gratuit**.

### 3. Caisse & Vente Rapide
- **URL :** `https://votre-app.onrender.com/cashier.php`
- Interface tactile et mobile-friendly avec panier dynamique, scan code-barres et impression ticket thermique.

### 4. Rappel d'abonnement WhatsApp
- En fin de période d'essai (15 jours), l'écran de blocage redirige automatiquement le commerçant vers votre WhatsApp au **0901247908** pour renouveler son abonnement.
