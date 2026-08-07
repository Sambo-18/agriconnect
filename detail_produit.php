<?php
/**
 * Fiche Détaillée d'un Produit Agricole
 * Application AgriConnect
 */

require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT p.*, c.nom AS categorie_nom, 
           u.id AS agriculteur_id, u.nom_complet AS agriculteur_nom, u.telephone, u.email, u.ville, u.adresse, u.photo AS agriculteur_photo
    FROM produits p
    JOIN categories c ON p.categorie_id = c.id
    JOIN utilisateurs u ON p.agriculteur_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$produit = $stmt->fetch();

if (!$produit) {
    definir_flash('danger', 'Produit introuvable.');
    header('Location: produits.php');
    exit();
}

$page_title = $produit['nom'];
require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-top: 20px; margin-bottom: 20px;">
    <a href="produits.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour au catalogue</a>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 40px; margin-bottom: 60px;">
    <!-- Photo & Carte -->
    <div>
        <div style="background: var(--white); padding: 10px; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); margin-bottom: 24px;">
            <img src="assets/images/<?php echo htmlspecialchars($produit['photo']); ?>" 
                 onerror="this.src='https://images.unsplash.com/photo-1595855759920-86582396756a?auto=format&fit=crop&w=800&q=80'" 
                 alt="<?php echo htmlspecialchars($produit['nom']); ?>" 
                 style="width: 100%; max-height: 380px; object-fit: cover; border-radius: var(--radius-sm);">
        </div>

        <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200);">
            <h4 style="margin-bottom: 12px; color: var(--dark);"><i class="fa-solid fa-map-pin" style="color: var(--primary);"></i> Localisation de l'exploitation</h4>
            <p style="font-size: 0.9rem; color: var(--gray-500); margin-bottom: 14px;">
                <?php echo htmlspecialchars($produit['adresse_retrait'] ?: $produit['adresse']); ?>, <?php echo htmlspecialchars($produit['ville']); ?>
            </p>
            <div id="mapProduit" class="map-container" style="height: 250px;"></div>
        </div>
    </div>

    <!-- Informations & Commande -->
    <div>
        <span class="product-badge-cat" style="position: static; display: inline-block; margin-bottom: 12px; font-size: 0.85rem; padding: 6px 16px;">
            <?php echo htmlspecialchars($produit['categorie_nom']); ?>
        </span>
        <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--dark); margin-bottom: 16px;"><?php echo htmlspecialchars($produit['nom']); ?></h1>

        <div class="product-price" style="font-size: 2.2rem; margin-bottom: 24px;">
            <?php echo formater_prix($produit['prix']); ?> <span style="font-size: 1.1rem;">/ <?php echo htmlspecialchars($produit['unite']); ?></span>
        </div>

        <div style="background: var(--white); padding: 24px; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); margin-bottom: 30px;">
            <h3 style="font-size: 1.1rem; margin-bottom: 12px; color: var(--dark);">Description du produit</h3>
            <p style="color: var(--gray-700); line-height: 1.7; margin-bottom: 20px;">
                <?php echo nl2br(htmlspecialchars($produit['description'] ?: 'Aucune description spécifique fournie pour ce produit.')); ?>
            </p>

            <div style="display: flex; gap: 20px; border-top: 1px solid var(--gray-200); padding-top: 16px; font-size: 0.95rem;">
                <div>Quantité disponible : <strong style="color: var(--primary-dark);"><?php echo $produit['quantite_disponible'] . ' ' . htmlspecialchars($produit['unite']); ?></strong></div>
                <div>Date de publication : <strong><?php echo formater_date($produit['date_publication']); ?></strong></div>
            </div>
        </div>

        <!-- Fiche Producteur -->
        <div style="background: #f0fdf4; padding: 20px; border-radius: var(--radius-md); border: 1px solid #bbf7d0; margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 700;">
                    <?php echo strtoupper(substr($produit['agriculteur_nom'], 0, 1)); ?>
                </div>
                <div>
                    <h4 style="margin: 0; color: var(--dark);"><?php echo htmlspecialchars($produit['agriculteur_nom']); ?></h4>
                    <span style="font-size: 0.85rem; color: var(--gray-500);"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($produit['telephone']); ?></span>
                </div>
            </div>

            <?php if (est_connecte()): ?>
                <a href="messagerie.php?contact_id=<?php echo $produit['agriculteur_id']; ?>" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-comments"></i> Contacter l'Agriculteur
                </a>
            <?php endif; ?>
        </div>

        <!-- Formulaire de Commande Immédiate -->
        <div style="background: var(--white); padding: 24px; border-radius: var(--radius-md); border: 2px solid var(--primary);">
            <h3 style="font-size: 1.2rem; color: var(--primary-dark); margin-bottom: 16px;"><i class="fa-solid fa-cart-shopping"></i> Passer la Commande</h3>
            
            <form action="commander.php" method="GET">
                <input type="hidden" name="produit_id" value="<?php echo $produit['id']; ?>">
                <input type="hidden" id="prix_unitaire_input" value="<?php echo $produit['prix']; ?>">

                <div class="form-group">
                    <label class="form-label">Quantité souhaitée (en <?php echo htmlspecialchars($produit['unite']); ?>)</label>
                    <input type="number" step="0.1" name="quantite" id="quantite_commande" class="form-control" value="10" min="1" max="<?php echo $produit['quantite_disponible']; ?>" required>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 12px; background: var(--gray-100); border-radius: var(--radius-sm);">
                    <span style="font-weight: 600;">Total de la commande :</span>
                    <strong id="total_commande_display" style="font-size: 1.4rem; color: var(--primary-dark);"><?php echo formater_prix($produit['prix'] * 10); ?></strong>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                    <i class="fa-solid fa-truck-ramp-box"></i> Valider et Choisir la Livraison
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof AgriMap !== 'undefined') {
        const lat = <?php echo !empty($produit['latitude']) ? $produit['latitude'] : 5.4778; ?>;
        const lng = <?php echo !empty($produit['longitude']) ? $produit['longitude'] : 10.4176; ?>;
        const map = L.map('mapProduit').setView([lat, lng], 10);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap | AgriConnect Cameroun 🇨🇲' }).addTo(map);
        L.marker([lat, lng], { icon: AgriMap.icons.ferme }).addTo(map).bindPopup("<b><?php echo htmlspecialchars($produit['agriculteur_nom']); ?></b>");
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
