<?php
/**
 * Panier d'Achat & Récapitulatif
 * Application AgriConnect
 */

$page_title = "Mon Panier";
require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/header.php';

// Initialisation panier en session s'il n'existe pas
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

// Action vider panier
if (isset($_GET['action']) && $_GET['action'] === 'vider') {
    $_SESSION['panier'] = [];
    definir_flash('info', 'Le panier a été vidé.');
    header('Location: panier.php');
    exit();
}

$produits_panier = [];
$total_general = 0;

if (!empty($_SESSION['panier'])) {
    $ids = array_keys($_SESSION['panier']);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.*, u.nom_complet AS agriculteur_nom
        FROM produits p
        JOIN utilisateurs u ON p.agriculteur_id = u.id
        WHERE p.id IN ($in)
    ");
    $stmt->execute($ids);
    $items = $stmt->fetchAll();

    foreach ($items as $item) {
        $qty = $_SESSION['panier'][$item['id']];
        $st = $qty * $item['prix'];
        $total_general += $st;
        $produits_panier[] = [
            'info' => $item,
            'quantite' => $qty,
            'sous_total' => $st
        ];
    }
}
?>

<div style="margin-top: 30px; margin-bottom: 30px;">
    <h2><i class="fa-solid fa-cart-shopping" style="color: var(--primary);"></i> Mon Panier d'Achats</h2>
</div>

<?php if (empty($produits_panier)): ?>
    <div style="text-align: center; padding: 60px 20px; background: var(--white); border-radius: var(--radius-md); border: 1px dashed var(--gray-500);">
        <i class="fa-solid fa-basket-shopping" style="font-size: 3rem; color: var(--gray-500); margin-bottom: 16px;"></i>
        <h3>Votre panier est vide</h3>
        <p style="color: var(--gray-500);">Explorez le catalogue pour ajouter des produits agricoles frais.</p>
        <a href="produits.php" class="btn btn-primary" style="margin-top: 16px;">Découvrir les produits</a>
    </div>
<?php else: ?>
    <div class="table-responsive" style="margin-bottom: 30px;">
        <table class="table">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Producteur</th>
                    <th>Prix Unitaire</th>
                    <th>Quantité</th>
                    <th>Sous-total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produits_panier as $p): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($p['info']['nom']); ?></strong></td>
                        <td><?php echo htmlspecialchars($p['info']['agriculteur_nom']); ?></td>
                        <td><?php echo formater_prix($p['info']['prix']); ?></td>
                        <td><?php echo $p['quantite'] . ' ' . htmlspecialchars($p['info']['unite']); ?></td>
                        <td><strong style="color: var(--primary-dark);"><?php echo formater_prix($p['sous_total']); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; background: var(--white); padding: 24px; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); flex-wrap: wrap; gap: 20px;">
        <a href="panier.php?action=vider" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i> Vider le panier</a>
        
        <div style="text-align: right;">
            <div style="font-size: 1.1rem; color: var(--gray-500); margin-bottom: 8px;">Total des articles : <strong style="color: var(--dark); font-size: 1.4rem;"><?php echo formater_prix($total_general); ?></strong></div>
            <a href="commander.php?produit_id=<?php echo $produits_panier[0]['info']['id']; ?>&quantite=<?php echo $produits_panier[0]['quantite']; ?>" class="btn btn-primary btn-lg">
                <i class="fa-solid fa-arrow-right"></i> Procéder au choix de livraison
            </a>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
