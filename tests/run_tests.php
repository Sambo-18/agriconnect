<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/connexion_db.php';
require_once __DIR__ . '/../includes/fonctions.php';
require_once __DIR__ . '/AuthUnitTest.php';
require_once __DIR__ . '/AuthIntegrationTest.php';

$mode = strtolower($argv[1] ?? 'all');

echo "\n🚀 DEMARRAGE DES TESTS AGRICONNECT 🇨🇲 (Mode: " . strtoupper($mode) . ")\n";
echo "------------------------------------------------------------------\n";

$unitOk = true;
$integrationOk = true;

if ($mode === 'unit' || $mode === 'all') {
    $unitTester = new AuthUnitTest();
    $unitOk = $unitTester->runAllTests();
}

if ($mode === 'integration' || $mode === 'all') {
    $integrationTester = new AuthIntegrationTest();
    $integrationOk = $integrationTester->runAllTests();
}

echo "\n------------------------------------------------------------------\n";
if ($unitOk && $integrationOk) {
    echo "🎉 \033[32mTESTS TERMINÉS AVEC SUCCÈS !\033[0m\n\n";
    exit(0);
} else {
    echo "⚠️ \033[31mÉCHEC DE CERTAINS TESTS. VEUILLEZ VÉRIFIER LES ERREURS.\033[0m\n\n";
    exit(1);
}

