<?php
/**
 * Catalogue Général des Produits Agricoles (Cameroun 🇨🇲)
 * Application AgriConnect
 */

require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

// Règle spécifique Agriculteur : s'il s'agit d'un agriculteur sans le mode "catalogue" explicite, le rediriger vers ses propres publications (mes_produits.php)
if (est_connecte() && $_SESSION['utilisateur_role'] === 'agriculteur' && (!isset($_GET['mode']) || $_GET['mode'] !== 'catalogue')) {
    header('Location: mes_produits.php');
    exit();
}

$page_title = "Produits Agricoles";
require_once __DIR__ . '/includes/header.php';

// Récupération des filtres
$recherche = securiser($_GET['q'] ?? '');
$categorie_id = !empty($_GET['categorie']) ? (int)$_GET['categorie'] : 0;
$region = securiser($_GET['region'] ?? '');
$prix_max = !empty($_GET['prix_max']) ? (float)$_GET['prix_max'] : 0;

// Construction de la requête SQL
$query = "
    SELECT p.*, c.nom AS categorie_nom, u.nom_complet AS agriculteur_nom, u.ville, u.telephone
    FROM produits p
    JOIN categories c ON p.categorie_id = c.id
    JOIN utilisateurs u ON p.agriculteur_id = u.id
    WHERE p.statut = 'disponible' AND p.quantite_disponible > 0
";

$params = [];

if (!empty($recherche)) {
    $query .= " AND (p.nom LIKE ? OR p.description LIKE ?)";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}

if ($categorie_id > 0) {
    $query .= " AND p.categorie_id = ?";
    $params[] = $categorie_id;
}

if (!empty($region)) {
    $query .= " AND (u.ville LIKE ? OR p.adresse_retrait LIKE ?)";
    $params[] = "%$region%";
    $params[] = "%$region%";
}

if ($prix_max > 0) {
    $query .= " AND p.prix <= ?";
    $params[] = $prix_max;
}

$query .= " ORDER BY p.date_publication DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$produits = $stmt->fetchAll();

// Récupérer toutes les catégories pour le menu déroulant
$categories = $pdo->query("SELECT * FROM categories ORDER BY nom ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2><i class="fa-solid fa-basket-shopping" style="color: var(--primary);"></i> Catalogue Global des Produits Agricoles</h2>
        <p style="color: var(--gray-500);">Achetez en direct auprès des agriculteurs camerounais aux meilleurs prix.</p>
    </div>

    <?php if (est_connecte() && $_SESSION['utilisateur_role'] === 'agriculteur'): ?>
        <a href="mes_produits.php" class="btn btn-outline">
            <i class="fa-solid fa-boxes-stacked"></i> Voir Uniquement Mes Récoltes
        </a>
    <?php endif; ?>
</div>

<!-- Barre de Recherche & Filtres -->
<div style="background: var(--white); padding: 24px; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); margin-bottom: 40px;">
    <form action="produits.php" method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
        <?php if (isset($_GET['mode'])): ?>
            <input type="hidden" name="mode" value="catalogue">
        <?php endif; ?>

        <div>
            <label class="form-label"><i class="fa-solid fa-magnifying-glass"></i> Recherche</label>
            <input type="text" name="q" class="form-control" placeholder="ex: Maïs, Plantains..." value="<?php echo htmlspecialchars($recherche); ?>">
        </div>

        <div>
            <label class="form-label"><i class="fa-solid fa-list"></i> Catégorie</label>
            <select name="categorie" class="form-control">
                <option value="0">Toutes les catégories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo ($categorie_id == $cat['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['nom']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="form-label"><i class="fa-solid fa-location-dot"></i> Région / Ville</label>
            <input type="text" name="region" class="form-control" placeholder="ex: Bafoussam, Douala..." value="<?php echo htmlspecialchars($region); ?>">
        </div>

        <div>
            <label class="form-label"><i class="fa-solid fa-tag"></i> Prix max (FCFA)</label>
            <input type="number" name="prix_max" class="form-control" placeholder="ex: 1000" value="<?php echo $prix_max > 0 ? $prix_max : ''; ?>">
        </div>

        <div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fa-solid fa-filter"></i> Filtrer
            </button>
        </div>
    </form>
</div>

<!-- Grille de Produits -->
<?php if (empty($produits)): ?>
    <div style="text-align: center; padding: 60px 20px; background: var(--white); border-radius: var(--radius-md); border: 1px dashed var(--gray-500);">
        <i class="fa-solid fa-seedling" style="font-size: 3rem; color: var(--gray-500); margin-bottom: 16px;"></i>
        <h3>Aucun produit trouvé</h3>
        <p style="color: var(--gray-500);">Essayez de modifier vos critères de recherche ou réinitialisez les filtres.</p>
        <a href="produits.php?mode=catalogue" class="btn btn-outline btn-sm" style="margin-top: 16px;">Réinitialiser la recherche</a>
    </div>
<?php else: ?>
    <div class="products-grid">
        <?php foreach ($produits as $p): ?>
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
                    <p style="font-size: 0.875rem; color: var(--gray-500); margin-bottom: 14px;">
                        Stock disponible : <strong><?php echo $p['quantite_disponible'] . ' ' . htmlspecialchars($p['unite']); ?></strong>
                    </p>
                    <div style="margin-top: auto;">
                        <a href="detail_produit.php?id=<?php echo $p['id']; ?>" class="btn btn-primary btn-sm" style="width: 100%;">
                            <i class="fa-solid fa-eye"></i> Voir Fiche Produit
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
