<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');

// Make sure user is logged in and not a student
if (!is_logged_in() || normalize_role(current_user_role()) === 'STUDENT') {
    http_response_code(403);
    exit('403 Forbidden');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
    <title><?php echo htmlspecialchars($pageTitle ?? 'General Admin'); ?> | JOSTUM PG School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../super-admin/super-admin.css">
</head>
<body>
    <div class="admin-shell" id="admin-shell">
