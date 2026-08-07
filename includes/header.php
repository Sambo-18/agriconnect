<?php
/**
 * En-tête HTML commun (Header Cameroun 🇨🇲 avec Sidebar Fixe Permanente & Responsivité Poussée)
 * Application AgriConnect
 */
require_once __DIR__ . '/fonctions.php';

$user_id = $_SESSION['utilisateur_id'] ?? null;
$user_role = $_SESSION['utilisateur_role'] ?? null;
$user_nom = $_SESSION['utilisateur_nom'] ?? null;

$nb_messages = $user_id ? compter_messages_non_lus($pdo, $user_id) : 0;
$nb_notifs = $user_id ? compter_notifications_non_lues($pdo, $user_id) : 0;

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | AgriConnect Cameroun' : 'AgriConnect Cameroun - Plateforme Agricole & Transport GPS'; ?></title>
    <meta name="description" content="Plateforme numérique de commercialisation agricole et de transport géolocalisé en temps réel au Cameroun.">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet CSS for OpenStreetMap GPS tracking -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    
    <!-- Custom Style -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if (est_connecte()): ?>
    <!-- DISPOSITION AVEC SIDEBAR FIXE PERMANENTE & FLUIDE SUR TOUS LES ÉCRANS -->
    <div class="app-layout">
        
        <!-- BACKDROP DE FERMETURE DU MENU SUR MOBILE -->
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <aside class="sidebar" id="appSidebar">
            <div class="sidebar-header">
                <a href="tableau_de_bord.php" class="sidebar-brand">
                    <i class="fa-solid fa-wheat-awn" style="color: var(--accent);"></i> AgriConnect <span>🇨🇲</span>
                </a>
            </div>

            <div class="sidebar-user">
                <div class="sidebar-avatar">
                    <?php echo strtoupper(substr($user_nom, 0, 1)); ?>
                </div>
                <div class="sidebar-user-info">
                    <h4><?php echo htmlspecialchars($user_nom); ?></h4>
                    <span><?php echo ucfirst($user_role); ?></span>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li class="sidebar-item <?php echo ($current_page === 'tableau_de_bord.php') ? 'active' : ''; ?>">
                    <a href="tableau_de_bord.php">
                        <span><i class="fa-solid fa-gauge-high"></i> Tableau de bord</span>
                    </a>
                </li>

                <?php if ($user_role === 'agriculteur'): ?>
                    <li class="sidebar-item <?php echo ($current_page === 'mes_produits.php' || ($current_page === 'produits.php' && !isset($_GET['mode']))) ? 'active' : ''; ?>">
                        <a href="mes_produits.php">
                            <span><i class="fa-solid fa-boxes-stacked"></i> Mes Produits Publiés</span>
                        </a>
                    </li>
                    <li class="sidebar-item <?php echo ($current_page === 'publier_produit.php') ? 'active' : ''; ?>">
                        <a href="publier_produit.php">
                            <span><i class="fa-solid fa-plus-circle"></i> Publier une Récolte</span>
                        </a>
                    </li>
                    <li class="sidebar-item <?php echo ($current_page === 'produits.php' && isset($_GET['mode']) && $_GET['mode'] === 'catalogue') ? 'active' : ''; ?>">
                        <a href="produits.php?mode=catalogue">
                            <span><i class="fa-solid fa-store"></i> Catalogue Global</span>
                        </a>
                    </li>

                <?php else: ?>
                    <li class="sidebar-item <?php echo ($current_page === 'produits.php' || $current_page === 'detail_produit.php') ? 'active' : ''; ?>">
                        <a href="produits.php">
                            <span><i class="fa-solid fa-store"></i> Produits Agricoles</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($user_role === 'acheteur'): ?>
                    <li class="sidebar-item <?php echo ($current_page === 'panier.php') ? 'active' : ''; ?>">
                        <a href="panier.php">
                            <span><i class="fa-solid fa-cart-shopping"></i> Mon Panier</span>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="sidebar-item <?php echo ($current_page === 'mes_commandes.php' || $current_page === 'suivi_livraison.php' || $current_page === 'commander.php') ? 'active' : ''; ?>">
                    <a href="mes_commandes.php">
                        <span><i class="fa-solid fa-truck-ramp-box"></i> Commandes & Livraisons</span>
                    </a>
                </li>

                <li class="sidebar-item <?php echo ($current_page === 'messagerie.php') ? 'active' : ''; ?>">
                    <a href="messagerie.php">
                        <span><i class="fa-solid fa-comments"></i> Messagerie</span>
                        <?php if ($nb_messages > 0): ?>
                            <span class="nav-badge"><?php echo $nb_messages; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="sidebar-item <?php echo ($current_page === 'notifications.php') ? 'active' : ''; ?>">
                    <a href="notifications.php">
                        <span><i class="fa-solid fa-bell"></i> Notifications</span>
                        <?php if ($nb_notifs > 0): ?>
                            <span class="nav-badge"><?php echo $nb_notifs; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <?php if ($user_role === 'admin'): ?>
                    <li class="sidebar-item <?php echo ($current_page === 'admin.php') ? 'active' : ''; ?>">
                        <a href="admin.php" style="color: var(--accent);">
                            <span><i class="fa-solid fa-chart-line"></i> Dashboard Admin</span>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="sidebar-item <?php echo ($current_page === 'profil.php') ? 'active' : ''; ?>">
                    <a href="profil.php">
                        <span><i class="fa-solid fa-user-gear"></i> Mon Profil</span>
                    </a>
                </li>

                <li class="sidebar-item" style="margin-top: auto;">
                    <a href="deconnexion.php" style="color: #f87171;">
                        <span><i class="fa-solid fa-power-off"></i> Déconnexion</span>
                    </a>
                </li>
            </ul>
        </aside>

        <div class="main-panel">
            <header class="topbar">
                <button class="mobile-toggle" id="btnToggleSidebar" aria-label="Menu Mobile">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div style="font-weight: 700; font-size: 1.05rem; color: var(--dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <i class="fa-solid fa-leaf" style="color: var(--primary);"></i> AgriConnect <span style="font-size: 0.85rem; color: var(--gray-500);">🇨🇲</span>
                </div>

                <div class="topbar-actions">
                    <a href="notifications.php" class="nav-link" style="position: relative;" title="Notifications">
                        <i class="fa-solid fa-bell" style="font-size: 1.2rem;"></i>
                        <?php if ($nb_notifs > 0): ?>
                            <span class="nav-badge" style="position: absolute; top: -5px; right: -8px;"><?php echo $nb_notifs; ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="profil.php" class="btn btn-outline btn-sm">
                        <i class="fa-solid fa-circle-user"></i> <span><?php echo htmlspecialchars($user_nom); ?></span>
                    </a>

                    <a href="deconnexion.php" class="btn btn-danger btn-sm" title="Déconnexion">
                        <i class="fa-solid fa-power-off"></i>
                    </a>
                </div>
            </header>

            <div class="content-wrapper">
                <?php afficher_flash(); ?>

<?php else: ?>
    <!-- BARRE DE NAVIGATION CLASSIQUE POUR VISITEURS NON CONNECTÉS -->
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="main-content">
        <div class="container" style="margin-top: 20px;">
            <?php afficher_flash(); ?>
        </div>
<?php endif; ?>
