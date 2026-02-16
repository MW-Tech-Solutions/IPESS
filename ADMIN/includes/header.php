<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/urls.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    redirect_to('ADMIN/login.php');
}
$timeoutSeconds = 900;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeoutSeconds) {
    session_unset();
    session_destroy();
    redirect_to('ADMIN/login.php?timeout=1');
}
$_SESSION['last_activity'] = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
    <title>JOSTUM PG SCHOOL - Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../asset/css/style.css">
    <link rel="stylesheet" href="../super-admin/super-admin.css">
</head>
<body>
    <div class="wrapper" id="main-wrapper">
