<?php
/**
 * Suivi de Livraison Géolocalisée en Temps Réel sur Carte Interactive
 * Application AgriConnect
 */

$page_title = "Suivi GPS en Temps Réel";
require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

exiger_connexion();

$cmd_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['utilisateur_id'];
$user_role = $_SESSION['utilisateur_role'];

// Récupérer la commande avec les coordonnées GPS
$stmt = $pdo->prepare("
    SELECT c.*, 
           u_agri.nom_complet AS agriculteur_nom, u_agri.telephone AS agriculteur_tel, u_agri.latitude AS agri_lat, u_agri.longitude AS agri_lng,
           u_ach.nom_complet AS acheteur_nom, u_ach.telephone AS acheteur_tel,
           u_trans.id AS trans_id, u_trans.nom_complet AS transporteur_nom, u_trans.telephone AS transporteur_tel,
           td.type_vehicule, td.immatriculation, td.latitude_actuelle, td.longitude_actuelle, td.derniere_maj_gps
    FROM commandes c
    JOIN utilisateurs u_agri ON c.agriculteur_id = u_agri.id
    JOIN utilisateurs u_ach ON c.acheteur_id = u_ach.id
    LEFT JOIN utilisateurs u_trans ON c.transporteur_id = u_trans.id
    LEFT JOIN transporteurs_details td ON u_trans.id = td.utilisateur_id
    WHERE c.id = ?
");
$stmt->execute([$cmd_id]);
$cmd = $stmt->fetch();

if (!$cmd) {
    definir_flash('danger', 'Commande introuvable.');
    header('Location: mes_commandes.php');
    exit();
}

$est_transporteur = ($user_role === 'transporteur' && $cmd['trans_id'] == $user_id);
$distance_totale = calculer_distance_gps($cmd['agri_lat'] ?: 5.4778, $cmd['agri_lng'] ?: 10.4176, $cmd['latitude_livraison'] ?: 4.0511, $cmd['longitude_livraison'] ?: 9.7679);

require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-top: 30px; margin-bottom: 20px;">
    <a href="mes_commandes.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour aux commandes</a>
</div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2><i class="fa-solid fa-satellite-dish" style="color: var(--primary);"></i> Suivi de Livraison GPS en Temps Réel</h2>
        <p style="color: var(--gray-500);">Commande #<?php echo $cmd['id']; ?> | Transporteur : <strong><?php echo htmlspecialchars($cmd['transporteur_nom'] ?: 'Non attribué'); ?></strong></p>
    </div>
    <div id="tracking-live-status">
        <?php if ($cmd['statut'] === 'en_cours_livraison'): ?>
            <span class="status-badge status-en_cours_livraison"><i class="fa-solid fa-spinner fa-spin"></i> Livraison en cours</span>
        <?php elseif ($cmd['statut'] === 'livree'): ?>
            <span class="status-badge status-livree"><i class="fa-solid fa-check-double"></i> Commande Livrée</span>
        <?php else: ?>
            <span class="status-badge status-en_attente"><i class="fa-solid fa-clock"></i> En attente de démarrage</span>
        <?php endif; ?>
    </div>
</div>

<?php if ($est_transporteur): ?>
    <div class="alert alert-info" style="margin-bottom: 24px;">
        <i class="fa-solid fa-circle-info"></i>
        <span><strong>Mode Transporteur Actif :</strong> Votre navigateur partage automatiquement votre position GPS en temps réel avec l'agriculteur et l'acheteur. Gardez votre GPS actif sur votre smartphone.</span>
    </div>
<?php endif; ?>

<!-- Carte de Suivi GPS Grand Format -->
<div id="mapTracking" class="map-container" style="height: 520px; box-shadow: var(--shadow-md);"></div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-top: 30px; margin-bottom: 60px;">
    
    <!-- Informations Trajet -->
    <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200);">
        <h4 style="margin-bottom: 14px; color: var(--dark); border-bottom: 1px solid var(--gray-100); padding-bottom: 8px;">
            <i class="fa-solid fa-route" style="color: var(--primary);"></i> Détails de l'Itinéraire
        </h4>
        <p style="margin-bottom: 8px;"><strong>Départ (Exploitation) :</strong> <?php echo htmlspecialchars($cmd['agriculteur_nom']); ?></p>
        <p style="margin-bottom: 8px;"><strong>Arrivée (Acheteur) :</strong> <?php echo htmlspecialchars($cmd['adresse_livraison']); ?></p>
        <p style="margin-bottom: 8px;"><strong>Distance Totale Estimée :</strong> <?php echo $distance_totale; ?> km</p>
        <p><strong>Frais de Transport :</strong> <?php echo formater_prix($cmd['frais_transport']); ?></p>
    </div>

    <!-- Informations Vehicule & Transporteur -->
    <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200);">
        <h4 style="margin-bottom: 14px; color: var(--dark); border-bottom: 1px solid var(--gray-100); padding-bottom: 8px;">
            <i class="fa-solid fa-truck" style="color: var(--accent);"></i> Fiche du Transporteur
        </h4>
        <p style="margin-bottom: 8px;"><strong>Chauffeur :</strong> <?php echo htmlspecialchars($cmd['transporteur_nom'] ?: 'N/A'); ?></p>
        <p style="margin-bottom: 8px;"><strong>Véhicule :</strong> <?php echo htmlspecialchars($cmd['type_vehicule'] ?: 'N/A'); ?></p>
        <p style="margin-bottom: 8px;"><strong>Immatriculation :</strong> <?php echo htmlspecialchars($cmd['immatriculation'] ?: 'N/A'); ?></p>
        <p><strong>Téléphone :</strong> <?php echo htmlspecialchars($cmd['transporteur_tel'] ?: 'N/A'); ?></p>
    </div>

    <!-- Contact & Action Chat -->
    <div style="background: #f0fdf4; padding: 20px; border-radius: var(--radius-md); border: 1px solid #bbf7d0; display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <h4 style="margin-bottom: 10px; color: var(--primary-dark);"><i class="fa-solid fa-comments"></i> Communication Directe</h4>
            <p style="font-size: 0.9rem; color: var(--gray-700); margin-bottom: 16px;">Une question sur l'heure exacte de livraison ou le chargement ?</p>
        </div>
        <?php if ($cmd['trans_id']): ?>
            <a href="messagerie.php?contact_id=<?php echo $cmd['trans_id']; ?>&commande_id=<?php echo $cmd['id']; ?>" class="btn btn-primary btn-sm" style="width: 100%;">
                <i class="fa-solid fa-comment-dots"></i> Ouvrir le Chat avec le Transporteur
            </a>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof AgriMap !== 'undefined') {
        const startLat = <?php echo !empty($cmd['agri_lat']) ? $cmd['agri_lat'] : 5.4778; ?>;
        const startLng = <?php echo !empty($cmd['agri_lng']) ? $cmd['agri_lng'] : 10.4176; ?>;
        const endLat = <?php echo !empty($cmd['latitude_livraison']) ? $cmd['latitude_livraison'] : 4.0511; ?>;
        const endLng = <?php echo !empty($cmd['longitude_livraison']) ? $cmd['longitude_livraison'] : 9.7679; ?>;
        const isTransporter = <?php echo $est_transporteur ? 'true' : 'false'; ?>;
        const orderId = <?php echo $cmd['id']; ?>;

        AgriMap.initLiveTracking('mapTracking', orderId, isTransporter, startLat, startLng, endLat, endLng);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
