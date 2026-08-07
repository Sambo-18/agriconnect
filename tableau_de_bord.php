<?php
/**
 * Tableau de Bord Principal (Cameroun 🇨🇲)
 * Application AgriConnect
 */

require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

exiger_connexion();

$user_id = $_SESSION['utilisateur_id'];
$user_role = $_SESSION['utilisateur_role'];
$user_nom = $_SESSION['utilisateur_nom'];

// Récupérer le profil utilisateur complet
$stmtU = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmtU->execute([$user_id]);
$userProfil = $stmtU->fetch();

// Compteurs
$nb_messages = compter_messages_non_lus($pdo, $user_id);
$nb_notifs = compter_notifications_non_lues($pdo, $user_id);

// Statistiques spécifiques selon le rôle
if ($user_role === 'agriculteur') {
    $stat_ventes = $pdo->query("SELECT SUM(total_prix) FROM commandes WHERE agriculteur_id = $user_id AND statut = 'livree'")->fetchColumn() ?: 0;
    $stat_prod = $pdo->query("SELECT COUNT(*) FROM produits WHERE agriculteur_id = $user_id AND quantite_disponible > 0")->fetchColumn();
    $stat_cmd_attente = $pdo->query("SELECT COUNT(*) FROM commandes WHERE agriculteur_id = $user_id AND statut = 'en_attente'")->fetchColumn();
    $commandes_recentes = $pdo->query("SELECT c.*, u.nom_complet AS acheteur_nom FROM commandes c JOIN utilisateurs u ON c.acheteur_id = u.id WHERE c.agriculteur_id = $user_id ORDER BY c.date_commande DESC LIMIT 5")->fetchAll();
} elseif ($user_role === 'acheteur') {
    $stat_achats = $pdo->query("SELECT SUM(total_prix) FROM commandes WHERE acheteur_id = $user_id AND statut = 'livree'")->fetchColumn() ?: 0;
    $stat_cmd_cours = $pdo->query("SELECT COUNT(*) FROM commandes WHERE acheteur_id = $user_id AND statut IN ('en_attente', 'acceptee', 'en_cours_livraison')")->fetchColumn();
    $commandes_recentes = $pdo->query("SELECT c.*, u.nom_complet AS agriculteur_nom FROM commandes c JOIN utilisateurs u ON c.agriculteur_id = u.id WHERE c.acheteur_id = $user_id ORDER BY c.date_commande DESC LIMIT 5")->fetchAll();
} elseif ($user_role === 'transporteur') {
    $stmtTD = $pdo->prepare("SELECT * FROM transporteurs_details WHERE utilisateur_id = ?");
    $stmtTD->execute([$user_id]);
    $transDetails = $stmtTD->fetch();
    $stat_missions_cours = $pdo->query("SELECT COUNT(*) FROM commandes WHERE transporteur_id = $user_id AND statut IN ('acceptee', 'en_cours_livraison')")->fetchColumn();
    $stat_missions_livrees = $pdo->query("SELECT COUNT(*) FROM commandes WHERE transporteur_id = $user_id AND statut = 'livree'")->fetchColumn();
    $commandes_recentes = $pdo->query("SELECT c.*, u.nom_complet AS acheteur_nom FROM commandes c JOIN utilisateurs u ON c.acheteur_id = u.id WHERE c.transporteur_id = $user_id ORDER BY c.date_commande DESC LIMIT 5")->fetchAll();
} else {
    header('Location: admin.php');
    exit();
}

$page_title = "Tableau de Bord (" . ucfirst($user_role) . ")";
require_once __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 1.8rem; font-weight: 800; color: var(--dark);">
            Bonjour, <?php echo htmlspecialchars($user_nom); ?> 👋
        </h2>
        <p style="color: var(--gray-500);">Bienvenue sur votre espace de contrôle AgriConnect Cameroun.</p>
    </div>

    <?php if ($user_role === 'agriculteur'): ?>
        <a href="publier_produit.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Publier une Nouvelle Récolte
        </a>
    <?php elseif ($user_role === 'acheteur'): ?>
        <a href="produits.php" class="btn btn-accent">
            <i class="fa-solid fa-basket-shopping"></i> Explorer les Produits
        </a>
    <?php elseif ($user_role === 'transporteur'): ?>
        <a href="profil.php" class="btn btn-outline">
            <i class="fa-solid fa-toggle-on"></i> Statut Disponibilité : <?php echo ($transDetails && $transDetails['disponible'] === 'oui') ? '<strong style="color: var(--success);">OUI</strong>' : '<strong style="color: var(--danger);">NON</strong>'; ?>
        </a>
    <?php endif; ?>
</div>

<!-- CARTES KPI DU DASHBOARD -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 32px;">
    
    <?php if ($user_role === 'agriculteur'): ?>
        <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm);">
            <span style="font-size: 0.85rem; color: var(--gray-500); font-weight: 600;">VENTES CUMULÉES</span>
            <h3 style="font-size: 1.8rem; color: var(--primary-dark); margin-top: 6px;"><?php echo formater_prix($stat_ventes); ?></h3>
        </div>
        <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm);">
            <span style="font-size: 0.85rem; color: var(--gray-500); font-weight: 600;">RÉCOLTES EN VENTE</span>
            <h3 style="font-size: 1.8rem; color: var(--accent); margin-top: 6px;"><?php echo $stat_prod; ?> Produits</h3>
        </div>
        <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm);">
            <span style="font-size: 0.85rem; color: var(--gray-500); font-weight: 600;">COMMANDES EN ATTENTE</span>
            <h3 style="font-size: 1.8rem; color: var(--info); margin-top: 6px;"><?php echo $stat_cmd_attente; ?></h3>
        </div>

    <?php elseif ($user_role === 'acheteur'): ?>
        <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm);">
            <span style="font-size: 0.85rem; color: var(--gray-500); font-weight: 600;">DÉPENSES D'ACHAT</span>
            <h3 style="font-size: 1.8rem; color: var(--primary-dark); margin-top: 6px;"><?php echo formater_prix($stat_achats); ?></h3>
        </div>
        <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm);">
            <span style="font-size: 0.85rem; color: var(--gray-500); font-weight: 600;">COMMANDES ET LIVRAISONS</span>
            <h3 style="font-size: 1.8rem; color: var(--accent); margin-top: 6px;"><?php echo $stat_cmd_cours; ?> En cours</h3>
        </div>

    <?php elseif ($user_role === 'transporteur'): ?>
        <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm);">
            <span style="font-size: 0.85rem; color: var(--gray-500); font-weight: 600;">MISSIONS EN COURS</span>
            <h3 style="font-size: 1.8rem; color: var(--accent); margin-top: 6px;"><?php echo $stat_missions_cours; ?> En cours</h3>
        </div>
        <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm);">
            <span style="font-size: 0.85rem; color: var(--gray-500); font-weight: 600;">LIVRAISONS EFFECTUÉES</span>
            <h3 style="font-size: 1.8rem; color: var(--primary-dark); margin-top: 6px;"><?php echo $stat_missions_livrees; ?> Réussies</h3>
        </div>
    <?php endif; ?>

    <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm);">
        <span style="font-size: 0.85rem; color: var(--gray-500); font-weight: 600;">MESSAGES NON LUS</span>
        <h3 style="font-size: 1.8rem; color: var(--dark); margin-top: 6px;"><?php echo $nb_messages; ?> non lus</h3>
    </div>
</div>

<!-- CARTE DU CAMEROUN ET DERNIÈRES ACTIVITÉS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-bottom: 32px;">
    <!-- Carte Leaflet du Cameroun -->
    <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200);">
        <h3 style="font-size: 1.1rem; margin-bottom: 12px; color: var(--dark);"><i class="fa-solid fa-map-location-dot" style="color: var(--primary);"></i> Localisation Cameroun (Douala / Yaoundé / Bafoussam)</h3>
        <div id="mapDashboard" class="map-container" style="height: 320px; margin-bottom: 0;"></div>
    </div>

    <!-- Activités récentes / Commandes -->
    <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="font-size: 1.1rem; color: var(--dark);"><i class="fa-solid fa-clock-rotate-left"></i> Activités Récents</h3>
            <a href="mes_commandes.php" style="font-size: 0.85rem; color: var(--primary); font-weight: 700;">Voir tout <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <?php if (empty($commandes_recentes)): ?>
            <p style="color: var(--gray-500); font-size: 0.9rem; text-align: center; margin-top: 40px;">Aucune transaction enregistrée pour l'instant.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($commandes_recentes as $cmd): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--light-bg); border-radius: var(--radius-sm);">
                        <div>
                            <strong style="color: var(--dark); font-size: 0.95rem;">Commande #<?php echo $cmd['id']; ?></strong>
                            <small style="display: block; color: var(--gray-500);"><?php echo formater_date($cmd['date_commande']); ?></small>
                        </div>
                        <div>
                            <span class="status-badge status-<?php echo $cmd['statut']; ?>"><?php echo ucfirst($cmd['statut']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof L !== 'undefined') {
        const userLat = <?php echo !empty($userProfil['latitude']) ? $userProfil['latitude'] : 4.0511; ?>;
        const userLng = <?php echo !empty($userProfil['longitude']) ? $userProfil['longitude'] : 9.7679; ?>;
        const map = L.map('mapDashboard').setView([userLat, userLng], 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap | AgriConnect Cameroun' }).addTo(map);
        
        L.marker([4.0511, 9.7679], { icon: AgriMap.icons.destination }).addTo(map).bindPopup("<b>Douala</b><br>Bassin d'Achat");
        L.marker([3.8480, 11.5021], { icon: AgriMap.icons.destination }).addTo(map).bindPopup("<b>Yaoundé</b><br>Capitale & Marché");
        L.marker([5.4778, 10.4176], { icon: AgriMap.icons.ferme }).addTo(map).bindPopup("<b>Bafoussam / Ouest</b><br>Zone Agricole");
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
