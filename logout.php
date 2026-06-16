<?php
// logout.php
session_start();
session_unset(); // Verwijder alle variabelen
session_destroy(); // Vernietig de sessie
header("Location: index.php"); // Stuur terug naar het inlogscherm
exit;
?>