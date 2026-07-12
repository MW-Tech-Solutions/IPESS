<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="#" class="sidebar-logo">
            <i class="fas fa-building"></i>
            <span>Dept Admin</span>
        </a>
    </div>
    <div class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'dashboard.php') ? 'active' : '' ?>" href="<?= app_url('ADMIN/dept-admin/dashboard.php') ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'department-applications.php') ? 'active' : '' ?>" href="<?= app_url('ADMIN/dept-admin/department-applications.php') ?>">
                    <i class="fas fa-folder-open"></i>
                    <span>Department Applications</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'supervisor-management.php') ? 'active' : '' ?>" href="<?= app_url('ADMIN/dept-admin/supervisor-management.php') ?>">
                    <i class="fas fa-user-plus"></i>
                    <span>Supervisor Assignment</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'student-management.php') ? 'active' : '' ?>" href="<?= app_url('ADMIN/dept-admin/student-management.php') ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>Student Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'department-reports.php') ? 'active' : '' ?>" href="<?= app_url('ADMIN/dept-admin/department-reports.php') ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
        </ul>
    </div>
</nav>
