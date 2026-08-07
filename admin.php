<?php
/**
 * Tableau de Bord Administrateur Sécurisé (Cameroun 🇨🇲 avec Validation CNI)
 * Application AgriConnect
 */

$page_title = "Tableau de Bord Administrateur";
require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

exiger_role('admin');

// 1. VALIDATION / REJET DES DEMANDES DE CERTIFICATION CNI
if (isset($_GET['action_certif']) && isset($_GET['user_id'])) {
    $uid = (int)$_GET['user_id'];
    $act = securiser($_GET['action_certif']);

    if ($act === 'valider') {
        $stmtC = $pdo->prepare("UPDATE utilisateurs SET est_certifie = 1, statut_certification = 'valide' WHERE id = ?");
        $stmtC->execute([$uid]);
        creer_notification($pdo, $uid, 'Félicitations ! Compte Certifié 🔵', 'Votre demande de certification CNI a été validée par l\'administrateur. Le Badge Bleu est désormais actif sur votre profil.');
        definir_flash('success', 'Certification CNI validée avec succès pour cet utilisateur.');
    } elseif ($act === 'rejeter') {
        $stmtC = $pdo->prepare("UPDATE utilisateurs SET est_certifie = 0, statut_certification = 'rejete' WHERE id = ?");
        $stmtC->execute([$uid]);
        creer_notification($pdo, $uid, 'Certification CNI non validée', 'Votre photo CNI n\'a pas pu être validée. Veuillez soumettre une nouvelle photo nette en suivant les consignes.');
        definir_flash('warning', 'La demande de certification a été rejetée.');
    }
    header('Location: admin.php#section-certifications');
    exit();
}

// 2. GESTION DES UTILISATEURS (Activer / Suspendre / Supprimer)
if (isset($_GET['action_user']) && isset($_GET['user_id'])) {
    $uid = (int)$_GET['user_id'];
    $act = securiser($_GET['action_user']);

    if ($act === 'activer') {
        $stmt = $pdo->prepare("UPDATE utilisateurs SET statut = 'actif' WHERE id = ?");
        $stmt->execute([$uid]);
        definir_flash('success', 'Compte utilisateur réactivé.');
    } elseif ($act === 'suspendre') {
        $stmt = $pdo->prepare("UPDATE utilisateurs SET statut = 'suspendu' WHERE id = ?");
        $stmt->execute([$uid]);
        definir_flash('warning', 'Compte utilisateur suspendu.');
    } elseif ($act === 'supprimer') {
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
        $stmt->execute([$uid]);
        definir_flash('danger', 'Compte utilisateur supprimé définitivement.');
    }
    header('Location: admin.php#section-users');
    exit();
}

// 3. MODÉRATION DES PRODUITS
if (isset($_GET['action_prod']) && isset($_GET['prod_id'])) {
    $pid = (int)$_GET['prod_id'];
    $stmtP = $pdo->prepare("DELETE FROM produits WHERE id = ?");
    $stmtP->execute([$pid]);
    definir_flash('success', 'L\'annonce produit a été supprimée du catalogue.');
    header('Location: admin.php#section-produits');
    exit();
}

// 4. GESTION DES CATÉGORIES (Ajout)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_ajout_cat'])) {
    $nom_cat = securiser($_POST['nom_cat'] ?? '');
    $desc_cat = securiser($_POST['desc_cat'] ?? '');
    $icone_cat = securiser($_POST['icone_cat'] ?? 'fa-leaf');

    if (!empty($nom_cat)) {
        $stmtCat = $pdo->prepare("INSERT INTO categories (nom, description, icone) VALUES (?, ?, ?)");
        $stmtCat->execute([$nom_cat, $desc_cat, $icone_cat]);
        definir_flash('success', 'Nouvelle catégorie "' . $nom_cat . '" ajoutée.');
    }
    header('Location: admin.php#section-categories');
    exit();
}

// 5. NOTIFICATION GLOBALE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_notif_globale'])) {
    $titre_notif = securiser($_POST['titre_notif'] ?? '');
    $msg_notif = securiser($_POST['msg_notif'] ?? '');

    if (!empty($titre_notif) && !empty($msg_notif)) {
        $users = $pdo->query("SELECT id FROM utilisateurs")->fetchAll();
        $stmtN = $pdo->prepare("INSERT INTO notifications (utilisateur_id, titre, message) VALUES (?, ?, ?)");
        foreach ($users as $u) {
            $stmtN->execute([$u['id'], $titre_notif, $msg_notif]);
        }
        definir_flash('success', 'Notification globale envoyée à ' . count($users) . ' utilisateurs.');
    }
    header('Location: admin.php');
    exit();
}

// 6. SAUVEGARDE DE LA BASE DE DONNÉES (Export SQL)
if (isset($_GET['action_export_sql'])) {
    $tables = ['utilisateurs', 'transporteurs_details', 'categories', 'produits', 'commandes', 'detail_commandes', 'gps_historique', 'messages', 'notifications', 'avis', 'signalements'];
    $sql_export = "-- Sauvegarde de la base bd_agricole (" . date('Y-m-d H:i:s') . ")\n\n";

    foreach ($tables as $t) {
        $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll();
        $sql_export .= "-- Table: $t\n";
        foreach ($rows as $r) {
            $keys = array_keys($r);
            $vals = array_map(function($v) use ($pdo) {
                return $v === null ? "NULL" : $pdo->quote($v);
            }, array_values($r));
            $sql_export .= "INSERT INTO `$t` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $vals) . ");\n";
        }
        $sql_export .= "\n";
    }

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="bd_agricole_backup_' . date('Ymd_His') . '.sql"');
    echo $sql_export;
    exit();
}

// STATISTIQUES GLOBALES
$nb_agri = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'agriculteur'")->fetchColumn();
$nb_ach = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'acheteur'")->fetchColumn();
$nb_trans = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'transporteur'")->fetchColumn();
$nb_cmd = $pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn();
$ca_total = $pdo->query("SELECT SUM(total_prix) FROM commandes WHERE statut = 'livree'")->fetchColumn() ?: 0;

// REQUÊTES DES DONNÉES
$demandes_certif = $pdo->query("SELECT * FROM utilisateurs WHERE statut_certification = 'en_attente' ORDER BY date_demande_certification ASC")->fetchAll();
$utilisateurs = $pdo->query("SELECT * FROM utilisateurs ORDER BY date_creation DESC")->fetchAll();
$produits_tous = $pdo->query("SELECT p.*, c.nom AS cat_nom, u.nom_complet AS agriculteur_nom FROM produits p JOIN categories c ON p.categorie_id = c.id JOIN utilisateurs u ON p.agriculteur_id = u.id ORDER BY p.date_publication DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; margin-bottom: 30px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2><i class="fa-solid fa-chart-line" style="color: var(--accent);"></i> Dashboard Administrateur AgriConnect 🇨🇲</h2>
        <p style="color: var(--gray-500);">Vue d'ensemble, validation des pièces CNI et contrôle de la base `bd_agricole`.</p>
    </div>
    <a href="admin.php?action_export_sql=1" class="btn btn-accent">
        <i class="fa-solid fa-download"></i> Sauvegarder la BDD (.sql)
    </a>
</div>

<!-- CARTES DE STATISTIQUES GLOBALES -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 40px;">
    <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200); text-align: center;">
        <h3 style="font-size: 2rem; color: var(--primary);"><?php echo $nb_agri; ?></h3>
        <p style="color: var(--gray-500); font-weight: 500;">Agriculteurs</p>
    </div>
    <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200); text-align: center;">
        <h3 style="font-size: 2rem; color: var(--accent);"><?php echo $nb_ach; ?></h3>
        <p style="color: var(--gray-500); font-weight: 500;">Acheteurs</p>
    </div>
    <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200); text-align: center;">
        <h3 style="font-size: 2rem; color: var(--info);"><?php echo $nb_trans; ?></h3>
        <p style="color: var(--gray-500); font-weight: 500;">Transporteurs</p>
    </div>
    <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200); text-align: center;">
        <h3 style="font-size: 2rem; color: var(--dark);"><?php echo $nb_cmd; ?></h3>
        <p style="color: var(--gray-500); font-weight: 500;">Commandes</p>
    </div>
    <div style="background: var(--white); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--gray-200); text-align: center;">
        <h3 style="font-size: 1.6rem; color: var(--success);"><?php echo formater_prix($ca_total); ?></h3>
        <p style="color: var(--gray-500); font-weight: 500;">Ventes Réalisées</p>
    </div>
</div>

<!-- SECTION VALIDATION DES DEMANDES DE CERTIFICATION CNI -->
<div id="section-certifications" style="margin-bottom: 40px; background: var(--white); padding: 24px; border-radius: var(--radius-md); border: 2px solid #3b82f6; box-shadow: var(--shadow-sm);">
    <h3 style="font-size: 1.3rem; margin-bottom: 16px; color: var(--dark);">
        <i class="fa-solid fa-id-card" style="color: #3b82f6;"></i> Demandes de Certification CNI (Badge Bleu 🔵)
        <?php if (!empty($demandes_certif)): ?>
            <span class="nav-badge" style="background: #3b82f6;"><?php echo count($demandes_certif); ?> en attente</span>
        <?php endif; ?>
    </h3>

    <?php if (empty($demandes_certif)): ?>
        <p style="color: var(--gray-500); font-size: 0.95rem;">Aucune nouvelle demande de certification en attente d'examen.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Rôle</th>
                        <th>Ville</th>
                        <th>Selfie avec CNI</th>
                        <th>Date Soumission</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($demandes_certif as $cert): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($cert['nom_complet']); ?></strong><br>
                                <small style="color: var(--gray-500);"><?php echo htmlspecialchars($cert['email']); ?></small>
                            </td>
                            <td><span class="status-badge status-acceptee"><?php echo ucfirst($cert['role']); ?></span></td>
                            <td><?php echo htmlspecialchars($cert['ville']); ?></td>
                            <td>
                                <a href="assets/uploads/cni/<?php echo htmlspecialchars($cert['photo_cni']); ?>" target="_blank" class="btn btn-sm btn-outline">
                                    <i class="fa-solid fa-image"></i> Voir Selfie CNI
                                </a>
                            </td>
                            <td><?php echo formater_date($cert['date_demande_certification']); ?></td>
                            <td>
                                <a href="admin.php?action_certif=valider&user_id=<?php echo $cert['id']; ?>" class="btn btn-sm btn-success" title="Valider & Accorder le Badge Bleu">
                                    <i class="fa-solid fa-check"></i> Valider Certification 🔵
                                </a>
                                <a href="admin.php?action_certif=rejeter&user_id=<?php echo $cert['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Rejeter cette demande ?');" title="Rejeter">
                                    <i class="fa-solid fa-xmark"></i> Rejeter
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- SECTION NOTIFICATION GLOBALE & CATEGORIES -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 30px; margin-bottom: 40px;">
    <!-- Formulaire Notification Globale -->
    <div style="background: var(--white); padding: 24px; border-radius: var(--radius-md); border: 1px solid var(--gray-200);">
        <h3 style="font-size: 1.2rem; margin-bottom: 16px; color: var(--dark);"><i class="fa-solid fa-bullhorn" style="color: var(--accent);"></i> Diffusion de Notification Globale</h3>
        <form action="admin.php" method="POST">
            <input type="hidden" name="action_notif_globale" value="1">
            <div class="form-group">
                <label class="form-label">Titre du message</label>
                <input type="text" name="titre_notif" class="form-control" placeholder="ex: Information officielle AgriConnect" required>
            </div>
            <div class="form-group">
                <label class="form-label">Contenu de la notification</label>
                <textarea name="msg_notif" class="form-control" rows="3" required placeholder="Message diffusé à tous les inscrits..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane"></i> Envoyer à tous</button>
        </form>
    </div>

    <!-- Formulaire Ajout Catégorie -->
    <div id="section-categories" style="background: var(--white); padding: 24px; border-radius: var(--radius-md); border: 1px solid var(--gray-200);">
        <h3 style="font-size: 1.2rem; margin-bottom: 16px; color: var(--dark);"><i class="fa-solid fa-folder-plus" style="color: var(--primary);"></i> Ajouter une Catégorie Produit</h3>
        <form action="admin.php" method="POST">
            <input type="hidden" name="action_ajout_cat" value="1">
            <div class="form-group">
                <label class="form-label">Nom de la catégorie</label>
                <input type="text" name="nom_cat" class="form-control" placeholder="ex: Oléagineux" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <input type="text" name="desc_cat" class="form-control" placeholder="ex: Huile de palme, arachides">
            </div>
            <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-plus"></i> Créer la Catégorie</button>
        </form>
    </div>
</div>

<!-- GESTION DES COMPTES UTILISATEURS -->
<div id="section-users" style="margin-bottom: 40px;">
    <h3 style="font-size: 1.4rem; margin-bottom: 16px; color: var(--dark);"><i class="fa-solid fa-users"></i> Gestion des Comptes Utilisateurs</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom Complet</th>
                    <th>E-mail</th>
                    <th>Rôle</th>
                    <th>Certification</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utilisateurs as $u): ?>
                    <tr>
                        <td>#<?php echo $u['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($u['nom_complet']); ?></strong>
                            <?php echo afficher_badge_certification($u['est_certifie'], $u['statut_certification']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><span class="status-badge status-acceptee"><?php echo ucfirst($u['role']); ?></span></td>
                        <td>
                            <?php if ($u['est_certifie']): ?>
                                <span style="color: #3b82f6; font-weight: 700;">Certifié 🔵</span>
                            <?php else: ?>
                                <span style="color: var(--gray-500);"><?php echo htmlspecialchars($u['statut_certification']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['statut'] === 'actif'): ?>
                                <span class="status-badge status-livree">Actif</span>
                            <?php else: ?>
                                <span class="status-badge status-refusee">Suspendu</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['statut'] === 'actif'): ?>
                                <a href="admin.php?action_user=suspendre&user_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-accent" title="Suspendre"><i class="fa-solid fa-ban"></i></a>
                            <?php else: ?>
                                <a href="admin.php?action_user=activer&user_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-success" title="Activer"><i class="fa-solid fa-check"></i></a>
                            <?php endif; ?>
                            <?php if ($u['role'] !== 'admin'): ?>
                                <a href="admin.php?action_user=supprimer&user_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ce compte ?');" title="Supprimer"><i class="fa-solid fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
