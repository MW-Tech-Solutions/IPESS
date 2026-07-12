<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role_or_permission(['PG_SCHOOL_OFFICER', 'SUPER_ADMIN', 'ICT_ADMIN'], 'pg_review', 'ADMIN/login.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
    <title><?php echo htmlspecialchars($pageTitle ?? 'PG School Office'); ?> | JOSTUM PG School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../super-admin/super-admin.css">
    <style>
        :root {
            --primary-color: #6EB533;
            --primary-hover: #5da02a;
        }
        .btn-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: var(--primary-hover) !important;
            border-color: var(--primary-hover) !important;
        }
        .btn-outline-primary {
            color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }
        .btn-outline-primary:hover {
            background-color: var(--primary-color) !important;
            color: #fff !important;
        }
        .text-primary {
            color: var(--primary-color) !important;
        }
    </style>
</head>
<body>
    <div class="admin-shell" id="admin-shell">
