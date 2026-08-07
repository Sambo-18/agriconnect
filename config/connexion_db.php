<?php
/**
 * Configuration et connexion à la base de données MySQL (bd_agricole)
 * Application AgriConnect
 */

// Paramètres de connexion MySQL (WAMP Server)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bd_agricole');
define('DB_CHARSET', 'utf8mb4');

// Démarrage sécurisé de la session PHP s'il n'est pas encore actif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Si la base de données n'existe pas encore ou que WAMP n'est pas démarré
    die("<div style='font-family: Arial; padding: 20px; background: #fee2e2; color: #991b1b; border-radius: 8px; margin: 40px auto; max-width: 600px; text-align: center;'>
        <h2>Erreur de Connexion à la Base de Données</h2>
        <p>Impossible de se connecter à la base de données <strong>bd_agricole</strong>.</p>
        <p>Assurez-vous que WAMP Server (MySQL) est démarré et que le fichier <code>bd_agricole.sql</code> a été importé dans PHPMyAdmin.</p>
        <small>Message d'erreur PDO : " . htmlspecialchars($e->getMessage()) . "</small>
    </div>");
}
