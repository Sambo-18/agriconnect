<?php
/**
 * Gestion des Ventes & Récoltes Publiées (Agriculteur)
 * Application AgriConnect
 */

$page_title = "Mes Produits & Récoltes";
require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

exiger_role(['agriculteur', 'admin']);

$agriculteur_id = $_SESSION['utilisateur_id'];

// Suppression d'un produit
if (isset($_GET['action']) && $_GET['action'] === 'supprimer' && isset($_GET['id'])) {
    $prod_id = (int)$_GET['id'];
    $stmtDel = $pdo->prepare("DELETE FROM produits WHERE id = ? AND agriculteur_id = ?");
    $stmtDel->execute([$prod_id, $agriculteur_id]);
    definir_flash('success', 'Produit supprimé avec succès.');
    header('Location: mes_produits.php');
    exit();
}

// Mise à jour rapide du stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_maj_stock'])) {
    $prod_id = (int)$_POST['produit_id'];
    $nouveau_stock = (float)$_POST['quantite_disponible'];
    $stmtUpd = $pdo->prepare("UPDATE produits SET quantite_disponible = ?, statut = IF(? > 0, 'disponible', 'epuise') WHERE id = ? AND agriculteur_id = ?");
    $stmtUpd->execute([$nouveau_stock, $nouveau_stock, $prod_id, $agriculteur_id]);
    definir_flash('success', 'Stock mis à jour.');
    header('Location: mes_produits.php');
    exit();
}

// Liste des produits
$stmt = $pdo->prepare("
    SELECT p.*, c.nom AS categorie_nom
    FROM produits p
    JOIN categories c ON p.categorie_id = c.id
    WHERE p.agriculteur_id = ?
    ORDER BY p.date_publication DESC
");
$stmt->execute([$agriculteur_id]);
$mes_produits = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; margin-bottom: 30px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2><i class="fa-solid fa-boxes-stacked" style="color: var(--primary);"></i> Mes Récoltes & Ventes</h2>
        <p style="color: var(--gray-500);">Gérez vos offres agricoles et mettez à jour votre stock disponible.</p>
    </div>
    <a href="publier_produit.php" class="btn btn-primary">
        <i class="fa-solid fa-plus-circle"></i> Ajouter un Nouveau Produit
    </a>
</div>

<?php if (empty($mes_produits)): ?>
    <div style="text-align: center; padding: 60px 20px; background: var(--white); border-radius: var(--radius-md); border: 1px dashed var(--gray-500);">
        <i class="fa-solid fa-tractor" style="font-size: 3rem; color: var(--gray-500); margin-bottom: 16px;"></i>
        <h3>Vous n'avez publié aucune récolte pour l'instant</h3>
        <p style="color: var(--gray-500);">Publiez vos produits pour recevoir des commandes d'acheteurs de toute la région.</p>
        <a href="publier_produit.php" class="btn btn-primary" style="margin-top: 16px;">Publier mon premier produit</a>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Nom du Produit</th>
                    <th>Catégorie</th>
                    <th>Prix Unitaire</th>
                    <th>Stock Disponible</th>
                    <th>Statut</th>
                    <th>Publication</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mes_produits as $p): ?>
                    <tr>
                        <td>
                            <img src="assets/images/<?php echo htmlspecialchars($p['photo']); ?>" 
                                 onerror="this.src='https://images.unsplash.com/photo-1595855759920-86582396756a?auto=format&fit=crop&w=100&q=80'" 
                                 style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover;">
                        </td>
                        <td><strong><?php echo htmlspecialchars($p['nom']); ?></strong></td>
                        <td><?php echo htmlspecialchars($p['categorie_nom']); ?></td>
                        <td><?php echo formater_prix($p['prix']); ?> / <?php echo htmlspecialchars($p['unite']); ?></td>
                        <td>
                            <form action="mes_produits.php" method="POST" style="display: flex; align-items: center; gap: 8px;">
                                <input type="hidden" name="action_maj_stock" value="1">
                                <input type="hidden" name="produit_id" value="<?php echo $p['id']; ?>">
                                <input type="number" step="0.1" name="quantite_disponible" value="<?php echo $p['quantite_disponible']; ?>" class="form-control" style="width: 90px; padding: 4px 8px;">
                                <small><?php echo htmlspecialchars($p['unite']); ?></small>
                                <button type="submit" class="btn btn-sm btn-outline" title="Mettre à jour stock"><i class="fa-solid fa-save"></i></button>
                            </form>
                        </td>
                        <td>
                            <?php if ($p['quantite_disponible'] > 0): ?>
                                <span class="status-badge status-livree">Disponible</span>
                            <?php else: ?>
                                <span class="status-badge status-refusee">Épuisé</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo formater_date($p['date_publication']); ?></td>
                        <td>
                            <a href="detail_produit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline" title="Voir fiche"><i class="fa-solid fa-eye"></i></a>
                            <a href="mes_produits.php?action=supprimer&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');" title="Supprimer"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
