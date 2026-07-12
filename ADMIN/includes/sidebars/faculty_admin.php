<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="#" class="sidebar-logo">
            <i class="fas fa-university"></i>
            <span>Faculty Admin</span>
        </a>
    </div>
    <div class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'dashboard.php') ? 'active' : '' ?>" href="<?= app_url('ADMIN/faculty/dashboard.php') ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'applications.php') ? 'active' : '' ?>" href="<?= app_url('ADMIN/faculty/applications.php') ?>">
                    <i class="fas fa-folder-open"></i>
                    <span>Faculty Review</span>
                </a>
            </li>
        </ul>
    </div>
</nav>
