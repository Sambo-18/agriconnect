<?php
/**
 * API REST Endpoint : Envoi de message dans le chat
 * Application AgriConnect
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/connexion_db.php';
require_once __DIR__ . '/../includes/fonctions.php';

if (!est_connecte()) {
    echo json_encode(['success' => false, 'message' => 'Veuillez vous connecter.']);
    exit();
}

$expediteur_id = $_SESSION['utilisateur_id'];
$destinataire_id = isset($_POST['destinataire_id']) ? (int)$_POST['destinataire_id'] : 0;
$commande_id = isset($_POST['commande_id']) && $_POST['commande_id'] !== '' ? (int)$_POST['commande_id'] : null;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($destinataire_id <= 0 || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Destinataire ou message invalide.']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO messages (expediteur_id, destinataire_id, commande_id, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$expediteur_id, $destinataire_id, $commande_id, $message]);

    // Notifier le destinataire
    creer_notification($pdo, $destinataire_id, 'Nouveau message reçu', $_SESSION['utilisateur_nom'] . ' vous a envoyé un message.');

    echo json_encode(['success' => true, 'message' => 'Message envoyé avec succès.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi du message.']);
}
