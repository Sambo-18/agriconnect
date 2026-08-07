<?php
/**
 * Tour de Contrôle des Commandes & Paiements (Cameroun 🇨🇲)
 * Application AgriConnect
 */

$page_title = "Mes Commandes & Livraisons";
require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

exiger_connexion();

$user_id = $_SESSION['utilisateur_id'];
$user_role = $_SESSION['utilisateur_role'];

// TRAITEMENT ACTIONS SUR LES COMMANDES
if (isset($_GET['action']) && isset($_GET['id'])) {
    $cmd_id = (int)$_GET['id'];
    $action = securiser($_GET['action']);

    // 1. Agriculteur accepte la commande & règle les frais de transport par MoMo / OM
    if ($user_role === 'agriculteur' || $user_role === 'admin') {
        if ($action === 'accepter') {
            $trans_id = !empty($_GET['transporteur_id']) ? (int)$_GET['transporteur_id'] : null;
            $op_trans = securiser($_GET['op_trans'] ?? 'mtn_momo');
            $ref_trans = ($op_trans === 'mtn_momo' ? 'MOMO-CM-T-' : 'OM-CM-T-') . rand(100000, 999999);

            $stmt = $pdo->prepare("
                UPDATE commandes 
                SET statut = 'acceptee', transporteur_id = ?, statut_paiement_transport = 'paye', mode_paiement_transport = ?, reference_transaction_transport = ?
                WHERE id = ? AND agriculteur_id = ?
            ");
            $stmt->execute([$trans_id, $op_trans, $ref_trans, $cmd_id, $user_id]);

            if ($trans_id) {
                creer_notification($pdo, $trans_id, 'Mission de Transport attribuée et financée !', 'Une mission de transport vous a été confiée pour la commande #' . $cmd_id . '. Les frais de transport ont été réglés (Réf: ' . $ref_trans . ').');
            }

            definir_flash('success', 'Commande #' . $cmd_id . ' acceptée ! Les frais de transport ont été réglés via ' . strtoupper($op_trans) . '.');
        } elseif ($action === 'refuser') {
            $stmt = $pdo->prepare("UPDATE commandes SET statut = 'refusee' WHERE id = ? AND agriculteur_id = ?");
            $stmt->execute([$cmd_id, $user_id]);
            definir_flash('warning', 'Commande #' . $cmd_id . ' refusée.');
        }
    }

    // 2. Transporteur démarre ou termine la livraison
    if ($user_role === 'transporteur' || $user_role === 'admin') {
        if ($action === 'demarrer_livraison') {
            $stmt = $pdo->prepare("UPDATE commandes SET statut = 'en_cours_livraison' WHERE id = ? AND transporteur_id = ?");
            $stmt->execute([$cmd_id, $user_id]);
            definir_flash('success', 'Livraison démarrée. Votre position GPS est désormais partagée en temps réel.');
        } elseif ($action === 'marquer_livree') {
            $stmt = $pdo->prepare("UPDATE commandes SET statut = 'livree', date_livraison = NOW() WHERE id = ? AND transporteur_id = ?");
            $stmt->execute([$cmd_id, $user_id]);
            definir_flash('success', 'Livraison confirmée comme effectuée avec succès !');
        }
    }

    // 3. Acheteur confirme la réception
    if ($user_role === 'acheteur' || $user_role === 'admin') {
        if ($action === 'confirmer_reception') {
            $stmt = $pdo->prepare("UPDATE commandes SET statut = 'livree', date_livraison = NOW() WHERE id = ? AND acheteur_id = ?");
            $stmt->execute([$cmd_id, $user_id]);
            definir_flash('success', 'Merci ! La réception de votre commande a été confirmée.');
        }
    }

    header('Location: mes_commandes.php');
    exit();
}

// TRAITEMENT D'AJOUT D'AVIS/ÉVALUATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_avis'])) {
    $cmd_id = (int)$_POST['commande_id'];
    $cible_id = (int)$_POST['cible_id'];
    $type_cible = securiser($_POST['type_cible']);
    $note = (int)$_POST['note'];
    $commentaire = securiser($_POST['commentaire']);

    if ($note >= 1 && $note <= 5 && $cible_id > 0) {
        $stmtAvis = $pdo->prepare("INSERT INTO avis (commande_id, auteur_id, cible_id, type_cible, note, commentaire) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtAvis->execute([$cmd_id, $user_id, $cible_id, $type_cible, $note, $commentaire]);
        definir_flash('success', 'Votre évaluation a été enregistrée avec succès. Merci !');
    }
    header('Location: mes_commandes.php');
    exit();
}

// REQUÊTE COMPLÈTE INCLUANT ACHETEUR, AGRICULTEUR ET TRANSPORTEUR
$query = "
    SELECT c.*, 
           u_ach.nom_complet AS acheteur_nom, u_ach.telephone AS acheteur_tel, u_ach.est_certifie AS ach_certifie,
           u_agri.nom_complet AS agriculteur_nom, u_agri.telephone AS agriculteur_tel, u_agri.est_certifie AS agri_certifie,
           u_trans.nom_complet AS transporteur_nom, u_trans.telephone AS transporteur_tel, u_trans.est_certifie AS trans_certifie
    FROM commandes c
    JOIN utilisateurs u_ach ON c.acheteur_id = u_ach.id
    JOIN utilisateurs u_agri ON c.agriculteur_id = u_agri.id
    LEFT JOIN utilisateurs u_trans ON c.transporteur_id = u_trans.id
";

$params = [];
if ($user_role === 'agriculteur') {
    $query .= " WHERE c.agriculteur_id = ?";
    $params[] = $user_id;
} elseif ($user_role === 'transporteur') {
    $query .= " WHERE c.transporteur_id = ?";
    $params[] = $user_id;
} elseif ($user_role === 'acheteur') {
    $query .= " WHERE c.acheteur_id = ?";
    $params[] = $user_id;
}

$query .= " ORDER BY c.date_commande DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$commandes = $stmt->fetchAll();

// Liste des transporteurs disponibles pour l'agriculteur
$transporteurs_liste = [];
if ($user_role === 'agriculteur' || $user_role === 'admin') {
    $transporteurs_liste = $pdo->query("
        SELECT u.id, u.nom_complet, u.est_certifie, td.type_vehicule,
               COALESCE(AVG(a.note), 5.0) AS moyenne_note, COUNT(a.id) AS total_avis
        FROM utilisateurs u 
        JOIN transporteurs_details td ON u.id = td.utilisateur_id 
        LEFT JOIN avis a ON a.cible_id = u.id AND a.type_cible = 'transporteur'
        WHERE u.role = 'transporteur' AND u.statut = 'actif' AND td.disponible = 'oui'
        GROUP BY u.id, td.id
    ")->fetchAll();
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-top: 20px; margin-bottom: 30px;">
    <h2><i class="fa-solid fa-clipboard-list" style="color: var(--primary);"></i> Suivi des Commandes & Paiements MoMo</h2>
    <p style="color: var(--gray-500);">Espace de gestion des transactions et suivi géolocalisé au Cameroun.</p>
</div>

<?php if (empty($commandes)): ?>
    <div style="text-align: center; padding: 60px 20px; background: var(--white); border-radius: var(--radius-md); border: 1px dashed var(--gray-500);">
        <i class="fa-solid fa-box-open" style="font-size: 3rem; color: var(--gray-500); margin-bottom: 16px;"></i>
        <h3>Aucune commande répertoriée pour le moment</h3>
        <p style="color: var(--gray-500);">Vos futures transactions et livraisons apparaîtront ici.</p>
    </div>
<?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 20px; margin-bottom: 60px;">
        <?php foreach ($commandes as $cmd): ?>
            <div style="background: var(--white); border-radius: var(--radius-md); padding: 24px; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200);">
                
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--gray-100); padding-bottom: 14px; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <strong style="font-size: 1.2rem; color: var(--dark);">Commande #<?php echo $cmd['id']; ?></strong>
                        <span style="color: var(--gray-500); font-size: 0.9rem; margin-left: 10px;"><?php echo formater_date($cmd['date_commande']); ?></span>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <?php if ($cmd['statut_paiement_acheteur'] === 'paye'): ?>
                            <span class="status-badge status-livree" title="Payé via <?php echo strtoupper($cmd['mode_paiement_acheteur'] ?? 'MOMO'); ?>">
                                <i class="fa-solid fa-check"></i> Réglé Par Acheteur (<?php echo strtoupper($cmd['mode_paiement_acheteur'] ?? 'MOMO'); ?>)
                            </span>
                        <?php endif; ?>
                        <span class="status-badge status-<?php echo $cmd['statut']; ?>">
                            <i class="fa-solid fa-circle-dot"></i> <?php echo str_replace('_', ' ', ucfirst($cmd['statut'])); ?>
                        </span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 20px; font-size: 0.95rem;">
                    <?php if ($user_role !== 'acheteur'): ?>
                        <div>
                            <strong style="color: var(--dark);"><i class="fa-solid fa-user"></i> Acheteur :</strong><br>
                            <?php echo htmlspecialchars($cmd['acheteur_nom'] ?? 'Acheteur'); ?> <?php echo afficher_badge_certification($cmd['ach_certifie'] ?? 0); ?><br>
                            <small style="color: var(--gray-500);">Tél: <?php echo htmlspecialchars($cmd['acheteur_tel'] ?? '-'); ?></small>
                        </div>
                    <?php endif; ?>

                    <?php if ($user_role !== 'agriculteur'): ?>
                        <div>
                            <strong style="color: var(--dark);"><i class="fa-solid fa-tractor"></i> Agriculteur :</strong><br>
                            <?php echo htmlspecialchars($cmd['agriculteur_nom'] ?? 'Agriculteur'); ?> <?php echo afficher_badge_certification($cmd['agri_certifie'] ?? 0); ?><br>
                            <small style="color: var(--gray-500);">Tél: <?php echo htmlspecialchars($cmd['agriculteur_tel'] ?? '-'); ?></small>
                        </div>
                    <?php endif; ?>

                    <div>
                        <strong style="color: var(--dark);"><i class="fa-solid fa-truck"></i> Transporteur :</strong><br>
                        <?php echo !empty($cmd['transporteur_nom']) ? htmlspecialchars($cmd['transporteur_nom']) . ' ' . afficher_badge_certification($cmd['trans_certifie'] ?? 0) : '<em>Non encore attribué</em>'; ?><br>
                        <?php if (!empty($cmd['transporteur_id'])): ?>
                            <?php $trNote = obtenir_note_moyenne_avis($pdo, $cmd['transporteur_id'], 'transporteur'); ?>
                            <div style="font-size: 0.85rem; margin-top: 2px;">
                                <?php echo afficher_etoiles_note($trNote['moyenne'], $trNote['total']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($cmd['transporteur_tel'])): ?>
                            <small style="color: var(--gray-500);">Tél: <?php echo htmlspecialchars($cmd['transporteur_tel']); ?></small>
                        <?php endif; ?>
                    </div>

                    <div>
                        <strong style="color: var(--dark);"><i class="fa-solid fa-coins"></i> Détails Financiers :</strong><br>
                        <strong style="color: var(--primary-dark); font-size: 1.15rem;"><?php echo formater_prix($cmd['total_prix']); ?></strong>
                        <small style="color: var(--gray-500); display: block;">Frais de transport : <?php echo formater_prix($cmd['frais_transport']); ?></small>
                        <?php if (!empty($cmd['reference_transaction_acheteur'])): ?>
                            <small style="color: var(--accent); display: block;">Réf MoMo Acheteur : <?php echo htmlspecialchars($cmd['reference_transaction_acheteur']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="background: var(--light-bg); padding: 12px 16px; border-radius: var(--radius-sm); font-size: 0.9rem; margin-bottom: 20px;">
                    <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> <strong>Lieu de Livraison :</strong> <?php echo htmlspecialchars($cmd['adresse_livraison']); ?>
                </div>

                <!-- ACTIONS PAR RÔLE -->
                <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; border-top: 1px solid var(--gray-200); padding-top: 16px;">
                    
                    <!-- Suivi GPS en direct (si la commande est acceptée ou en cours) -->
                    <?php if (in_array($cmd['statut'], ['acceptee', 'en_cours_livraison', 'livree'])): ?>
                        <a href="suivi_livraison.php?id=<?php echo $cmd['id']; ?>" class="btn btn-accent btn-sm">
                            <i class="fa-solid fa-satellite-dish"></i> Suivre sur la Carte GPS
                        </a>
                    <?php endif; ?>

                    <!-- Actions Agriculteur (Acceptation & Financement du Transport par MoMo/OM) -->
                    <?php if ($user_role === 'agriculteur' && $cmd['statut'] === 'en_attente'): ?>
                        <form action="mes_commandes.php" method="GET" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; background: #ecfdf5; padding: 12px; border-radius: 8px; border: 1px solid #bbf7d0;">
                            <input type="hidden" name="action" value="accepter">
                            <input type="hidden" name="id" value="<?php echo $cmd['id']; ?>">
                            
                            <select name="transporteur_id" class="form-control" style="width: auto; padding: 6px 12px;" required>
                                <option value="">-- Attribuer un Transporteur --</option>
                                <?php foreach ($transporteurs_liste as $tr): ?>
                                    <option value="<?php echo $tr['id']; ?>">
                                        <?php echo htmlspecialchars($tr['nom_complet']); ?> <?php echo $tr['est_certifie'] ? '🔵 Certifié' : ''; ?> (⭐ <?php echo number_format($tr['moyenne_note'], 1); ?>/5 - <?php echo $tr['total_avis']; ?> avis)
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="op_trans" class="form-control" style="width: auto; padding: 6px 12px;">
                                <option value="mtn_momo">MTN Mobile Money</option>
                                <option value="orange_money">Orange Money</option>
                            </select>

                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fa-solid fa-check"></i> Régler le Transport (<?php echo formater_prix($cmd['frais_transport']); ?>) & Accepter
                            </button>
                        </form>

                        <a href="mes_commandes.php?action=refuser&id=<?php echo $cmd['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Refuser cette commande ?');">
                            <i class="fa-solid fa-xmark"></i> Refuser
                        </a>
                    <?php endif; ?>

                    <!-- Actions Transporteur -->
                    <?php if ($user_role === 'transporteur'): ?>
                        <?php if ($cmd['statut'] === 'acceptee'): ?>
                            <a href="mes_commandes.php?action=demarrer_livraison&id=<?php echo $cmd['id']; ?>" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-play"></i> Démarrer la Livraison (Partager GPS)
                            </a>
                        <?php elseif ($cmd['statut'] === 'en_cours_livraison'): ?>
                            <a href="mes_commandes.php?action=marquer_livree&id=<?php echo $cmd['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Confirmer la livraison effectuée ?');">
                                <i class="fa-solid fa-flag-checkered"></i> Marquer la Commande comme Livrée
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Actions Acheteur -->
                    <?php if ($user_role === 'acheteur'): ?>
                        <?php if ($cmd['statut'] === 'en_cours_livraison'): ?>
                            <a href="mes_commandes.php?action=confirmer_reception&id=<?php echo $cmd['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Confirmez-vous la bonne réception ?');">
                                <i class="fa-solid fa-box-archive"></i> Confirmer la Réception
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Discussion Directe -->
                    <?php 
                    $contact_chat_id = null;
                    if ($user_role === 'acheteur') $contact_chat_id = $cmd['agriculteur_id'];
                    elseif ($user_role === 'agriculteur') $contact_chat_id = $cmd['acheteur_id'];
                    elseif ($user_role === 'transporteur') $contact_chat_id = $cmd['acheteur_id'];
                    ?>
                    <?php if ($contact_chat_id): ?>
                        <a href="messagerie.php?contact_id=<?php echo $contact_chat_id; ?>&commande_id=<?php echo $cmd['id']; ?>" class="btn btn-outline btn-sm">
                            <i class="fa-solid fa-comments"></i> Discuter
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Formulaire d'Avis (si la commande est livrée) -->
                <?php if ($cmd['statut'] === 'livree' && $user_role === 'acheteur'): ?>
                    <div style="background: #f8fafc; border-radius: 8px; padding: 16px; margin-top: 16px; border: 1px dashed var(--gray-200);">
                        <h4 style="font-size: 0.95rem; margin-bottom: 8px;"><i class="fa-solid fa-star" style="color: var(--accent);"></i> Donner votre avis sur cette livraison</h4>
                        <form action="mes_commandes.php" method="POST" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                            <input type="hidden" name="action_avis" value="1">
                            <input type="hidden" name="commande_id" value="<?php echo $cmd['id']; ?>">
                            <input type="hidden" name="cible_id" value="<?php echo $cmd['agriculteur_id']; ?>">
                            <input type="hidden" name="type_cible" value="agriculteur">
                            <select name="note" class="form-control" style="width: auto;">
                                <option value="5">⭐⭐⭐⭐⭐ 5/5 Excellent</option>
                                <option value="4">⭐⭐⭐⭐ 4/5 Très bon</option>
                                <option value="3">⭐⭐⭐ 3/5 Moyen</option>
                                <option value="2">⭐⭐ 2/2 Médiocre</option>
                                <option value="1">⭐ 1/5 Mauvais</option>
                            </select>
                            <input type="text" name="commentaire" class="form-control" placeholder="Votre commentaire..." style="flex-grow: 1;">
                            <button type="submit" class="btn btn-sm btn-accent">Publier l'Avis</button>
                        </form>
                    </div>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
