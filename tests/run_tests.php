<?php
/**
 * Test Runner - Exécution Globale des Tests AgriConnect
 * 
 * Lancement: php tests/run_tests.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/connexion_db.php';
require_once __DIR__ . '/../includes/fonctions.php';
require_once __DIR__ . '/AuthUnitTest.php';
require_once __DIR__ . '/AuthIntegrationTest.php';

echo "\n🚀 DEMARRAGE DES TESTS D'AUTHENTIFICATION AGRICONNECT 🇨🇲\n";
echo "------------------------------------------------------------------\n";

$unitTester = new AuthUnitTest();
$unitOk = $unitTester->runAllTests();

$integrationTester = new AuthIntegrationTest();
$integrationOk = $integrationTester->runAllTests();

echo "\n------------------------------------------------------------------\n";
if ($unitOk && $integrationOk) {
    echo "🎉 \033[32mTOUS LES TESTS (UNITAIRES ET INTÉGRATION) ONT RÉUSSI !\033[0m\n\n";
    exit(0);
} else {
    echo "⚠️ \033[31mCERTAINS TESTS ONT ÉCHOUÉ. VEUILLEZ VÉRIFIER LES LOGS CI-DESSUS.\033[0m\n\n";
    exit(1);
}

