-- ============================================================
-- Base de données : bd_agricole
-- Application : AgriConnect (Version Cameroun 🇨🇲 avec Paiement MoMo & Certification CNI)
-- Description : Plateforme de mise en relation entre Agriculteurs, Acheteurs et Transporteurs au Cameroun
-- ============================================================

CREATE DATABASE IF NOT EXISTS `bd_agricole` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bd_agricole`;

-- --------------------------------------------------------
-- Table : utilisateurs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nom_complet` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `mot_de_passe` VARCHAR(255) NOT NULL,
  `role` ENUM('agriculteur', 'acheteur', 'transporteur', 'admin') NOT NULL DEFAULT 'acheteur',
  `telephone` VARCHAR(30) DEFAULT NULL,
  `photo` VARCHAR(255) DEFAULT 'default_avatar.png',
  `adresse` VARCHAR(255) DEFAULT NULL,
  `ville` VARCHAR(100) DEFAULT NULL,
  `latitude` DECIMAL(10, 8) DEFAULT NULL,
  `longitude` DECIMAL(11, 8) DEFAULT NULL,
  `statut` ENUM('actif', 'suspendu') NOT NULL DEFAULT 'actif',
  `est_certifie` TINYINT(1) NOT NULL DEFAULT 0,
  `statut_certification` ENUM('non_demande', 'en_attente', 'valide', 'rejete') NOT NULL DEFAULT 'non_demande',
  `photo_cni` VARCHAR(255) DEFAULT NULL,
  `date_demande_certification` DATETIME DEFAULT NULL,
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table : transporteurs_details
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transporteurs_details` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `utilisateur_id` INT NOT NULL,
  `type_vehicule` VARCHAR(100) NOT NULL,
  `immatriculation` VARCHAR(50) NOT NULL,
  `capacite_tonnes` DECIMAL(8,2) NOT NULL DEFAULT 1.00,
  `disponible` ENUM('oui', 'non') NOT NULL DEFAULT 'oui',
  `latitude_actuelle` DECIMAL(10, 8) DEFAULT NULL,
  `longitude_actuelle` DECIMAL(11, 8) DEFAULT NULL,
  `derniere_maj_gps` DATETIME DEFAULT NULL,
  FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table : categories
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `icone` VARCHAR(50) DEFAULT 'fa-leaf'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table : produits
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `produits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agriculteur_id` INT NOT NULL,
  `categorie_id` INT NOT NULL,
  `nom` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `prix` DECIMAL(10, 2) NOT NULL,
  `unite` VARCHAR(30) NOT NULL DEFAULT 'kg',
  `quantite_disponible` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `photo` VARCHAR(255) DEFAULT 'default_product.jpg',
  `adresse_retrait` VARCHAR(255) DEFAULT NULL,
  `latitude` DECIMAL(10, 8) DEFAULT NULL,
  `longitude` DECIMAL(11, 8) DEFAULT NULL,
  `statut` ENUM('disponible', 'epuise', 'masque') NOT NULL DEFAULT 'disponible',
  `date_publication` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`agriculteur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`categorie_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table : commandes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commandes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `acheteur_id` INT NOT NULL,
  `agriculteur_id` INT NOT NULL,
  `transporteur_id` INT DEFAULT NULL,
  `statut` ENUM('en_attente', 'acceptee', 'refusee', 'en_cours_livraison', 'livree', 'annulee') NOT NULL DEFAULT 'en_attente',
  `adresse_livraison` VARCHAR(255) NOT NULL,
  `latitude_livraison` DECIMAL(10, 8) DEFAULT NULL,
  `longitude_livraison` DECIMAL(11, 8) DEFAULT NULL,
  `frais_transport` DECIMAL(10, 2) DEFAULT 0.00,
  `total_prix` DECIMAL(10, 2) NOT NULL,
  `statut_paiement_acheteur` ENUM('non_paye', 'paye') NOT NULL DEFAULT 'non_paye',
  `mode_paiement_acheteur` ENUM('mtn_momo', 'orange_money') DEFAULT NULL,
  `reference_transaction_acheteur` VARCHAR(100) DEFAULT NULL,
  `statut_paiement_transport` ENUM('non_paye', 'paye') NOT NULL DEFAULT 'non_paye',
  `mode_paiement_transport` ENUM('mtn_momo', 'orange_money') DEFAULT NULL,
  `reference_transaction_transport` VARCHAR(100) DEFAULT NULL,
  `date_commande` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `date_livraison` DATETIME DEFAULT NULL,
  FOREIGN KEY (`acheteur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`agriculteur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`transporteur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table : detail_commandes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `detail_commandes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `commande_id` INT NOT NULL,
  `produit_id` INT NOT NULL,
  `quantite` DECIMAL(10, 2) NOT NULL,
  `prix_unitaire` DECIMAL(10, 2) NOT NULL,
  FOREIGN KEY (`commande_id`) REFERENCES `commandes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table : gps_historique
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gps_historique` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `commande_id` INT NOT NULL,
  `transporteur_id` INT NOT NULL,
  `latitude` DECIMAL(10, 8) NOT NULL,
  `longitude` DECIMAL(11, 8) NOT NULL,
  `horodatage` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`commande_id`) REFERENCES `commandes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`transporteur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table : messages
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `expediteur_id` INT NOT NULL,
  `destinataire_id` INT NOT NULL,
  `commande_id` INT DEFAULT NULL,
  `message` TEXT NOT NULL,
  `date_envoi` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `lu` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`expediteur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`destinataire_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`commande_id`) REFERENCES `commandes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table : notifications
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `utilisateur_id` INT NOT NULL,
  `titre` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `lu` TINYINT(1) DEFAULT 0,
  `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table : avis
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `avis` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `commande_id` INT NOT NULL,
  `auteur_id` INT NOT NULL,
  `cible_id` INT NOT NULL,
  `type_cible` ENUM('agriculteur', 'transporteur') NOT NULL,
  `note` INT NOT NULL CHECK (`note` BETWEEN 1 AND 5),
  `commentaire` TEXT DEFAULT NULL,
  `date_avis` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`commande_id`) REFERENCES `commandes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`auteur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cible_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table : signalements
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `signalements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `auteur_id` INT NOT NULL,
  `produit_id` INT DEFAULT NULL,
  `raison` TEXT NOT NULL,
  `statut` ENUM('nouveau', 'traite', 'rejete') NOT NULL DEFAULT 'nouveau',
  `date_signalement` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`auteur_id`) REFERENCES `utilisateurs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`produit_id`) REFERENCES `produits`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DONNEES PAR DEFAUT - CAMEROUN 🇨🇲
-- ============================================================

INSERT IGNORE INTO `categories` (`id`, `nom`, `description`, `icone`) VALUES
(1, 'Céréales & Grains', 'Maïs jaune, Riz de Yagoua, Sorgho, Haricots rouges', 'fa-wheat-awn'),
(2, 'Fruits Frais', 'Mangues, Bananes plantains, Ananas, Papayes', 'fa-apple-whole'),
(3, 'Légumes & Racines', 'Tomates de Foumbot, Oignons de Maroua, Manioc, Carottes', 'fa-carrot'),
(4, 'Tubercules & Macabo', 'Igname, Macabo, Patate douce, Taro', 'fa-seedling'),
(5, 'Épices & Condiments', 'Piment de Penja, Poivre blanc de Penja, Gingembre, Ail', 'fa-pepper-hot');

-- Utilisateurs avec certification pré-validée pour l'Agriculteur et le Transporteur démo
INSERT IGNORE INTO `utilisateurs` (`id`, `nom_complet`, `email`, `mot_de_passe`, `role`, `telephone`, `adresse`, `ville`, `latitude`, `longitude`, `statut`, `est_certifie`, `statut_certification`) VALUES
(1, 'Administrateur AgriConnect 🇨🇲', 'admin@agriconnect.com', '$2y$10$TdkA3Hbzn1ETOS8UBynrl.g/5XS3i2eMHI0dTiAFhdnq6iXeG0SlS', 'admin', '+237 699 00 11 22', 'Quartier Bastos', 'Yaoundé', 3.8812, 11.5173, 'actif', 1, 'valide'),
(2, 'Ferme Agricole Fotso & Fils', 'agriculteur@agriconnect.com', '$2y$10$TdkA3Hbzn1ETOS8UBynrl.g/5XS3i2eMHI0dTiAFhdnq6iXeG0SlS', 'agriculteur', '+237 677 88 99 00', 'Secteur Agricole Dschang-Bafoussam', 'Bafoussam', 5.4778, 10.4176, 'actif', 1, 'valide'),
(3, 'Tagne Emmanuel (Grossiste Marche Akwa)', 'acheteur@agriconnect.com', '$2y$10$TdkA3Hbzn1ETOS8UBynrl.g/5XS3i2eMHI0dTiAFhdnq6iXeG0SlS', 'acheteur', '+237 655 44 33 22', 'Marché Central Akwa, Allée B', 'Douala', 4.0511, 9.7679, 'actif', 0, 'non_demande'),
(4, 'Express Logistique Cameroun (Kamdem)', 'transporteur@agriconnect.com', '$2y$10$TdkA3Hbzn1ETOS8UBynrl.g/5XS3i2eMHI0dTiAFhdnq6iXeG0SlS', 'transporteur', '+237 690 12 34 56', 'Gare Routière de Bafoussam', 'Bafoussam', 5.4650, 10.4050, 'actif', 1, 'valide');

-- Détails Transporteur Camerounais
INSERT INTO `transporteurs_details` (`utilisateur_id`, `type_vehicule`, `immatriculation`, `capacite_tonnes`, `disponible`, `latitude_actuelle`, `longitude_actuelle`, `derniere_maj_gps`) VALUES
(4, 'Camion Canter 7.5T Bâché', 'LT-8492-AX', 7.50, 'oui', 5.4700, 10.4100, NOW());

-- Produits d'exemple
INSERT INTO `produits` (`agriculteur_id`, `categorie_id`, `nom`, `description`, `prix`, `unite`, `quantite_disponible`, `photo`, `adresse_retrait`, `latitude`, `longitude`, `statut`) VALUES
(2, 1, 'Maïs Jaune Frais Récolte 2026', 'Maïs jaune de la région de l\'Ouest Cameroun, séché naturellement au soleil.', 250.00, 'kg', 1500.00, 'mais.jpg', 'Exploitation Agricole Fotso, Bafoussam', 5.4778, 10.4176, 'disponible'),
(2, 2, 'Banane Plantain Mûre de Penja', 'Bananes plantains bien charnues et mûries sur pied à Penja.', 500.00, 'kg', 800.00, 'plantain.jpg', 'Exploitation Agricole Fotso, Bafoussam', 5.4778, 10.4176, 'disponible'),
(2, 3, 'Manioc Frais de Champ (Foumbot)', 'Tubercules de manioc frais déterrés pour fabriquer l\'attiéké et le baton de manioc.', 350.00, 'sac', 450.00, 'manioc.jpg', 'Exploitation Agricole Fotso, Bafoussam', 5.4778, 10.4176, 'disponible'),
(2, 4, 'Igname Florido de Première Qualité', 'Belles tubercules d\'ignames blanches très prisées sur le marché.', 750.00, 'sac', 120.00, 'igname.jpg', 'Exploitation Agricole Fotso, Bafoussam', 5.4778, 10.4176, 'disponible');

-- Notifications
INSERT IGNORE INTO `notifications` (`utilisateur_id`, `titre`, `message`) VALUES
(2, 'Bienvenue sur AgriConnect Cameroun !', 'Votre compte agriculteur est certifié avec le badge bleu 🔵.'),
(3, 'Bienvenue sur AgriConnect Cameroun !', 'Trouvez les meilleurs produits agricoles en direct des producteurs certifiés.'),
(4, 'Bienvenue sur AgriConnect Cameroun !', 'Votre véhicule et profil transporteur sont certifiés.');

-- Avis et Notes Évaluations d'exemple pour les transporteurs et agriculteurs
INSERT IGNORE INTO `commandes` (`id`, `acheteur_id`, `agriculteur_id`, `transporteur_id`, `statut`, `adresse_livraison`, `frais_transport`, `total_prix`, `statut_paiement_acheteur`, `statut_paiement_transport`) VALUES
(100, 3, 2, 4, 'livree', 'Marché Central Akwa, Douala', 12000.00, 45000.00, 'paye', 'paye');

INSERT IGNORE INTO `avis` (`id`, `commande_id`, `auteur_id`, `cible_id`, `type_cible`, `note`, `commentaire`) VALUES
(1, 100, 3, 4, 'transporteur', 5, 'Transporteur très ponctuel et professionnel ! Les sacs de plantain sont arrivés intacts à Douala.'),
(2, 100, 3, 2, 'agriculteur', 5, 'Produits d\'excellente qualité, maïs très bien séché.');
