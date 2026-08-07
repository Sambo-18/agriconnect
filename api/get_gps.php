<?php
/**
 * API REST Endpoint : Récupération de la dernière position GPS du transporteur d'une commande
 * Application AgriConnect
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/connexion_db.php';
require_once __DIR__ . '/../includes/fonctions.php';

$commande_id = isset($_GET['commande_id']) ? (int)$_GET['commande_id'] : 0;

if ($commande_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Identifiant de commande manquant']);
    exit();
}

try {
    // Récupérer la commande et les détails GPS du transporteur associé
    $stmt = $pdo->prepare("
        SELECT c.id AS commande_id, c.statut, 
               t.id AS transporteur_id, u.nom_complet AS transporteur_nom, u.telephone,
               td.latitude_actuelle, td.longitude_actuelle, td.derniere_maj_gps
        FROM commandes c
        JOIN utilisateurs u ON c.transporteur_id = u.id
        JOIN transporteurs_details td ON u.id = td.utilisateur_id
        WHERE c.id = ?
    ");
    $stmt->execute([$commande_id]);
    $data = $stmt->fetch();

    if ($data) {
        echo json_encode([
            'success' => true,
            'commande_id' => $data['commande_id'],
            'statut' => $data['statut'],
            'transporteur_nom' => $data['transporteur_nom'],
            'telephone' => $data['telephone'],
            'latitude' => $data['latitude_actuelle'],
            'longitude' => $data['longitude_actuelle'],
            'derniere_maj' => $data['derniere_maj_gps'] ? formater_date($data['derniere_maj_gps']) : 'N/A'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aucun transporteur en livraison sur cette commande']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur SQL: ' . $e->getMessage()]);
}
