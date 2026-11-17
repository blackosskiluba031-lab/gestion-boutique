# POSSIBILITY TECH — Gestion Multi-Boutique & Point de Vente (PWA)

Application moderne de gestion multi-boutique, caisse enregistreuse tactile, suivi de stock et statistiques en temps réel développée en **PHP 8 (PDO)**, **MySQL / TiDB Cloud**, **Tailwind CSS** et **Chart.js**.

---

## 🚀 Déploiement en Production (Render + TiDB Cloud)

Consultez le guide complet pas à pas : **[DEPLOY_RENDER_TIDB.md](DEPLOY_RENDER_TIDB.md)**.

### En résumé :
1. Créez un cluster gratuit sur **[TiDB Cloud](https://tidbcloud.com)** (compatible MySQL avec SSL automatique).
2. Poussez votre code sur votre compte **GitHub**.
3. Créez un **Web Service (Docker)** sur **[Render.com](https://render.com)**.
4. Renseignez les variables d'environnement (`TIDB_HOST`, `TIDB_PORT=4000`, `TIDB_USER`, `TIDB_PASSWORD`, `TIDB_DATABASE`).
5. Render lance le service : la base de données s'auto-initialise avec le compte admin par défaut (`admin` / `admin2024`).

---

## 📱 Fonctionnalités Clés

- **🔑 Connexion par Identifiant (Username) :** Plus besoin d'email, chaque boutique possède son propre pseudo court.
- **📲 Progressive Web App (PWA) :** Bannière d'installation automatique sur smartphone (Android / iOS) avec support hors-ligne.
- **⏳ Essai Gratuit 15 Jours :** Compteur automatique sur le tableau de bord avec blocage automatique à expiration et redirection WhatsApp vers le contact support **0901247908**.
- **🛡️ Espace Administrateur (/admin) :** Tableau de bord pour suivre les boutiques inscrites, activer/suspendre les comptes et prolonger les périodes d'essai.
- **🛒 Caisse & POS Mobile :** Panier réactif, lecteur code-barres, calcul automatique de la monnaie et impression thermique.
- **📊 Tableau de bord fidèle :** 5 indicateurs clés dynamiques avec design par vagues asymétriques conformes à la charte graphique.
