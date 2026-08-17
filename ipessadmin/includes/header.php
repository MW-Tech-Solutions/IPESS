<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ipessadmin/login.php');
require_role(['PG_SCHOOL_OFFICER', 'ADMISSIONS_OFFICER', 'PORTAL_ADMIN', 'SUPER_ADMIN'], 'ipessadmin/login.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="<?php echo app_url('ipessadmin/images/logo.jpeg'); ?>">
    <title>JOSTUM PG SCHOOL - Admin</title>
    <?php include(__DIR__ . "/../inc/htmlheaderotherfolders.php"); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="<?php echo app_url('ipessadmin/super-admin.css?v=1.0.2'); ?>" rel="stylesheet">
    <!-- Bootstrap JS loaded in head to ensure availability before inline scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
