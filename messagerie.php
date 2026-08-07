<?php
/**
 * System de Messagerie Directe entre Utilisateurs
 * Application AgriConnect
 */

$page_title = "Messagerie & Conversations";
require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

exiger_connexion();

$user_id = $_SESSION['utilisateur_id'];
$contact_id = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : 0;
$commande_id = isset($_GET['commande_id']) ? (int)$_GET['commande_id'] : null;

// Obtenir la liste de toutes les personnes avec qui l'utilisateur a déjà échangé ou commandé
$stmtContacts = $pdo->prepare("
    SELECT DISTINCT u.id, u.nom_complet, u.role, u.photo, u.telephone
    FROM utilisateurs u
    WHERE u.id IN (
        SELECT expediteur_id FROM messages WHERE destinataire_id = ?
        UNION
        SELECT destinataire_id FROM messages WHERE expediteur_id = ?
        UNION
        SELECT agriculteur_id FROM commandes WHERE acheteur_id = ? OR transporteur_id = ?
        UNION
        SELECT acheteur_id FROM commandes WHERE agriculteur_id = ? OR transporteur_id = ?
        UNION
        SELECT transporteur_id FROM commandes WHERE agriculteur_id = ? OR acheteur_id = ?
    ) AND u.id != ?
");
$stmtContacts->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
$contacts = $stmtContacts->fetchAll();

// Si aucun contact sélectionné mais que la liste n'est pas vide
if ($contact_id <= 0 && !empty($contacts)) {
    $contact_id = $contacts[0]['id'];
}

// Récupérer les infos du contact actif
$contact_actif = null;
if ($contact_id > 0) {
    $stmtC = $pdo->prepare("SELECT id, nom_complet, role, telephone, email FROM utilisateurs WHERE id = ?");
    $stmtC->execute([$contact_id]);
    $contact_actif = $stmtC->fetch();
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-top: 30px; margin-bottom: 30px;">
    <h2><i class="fa-solid fa-comments" style="color: var(--primary);"></i> Messagerie Directe</h2>
    <p style="color: var(--gray-500);">Échangez en toute sécurité avec vos acheteurs, producteurs et livreurs.</p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 60px;">
    
    <!-- Liste des contacts -->
    <div style="background: var(--white); border-radius: var(--radius-md); border: 1px solid var(--gray-200); padding: 16px; height: 550px; overflow-y: auto;">
        <h4 style="margin-bottom: 16px; color: var(--dark); border-bottom: 1px solid var(--gray-100); padding-bottom: 10px;">
            <i class="fa-solid fa-address-book"></i> Conversations
        </h4>

        <?php if (empty($contacts)): ?>
            <p style="color: var(--gray-500); font-size: 0.9rem; text-align: center; margin-top: 40px;">Aucun contact disponible pour l'instant.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($contacts as $c): ?>
                    <a href="messagerie.php?contact_id=<?php echo $c['id']; ?>" 
                       style="display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: var(--radius-sm); border: 1px solid <?php echo ($contact_id == $c['id']) ? 'var(--primary)' : 'transparent'; ?>; background: <?php echo ($contact_id == $c['id']) ? 'var(--primary-light)' : 'var(--light-bg)'; ?>;">
                        <div style="width: 42px; height: 42px; border-radius: 50%; background: var(--primary-dark); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                            <?php echo strtoupper(substr($c['nom_complet'], 0, 1)); ?>
                        </div>
                        <div style="overflow: hidden;">
                            <strong style="display: block; color: var(--dark); font-size: 0.95rem; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
                                <?php echo htmlspecialchars($c['nom_complet']); ?>
                            </strong>
                            <small style="color: var(--gray-500); text-transform: capitalize;"><?php echo htmlspecialchars($c['role']); ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Boîte de Chat principale -->
    <div style="grid-column: span 2;">
        <?php if (!$contact_actif): ?>
            <div style="text-align: center; padding: 100px 20px; background: var(--white); border-radius: var(--radius-md); border: 1px dashed var(--gray-500);">
                <i class="fa-solid fa-comment-slash" style="font-size: 3rem; color: var(--gray-500); margin-bottom: 16px;"></i>
                <h3>Sélectionnez une conversation</h3>
                <p style="color: var(--gray-500);">Choisissez un interlocuteur dans la liste de gauche pour démarrer le dialogue.</p>
            </div>
        <?php else: ?>
            <div class="chat-container">
                <div class="chat-header">
                    <div>
                        <strong style="font-size: 1.1rem;"><?php echo htmlspecialchars($contact_actif['nom_complet']); ?></strong>
                        <span style="font-size: 0.8rem; opacity: 0.8; margin-left: 8px;">(<?php echo ucfirst($contact_actif['role']); ?> - Tél: <?php echo htmlspecialchars($contact_actif['telephone']); ?>)</span>
                    </div>
                    <?php if ($commande_id): ?>
                        <span class="status-badge status-acceptee">Commande #<?php echo $commande_id; ?></span>
                    <?php endif; ?>
                </div>

                <div class="chat-messages" id="chatMessagesBox">
                    <!-- Charger par JavaScript -->
                    <div style="text-align: center; color: var(--gray-500); margin-top: 40px;">Chargement de la conversation...</div>
                </div>

                <form id="formSendMessage" class="chat-footer">
                    <input type="hidden" id="chatContactId" value="<?php echo $contact_actif['id']; ?>">
                    <input type="hidden" id="chatCommandeId" value="<?php echo $commande_id ?: ''; ?>">
                    <input type="text" id="chatInputMessage" class="form-control" placeholder="Écrivez votre message ici..." required autocomplete="off">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const contactId = document.getElementById('chatContactId')?.value;
    const msgBox = document.getElementById('chatMessagesBox');
    const formSend = document.getElementById('formSendMessage');
    const inputMsg = document.getElementById('chatInputMessage');

    if (!contactId || !msgBox) return;

    function chargerMessages() {
        fetch(`api/get_messages.php?contact_id=${contactId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.messages.length === 0) {
                        msgBox.innerHTML = '<div style="text-align: center; color: var(--gray-500); margin-top: 40px;">Aucun message échangé pour le moment. Écrivez le premier message !</div>';
                        return;
                    }

                    let html = '';
                    data.messages.forEach(m => {
                        const classeBubble = m.est_moi ? 'sent' : 'received';
                        html += `
                            <div class="message-bubble ${classeBubble}">
                                <div>${m.message}</div>
                                <div class="message-time">${m.date_envoi}</div>
                            </div>
                        `;
                    });

                    // Ne faire défiler vers le bas que si de nouveaux messages sont ajoutés
                    const scrolledBottom = (msgBox.scrollHeight - msgBox.clientHeight <= msgBox.scrollTop + 50);
                    msgBox.innerHTML = html;
                    if (scrolledBottom || msgBox.dataset.loaded !== '1') {
                        msgBox.scrollTop = msgBox.scrollHeight;
                        msgBox.dataset.loaded = '1';
                    }
                }
            })
            .catch(err => console.error('Erreur chat:', err));
    }

    if (formSend) {
        formSend.addEventListener('submit', function(e) {
            e.preventDefault();
            const msg = inputMsg.value.trim();
            const commandeId = document.getElementById('chatCommandeId').value;

            if (!msg) return;

            fetch('api/envoyer_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `destinataire_id=${contactId}&commande_id=${commandeId}&message=${encodeURIComponent(msg)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    inputMsg.value = '';
                    chargerMessages();
                } else {
                    alert(data.message);
                }
            });
        });
    }

    chargerMessages();
    setInterval(chargerMessages, 3000);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
