# 📘 GUIDE D'INSTALLATION ET DE DÉPLOIEMENT
## Application Web Agricole : **AgriConnect 🇨🇲**

Ce guide détaille la procédure pas à pas pour installer, configurer, déployer et tester l'application **AgriConnect** sur votre environnement local ou serveur. Il est structuré pour être directement inséré dans un rapport technique ou académique.

---

## 1. 🏗️ Architecture Technique & Prérequis

### 1.1 Technologies Utilisées
* **Langage Backend** : PHP 8.0+ (PHP 8.2 recommandé)
* **Base de Données** : MySQL 8.0+ / MariaDB (Driver PDO)
* **Frontend** : HTML5, CSS3 Vanilla (Responsive Design, Modèles de cartes modernisés), JavaScript ES6+
* **Gestionnaire de Version** : Git
* **Outils d'Exécution** : WampServer / XAMPP / Serveur PHP CLI

### 1.2 Extensions PHP Requises
Assurez-vous que les extensions PHP suivantes sont activées dans votre `php.ini` :
* `pdo_mysql` (Obligatoire pour les requêtes BDD)
* `mbstring` (Gestion de l'encodage UTF-8)
* `session` (Gestion des sessions utilisateurs)
* `json` (Traitement des réponses API et données géographiques)

---

## 2. 📥 Étape 1 : Récupération du Code Source

Déplacez-vous dans le répertoire web de votre serveur local (`C:\wamp64\www\` sous WampServer ou `C:\xampp\htdocs\` sous XAMPP) et clonez le dépôt Git officiel :

```bash
git clone https://github.com/Sambo-18/agriconnect.git
cd agriconnect
```

---

## 3. 🗄️ Étape 2 : Configuration de la Base de Données (`bd_agricole`)

1. **Démarrer les services MySQL & Apache** depuis votre panneau de contrôle WampServer ou XAMPP.
2. Ouvrez **PHPMyAdmin** dans votre navigateur : `http://localhost/phpmyadmin`.
3. Cliquez sur **Nouvelle base de données** :
   * **Nom de la base** : `bd_agricole`
   * **Interclassement** : `utf8mb4_unicode_ci` (ou `utf8mb4_general_ci`)
4. Cliquez sur l'onglet **Importer** :
   * Sélectionnez le fichier SQL fourni à la racine du projet : `bd_agricole.sql`.
   * Cliquez sur **Importer** en bas de page pour exécuter la création des tables et le chargement des données de démonstration.

---

## 4. ⚙️ Étape 3 : Configuration du Fichier de Connexion (`config/connexion_db.php`)

Vérifiez les paramètres de connexion dans le fichier `config/connexion_db.php` :

```php
// Parameters MySQL
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Utilisateur MySQL par défaut
define('DB_PASS', '');          // Mot de passe (vide par défaut sous WAMP)
define('DB_NAME', 'bd_agricole');
define('DB_CHARSET', 'utf8mb4');
```

*Note : Ajustez `DB_USER` et `DB_PASS` si votre serveur MySQL dispose d'un mot de passe dédié.*

---

## 5. 🚀 Étape 4 : Lancement de l'Application Web

### Option A : Lancement via le Serveur Intégré PHP (Méthode Recommandée / Rapide)
Ouvrez PowerShell ou Invite de commandes dans le dossier du projet et lancez :

```powershell
C:\wamp64\bin\php\php8.2.29\php.exe -S 127.0.0.1:8080
```
> L'application sera accessible instantanément sur : **[http://127.0.0.1:8080](http://127.0.0.1:8080)**

### Option B : Lancement via WampServer / XAMPP Standard
Placez le projet dans `www/agriconnect` et accédez à :  
👉 `http://localhost/agriconnect`

---

## 6. 🔐 Étape 5 : Comptes de Démonstration (Prêts à l'emploi)

Pour tester chaque rôle de l'application (Administrateur, Agriculteur, Transporteur, Acheteur), utilisez les comptes de test pré-configurés :

| Rôle | Adresse E-mail | Mot de passe | Espace dédié |
| :--- | :--- | :--- | :--- |
| **Administrateur** | `admin@agriconnect.com` | `password123` | Dashboard Admin (`admin.php`) |
| **Agriculteur** | `agriculteur@agriconnect.com` | `password123` | Mes Produits & Ventes (`mes_produits.php`) |
| **Acheteur** | `acheteur@agriconnect.com` | `password123` | Catalogue & Panier (`produits.php`) |
| **Transporteur** | `transporteur@agriconnect.com` | `password123` | Suivi des Livraisons (`mes_commandes.php`) |

---

## 7. 🧪 Étape 6 : Exécution de la Suite de Tests Automatisés

AgriConnect intègre une suite automatisée comprenant des **Tests Unitaires** (sécurité, assainissement XSS, hashage) et des **Tests d'Intégration** (interrogation BDD MySQL, vérification de session).

### Exécuter l'intégralité des tests :
```powershell
C:\wamp64\bin\php\php8.2.29\php.exe tests/run_tests.php
```

### Exécuter un test spécifique :
* **Tests Unitaires uniquement** :
  ```powershell
  C:\wamp64\bin\php\php8.2.29\php.exe tests/AuthUnitTest.php
  ```
* **Tests d'Intégration uniquement** :
  ```powershell
  C:\wamp64\bin\php\php8.2.29\php.exe tests/AuthIntegrationTest.php
  ```

---

## 🛠️ 8. Résolution des Problèmes Courants (Troubleshooting)

1. **Erreur : `CommandNotFoundException` pour `php`**
   * *Solution* : Spécifiez le chemin complet `C:\wamp64\bin\php\php8.2.29\php.exe` ou ajoutez le dossier PHP à la variable d'environnement PATH avec la commande `$env:Path += ";C:\wamp64\bin\php\php8.2.29"`.

2. **Erreur de Connexion à la Base de Données (PDOException)**
   * *Solution* : Vérifiez que MySQL est démarré sur WAMP/XAMPP et que la base de données s'appelle bien `bd_agricole`.

3. **Port 8000 déjà utilisé**
   * *Solution* : Lancez le serveur sur le port 8080 avec `php -S 127.0.0.1:8080`.
