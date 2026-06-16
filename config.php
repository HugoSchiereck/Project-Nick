<?php
// config.php
session_start();

$db_host = 'localhost';
$db_name = 'mstlog_portal';
$db_user = 'mstlog_dbuser';
$db_pass = 'a1#u4y38B'; // <-- Plak hier exact je nieuwe wachtwoord

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database verbinding mislukt. Controleer je gegevens in config.php");
}
?>