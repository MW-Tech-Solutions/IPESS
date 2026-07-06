<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(300, 'ADMIN/login.php');
require_role(['FACULTY_OFFICER', 'DEPARTMENT_ADMIN', 'HOD', 'SUPER_ADMIN'], 'ADMIN/login.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JOSTUM PG SCHOOL - Department Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="wrapper" id="main-wrapper">
        <!-- Fixed Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="#" class="sidebar-logo">
                    <i class="fas fa-graduation-cap"></i>
                    <span>JOSTUM PG</span>
                </a>
            </div>
            <div class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item dropdown">
                        <a class="nav-link active" href="#applications" data-bs-toggle="tab" data-bs-target="#applications">
                            <i class="fas fa-folder-open"></i>
                            <span>Department Applications</span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#assignment" data-bs-toggle="tab" data-bs-target="#assignment">
                            <i class="fas fa-user-plus"></i>
                            <span>Supervisor Assignment</span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#reports" data-bs-toggle="tab" data-bs-target="#reports">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#analytics" data-bs-toggle="tab" data-bs-target="#analytics">
                            <i class="fas fa-chart-line"></i>
                            <span>Analytics</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navbar -->
            <nav class="navbar">
                <div class="navbar-brand">
                    Department Admin Dashboard
                </div>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <button class="dropdown-toggle notification-btn" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge">3</span>
                        </button>
                        <ul class="dropdown-menu notification-dropdown">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user-plus"></i> New user registered</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-file-alt"></i> Application submitted</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-exclamation-triangle"></i> System alert</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="#">View all notifications</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown user-dropdown">
                        <button class="dropdown-toggle" data-bs-toggle="dropdown">
                            <div class="user-avatar">
                                <i class="fas fa-user-cog"></i>
                            </div>
                            <span>Dept Admin</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </nav>

            <!-- Content Container -->
            <div class="content-container">
                <?php include 'dashboard.php'; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Sidebar toggle functionality
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            document.getElementById('main-wrapper').classList.toggle('sidebar-collapsed');
        });

        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebar-toggle');
            if (!sidebar.contains(event.target) && !toggle.contains(event.target) && window.innerWidth <= 768) {
                document.getElementById('main-wrapper').classList.remove('sidebar-collapsed');
            }
        });
    </script>
</body>
</html>
