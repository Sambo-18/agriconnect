<?php
/**
 * Centre de Notifications
 * Application AgriConnect
 */

$page_title = "Notifications";
require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

exiger_connexion();

$user_id = $_SESSION['utilisateur_id'];

// Marquer tout comme lu
if (isset($_GET['action']) && $_GET['action'] === 'tout_lire') {
    $stmtLu = $pdo->prepare("UPDATE notifications SET lu = 1 WHERE utilisateur_id = ?");
    $stmtLu->execute([$user_id]);
    definir_flash('success', 'Toutes les notifications ont été marquées comme lues.');
    header('Location: notifications.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE utilisateur_id = ? ORDER BY date_creation DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; margin-bottom: 30px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2><i class="fa-solid fa-bell" style="color: var(--primary);"></i> Centre de Notifications</h2>
        <p style="color: var(--gray-500);">Retrouvez l'historique de vos alertes de commandes et de messages.</p>
    </div>
    <?php if (!empty($notifications)): ?>
        <a href="notifications.php?action=tout_lire" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-check-double"></i> Tout marquer comme lu
        </a>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
    <div style="text-align: center; padding: 60px 20px; background: var(--white); border-radius: var(--radius-md); border: 1px dashed var(--gray-500);">
        <i class="fa-solid fa-bell-slash" style="font-size: 3rem; color: var(--gray-500); margin-bottom: 16px;"></i>
        <h3>Aucune notification pour l'instant</h3>
        <p style="color: var(--gray-500);">Vous recevrez des alertes dès qu'une action concerne vos commandes ou livraisons.</p>
    </div>
<?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 60px;">
        <?php foreach ($notifications as $n): ?>
            <div style="background: <?php echo $n['lu'] ? 'var(--white)' : '#ecfdf5'; ?>; padding: 18px 24px; border-radius: var(--radius-md); border-left: 5px solid <?php echo $n['lu'] ? 'var(--gray-200)' : 'var(--primary)'; ?>; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h4 style="margin: 0; color: var(--dark); font-size: 1.05rem;"><?php echo htmlspecialchars($n['titre']); ?></h4>
                    <p style="margin-top: 4px; color: var(--gray-700); font-size: 0.95rem;"><?php echo htmlspecialchars($n['message']); ?></p>
                </div>
                <small style="color: var(--gray-500); font-size: 0.85rem;"><i class="fa-solid fa-clock"></i> <?php echo formater_date($n['date_creation']); ?></small>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
