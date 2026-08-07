<?php
/**
 * Fonctions utilitaires, sécurité, calculs GPS, paiements MoMo, certification CNI & Évaluations / Notes
 * Application AgriConnect Cameroun 🇨🇲
 */

require_once __DIR__ . '/../config/connexion_db.php';

/**
 * Nettoie une chaîne contre les attaques XSS
 */
function securiser($donnee) {
    if (is_array($donnee)) {
        return array_map('securiser', $donnee);
    }
    return htmlspecialchars(trim((string)$donnee), ENT_QUOTES, 'UTF-8');
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function est_connecte() {
    return isset($_SESSION['utilisateur_id']) && !empty($_SESSION['utilisateur_id']);
}

/**
 * Redirige l'utilisateur s'il n'est pas connecté
 */
function exiger_connexion() {
    if (!est_connecte()) {
        definir_flash('danger', 'Veuillez vous connecter pour accéder à cette page.');
        header('Location: connexion.php');
        exit();
    }
}

/**
 * Exige un rôle spécifique pour accéder à la page
 */
function exiger_role($roles_autorises) {
    exiger_connexion();
    if (!is_array($roles_autorises)) {
        $roles_autorises = [$roles_autorises];
    }
    if (!in_array($_SESSION['utilisateur_role'], $roles_autorises)) {
        definir_flash('warning', 'Accès non autorisé pour votre profil.');
        header('Location: index.php');
        exit();
    }
}

/**
 * Définit un message flash pour la session actuelle
 */
function definir_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Affiche et réinitialise le message flash
 */
function afficher_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . ' alert-dismissible fade show" role="alert">
                <span>' . htmlspecialchars($flash['message']) . '</span>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()">&times;</button>
              </div>';
    }
}

/**
 * Crée une notification en base pour un utilisateur
 */
function creer_notification($pdo, $utilisateur_id, $titre, $message) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (utilisateur_id, titre, message) VALUES (?, ?, ?)");
        $stmt->execute([$utilisateur_id, $titre, $message]);
    } catch (PDOException $e) {
        // Ignorer
    }
}

/**
 * Affiche le Badge Bleu de Certification CNI si le compte est validé
 */
function afficher_badge_certification($est_certifie, $statut_certif = '') {
    if ($est_certifie || $statut_certif === 'valide') {
        return '<span class="badge-certifie" title="Compte Vérifié CNI Cameroun"><i class="fa-solid fa-circle-check" style="color: #3b82f6;"></i> <small style="color: #1d4ed8; font-weight: 700;">Certifié 🇨🇲</small></span>';
    }
    return '';
}

/**
 * Calcule la note moyenne et le nombre d'avis d'un agriculteur ou d'un transporteur
 */
function obtenir_note_moyenne_avis($pdo, $cible_id, $type_cible = 'transporteur') {
    $stmt = $pdo->prepare("SELECT AVG(note) AS moyenne, COUNT(*) AS total FROM avis WHERE cible_id = ? AND type_cible = ?");
    $stmt->execute([$cible_id, $type_cible]);
    $res = $stmt->fetch();
    $moyenne = $res['moyenne'] ? round((float)$res['moyenne'], 1) : 5.0;
    $total = (int)($res['total'] ?? 0);
    return ['moyenne' => $moyenne, 'total' => $total];
}

/**
 * Affiche le rendu HTML des étoiles dorées et de la note
 */
function afficher_etoiles_note($note_moyenne, $nb_avis = 0) {
    $html = '<span style="color: #f59e0b; font-weight: 700; white-space: nowrap;">';
    $plein = floor($note_moyenne);
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $plein) {
            $html .= '<i class="fa-solid fa-star"></i>';
        } else {
            $html .= '<i class="fa-regular fa-star" style="color: #cbd5e1;"></i>';
        }
    }
    $html .= ' ' . number_format($note_moyenne, 1) . '/5</span>';
    if ($nb_avis > 0) {
        $html .= ' <small style="color: var(--gray-500);">(' . $nb_avis . ' avis)</small>';
    }
    return $html;
}

/**
 * Calcule la distance orthodromique (Haversine) entre 2 coordonnées GPS en Kilomètres
 */
function calculer_distance_gps($lat1, $lon1, $lat2, $lon2) {
    if ($lat1 == null || $lon1 == null || $lat2 == null || $lon2 == null) {
        return 10.0;
    }
    $earth_radius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earth_radius * $c, 1);
}

/**
 * Calcule des frais de transport ultra-réalistes au Cameroun :
 * - Course locale / Même zone (< 30 km) : Tarif forfaitaire de 1 500 FCFA
 * - Expédition inter-villes (ex: Bafoussam -> Douala = 215 km) :
 *   • Pour les petits colis/commandes (<= 20 kg) : Tarif colis fixe plafonné entre 2 000 FCFA et 2 500 FCFA max !
 *   • Pour les charges lourdes (> 20 kg) : 2 000 FCFA de base + léger supplément selon la distance et le tonnage
 */
function estimer_frais_transport($distance_km, $poids_kg = 1) {
    // 1. Livraison locale (Même zone < 30 km)
    if ($distance_km <= 30) {
        $frais = 1500; // Forfait local 1 500 FCFA
    } else {
        // 2. Transport inter-villes longue distance (ex: 215 km)
        if ($poids_kg <= 20) {
            // Pour les petits achats/colis (<= 20 kg), tarif colis très abordable plafonné à 2 500 FCFA max
            $frais = 2000 + min(500, round(($distance_km - 30) * 3));
        } else {
            // Pour les grosses commandes (> 20 kg)
            $tarif_de_base = 2000;
            $supp_dist = min(2000, round(($distance_km - 30) * 5)); // Plafonné pour la distance
            $supp_poids = floor(($poids_kg - 20) / 50) * 500; // 500 FCFA par 50 kg supplémentaires
            $frais = $tarif_de_base + $supp_dist + $supp_poids;
        }
    }
    
    return round($frais, -2); // Arrondi propre au centaine de FCFA
}

/**
 * Formate un montant financier
 */
function formater_prix($montant) {
    return number_format((float)$montant, 0, ',', ' ') . ' FCFA';
}

/**
 * Formate une date en français
 */
function formater_date($date_str) {
    if (!$date_str) return '-';
    $time = strtotime($date_str);
    return date('d/m/Y à H:i', $time);
}

/**
 * Compte les messages non lus
 */
function compter_messages_non_lus($pdo, $utilisateur_id) {
    if (!$utilisateur_id) return 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
    $stmt->execute([$utilisateur_id]);
    return (int)$stmt->fetchColumn();
}

/**
 * Compte les notifications non lues
 */
function compter_notifications_non_lues($pdo, $utilisateur_id) {
    if (!$utilisateur_id) return 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE utilisateur_id = ? AND lu = 0");
    $stmt->execute([$utilisateur_id]);
    return (int)$stmt->fetchColumn();
}
