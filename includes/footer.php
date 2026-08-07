<?php
/**
 * Pied de page commun (Footer Cameroun 🇨🇲 avec Responsivité Mobile Fluidifiée)
 * Application AgriConnect
 */
?>

<?php if (est_connecte()): ?>
        </div> <!-- Fin content-wrapper -->
    </div> <!-- Fin main-panel -->
</div> <!-- Fin app-layout -->

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/carte_gps.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnToggle = document.getElementById('btnToggleSidebar');
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('show');
        if (backdrop) backdrop.classList.add('show');
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('show');
        if (backdrop) backdrop.classList.remove('show');
    }

    if (btnToggle) {
        btnToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('show')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    // Fermer automatiquement le menu lors d'un clic sur un lien sur mobile
    const menuLinks = document.querySelectorAll('.sidebar-item a');
    menuLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 992) {
                closeSidebar();
            }
        });
    });
});
</script>
</body>
</html>

<?php else: ?>

</main>

<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <a href="index.php" class="navbar-brand" style="color: var(--white); margin-bottom: 16px;">
                    <i class="fa-solid fa-wheat-awn" style="color: var(--accent);"></i> AgriConnect 🇨🇲
                </a>
                <p style="color: var(--gray-500); font-size: 0.95rem;">
                    La plateforme numérique qui connecte les agriculteurs, les acheteurs et les transporteurs du Cameroun avec suivi GPS en temps réel.
                </p>
            </div>
            <div class="footer-col">
                <h4>Navigation</h4>
                <ul>
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="produits.php">Produits Agricoles</a></li>
                    <li><a href="inscription.php">Créer un compte</a></li>
                    <li><a href="connexion.php">Se connecter</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Services Cameroun</h4>
                <ul>
                    <li><a href="publier_produit.php">Vendre ma récolte</a></li>
                    <li><a href="produits.php">Acheter des produits frais</a></li>
                    <li><a href="profil.php">Proposer des livraisons</a></li>
                    <li><a href="suivi_livraison.php">Suivi GPS Temps Réel</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Contact Cameroun</h4>
                <p style="color: var(--gray-500); font-size: 0.95rem; margin-bottom: 8px;">
                    <i class="fa-solid fa-location-dot" style="color: var(--accent);"></i> Douala & Yaoundé, Cameroun
                </p>
                <p style="color: var(--gray-500); font-size: 0.95rem; margin-bottom: 8px;">
                    <i class="fa-solid fa-envelope" style="color: var(--accent);"></i> contact@agriconnect.cm
                </p>
                <p style="color: var(--gray-500); font-size: 0.95rem;">
                    <i class="fa-solid fa-phone" style="color: var(--accent);"></i> +237 699 00 11 22
                </p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> AgriConnect Cameroun 🇨🇲. Tous droits réservés. Développé avec PHP, MySQL & Leaflet OpenStreetMap.</p>
        </div>
    </div>
</footer>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/carte_gps.js"></script>
</body>
</html>
<?php endif; ?>
