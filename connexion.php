<?php
/**
 * Page de Connexion Utilisateur
 * Application AgriConnect
 */

$page_title = "Se Connecter";
require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

if (est_connecte()) {
    header('Location: index.php');
    exit();
}

$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = securiser($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    if (empty($email) || empty($mot_de_passe)) {
        $erreur = "Veuillez remplir tous les champs.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($mot_de_passe, $user['mot_de_passe'])) {
            if ($user['statut'] === 'suspendu') {
                $erreur = "Votre compte a été suspendu par l'administrateur. Veuillez contacter le support.";
            } else {
                // Connexion réussie
                $_SESSION['utilisateur_id'] = $user['id'];
                $_SESSION['utilisateur_nom'] = $user['nom_complet'];
                $_SESSION['utilisateur_email'] = $user['email'];
                $_SESSION['utilisateur_role'] = $user['role'];

                definir_flash('success', 'Ravi de vous revoir, ' . $user['nom_complet'] . ' !');

                // Redirection selon le rôle
                if ($user['role'] === 'admin') {
                    header('Location: admin.php');
                } elseif ($user['role'] === 'agriculteur') {
                    header('Location: mes_produits.php');
                } elseif ($user['role'] === 'transporteur') {
                    header('Location: mes_commandes.php');
                } else {
                    header('Location: produits.php');
                }
                exit();
            }
        } else {
            $erreur = "Identifiants incorrects (E-mail ou mot de passe invalide).";
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-card" style="max-width: 480px;">
    <h2 class="form-title"><i class="fa-solid fa-right-to-bracket"></i> Connexion</h2>

    <?php if ($erreur): ?>
        <div class="alert alert-danger">
            <span><?php echo htmlspecialchars($erreur); ?></span>
        </div>
    <?php endif; ?>

    <form action="connexion.php" method="POST">
        <div class="form-group">
            <label class="form-label">Adresse E-mail</label>
            <input type="email" name="email" class="form-control" placeholder="exemple@agriconnect.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Mot de passe</label>
            <input type="password" name="mot_de_passe" class="form-control" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
            <i class="fa-solid fa-sign-in-alt"></i> Se Connecter
        </button>
    </form>

    <div style="background: #f8fafc; border-radius: 8px; padding: 15px; margin-top: 24px; font-size: 0.85rem; border: 1px dashed #cbd5e1;">
        <strong style="color: var(--dark);"><i class="fa-solid fa-info-circle"></i> Comptes de démo disponibles (Mot de passe: <code>password123</code>) :</strong>
        <ul style="margin-left: 20px; margin-top: 6px;">
            <li><strong>Admin :</strong> admin@agriconnect.com</li>
            <li><strong>Agriculteur :</strong> agriculteur@agriconnect.com</li>
            <li><strong>Acheteur :</strong> acheteur@agriconnect.com</li>
            <li><strong>Transporteur :</strong> transporteur@agriconnect.com</li>
        </ul>
    </div>

    <div style="text-align: center; margin-top: 20px;">
        <p>Vous n'avez pas de compte ? <a href="inscription.php" style="color: var(--primary); font-weight: 700;">Créer un compte</a></p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
