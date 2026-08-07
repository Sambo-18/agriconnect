<?php
/**
 * Script de Déconnexion
 * Application AgriConnect
 */
require_once __DIR__ . '/config/connexion_db.php';
require_once __DIR__ . '/includes/fonctions.php';

$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

session_start();
definir_flash('info', 'Vous vous êtes déconnecté avec succès.');
header('Location: index.php');
exit();
