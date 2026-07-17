<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$userRole = normalize_role(current_user_role());
$sidebarMap = [
    'SUPER_ADMIN' => 'super_admin.php',
    'ICT_ADMIN' => 'ict_admin.php',
    'ICT_SUPPORT' => 'super_admin.php',
    'STUDENT_MANAGER' => 'super_admin.php',
    'ACADEMIC_MANAGER' => 'super_admin.php',
    'SUPERVISOR_MANAGER' => 'super_admin.php',
    'ICTO' => 'icto.php',
    'DEPARTMENT_ADMIN' => 'dept_admin.php',
    'HOD' => 'dept_admin.php',
    'FACULTY_OFFICER' => 'faculty_admin.php',
    'COLLEGE_ADMIN' => 'faculty_admin.php',
    'PG_SCHOOL_OFFICER' => 'pg_admin.php',
    'PG_ADMIN' => 'pg_admin.php',
    'ICT_STAFF' => 'ict_staff.php',
    'REVIEWER' => 'reviewer.php',
    'SUPERVISOR' => 'supervisor.php',
    'CENTER_LEADER' => 'center_leader.php'
];

if (isset($sidebarMap[$userRole])) {
    $sidebarFile = __DIR__ . '/sidebars/' . $sidebarMap[$userRole];
    if (file_exists($sidebarFile)) {
        require_once $sidebarFile;
        return;
    }
}

// No mapped role sidebar — use the comprehensive general sidebar
$generalSidebar = __DIR__ . '/sidebars/general_admin.php';
if (file_exists($generalSidebar)) {
    require_once $generalSidebar;
} else {
    // Ultimate fallback: minimal sidebar
    echo '<aside class="sidebar" id="sidebar"><div class="sidebar-brand"><div class="brand-text"><span class="brand-name">IPESS FUAM</span></div></div></aside>';
}
