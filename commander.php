<?php
/**
 * Validation de Commande & Paiement Mobile Money (MTN MoMo & Orange Money Cameroun 🇨🇲)
 * Application AgriConnect
 */

$page_title = "Valider & Payer la Commande";
require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

exiger_connexion();

$acheteur_id = $_SESSION['utilisateur_id'];
$produit_id = isset($_GET['produit_id']) ? (int)$_GET['produit_id'] : 0;
$quantite = isset($_GET['quantite']) ? (float)$_GET['quantite'] : 1.0;

$stmtP = $pdo->prepare("
    SELECT p.*, u.nom_complet AS agriculteur_nom, u.ville AS agriculteur_ville, u.latitude AS agri_lat, u.longitude AS agri_lng, u.est_certifie AS agri_certifie
    FROM produits p
    JOIN utilisateurs u ON p.agriculteur_id = u.id
    WHERE p.id = ?
");
$stmtP->execute([$produit_id]);
$produit = $stmtP->fetch();

if (!$produit) {
    definir_flash('danger', 'Produit introuvable.');
    header('Location: produits.php');
    exit();
}

// Coordonnées et adresse par défaut de l'acheteur
$stmtAch = $pdo->prepare("SELECT adresse, ville, latitude, longitude, telephone FROM utilisateurs WHERE id = ?");
$stmtAch->execute([$acheteur_id]);
$achInfo = $stmtAch->fetch();

// Transporteurs disponibles avec leurs notes d'évaluation
$stmtTrans = $pdo->query("
    SELECT u.id, u.nom_complet, u.telephone, u.est_certifie, td.type_vehicule, td.immatriculation, td.capacite_tonnes,
           COALESCE(AVG(a.note), 5.0) AS moyenne_note, COUNT(a.id) AS total_avis
    FROM utilisateurs u
    JOIN transporteurs_details td ON u.id = td.utilisateur_id
    LEFT JOIN avis a ON a.cible_id = u.id AND a.type_cible = 'transporteur'
    WHERE u.role = 'transporteur' AND u.statut = 'actif' AND td.disponible = 'oui'
    GROUP BY u.id, td.id
");
$transporteurs_dispo = $stmtTrans->fetchAll();

// Traitement POST de la commande et du PAIEMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adresse_livraison = securiser($_POST['adresse_livraison'] ?? '');
    $lat_livraison = !empty($_POST['latitude_livraison']) ? (float)$_POST['latitude_livraison'] : 4.0511;
    $lng_livraison = !empty($_POST['longitude_livraison']) ? (float)$_POST['longitude_livraison'] : 9.7679;
    $transporteur_id = !empty($_POST['transporteur_id']) ? (int)$_POST['transporteur_id'] : null;

    // Champs de paiement Mobile Money
    $op_paiement = securiser($_POST['operateur_paiement'] ?? 'mtn_momo');
    $tel_paiement = securiser($_POST['telephone_paiement'] ?? '');
    $pin_momo = securiser($_POST['pin_paiement'] ?? '');

    if (empty($adresse_livraison)) {
        definir_flash('danger', 'Veuillez saisir une adresse de livraison valide.');
    } elseif (empty($tel_paiement) || empty($pin_momo)) {
        definir_flash('danger', 'Veuillez renseigner votre numéro Mobile Money / Orange Money et votre code de confirmation.');
    } else {
        // Calcul des coûts
        $dist_km = calculer_distance_gps($produit['latitude'] ?: 5.4778, $produit['longitude'] ?: 10.4176, $lat_livraison, $lng_livraison);
        $frais_transport = estimer_frais_transport($dist_km, $quantite * 10);
        $total_produits = $produit['prix'] * $quantite;
        $total_commande = $total_produits + $frais_transport;

        // Générer une référence de transaction simulée
        $prefix = ($op_paiement === 'mtn_momo') ? 'MOMO-CM-' : 'OM-CM-';
        $ref_transaction = $prefix . rand(100000, 999999);

        try {
            $pdo->beginTransaction();

            // 1. Créer la commande payée par l'acheteur
            $stmtCmd = $pdo->prepare("
                INSERT INTO commandes (acheteur_id, agriculteur_id, transporteur_id, statut, adresse_livraison, latitude_livraison, longitude_livraison, frais_transport, total_prix, statut_paiement_acheteur, mode_paiement_acheteur, reference_transaction_acheteur)
                VALUES (?, ?, ?, 'en_attente', ?, ?, ?, ?, ?, 'paye', ?, ?)
            ");
            $stmtCmd->execute([
                $acheteur_id, $produit['agriculteur_id'], $transporteur_id, 
                $adresse_livraison, $lat_livraison, $lng_livraison, 
                $frais_transport, $total_commande,
                $op_paiement, $ref_transaction
            ]);
            $commande_id = $pdo->lastInsertId();

            // 2. Créer le détail de commande
            $stmtDet = $pdo->prepare("INSERT INTO detail_commandes (commande_id, produit_id, quantite, prix_unitaire) VALUES (?, ?, ?, ?)");
            $stmtDet->execute([$commande_id, $produit_id, $quantite, $produit['prix']]);

            // 3. Déduire le stock
            $stmtStock = $pdo->prepare("UPDATE produits SET quantite_disponible = quantite_disponible - ? WHERE id = ?");
            $stmtStock->execute([$quantite, $produit_id]);

            $pdo->commit();

            // Notifications
            $nom_op = ($op_paiement === 'mtn_momo') ? 'MTN Mobile Money' : 'Orange Money';
            creer_notification($pdo, $produit['agriculteur_id'], 'Nouvelle commande payée !', 'Commande #' . $commande_id . ' réglée par ' . $_SESSION['utilisateur_nom'] . ' (' . formater_prix($total_commande) . ' via ' . $nom_op . ').');
            if ($transporteur_id) {
                creer_notification($pdo, $transporteur_id, 'Nouvelle opportunité de transport', 'Une livraison vous a été sollicitée pour la commande #' . $commande_id);
            }

            definir_flash('success', 'Paiement de ' . formater_prix($total_commande) . ' effectué avec succès via ' . $nom_op . ' (Réf: ' . $ref_transaction . ') ! Votre commande #' . $commande_id . ' a été transmise.');
            header('Location: mes_commandes.php');
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            definir_flash('danger', 'Erreur lors du traitement du paiement : ' . $e->getMessage());
        }
    }
}

// Estimation initiale des frais
$dist_initiale = calculer_distance_gps($produit['latitude'] ?: 5.4778, $produit['longitude'] ?: 10.4176, $achInfo['latitude'] ?: 4.0511, $achInfo['longitude'] ?: 9.7679);
$frais_estimes = estimer_frais_transport($dist_initiale, $quantite * 10);
$total_marchandise = $produit['prix'] * $quantite;
$total_estime = $total_marchandise + $frais_estimes;

require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-top: 20px; margin-bottom: 30px;">
    <h2><i class="fa-solid fa-credit-card" style="color: var(--primary);"></i> Validation & Paiement Mobile Money 🇨🇲</h2>
    <p style="color: var(--gray-500);">Réglez la marchandise et les frais de transport via MTN MoMo ou Orange Money.</p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 30px; margin-bottom: 60px;">
    
    <!-- Récapitulatif détaillé avec Prix Produit + Transport -->
    <div style="background: var(--white); padding: 24px; border-radius: var(--radius-md); border: 1px solid var(--gray-200); height: fit-content;">
        <h3 style="margin-bottom: 16px; color: var(--dark); border-bottom: 2px solid var(--gray-100); padding-bottom: 10px;">Récapitulatif Financier</h3>
        
        <div style="display: flex; gap: 14px; margin-bottom: 20px;">
            <img src="assets/images/<?php echo htmlspecialchars($produit['photo']); ?>" 
                 onerror="this.src='https://images.unsplash.com/photo-1595855759920-86582396756a?auto=format&fit=crop&w=200&q=80'" 
                 style="width: 70px; height: 70px; border-radius: 8px; object-fit: cover;">
            <div>
                <h4 style="margin: 0; color: var(--dark);"><?php echo htmlspecialchars($produit['nom']); ?></h4>
                <small style="color: var(--gray-500);">
                    <i class="fa-solid fa-user"></i> Producteur : <?php echo htmlspecialchars($produit['agriculteur_nom']); ?>
                    <?php echo afficher_badge_certification($produit['agri_certifie']); ?>
                </small><br>
                <small style="color: var(--gray-500);"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($produit['agriculteur_ville']); ?></small>
            </div>
        </div>

        <div style="border-top: 1px solid var(--gray-200); padding-top: 14px; font-size: 0.95rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <span>Prix unitaire :</span>
                <strong><?php echo formater_prix($produit['prix']); ?> / <?php echo htmlspecialchars($produit['unite']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <span>Quantité commandée :</span>
                <strong><?php echo $quantite . ' ' . htmlspecialchars($produit['unite']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 1.05rem;">
                <span>1. Sous-total Marchandise :</span>
                <strong style="color: var(--dark);"><?php echo formater_prix($total_marchandise); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 1.05rem; color: var(--primary-dark);">
                <span>2. Frais de Transport (<?php echo $dist_initiale; ?> km) :</span>
                <strong><?php echo formater_prix($frais_estimes); ?></strong>
            </div>
            <hr style="margin: 14px 0; border: none; border-top: 2px dashed var(--gray-200);">
            <div style="display: flex; justify-content: space-between; font-size: 1.3rem; color: var(--dark); background: #ecfdf5; padding: 12px; border-radius: var(--radius-sm); border: 1px solid #bbf7d0;">
                <span>TOTAL À PAYER :</span>
                <strong style="color: var(--primary-dark);"><?php echo formater_prix($total_estime); ?></strong>
            </div>
        </div>
    </div>

    <!-- Formulaire d'Adresse de Livraison et Simulation de Paiement -->
    <div style="background: var(--white); padding: 24px; border-radius: var(--radius-md); border: 1px solid var(--gray-200);">
        <h3 style="margin-bottom: 16px; color: var(--dark); border-bottom: 2px solid var(--gray-100); padding-bottom: 10px;">Livraison & Guichet de Paiement</h3>

        <form action="commander.php?produit_id=<?php echo $produit_id; ?>&quantite=<?php echo $quantite; ?>" method="POST">
            
            <div class="form-group">
                <label class="form-label">Adresse de livraison (Quartier / Repère au Cameroun)</label>
                <input type="text" name="adresse_livraison" class="form-control" placeholder="ex: Douala Akwa, Face Pharmacie de la Côte" required value="<?php echo htmlspecialchars($achInfo['adresse'] ?: ''); ?>">
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 10px;">
                    <label class="form-label" style="margin: 0;"><i class="fa-solid fa-map-pin"></i> Point GPS de livraison</label>
                    <button type="button" id="btnMaPositionGPS" class="btn btn-outline btn-sm">
                        <i class="fa-solid fa-crosshairs"></i> Capturer Ma Position GPS
                    </button>
                </div>
                <div id="mapCommander" class="map-container map-picker-container"></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 10px;">
                    <input type="text" name="latitude_livraison" id="latCommander" class="form-control" readonly value="<?php echo $achInfo['latitude'] ?: 4.0511; ?>">
                    <input type="text" name="longitude_livraison" id="lngCommander" class="form-control" readonly value="<?php echo $achInfo['longitude'] ?: 9.7679; ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-truck"></i> Choisir un Transporteur Référencé (Optionnel)</label>
                <select name="transporteur_id" class="form-control">
                    <option value="">-- Laissez l'Agriculteur choisir un transporteur --</option>
                    <?php foreach ($transporteurs_dispo as $t): ?>
                        <option value="<?php echo $t['id']; ?>">
                            <?php echo htmlspecialchars($t['nom_complet']); ?> <?php echo $t['est_certifie'] ? '🔵 Certifié' : ''; ?> - <?php echo htmlspecialchars($t['type_vehicule']); ?> (⭐ <?php echo number_format($t['moyenne_note'], 1); ?>/5 - <?php echo $t['total_avis']; ?> avis)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- SIMULATION PAIEMENT MOBILE MONEY -->
            <div style="background: #fffbebfb; padding: 20px; border-radius: 12px; border: 2px solid #fef08a; margin-top: 24px; margin-bottom: 24px;">
                <h4 style="color: #92400e; margin-bottom: 14px;"><i class="fa-solid fa-mobile-screen-button"></i> Guichet de Paiement Mobile Money Cameroun</h4>
                
                <div class="form-group">
                    <label class="form-label">Sélectionnez le mode de paiement :</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <label style="background: var(--white); padding: 12px; border-radius: 8px; border: 2px solid #f59e0b; display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700;">
                            <input type="radio" name="operateur_paiement" value="mtn_momo" checked onclick="updatePaymentStyle('mtn')">
                            <span style="color: #d97706;"><i class="fa-solid fa-phone"></i> MTN MoMo</span>
                        </label>
                        <label style="background: var(--white); padding: 12px; border-radius: 8px; border: 2px solid #ea580c; display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700;">
                            <input type="radio" name="operateur_paiement" value="orange_money" onclick="updatePaymentStyle('orange')">
                            <span style="color: #ea580c;"><i class="fa-solid fa-mobile-retro"></i> Orange Money</span>
                        </label>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Numéro de téléphone compte</label>
                        <input type="text" name="telephone_paiement" class="form-control" placeholder="ex: 677 00 11 22 ou 699 11 22 33" required value="<?php echo htmlspecialchars($achInfo['telephone'] ?: ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Code secret PIN (MoMo / OM)</label>
                        <input type="password" name="pin_paiement" class="form-control" placeholder="••••" required max="4" value="1234">
                    </div>
                </div>
                <small style="color: #92400e;"><i class="fa-solid fa-lock"></i> Simulation sécurisée du débit instantané des <?php echo formater_prix($total_estime); ?>.</small>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                <i class="fa-solid fa-check-double"></i> Payer <?php echo formater_prix($total_estime); ?> & Valider la Commande
            </button>
        </form>
    </div>
</div>

<script>
function updatePaymentStyle(op) {
    console.log('Opérateur sélectionné:', op);
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof AgriMap !== 'undefined') {
        const initLat = <?php echo !empty($achInfo['latitude']) ? $achInfo['latitude'] : 4.0511; ?>;
        const initLng = <?php echo !empty($achInfo['longitude']) ? $achInfo['longitude'] : 9.7679; ?>;
        AgriMap.initPicker('mapCommander', 'latCommander', 'lngCommander', initLat, initLng);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
