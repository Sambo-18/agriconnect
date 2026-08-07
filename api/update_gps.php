<?php
/**
 * API REST Endpoint : Mise à jour des coordonnées GPS du transporteur
 * Application AgriConnect
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/connexion_db.php';
require_once __DIR__ . '/../includes/fonctions.php';

if (!est_connecte() || $_SESSION['utilisateur_role'] !== 'transporteur') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$transporteur_id = $_SESSION['utilisateur_id'];
$commande_id = isset($_POST['commande_id']) ? (int)$_POST['commande_id'] : 0;
$latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
$longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;

if (!$latitude || !$longitude) {
    echo json_encode(['success' => false, 'message' => 'Coordonnées GPS invalides']);
    exit();
}

try {
    // 1. Mettre à jour la table transporteurs_details
    $stmt = $pdo->prepare("UPDATE transporteurs_details 
                           SET latitude_actuelle = ?, longitude_actuelle = ?, derniere_maj_gps = NOW() 
                           WHERE utilisateur_id = ?");
    $stmt->execute([$latitude, $longitude, $transporteur_id]);

    // 2. Si une commande est spécifiée, enregistrer dans l'historique GPS de la livraison
    if ($commande_id > 0) {
        $stmtHist = $pdo->prepare("INSERT INTO gps_historique (commande_id, transporteur_id, latitude, longitude) VALUES (?, ?, ?, ?)");
        $stmtHist->execute([$commande_id, $transporteur_id, $latitude, $longitude]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Position GPS mise à jour avec succès',
        'latitude' => $latitude,
        'longitude' => $longitude,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur BDD: ' . $e->getMessage()]);
}
