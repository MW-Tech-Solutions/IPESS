<div class="main-content">
    <nav class="navbar navbar-expand bg-white shadow-sm px-4">
        <div class="container-fluid">
            <div class="navbar-brand fw-bold">
                Admin Dashboard
            </div>

            <ul class="navbar-nav ms-auto align-items-center">
                
                <li class="nav-item dropdown">
                    <button class="btn border-0 position-relative me-3" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            3
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user-plus me-2"></i> New user registered</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-file-alt me-2"></i> Application submitted</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-exclamation-triangle me-2"></i> System alert</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center" href="#">View all notifications</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown user-dropdown">
                    <button class="btn border-0 d-flex align-items-center" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <span>Admin</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
