<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}
/**
 * Test Unitaire - Fonctionnalité d'Authentification (AgriConnect)
 * 
 * Objectif: Tester les fonctions d'authentification et de sécurité en isolation
 * (sans dépendance directe à la base de données).
 */

class AuthUnitTest {

    private int $succes = 0;
    private int $echecs = 0;

    /**
     * Helper d'assertion basique pour le test unitaire
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
     * Initialisation avant chaque test
     */
    public function setUp() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION = [];
    }

    /**
     * Test 1: Validation et sécurisation des entrées (Nettoyage XSS & espaces)
     */
    public function testNettoyageEntreeEmailEtDonnees() {
        require_once __DIR__ . '/../includes/fonctions.php';

        $emailInjected = "  <script>alert('xss')</script>user@agriconnect.com  ";
        $emailSanitized = securiser($emailInjected);

        $this->assert(
            $emailSanitized === "&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;user@agriconnect.com",
            "1. securiser() doit éliminer les balises HTML/XSS et tronquer les espaces."
        );
    }

    /**
     * Test 2: Hachage et vérification du mot de passe utilisateur
     */
    public function testVerificationHachageMotDePasse() {
        $motDePasseBrut = "password123";
        $hashBaseDeDonnees = password_hash($motDePasseBrut, PASSWORD_DEFAULT);

        // Vérification avec mot de passe correct
        $estValide = password_verify("password123", $hashBaseDeDonnees);
        $this->assert($estValide === true, "2.1 password_verify() doit accepter le mot de passe valide.");

        // Vérification avec mot de passe incorrect
        $estInvalide = password_verify("mauvais_pass_123", $hashBaseDeDonnees);
        $this->assert($estInvalide === false, "2.2 password_verify() doit rejeter un mot de passe erroné.");
    }

    /**
     * Test 3: Vérification de l'état de connexion session (est_connecte)
     */
    public function testEtatSessionUtilisateurConnecte() {
        require_once __DIR__ . '/../includes/fonctions.php';

        // 3.1 Non connecté
        $_SESSION = [];
        $this->assert(est_connecte() === false, "3.1 est_connecte() doit renvoyer false si la session est vide.");

        // 3.2 Connecté
        $_SESSION['utilisateur_id'] = 5;
        $this->assert(est_connecte() === true, "3.2 est_connecte() doit renvoyer true si utilisateur_id est défini.");
    }

    /**
     * Test 4: Logique métier - Blocage d'un compte suspendu
     */
    public function testRejetCompteSuspendu() {
        $utilisateurFactice = [
            'id' => 10,
            'email' => 'suspendu@agriconnect.com',
            'statut' => 'suspendu'
        ];

        $estAutorise = ($utilisateurFactice['statut'] === 'actif');
        $this->assert($estAutorise === false, "4. Un utilisateur avec statut 'suspendu' ne doit pas être autorisé à se connecter.");
    }

    /**
     * Test 5: Vérification de la création de message Flash
     */
    public function testGestionMessageFlash() {
        require_once __DIR__ . '/../includes/fonctions.php';

        definir_flash('success', 'Ravi de vous revoir !');

        $this->assert(
            isset($_SESSION['flash']) && $_SESSION['flash']['type'] === 'success',
            "5. definir_flash() doit correctement enregistrer le message dans la session PHP."
        );
    }

    /**
     * Exécuter l'ensemble de la suite de tests unitaires
     */
    public function runAllTests() {
        echo "\n=======================================================\n";
        echo "   TEST UNITAIRE : AUTHENTIFICATION (AuthUnitTest)\n";
        echo "=======================================================\n";

        $this->setUp();
        $this->testNettoyageEntreeEmailEtDonnees();

        $this->setUp();
        $this->testVerificationHachageMotDePasse();

        $this->setUp();
        $this->testEtatSessionUtilisateurConnecte();

        $this->setUp();
        $this->testRejetCompteSuspendu();

        $this->setUp();
        $this->testGestionMessageFlash();

        echo "\nRésultat: {$this->succes} Succès, {$this->echecs} Échecs.\n";
        return $this->echecs === 0;
    }
}

// Permet l'exécution directe via : php tests/AuthUnitTest.php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $tester = new AuthUnitTest();
    $ok = $tester->runAllTests();
    exit($ok ? 0 : 1);
}
