<?php
/**
 * Gestion du Profil Utilisateur, Évaluations Clients & Certification CNI Temps Réel (Cameroun 🇨🇲)
 * Application AgriConnect
 */

$page_title = "Mon Profil, Évaluations & Certification";
require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

exiger_connexion();

$user_id = $_SESSION['utilisateur_id'];
$user_role = $_SESSION['utilisateur_role'];

// Récupérer le profil actuel
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$profil = $stmt->fetch();

// Détails si transporteur
$transDetails = null;
if ($user_role === 'transporteur') {
    $stmtT = $pdo->prepare("SELECT * FROM transporteurs_details WHERE utilisateur_id = ?");
    $stmtT->execute([$user_id]);
    $transDetails = $stmtT->fetch();
}

// Récupérer la note moyenne et les avis récents
$type_cible_avis = ($user_role === 'transporteur') ? 'transporteur' : 'agriculteur';
$noteInfo = obtenir_note_moyenne_avis($pdo, $user_id, $type_cible_avis);

$stmtAvisList = $pdo->prepare("
    SELECT a.*, u.nom_complet AS auteur_nom
    FROM avis a
    JOIN utilisateurs u ON a.auteur_id = u.id
    WHERE a.cible_id = ? AND a.type_cible = ?
    ORDER BY a.date_avis DESC
");
$stmtAvisList->execute([$user_id, $type_cible_avis]);
$avisRecus = $stmtAvisList->fetchAll();

$erreurs = [];

// TRAITEMENT SOUMISSION DEMANDE DE CERTIFICATION CNI (TEMPS RÉEL OU FICHIER)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_demande_certif'])) {
    $base64_data = $_POST['selfie_cni_base64'] ?? '';
    $nom_cni = null;

    $dossier_cni = __DIR__ . '/assets/uploads/cni/';
    if (!is_dir($dossier_cni)) {
        mkdir($dossier_cni, 0777, true);
    }

    if (!empty($base64_data) && strpos($base64_data, 'data:image') === 0) {
        $data_clean = preg_replace('#^data:image/\w+;base64,#i', '', $base64_data);
        $decoded = base64_decode($data_clean);
        
        if ($decoded !== false) {
            $nom_cni = 'cni_live_' . $user_id . '_' . time() . '.jpg';
            file_put_contents($dossier_cni . $nom_cni, $decoded);
        }
    } 
    elseif (isset($_FILES['photo_cni']) && $_FILES['photo_cni']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['photo_cni']['name'], PATHINFO_EXTENSION));
        $extensions_valides = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $extensions_valides)) {
            $nouveau_nom = 'cni_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo_cni']['tmp_name'], $dossier_cni . $nouveau_nom)) {
                $nom_cni = $nouveau_nom;
            }
        }
    }

    if ($nom_cni) {
        $stmtC = $pdo->prepare("
            UPDATE utilisateurs 
            SET photo_cni = ?, statut_certification = 'en_attente', date_demande_certification = NOW() 
            WHERE id = ?
        ");
        $stmtC->execute([$nom_cni, $user_id]);

        $admins = $pdo->query("SELECT id FROM utilisateurs WHERE role = 'admin'")->fetchAll();
        foreach ($admins as $ad) {
            creer_notification($pdo, $ad['id'], 'Nouvelle capture Selfie CNI en temps réel', $_SESSION['utilisateur_nom'] . ' (' . ucfirst($user_role) . ') a capturé son selfie CNI pour vérification.');
        }

        definir_flash('success', 'Votre selfie CNI capturé en temps réel a été transmis à l\'administrateur pour validation !');
        header('Location: profil.php');
        exit();
    } else {
        $erreurs[] = "Veuillez capturer votre selfie avec la caméra ou choisir un fichier d'image valide.";
    }
}

// TRAITEMENT MISE À JOUR PROFIL GENERAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_maj_profil'])) {
    $nom_complet = securiser($_POST['nom_complet'] ?? '');
    $telephone = securiser($_POST['telephone'] ?? '');
    $adresse = securiser($_POST['adresse'] ?? '');
    $ville = securiser($_POST['ville'] ?? '');
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : $profil['latitude'];
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : $profil['longitude'];

    if (empty($nom_complet)) $erreurs[] = "Le nom complet est obligatoire.";

    if (empty($erreurs)) {
        try {
            $pdo->beginTransaction();

            $stmtUpd = $pdo->prepare("
                UPDATE utilisateurs 
                SET nom_complet = ?, telephone = ?, adresse = ?, ville = ?, latitude = ?, longitude = ?
                WHERE id = ?
            ");
            $stmtUpd->execute([$nom_complet, $telephone, $adresse, $ville, $latitude, $longitude, $user_id]);

            $_SESSION['utilisateur_nom'] = $nom_complet;

            if ($user_role === 'transporteur') {
                $type_vehicule = securiser($_POST['type_vehicule'] ?? '');
                $immatriculation = securiser($_POST['immatriculation'] ?? '');
                $capacite = (float)($_POST['capacite_tonnes'] ?? 1.0);
                $disponible = isset($_POST['disponible']) ? 'oui' : 'non';

                if ($transDetails) {
                    $stmtTransUpd = $pdo->prepare("
                        UPDATE transporteurs_details 
                        SET type_vehicule = ?, immatriculation = ?, capacite_tonnes = ?, disponible = ?
                        WHERE utilisateur_id = ?
                    ");
                    $stmtTransUpd->execute([$type_vehicule, $immatriculation, $capacite, $disponible, $user_id]);
                } else {
                    $stmtTransIns = $pdo->prepare("
                        INSERT INTO transporteurs_details (utilisateur_id, type_vehicule, immatriculation, capacite_tonnes, disponible)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmtTransIns->execute([$user_id, $type_vehicule, $immatriculation, $capacite, $disponible]);
                }
            }

            $pdo->commit();
            definir_flash('success', 'Profil mis à jour avec succès.');
            header('Location: profil.php');
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erreurs[] = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-top: 20px; margin-bottom: 30px;">
    <h2><i class="fa-solid fa-circle-user" style="color: var(--primary);"></i> Mon Profil, Évaluations & Certification CNI</h2>
    <p style="color: var(--gray-500);">Modifiez vos coordonnées, consultez vos notes clients et effectuez la vérification CNI 📸.</p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 30px; margin-bottom: 60px;">
    
    <!-- Formulaire d'Édition du Profil -->
    <div class="form-card" style="margin: 0; max-width: 100%;">

        <?php if (!empty($erreurs)): ?>
            <div class="alert alert-danger">
                <ul style="margin-left: 20px;">
                    <?php foreach ($erreurs as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="profil.php" method="POST">
            <input type="hidden" name="action_maj_profil" value="1">

            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 24px; background: var(--light-bg); padding: 16px; border-radius: var(--radius-md);">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: var(--primary-dark); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 700; flex-shrink: 0;">
                    <?php echo strtoupper(substr($profil['nom_complet'], 0, 1)); ?>
                </div>
                <div>
                    <h3 style="margin: 0; color: var(--dark);">
                        <?php echo htmlspecialchars($profil['nom_complet']); ?>
                        <?php echo afficher_badge_certification($profil['est_certifie'], $profil['statut_certification']); ?>
                    </h3>
                    <div style="margin-top: 4px; margin-bottom: 6px;">
                        <?php echo afficher_etoiles_note($noteInfo['moyenne'], $noteInfo['total']); ?>
                    </div>
                    <span class="status-badge status-acceptee" style="text-transform: uppercase;"><?php echo htmlspecialchars($profil['role']); ?></span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Nom complet / Raison Sociale</label>
                <input type="text" name="nom_complet" class="form-control" value="<?php echo htmlspecialchars($profil['nom_complet']); ?>" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Téléphone (+237)</label>
                    <input type="text" name="telephone" class="form-control" value="<?php echo htmlspecialchars($profil['telephone'] ?: ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Ville / Région</label>
                    <input type="text" name="ville" class="form-control" value="<?php echo htmlspecialchars($profil['ville'] ?: 'Douala'); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Adresse physique complète</label>
                <input type="text" name="adresse" class="form-control" value="<?php echo htmlspecialchars($profil['adresse'] ?: ''); ?>">
            </div>

            <!-- Section Spécifique Transporteur -->
            <?php if ($user_role === 'transporteur'): ?>
                <div style="background: #f0fdf4; padding: 20px; border-radius: 12px; margin-bottom: 24px; border: 1px solid #bbf7d0;">
                    <h4 style="color: var(--primary-dark); margin-bottom: 14px;"><i class="fa-solid fa-truck-fast"></i> Paramètres du Transporteur</h4>
                    
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600; color: var(--dark);">
                            <input type="checkbox" name="disponible" value="1" <?php echo ($transDetails && $transDetails['disponible'] === 'oui') ? 'checked' : ''; ?> style="width: 20px; height: 20px;">
                            <span>Activer ma disponibilité pour les courses de livraison</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Type de véhicule</label>
                        <input type="text" name="type_vehicule" class="form-control" value="<?php echo htmlspecialchars($transDetails['type_vehicule'] ?? ''); ?>">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Plaque d'immatriculation</label>
                            <input type="text" name="immatriculation" class="form-control" value="<?php echo htmlspecialchars($transDetails['immatriculation'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Capacité max (Tonnes)</label>
                            <input type="number" step="0.1" name="capacite_tonnes" class="form-control" value="<?php echo htmlspecialchars($transDetails['capacite_tonnes'] ?? 5.0); ?>">
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                <i class="fa-solid fa-floppy-disk"></i> Enregistrer les Modifications
            </button>
        </form>
    </div>

    <!-- SECTION APPRÉCIATION, AVIS & CERTIFICATION -->
    <div style="display: flex; flex-direction: column; gap: 30px;">
        
        <!-- CARTE SCORE & AVIS CLIENTS -->
        <div style="background: var(--white); padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0; color: var(--dark); font-size: 1.2rem;">
                    <i class="fa-solid fa-star" style="color: var(--accent);"></i> Évaluations & Avis Clients
                </h3>
                <div style="font-size: 1.2rem;">
                    <?php echo afficher_etoiles_note($noteInfo['moyenne'], $noteInfo['total']); ?>
                </div>
            </div>

            <?php if (empty($avisRecus)): ?>
                <p style="color: var(--gray-500); font-size: 0.9rem;">Aucun avis reçu pour l'instant. Vos évaluations de livraison apparaîtront ici.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 14px; max-height: 280px; overflow-y: auto;">
                    <?php foreach ($avisRecus as $av): ?>
                        <div style="background: var(--light-bg); padding: 14px; border-radius: var(--radius-sm); border-left: 4px solid var(--accent);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <strong style="font-size: 0.9rem; color: var(--dark);"><?php echo htmlspecialchars($av['auteur_nom']); ?></strong>
                                <span><?php echo afficher_etoiles_note($av['note']); ?></span>
                            </div>
                            <p style="font-size: 0.88rem; color: var(--gray-700); margin: 0;"><?php echo htmlspecialchars($av['commentaire'] ?: 'Aucun commentaire écrit.'); ?></p>
                            <small style="color: var(--gray-500); font-size: 0.75rem; display: block; margin-top: 4px;"><?php echo formater_date($av['date_avis']); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- SECTION CAPTURE SELFIE CNI EN TEMPS RÉEL (BADGE BLEU) -->
        <div style="background: var(--white); padding: 24px; border-radius: var(--radius-lg); border: 2px solid #3b82f6; box-shadow: var(--shadow-md);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <i class="fa-solid fa-camera-retro" style="font-size: 2rem; color: #3b82f6;"></i>
                <div>
                    <h3 style="margin: 0; color: var(--dark); font-size: 1.3rem;">Certification CNI Temps Réel 📸</h3>
                    <small style="color: var(--gray-500);">Prenez une photo en direct pour obtenir le Badge Bleu 🔵</small>
                </div>
            </div>

            <?php if ($profil['est_certifie'] || $profil['statut_certification'] === 'valide'): ?>
                <div style="background: #eff6ff; border-left: 5px solid #3b82f6; padding: 18px; border-radius: 8px; text-align: center;">
                    <i class="fa-solid fa-shield-halved" style="font-size: 2.5rem; color: #3b82f6; margin-bottom: 10px;"></i>
                    <h4 style="color: #1e40af; margin-bottom: 6px;">Votre compte est Officiellement Certifié 🔵</h4>
                    <p style="font-size: 0.9rem; color: #1e3a8a;">
                        Votre identité CNI en temps réel a été validée par l'administrateur. Votre Badge Bleu est actif sur l'ensemble de la plateforme !
                    </p>
                </div>
            <?php elseif ($profil['statut_certification'] === 'en_attente'): ?>
                <div style="background: #fef3c7; border-left: 5px solid #f59e0b; padding: 18px; border-radius: 8px;">
                    <h4 style="color: #b45309; margin-bottom: 6px;"><i class="fa-solid fa-hourglass-half"></i> Demande en Cours d'Examen</h4>
                    <p style="font-size: 0.9rem; color: #78350f;">
                        Votre capture selfie CNI temps réel a bien été transmise. L'administrateur vérifie actuellement votre photo.
                    </p>
                </div>
            <?php else: ?>
                <p style="font-size: 0.9rem; color: var(--gray-700); margin-bottom: 16px;">
                    Pour garantir l'authenticité de votre profil, **ouvrez votre caméra en direct** et cadrez votre visage avec votre **Carte Nationale d'Identité (CNI)** sous votre menton.
                </p>

                <form action="profil.php" method="POST" enctype="multipart/form-data" id="formCertifLive">
                    <input type="hidden" name="action_demande_certif" value="1">
                    <input type="hidden" name="selfie_cni_base64" id="selfieCniBase64">

                    <!-- MODULE CAMÉRA WEBCAM TEMPS RÉEL -->
                    <div style="background: #0f172a; border-radius: 12px; padding: 16px; text-align: center; color: white; margin-bottom: 20px; position: relative;">
                        
                        <div id="cameraViewContainer" style="position: relative; max-width: 100%; height: 260px; background: #1e293b; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; margin-bottom: 14px;">
                            <video id="webcamVideo" autoplay playsinline style="width: 100%; height: 100%; object-fit: cover; display: none;"></video>
                            <img id="photoCapturedPreview" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                            
                            <div id="cameraPlaceholder" style="padding: 20px; color: #94a3b8;">
                                <i class="fa-solid fa-camera" style="font-size: 3rem; margin-bottom: 10px; display: block; color: #38bdf8;"></i>
                                <span>La caméra est éteinte. Cliquez sur "Activer la Caméra" ci-dessous.</span>
                            </div>
                        </div>

                        <canvas id="photoCanvas" style="display: none;"></canvas>

                        <!-- Controles Caméra -->
                        <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                            <button type="button" id="btnActiverCamera" class="btn btn-sm btn-accent">
                                <i class="fa-solid fa-video"></i> Activer la Caméra 📸
                            </button>
                            <button type="button" id="btnPrendrePhoto" class="btn btn-sm btn-success" style="display: none;">
                                <i class="fa-solid fa-camera"></i> Prendre le Selfie CNI
                            </button>
                            <button type="button" id="btnRecommencer" class="btn btn-sm btn-outline" style="display: none; color: white; border-color: white;">
                                <i class="fa-solid fa-rotate-left"></i> Recommencer
                            </button>
                        </div>
                    </div>

                    <!-- Option alternative Fichier -->
                    <div style="margin-bottom: 20px; border-top: 1px dashed var(--gray-200); padding-top: 14px;">
                        <small style="color: var(--gray-500); display: block; margin-bottom: 6px;">Ou téléversez une photo si la caméra n'est pas disponible :</small>
                        <input type="file" name="photo_cni" class="form-control" accept="image/*">
                    </div>

                    <button type="submit" id="btnSoumettreCertif" class="btn btn-primary btn-lg" style="width: 100%;">
                        <i class="fa-solid fa-paper-plane"></i> Transmettre ma Demande de Certification
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- JAVASCRIPT WEBCAM SELFIE EN TEMPS RÉEL -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const video = document.getElementById('webcamVideo');
    const canvas = document.getElementById('photoCanvas');
    const imgPreview = document.getElementById('photoCapturedPreview');
    const placeholder = document.getElementById('cameraPlaceholder');
    const inputBase64 = document.getElementById('selfieCniBase64');

    const btnStart = document.getElementById('btnActiverCamera');
    const btnSnap = document.getElementById('btnPrendrePhoto');
    const btnRetake = document.getElementById('btnRecommencer');

    let streamObj = null;

    if (btnStart) {
        btnStart.addEventListener('click', function() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert("Votre navigateur ne permet pas l'accès direct à la caméra. Veuillez sélectionner une photo via le bouton de téléversement de fichier ci-dessous.");
                return;
            }

            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } } })
                .then(function(stream) {
                    streamObj = stream;
                    video.srcObject = stream;
                    video.style.display = 'block';
                    placeholder.style.display = 'none';
                    imgPreview.style.display = 'none';

                    btnStart.style.display = 'none';
                    btnSnap.style.display = 'inline-flex';
                    btnRetake.style.display = 'none';
                })
                .catch(function(err) {
                    alert("Impossible d'accéder à la caméra : " + err.message);
                });
        });
    }

    if (btnSnap) {
        btnSnap.addEventListener('click', function() {
            if (!video || !streamObj) return;

            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
            inputBase64.value = dataUrl;

            imgPreview.src = dataUrl;
            imgPreview.style.display = 'block';
            video.style.display = 'none';

            btnSnap.style.display = 'none';
            btnRetake.style.display = 'inline-flex';

            streamObj.getTracks().forEach(track => track.stop());
            streamObj = null;
        });
    }

    if (btnRetake) {
        btnRetake.addEventListener('click', function() {
            inputBase64.value = '';
            imgPreview.style.display = 'none';
            btnStart.click();
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
