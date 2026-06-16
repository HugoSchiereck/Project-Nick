<?php
// config.php
session_start(); // Start de veilige sessie voor ingelogde gebruikers

// Vul hieronder jouw Plesk database-gegevens in
$db_host = 'localhost'; // Op Plesk blijft dit localhost
$db_name = 'mstlog_portal'; // Check of dit klopt
$db_user = 'vul_hier_je_database_gebruiker_in';
$db_pass = 'vul_hier_het_wachtwoord_in';

try {
    // We gebruiken de moderne PDO methode voor PHP 8.3
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    
    // Zorg voor duidelijke foutmeldingen als er iets misgaat
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database verbinding mislukt. Controleer je gegevens in config.php");
}
?>