<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="#" class="sidebar-logo">
            <i class="fas fa-laptop-code"></i>
            <span>ICT Staff Desk</span>
        </a>
    </div>
    <div class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'dashboard.php') ? 'active' : '' ?>" href="<?= app_url('ADMIN/ict-staff/dashboard.php') ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'admissions.php') ? 'active' : '' ?>" href="<?= app_url('ADMIN/ict-staff/admissions.php') ?>">
                    <i class="fas fa-id-card"></i>
                    <span>Admissions Processing</span>
                </a>
            </li>
        </ul>
    </div>
</nav>
