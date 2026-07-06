<?php
require_once __DIR__ . '/../app/bootstrap.php';
header('Content-Type: application/json');

$response = [];

// Query users table
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = 'muhdmukhtar2019@gmail.com' LIMIT 1");
$stmt->execute();
$response['live_user'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

// Query roles table
$stmt = $pdo->query("SELECT * FROM roles");
$response['live_roles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($response, JSON_PRETTY_PRINT);
