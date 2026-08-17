<?php
session_start();
header('Content-Type: application/json');

$_SESSION['last_activity'] = time();

echo json_encode([
    'success' => true,
    'timestamp' => $_SESSION['last_activity']
]);
?>
