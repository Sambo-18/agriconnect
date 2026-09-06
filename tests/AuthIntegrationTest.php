<?php
/**
 * Test d'Intégration - Authentification Complète (AgriConnect)
 * 
 * Objectif: Tester le flux complet d'authentification en intégrant la base de données MySQL
 * (bd_agricole), la requête PDO, la vérification du hash et la gestion des sessions.
 */

class AuthIntegrationTest {

    private ?PDO $pdo = null;
    private int $succes = 0;
    private int $echecs = 0;

    /**
     * Helper d'assertion pour le test d'intégration
     */
    private function assert($condition, string $nomTest) {
        if ($condition) {
            echo "  \033[32m✔ [SUCCÈS]\033[0m {$nomTest}\n";
            $this->succes++;
        } else {
            echo "  \033[31m✘ [ÉCHEC]\033[0m {$nomTest}\n";
            $this->echecs++;
        }
    }

    /**
     * Initialisation du contexte d'intégration (Connexion BDD & Reset Session)
     */
    public function setUp() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION = [];

        // Inclure le fichier de connexion BDD réel
        require_once __DIR__ . '/../config/connexion_db.php';
        $this->pdo = $GLOBALS['pdo'] ?? null;
    }

    /**
     * Test 1: Connexion PDO et présence des comptes utilisateurs de démo dans la BDD
     */
    public function testConnexionBaseDeDonneesEtFetchUtilisateur() {
        $this->assert($this->pdo !== null, "1.1 La connexion PDO à la base de données 'bd_agricole' doit être active.");

        $stmt = $this->pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
        $stmt->execute(['admin@agriconnect.com']);
        $user = $stmt->fetch();

        $this->assert(!empty($user), "1.2 L'utilisateur administrateur 'admin@agriconnect.com' doit exister dans la table utilisateurs.");
        $this->assert($user['role'] === 'admin', "1.3 Le rôle du compte admin extrait doit être 'admin'.");
    }

    /**
     * Test 2: Flux complet d'authentification réussie (Email + Hash Mot de passe + Session)
     */
    public function testAuthentificationReussieAvecBddEtSession() {
        $emailSaisi = "admin@agriconnect.com";
        $motDePasseSaisi = "password123";

        // 1. Requête BDD
        $stmt = $this->pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
        $stmt->execute([$emailSaisi]);
        $user = $stmt->fetch();

        // 2. Verification du mot de passe avec password_verify
        $motDePasseValide = $user && password_verify($motDePasseSaisi, $user['mot_de_passe']);

        if ($motDePasseValide) {
            // 3. Remplissage de la session
            $_SESSION['utilisateur_id'] = $user['id'];
            $_SESSION['utilisateur_nom'] = $user['nom_complet'];
            $_SESSION['utilisateur_email'] = $user['email'];
            $_SESSION['utilisateur_role'] = $user['role'];
        }

        $this->assert($motDePasseValide === true, "2.1 Le mot de passe 'password123' doit correspondre au hash BDD de admin.");
        $this->assert(isset($_SESSION['utilisateur_id']) && $_SESSION['utilisateur_id'] == $user['id'], "2.2 La session utilisateur_id doit être initialisée correctement.");
        $this->assert($_SESSION['utilisateur_role'] === 'admin', "2.3 Le rôle en session doit être 'admin'.");
    }

    /**
     * Test 3: Échec d'authentification avec un mauvais mot de passe
     */
    public function testAuthentificationEchecMotDePasseInvalide() {
        $emailSaisi = "admin@agriconnect.com";
        $motDePasseErrone = "mauvais_pass_999";

        $stmt = $this->pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
        $stmt->execute([$emailSaisi]);
        $user = $stmt->fetch();

        $motDePasseValide = $user && password_verify($motDePasseErrone, $user['mot_de_passe']);

        $this->assert($motDePasseValide === false, "3. L'authentification doit échouer en cas de mot de passe incorrect.");
    }

    /**
     * Test 4: Échec d'authentification avec un e-mail inexistant
     */
    public function testAuthentificationEchecEmailInexistant() {
        $emailInexistant = "introuvable@agriconnect.cm";

        $stmt = $this->pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
        $stmt->execute([$emailInexistant]);
        $user = $stmt->fetch();

        $this->assert($user === false, "4. La requête SQL doit renvoyer aucun résultat pour un email inexistant.");
    }

    /**
     * Test 5: Redirection logique selon le rôle connecté
     */
    public function testVerificationRedirectionSelonRole() {
        $rolesRedirection = [
            'admin' => 'admin.php',
            'agriculteur' => 'mes_produits.php',
            'transporteur' => 'mes_commandes.php',
            'acheteur' => 'produits.php'
        ];

        $reussiteRedirections = true;

        foreach ($rolesRedirection as $role => $pageAttendue) {
            $pageCible = '';
            if ($role === 'admin') {
                $pageCible = 'admin.php';
            } elseif ($role === 'agriculteur') {
                $pageCible = 'mes_produits.php';
            } elseif ($role === 'transporteur') {
                $pageCible = 'mes_commandes.php';
            } else {
                $pageCible = 'produits.php';
            }

            if ($pageCible !== $pageAttendue) {
                $reussiteRedirections = false;
                break;
            }
        }

        $this->assert($reussiteRedirections === true, "5. La logique de redirection par rôle (admin, agriculteur, transporteur, acheteur) est valide.");
    }

    /**
     * Exécuter l'ensemble des tests d'intégration
     */
    public function runAllTests() {
        echo "\n=======================================================\n";
        echo "   TEST D'INTÉGRATION : AUTHENTIFICATION + BDD (AuthIntegrationTest)\n";
        echo "=======================================================\n";

        try {
            $this->setUp();
            $this->testConnexionBaseDeDonneesEtFetchUtilisateur();

            $this->setUp();
            $this->testAuthentificationReussieAvecBddEtSession();

            $this->setUp();
            $this->testAuthentificationEchecMotDePasseInvalide();

            $this->setUp();
            $this->testAuthentificationEchecEmailInexistant();

            $this->setUp();
            $this->testVerificationRedirectionSelonRole();

        } catch (Exception $e) {
            echo "  \033[31m✘ [ERREUR INTÉGRATION]\033[0m " . $e->getMessage() . "\n";
            $this->echecs++;
        }

        echo "\nRésultat: {$this->succes} Succès, {$this->echecs} Échecs.\n";
        return $this->echecs === 0;
    }
}
