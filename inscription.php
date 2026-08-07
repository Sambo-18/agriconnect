<?php
/**
 * Page d'Inscription des Utilisateurs (Cameroun 🇨🇲)
 * Application AgriConnect
 */

$page_title = "Créer un compte";
require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

// Si déjà connecté, rediriger
if (est_connecte()) {
    header('Location: index.php');
    exit();
}

$role_selectionne = $_GET['role'] ?? 'acheteur';
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_complet = securiser($_POST['nom_complet'] ?? '');
    $email = securiser($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $mot_de_passe_confirm = $_POST['mot_de_passe_confirm'] ?? '';
    $role = securiser($_POST['role'] ?? 'acheteur');
    $telephone = securiser($_POST['telephone'] ?? '');
    $adresse = securiser($_POST['adresse'] ?? '');
    $ville = securiser($_POST['ville'] ?? 'Douala');
    
    // Coordonnées GPS par défaut centrées sur la ville au Cameroun (seront capturées en temps réel lors des activités)
    $latitude = 4.0511; // Douala par défaut
    $longitude = 9.7679;
    if (strpos(strtolower($ville), 'yaounde') !== false) {
        $latitude = 3.8480; $longitude = 11.5021;
    } elseif (strpos(strtolower($ville), 'bafoussam') !== false) {
        $latitude = 5.4778; $longitude = 10.4176;
    }

    // Validation des champs
    if (empty($nom_complet)) $erreurs[] = "Le nom complet est obligatoire.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $erreurs[] = "Un email valide est requis.";
    if (strlen($mot_de_passe) < 6) $erreurs[] = "Le mot de passe doit contenir au moins 6 caractères.";
    if ($mot_de_passe !== $mot_de_passe_confirm) $erreurs[] = "Les mots de passe ne correspondent pas.";

    // Vérification de l'unicité de l'email
    $stmtCheck = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
    $stmtCheck->execute([$email]);
    if ($stmtCheck->fetch()) {
        $erreurs[] = "Cet e-mail est déjà utilisé par un autre compte.";
    }

    if (empty($erreurs)) {
        $pass_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            $stmtIns = $pdo->prepare("
                INSERT INTO utilisateurs (nom_complet, email, mot_de_passe, role, telephone, adresse, ville, latitude, longitude, statut) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif')
            ");
            $stmtIns->execute([$nom_complet, $email, $pass_hash, $role, $telephone, $adresse, $ville, $latitude, $longitude]);
            $nouvel_id = $pdo->lastInsertId();

            // Si transporteur, insérer dans transporteurs_details
            if ($role === 'transporteur') {
                $type_vehicule = securiser($_POST['type_vehicule'] ?? 'Camion Canter 7.5T');
                $immatriculation = securiser($_POST['immatriculation'] ?? 'LT-0000-XX');
                $capacite = !empty($_POST['capacite_tonnes']) ? (float)$_POST['capacite_tonnes'] : 5.0;

                $stmtTrans = $pdo->prepare("
                    INSERT INTO transporteurs_details (utilisateur_id, type_vehicule, immatriculation, capacite_tonnes, disponible, latitude_actuelle, longitude_actuelle)
                    VALUES (?, ?, ?, ?, 'oui', ?, ?)
                ");
                $stmtTrans->execute([$nouvel_id, $type_vehicule, $immatriculation, $capacite, $latitude, $longitude]);
            }

            $pdo->commit();

            // Notification de bienvenue
            creer_notification($pdo, $nouvel_id, 'Bienvenue sur AgriConnect Cameroun !', 'Votre compte a été créé avec succès.');

            // Connecter automatiquement l'utilisateur
            $_SESSION['utilisateur_id'] = $nouvel_id;
            $_SESSION['utilisateur_nom'] = $nom_complet;
            $_SESSION['utilisateur_email'] = $email;
            $_SESSION['utilisateur_role'] = $role;

            definir_flash('success', 'Bienvenue sur AgriConnect Cameroun ! Votre compte a été créé avec succès.');
            header('Location: index.php');
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erreurs[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="form-card" style="max-width: 580px;">
    <h2 class="form-title"><i class="fa-solid fa-user-plus"></i> Créer un compte AgriConnect</h2>

    <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger">
            <ul style="margin-left: 20px;">
                <?php foreach ($erreurs as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="inscription.php" method="POST" id="formInscription">
        
        <!-- Choix du Rôle -->
        <div class="form-group">
            <label class="form-label"><i class="fa-solid fa-users-gear"></i> Je souhaite m'inscrire en tant que :</label>
            <select name="role" id="roleSelector" class="form-control" onchange="toggleTransporteurFields()">
                <option value="acheteur" <?php echo ($role_selectionne === 'acheteur') ? 'selected' : ''; ?>>Acheteur / Grossiste / Particulier</option>
                <option value="agriculteur" <?php echo ($role_selectionne === 'agriculteur') ? 'selected' : ''; ?>>Agriculteur / Producteur</option>
                <option value="transporteur" <?php echo ($role_selectionne === 'transporteur') ? 'selected' : ''; ?>>Transporteur / Livreur</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Nom complet / Nom de l'exploitation</label>
            <input type="text" name="nom_complet" class="form-control" placeholder="ex: Fotso Emmanuel" required value="<?php echo htmlspecialchars($_POST['nom_complet'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Adresse E-mail</label>
            <input type="email" name="email" class="form-control" placeholder="ex: fotso@gmail.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group">
                <label class="form-label">Mot de passe</label>
                <input type="password" name="mot_de_passe" class="form-control" required minlength="6">
            </div>
            <div class="form-group">
                <label class="form-label">Confirmer le mot de passe</label>
                <input type="password" name="mot_de_passe_confirm" class="form-control" required minlength="6">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="form-group">
                <label class="form-label">Téléphone (+237)</label>
                <input type="text" name="telephone" class="form-control" placeholder="ex: +237 677 88 99 00" required value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Ville / Région</label>
                <select name="ville" class="form-control">
                    <option value="Douala">Douala (Littoral)</option>
                    <option value="Yaoundé">Yaoundé (Centre)</option>
                    <option value="Bafoussam">Bafoussam (Ouest)</option>
                    <option value="Bamenda">Bamenda (Nord-Ouest)</option>
                    <option value="Garoua">Garoua (Nord)</option>
                    <option value="Maroua">Maroua (Extrême-Nord)</option>
                    <option value="Ngaoundéré">Ngaoundéré (Adamaoua)</option>
                    <option value="Kribi">Kribi (Sud)</option>
                    <option value="Bertoua">Bertoua (Est)</option>
                    <option value="Limbe">Limbe (Sud-Ouest)</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Adresse / Quartier</label>
            <input type="text" name="adresse" class="form-control" placeholder="ex: Quartier Akwa, Rue du Marché" value="<?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?>">
        </div>

        <!-- Champs spécifiques Transporteur -->
        <div id="fieldsTransporteur" style="display: none; background: #f0fdf4; padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
            <h4 style="color: var(--primary-dark); margin-bottom: 14px;"><i class="fa-solid fa-truck"></i> Informations du Véhicule</h4>
            <div class="form-group">
                <label class="form-label">Type de véhicule</label>
                <input type="text" name="type_vehicule" class="form-control" placeholder="ex: Camion Canter 7.5T, Pick-Up Hilux">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Plaque d'immatriculation</label>
                    <input type="text" name="immatriculation" class="form-control" placeholder="ex: LT-8492-AX">
                </div>
                <div class="form-group">
                    <label class="form-label">Capacité (Tonnes)</label>
                    <input type="number" step="0.1" name="capacite_tonnes" class="form-control" value="5.0">
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
            <i class="fa-solid fa-check-circle"></i> Valider mon Inscription
        </button>
    </form>

    <div style="text-align: center; margin-top: 24px;">
        <p>Vous avez déjà un compte ? <a href="connexion.php" style="color: var(--primary); font-weight: 700;">Se connecter</a></p>
    </div>
</div>

<script>
function toggleTransporteurFields() {
    const role = document.getElementById('roleSelector').value;
    const transDiv = document.getElementById('fieldsTransporteur');
    transDiv.style.display = (role === 'transporteur') ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    toggleTransporteurFields();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
