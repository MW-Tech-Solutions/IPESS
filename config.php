<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    $_SESSION['student_id'] = 1; 
}

$host = 'localhost';
$db   = 'pg';
$user = 'root';
$pass = '997667'; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed. Please check config.");
}


function getStudentDetails($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getAlerts($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM alerts WHERE student_id = ? ORDER BY created_at DESC");
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

function getAcademics($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM academic_records WHERE student_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

function getResearch($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM research_progress WHERE student_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getFinance($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM finances WHERE student_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getResources($pdo) {
    $stmt = $pdo->query("SELECT * FROM resources LIMIT 5");
    return $stmt->fetchAll();
}

function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>