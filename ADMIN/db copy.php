<?php
$host = '127.0.0.1';
$db   = 'jostum_pg'; 
$user = 'root'; 
$pass = '99766'; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // We exit without echoing to prevent breaking the JSON response
    exit;
}       
?>