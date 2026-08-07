<?php
/**
 * API REST Endpoint : Récupération des messages entre 2 utilisateurs
 * Application AgriConnect
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/connexion_db.php';
require_once __DIR__ . '/../includes/fonctions.php';

if (!est_connecte()) {
    echo json_encode(['success' => false, 'messages' => []]);
    exit();
}

$user1 = $_SESSION['utilisateur_id'];
$user2 = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : 0;

if ($user2 <= 0) {
    echo json_encode(['success' => false, 'messages' => []]);
    exit();
}

try {
    // Marquer les messages reçus comme lus
    $stmtLu = $pdo->prepare("UPDATE messages SET lu = 1 WHERE expediteur_id = ? AND destinataire_id = ?");
    $stmtLu->execute([$user2, $user1]);

    // Récupérer la conversation
    $stmt = $pdo->prepare("
        SELECT m.*, u.nom_complet AS expediteur_nom
        FROM messages m
        JOIN utilisateurs u ON m.expediteur_id = u.id
        WHERE (m.expediteur_id = ? AND m.destinataire_id = ?)
           OR (m.expediteur_id = ? AND m.destinataire_id = ?)
        ORDER BY m.date_envoi ASC
    ");
    $stmt->execute([$user1, $user2, $user2, $user1]);
    $list = $stmt->fetchAll();

    $messages = [];
    foreach ($list as $msg) {
        $messages[] = [
            'id' => $msg['id'],
            'expediteur_id' => $msg['expediteur_id'],
            'est_moi' => ($msg['expediteur_id'] == $user1),
            'expediteur_nom' => $msg['expediteur_nom'],
            'message' => htmlspecialchars($msg['message']),
            'date_envoi' => formater_date($msg['date_envoi'])
        ];
    }

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'messages' => [], 'error' => $e->getMessage()]);
}
