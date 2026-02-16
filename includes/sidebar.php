<?php
// 1. Load Dependencies
$menuStructure = require __DIR__ . '/../config/menu_map.php';
require_once __DIR__ . '/nav_functions.php';

// 2. Mock User Role (In production, get this from $_SESSION['role'])
$currentUserRole = $_SESSION['role'] ?? 'student'; 
?>

<nav class="navbar navbar-light bg-light d-lg-none border-bottom px-3 sticky-top">
    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
        <span class="navbar-toggler-icon"></span>
    </button>
    <span class="navbar-brand mb-0 h1">GradTracker</span>
</nav>

<div class="offcanvas-lg offcanvas-start bg-light border-end vh-100 position-fixed-lg" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel" style="width: 280px; top: 0; left: 0; overflow-y: auto;">
    
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="sidebarMenuLabel">
            <i class="bi bi-mortarboard-fill text-primary me-2"></i>GradTracker
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body p-0">
        <div class="d-none d-lg-block p-3 border-bottom">
             <h4 class="h5 mb-0"><i class="bi bi-mortarboard-fill text-primary me-2"></i>GradTracker</h4>
             <small class="text-muted">Postgrad Management</small>
        </div>

        <nav class="nav flex-column p-2">
            <?php foreach ($menuStructure as $section): ?>
                
                <?php 
                // Optional: Check if at least one item in this section is visible to the user
                // skipping for brevity, but good for cleanup
                ?>

                <?php if (!empty($section['category'])): ?>
                    <div class="text-uppercase text-muted fw-bold small mt-3 mb-1 px-3">
                        <?= htmlspecialchars($section['category']) ?>
                    </div>
                <?php endif; ?>

                <ul class="nav nav-pills flex-column mb-0">
                    <?php foreach ($section['items'] as $item): ?>
                        <?php if (hasAccess($item['roles'], $currentUserRole)): ?>
                            
                            <li class="nav-item mb-1">
                                <a href="<?= htmlspecialchars($item['url']) ?>" 
                                   class="nav-link d-flex align-items-center gap-2 <?= isActive($item['url']) ?> <?= $item['class'] ?? '' ?>"
                                   <?= isset($item['class']) && strpos($item['class'], 'text-danger') !== false ? '' : '' ?>
                                >
                                    <i class="bi <?= htmlspecialchars($item['icon']) ?> fs-5"></i>
                                    <span><?= htmlspecialchars($item['title']) ?></span>
                                    
                                    <?php if (isset($item['badge'])): ?>
                                        <span class="badge bg-danger rounded-pill ms-auto"><?= $item['badge'] ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>

                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>

            <?php endforeach; ?>
        </nav>
        
        <div class="mt-auto p-3 border-top bg-white">
            <div class="d-flex align-items-center">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                    <?= strtoupper(substr($currentUserRole, 0, 1)) ?>
                </div>
                <div class="lh-sm">
                    <div class="fw-bold text-capitalize"><?= $currentUserRole ?> User</div>
                    <small class="text-muted">Online</small>
                </div>
            </div>
        </div>
    </div>
</div>