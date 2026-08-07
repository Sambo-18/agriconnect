<?php
/**
 * Page d'Accueil Publique (Visiteurs)
 * Application AgriConnect Cameroun 🇨🇲
 */

require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

// Si un acteur est déjà connecté, redirection automatique vers son Tableau de Bord avec Sidebar fixe
if (est_connecte()) {
    header('Location: tableau_de_bord.php');
    exit();
}

$page_title = "Accueil - Plateforme Agricole & Transport Géolocalisé Cameroun";
require_once __DIR__ . '/includes/header.php';

// Récupérer quelques produits récents
$stmtProd = $pdo->query("
    SELECT p.*, c.nom AS categorie_nom, u.nom_complet AS agriculteur_nom, u.ville
    FROM produits p
    JOIN categories c ON p.categorie_id = c.id
    JOIN utilisateurs u ON p.agriculteur_id = u.id
    WHERE p.statut = 'disponible' AND p.quantite_disponible > 0
    ORDER BY p.date_publication DESC
    LIMIT 6
");
$produits_recents = $stmtProd->fetchAll();

// Statistiques globales du marché camerounais
$stat_agri = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'agriculteur'")->fetchColumn();
$stat_ach = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'acheteur'")->fetchColumn();
$stat_trans = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'transporteur'")->fetchColumn();
$stat_cmd = $pdo->query("SELECT COUNT(*) FROM commandes WHERE statut = 'livree'")->fetchColumn();
?>

<!-- Hero Banner -->
<section class="hero-section">
    <div class="container hero-content">
        <div class="hero-badge">
            <i class="fa-solid fa-satellite-dish"></i> Plateforme Numérique Agricole & Suivi GPS Temps Réel 🇨🇲
        </div>
        <h1 class="hero-title">
            Commercialisez vos récoltes & <span>suivez vos livraisons</span> au Cameroun
        </h1>
        <p class="hero-subtitle">
            AgriConnect relie directement les <strong>Agriculteurs</strong>, les <strong>Acheteurs</strong> et les <strong>Transporteurs</strong> camerounais (Douala, Yaoundé, Bafoussam, Bamenda...). Réduisez les intermédiaires et suivez les transports en temps réel.
        </p>

        <div class="hero-actions">
            <a href="inscription.php" class="btn btn-accent btn-lg">
                <i class="fa-solid fa-user-plus"></i> Créer un compte Gratuitement
            </a>
            <a href="connexion.php" class="btn btn-outline btn-lg" style="color: var(--white); border-color: var(--white);">
                <i class="fa-solid fa-right-to-bracket"></i> Se Connecter
            </a>
        </div>
    </div>
</section>

<!-- Cartes des 3 Rôles d'Utilisateurs -->
<div class="container">
    <div class="roles-grid">
        <div class="role-card agriculteur">
            <div class="role-icon">
                <i class="fa-solid fa-tractor"></i>
            </div>
            <h3>Agriculteurs</h3>
            <p>
                Publiez rapidement vos récoltes avec photos, prix et localisation de votre champ. Choisissez vos transporteurs et recevez vos commandes directement.
            </p>
            <a href="inscription.php?role=agriculteur" class="btn btn-outline btn-sm">S'inscrire comme Agriculteur <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <div class="role-card acheteur">
            <div class="role-icon">
                <i class="fa-solid fa-basket-shopping"></i>
            </div>
            <h3>Acheteurs & Grossistes</h3>
            <p>
                Recherchez des produits agricoles frais par région, catégorie et prix. Commandez en ligne et suivez le transporteur en temps réel sur la carte.
            </p>
            <a href="inscription.php?role=acheteur" class="btn btn-outline btn-sm">S'inscrire comme Acheteur <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <div class="role-card transporteur">
            <div class="role-icon">
                <i class="fa-solid fa-truck-fast"></i>
            </div>
            <h3>Transporteurs</h3>
            <p>
                Recevez des opportunités de livraison agricole sur vos trajets habituels entre régions. Partagez votre position GPS en direct.
            </p>
            <a href="inscription.php?role=transporteur" class="btn btn-outline btn-sm">S'inscrire comme Transporteur <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </div>
</div>

<!-- Section Statistiques -->
<div class="container">
    <div class="stats-section">
        <div class="stats-grid">
            <div class="stat-item">
                <h3><?php echo number_format($stat_agri); ?>+</h3>
                <p>Agriculteurs Inscrits</p>
            </div>
            <div class="stat-item">
                <h3><?php echo number_format($stat_ach); ?>+</h3>
                <p>Acheteurs Actifs</p>
            </div>
            <div class="stat-item">
                <h3><?php echo number_format($stat_trans); ?>+</h3>
                <p>Transporteurs Vérifiés</p>
            </div>
            <div class="stat-item">
                <h3><?php echo number_format($stat_cmd); ?>+</h3>
                <p>Livraisons Réussies</p>
            </div>
        </div>
    </div>
</div>

<!-- Dernières Récoltes Publiées -->
<div class="container" style="margin-bottom: 70px;">
    <div class="section-header">
        <h2>Récoltes Récentes au Cameroun</h2>
        <p>Découvrez les produits agricoles frais en direct des exploitations camerounaises.</p>
    </div>

    <div class="products-grid">
        <?php foreach ($produits_recents as $p): ?>
            <div class="product-card">
                <div class="product-image-wrap">
                    <span class="product-badge-cat"><?php echo htmlspecialchars($p['categorie_nom']); ?></span>
                    <img src="assets/images/<?php echo htmlspecialchars($p['photo']); ?>" 
                         onerror="this.src='https://images.unsplash.com/photo-1595855759920-86582396756a?auto=format&fit=crop&w=600&q=80'" 
                         alt="<?php echo htmlspecialchars($p['nom']); ?>">
                </div>
                <div class="product-details">
                    <h3 class="product-title"><?php echo htmlspecialchars($p['nom']); ?></h3>
                    <div class="product-meta">
                        <span><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($p['agriculteur_nom']); ?></span>
                        <span><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($p['ville']); ?></span>
                    </div>
                    <div class="product-price">
                        <?php echo formater_prix($p['prix']); ?> <span>/ <?php echo htmlspecialchars($p['unite']); ?></span>
                    </div>
                    <div style="margin-top: auto;">
                        <a href="detail_produit.php?id=<?php echo $p['id']; ?>" class="btn btn-outline btn-sm" style="width: 100%;">
                            <i class="fa-solid fa-eye"></i> Voir Fiche Produit
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="text-align: center;">
        <a href="produits.php" class="btn btn-primary btn-lg">
            <i class="fa-solid fa-border-all"></i> Consulter tout le catalogue de produits
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
