<?php
/**
 * Publication d'un Produit Agricole (Réservé Agriculteurs - Cameroun 🇨🇲)
 * Application AgriConnect
 */

$page_title = "Publier une récolte";
require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

exiger_role(['agriculteur', 'admin']);

$agriculteur_id = $_SESSION['utilisateur_id'];
$erreurs = [];

// Récupérer les coordonnées par défaut de l'agriculteur
$stmtUser = $pdo->prepare("SELECT latitude, longitude, adresse, ville FROM utilisateurs WHERE id = ?");
$stmtUser->execute([$agriculteur_id]);
$userInfo = $stmtUser->fetch();

$categories = $pdo->query("SELECT * FROM categories ORDER BY nom ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = securiser($_POST['nom'] ?? '');
    $categorie_id = (int)($_POST['categorie_id'] ?? 0);
    $prix = (float)($_POST['prix'] ?? 0);
    $unite = securiser($_POST['unite'] ?? 'kg');
    $quantite_disponible = (float)($_POST['quantite_disponible'] ?? 0);
    $description = securiser($_POST['description'] ?? '');
    $adresse_retrait = securiser($_POST['adresse_retrait'] ?? '');
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : ($userInfo['latitude'] ?? 5.4778);
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : ($userInfo['longitude'] ?? 10.4176);

    // Validation
    if (empty($nom)) $erreurs[] = "Le nom du produit est obligatoire.";
    if ($categorie_id <= 0) $erreurs[] = "Veuillez sélectionner une catégorie.";
    if ($prix <= 0) $erreurs[] = "Le prix doit être supérieur à 0 FCFA.";
    if ($quantite_disponible <= 0) $erreurs[] = "La quantité disponible doit être supérieure à 0.";

    // Upload Photo
    $nom_photo = 'default_product.jpg';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $extensions_valides = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $extensions_valides)) {
            $nouveau_nom = 'produit_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $dossier_destination = __DIR__ . '/assets/images/' . $nouveau_nom;
            if (!is_dir(__DIR__ . '/assets/images')) {
                mkdir(__DIR__ . '/assets/images', 0777, true);
            }
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dossier_destination)) {
                $nom_photo = $nouveau_nom;
            }
        }
    }

    if (empty($erreurs)) {
        try {
            $stmtIns = $pdo->prepare("
                INSERT INTO produits (agriculteur_id, categorie_id, nom, description, prix, unite, quantite_disponible, photo, adresse_retrait, latitude, longitude, statut)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'disponible')
            ");
            $stmtIns->execute([$agriculteur_id, $categorie_id, $nom, $description, $prix, $unite, $quantite_disponible, $nom_photo, $adresse_retrait, $latitude, $longitude]);

            definir_flash('success', 'Votre produit "' . $nom . '" a été publié avec succès.');
            header('Location: mes_produits.php');
            exit();
        } catch (PDOException $e) {
            $erreurs[] = "Erreur BDD : " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-card" style="max-width: 750px;">
    <h2 class="form-title"><i class="fa-solid fa-plus-circle" style="color: var(--primary);"></i> Publier une Récolte Agricole</h2>

    <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger">
            <ul style="margin-left: 20px;">
                <?php foreach ($erreurs as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="publier_produit.php" method="POST" enctype="multipart/form-data">
        
        <div class="form-group">
            <label class="form-label">Nom du produit agricole</label>
            <input type="text" name="nom" class="form-control" placeholder="ex: Maïs Jaune de Penja, Bananes Plantains, Manioc..." required value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group">
                <label class="form-label">Catégorie</label>
                <select name="categorie_id" class="form-control" required>
                    <option value="">-- Choisir une catégorie --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['categorie_id']) && $_POST['categorie_id'] == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Unité de mesure</label>
                <select name="unite" class="form-control">
                    <option value="kg">Kilogramme (kg)</option>
                    <option value="sac">Sac</option>
                    <option value="seau">Seau</option>
                    <option value="tonne">Tonne</option>
                    <option value="regime">Régime (Plantain/Banane)</option>
                    <option value="carton">Carton</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group">
                <label class="form-label">Prix unitaire (FCFA)</label>
                <input type="number" step="10" name="prix" class="form-control" placeholder="ex: 500" required value="<?php echo htmlspecialchars($_POST['prix'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Quantité disponible</label>
                <input type="number" step="0.1" name="quantite_disponible" class="form-control" placeholder="ex: 200" required value="<?php echo htmlspecialchars($_POST['quantite_disponible'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Description détaillée</label>
            <textarea name="description" class="form-control" rows="4" placeholder="Précisez la variété, le mode de culture, la disponibilité..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label"><i class="fa-solid fa-camera"></i> Photo du produit</label>
            <input type="file" name="photo" class="form-control" accept="image/*">
        </div>

        <div class="form-group">
            <label class="form-label">Adresse / Lieu de retrait de la marchandise</label>
            <input type="text" name="adresse_retrait" class="form-control" placeholder="ex: Exploitation Agricole Fotso, Bafoussam" value="<?php echo htmlspecialchars($_POST['adresse_retrait'] ?? ($userInfo['adresse'] ?: '')); ?>">
        </div>

        <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 10px;">
                <label class="form-label" style="margin: 0;"><i class="fa-solid fa-location-pin"></i> Point GPS de l'exploitation</label>
                <button type="button" id="btnMaPositionGPS" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-crosshairs"></i> Capturer Ma Position GPS Temps Réel
                </button>
            </div>
            <div id="mapPublier" class="map-container map-picker-container"></div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 10px;">
                <input type="text" name="latitude" id="latPublier" class="form-control" readonly value="<?php echo $userInfo['latitude'] ?? 5.4778; ?>">
                <input type="text" name="longitude" id="lngPublier" class="form-control" readonly value="<?php echo $userInfo['longitude'] ?? 10.4176; ?>">
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
            <i class="fa-solid fa-paper-plane"></i> Publier l'Annonce
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof AgriMap !== 'undefined') {
        const initLat = <?php echo !empty($userInfo['latitude']) ? $userInfo['latitude'] : 5.4778; ?>;
        const initLng = <?php echo !empty($userInfo['longitude']) ? $userInfo['longitude'] : 10.4176; ?>;
        AgriMap.initPicker('mapPublier', 'latPublier', 'lngPublier', initLat, initLng);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
