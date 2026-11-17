# POSSYBILITY TECH - Système Point de Vente & Gestion de Boutique

Application moderne de gestion de stock, caisse enregistreuse et statistiques de vente développée en **PHP 8 (PDO)**, **MySQL**, **Tailwind CSS** et **Chart.js**.

---

## 🚀 Déploiement sur Render (Étape par étape)

### 1. Prérequis : Une base de données MySQL dans le Cloud
Render ne proposant pas de base MySQL gratuite intégrée (uniquement PostgreSQL), vous pouvez créer une base MySQL gratuite en 1 minute sur :
- **[Aiven.io](https://aiven.io/)** (Offre gratuite MySQL avec SSL)
- **[Clever Cloud](https://www.clever-cloud.com/)** (MySQL gratuit 10 Mo)
- **[TiDB Cloud](https://tidbcloud.com/)** (Compatible MySQL 100% gratuit)
- **[Railway.app](https://railway.app/)**

Vous obtiendrez vos identifiants :
- `DB_HOST` (ex: `mysql-xxxx.aivencloud.com`)
- `DB_PORT` (ex: `3306` ou personnalisé)
- `DB_NAME` (ex: `defaultdb` ou `gestion_boutique_db`)
- `DB_USER` (ex: `avnadmin` ou `root`)
- `DB_PASSWORD` (votre mot de passe)

---

### 2. Publier le projet sur GitHub
Dans votre terminal local :
```bash
git init
git add .
git commit -m "Déploiement POSSYBILITY TECH sur Render"
git branch -M main
git remote add origin https://github.com/VOTRE_COMPTE/gestion-boutique.git
git push -u origin main
```

---

### 3. Créer le Web Service sur Render
1. Connectez-vous sur [dashboard.render.com](https://dashboard.render.com/).
2. Cliquez sur **New +** > **Web Service**.
3. Sélectionnez votre dépôt GitHub **gestion-boutique**.
4. Configurez :
   - **Name** : `possybility-tech`
   - **Language / Runtime** : `Docker`
   - **Region** : Frankfurt (ou au choix)
   - **Instance Type** : `Free`
5. Dans la section **Environment Variables**, ajoutez :
   - `DB_HOST` = votre hôte MySQL
   - `DB_PORT` = `3306`
   - `DB_NAME` = votre nom de base
   - `DB_USER` = votre utilisateur
   - `DB_PASSWORD` = votre mot de passe
6. Cliquez sur **Deploy Web Service** !

---

### 4. Initialisation Automatique de la Base
Le conteneur exécute automatiquement `init_db_auto.php` au démarrage :
- Création automatique des tables (`stores`, `products`, `sales`, `sale_items`).
- Insertion automatique de la boutique par défaut et du catalogue de démonstration.

Votre application sera en ligne à l'adresse :  
`https://possybility-tech.onrender.com`
