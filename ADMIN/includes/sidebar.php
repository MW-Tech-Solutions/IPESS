<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$userRole = normalize_role(current_user_role());
$sidebarMap = [
    'SUPER_ADMIN' => 'super_admin.php',
    'ICT_ADMIN' => 'super_admin.php',
    'ICT_SUPPORT' => 'super_admin.php',
    'STUDENT_MANAGER' => 'super_admin.php',
    'ACADEMIC_MANAGER' => 'super_admin.php',
    'SUPERVISOR_MANAGER' => 'super_admin.php',
    'ICTO' => 'icto.php',
    'DEPARTMENT_ADMIN' => 'dept_admin.php',
    'HOD' => 'dept_admin.php',
    'FACULTY_OFFICER' => 'faculty_admin.php',
    'PG_SCHOOL_OFFICER' => 'pg_admin.php',
    'ICT_STAFF' => 'ict_staff.php',
    'REVIEWER' => 'reviewer.php',
    'SUPERVISOR' => 'supervisor.php'
];

if (isset($sidebarMap[$userRole])) {
    $sidebarFile = __DIR__ . '/sidebars/' . $sidebarMap[$userRole];
    if (file_exists($sidebarFile)) {
        require_once $sidebarFile;
        return;
    }
}
?>
<nav class="sidebar" id="sidebar">

    <div class="sidebar-header">
        <a href="dashboard.php" class="sidebar-logo">
            <i class="fas fa-graduation-cap"></i>
            <span>JOSTUM PG</span>
        </a>
    </div>

    <div class="sidebar-nav">
        <ul class="nav flex-column">

            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'dashboard.php') ? 'active' : '' ?>"
                   href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'application-management.php') ? 'active' : '' ?>"
                   href="application-management.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Application Management</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'document-verification.php') ? 'active' : '' ?>"
                   href="document-verification.php">
                    <i class="fas fa-check-circle"></i>
                    <span>Document Verification</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'academic-review.php') ? 'active' : '' ?>"
                   href="academic-review.php">
                    <i class="fas fa-book-open"></i>
                    <span>Academic Review</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'referees.php') ? 'active' : '' ?>"
                   href="referees.php">
                    <i class="fas fa-user-check"></i>
                    <span>Referees</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'admission-decisions.php') ? 'active' : '' ?>"
                   href="admission-decisions.php">
                    <i class="fas fa-gavel"></i>
                    <span>Admission Decisions</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'reports.php') ? 'active' : '' ?>"
                   href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>

        </ul>
    </div>

</nav>
