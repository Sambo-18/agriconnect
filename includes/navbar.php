<?php
/**
 * Barre de Navigation dynamique (Navbar Cameroun 🇨🇲)
 * Application AgriConnect
 */

$user_id = $_SESSION['utilisateur_id'] ?? null;
$user_role = $_SESSION['utilisateur_role'] ?? null;
$user_nom = $_SESSION['utilisateur_nom'] ?? null;

$nb_messages = $user_id ? compter_messages_non_lus($pdo, $user_id) : 0;
$nb_notifs = $user_id ? compter_notifications_non_lues($pdo, $user_id) : 0;
?>
<nav class="navbar">
    <div class="container navbar-container">
        <a href="<?php echo est_connecte() ? 'tableau_de_bord.php' : 'index.php'; ?>" class="navbar-brand">
            <i class="fa-solid fa-wheat-awn"></i>
            <span>AgriConnect <small style="font-size: 1.1rem;">🇨🇲</small></span>
        </a>

        <button class="mobile-toggle" id="mobileMenuBtn" aria-label="Menu">
            <i class="fa-solid fa-bars"></i>
        </button>

        <ul class="navbar-nav" id="navbarNav">
            <?php if (!est_connecte()): ?>
                <!-- Visiteur -->
                <li><a href="index.php" class="nav-link"><i class="fa-solid fa-house"></i> Accueil</a></li>
                <li><a href="produits.php" class="nav-link"><i class="fa-solid fa-store"></i> Produits Agricoles</a></li>
                <li><a href="connexion.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-right-to-bracket"></i> Se connecter</a></li>
                <li><a href="inscription.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-plus"></i> Créer un compte</a></li>
            <?php else: ?>
                <!-- Utilisateur Connecté -->
                <li><a href="tableau_de_bord.php" class="nav-link"><i class="fa-solid fa-gauge-high"></i> Tableau de bord</a></li>
                <li><a href="produits.php" class="nav-link"><i class="fa-solid fa-store"></i> Produits Agricoles</a></li>

                <?php if ($user_role === 'agriculteur'): ?>
                    <li><a href="publier_produit.php" class="nav-link"><i class="fa-solid fa-plus-circle"></i> Publier Récolte</a></li>
                    <li><a href="mes_produits.php" class="nav-link"><i class="fa-solid fa-boxes-stacked"></i> Mes Ventes</a></li>
                    <li><a href="mes_commandes.php" class="nav-link"><i class="fa-solid fa-clipboard-list"></i> Commandes</a></li>

                <?php elseif ($user_role === 'acheteur'): ?>
                    <li><a href="panier.php" class="nav-link"><i class="fa-solid fa-cart-shopping"></i> Panier</a></li>
                    <li><a href="mes_commandes.php" class="nav-link"><i class="fa-solid fa-truck-ramp-box"></i> Mes Achats</a></li>

                <?php elseif ($user_role === 'transporteur'): ?>
                    <li><a href="mes_commandes.php" class="nav-link"><i class="fa-solid fa-truck-fast"></i> Missions Transport</a></li>

                <?php elseif ($user_role === 'admin'): ?>
                    <li><a href="admin.php" class="nav-link" style="color: var(--accent); font-weight: 700;"><i class="fa-solid fa-chart-line"></i> Dashboard Admin</a></li>
                <?php endif; ?>

                <li>
                    <a href="messagerie.php" class="nav-link">
                        <i class="fa-solid fa-comments"></i> Chat
                        <?php if ($nb_messages > 0): ?>
                            <span class="nav-badge"><?php echo $nb_messages; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li>
                    <a href="notifications.php" class="nav-link">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($nb_notifs > 0): ?>
                            <span class="nav-badge"><?php echo $nb_notifs; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li>
                    <a href="profil.php" class="nav-link" style="font-weight: 600;">
                        <i class="fa-solid fa-circle-user"></i> <?php echo htmlspecialchars($user_nom); ?>
                    </a>
                </li>

                <li>
                    <a href="deconnexion.php" class="btn btn-danger btn-sm" title="Déconnexion">
                        <i class="fa-solid fa-power-off"></i>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
