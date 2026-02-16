<?php
session_start();
require_once __DIR__ . '/../config/urls.php';

// Check if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // Role-based redirect
    switch ($_SESSION['role']) {
        case 'SUPER_ADMIN':
            redirect_to('ADMIN/super-admin/layout.php');
            break;
        case 'ADMIN':
            redirect_to('ADMIN/admin/dashboard.php');
            break;
        case 'DEPARTMENT_ADMIN':
            redirect_to('ADMIN/dept-admin/dashboard.php');
            break;
        case 'SUPERVISOR':
            redirect_to('ADMIN/supervisor/dashboard.php');
            break;
        case 'REVIEWER':
            redirect_to('ADMIN/reviewer/dashboard.php');
            break;
        default:
            redirect_to('ADMIN/login.php');
            break;
    }
} else {
    // Not logged in, redirect to login
    redirect_to('ADMIN/login.php');
}
?>
