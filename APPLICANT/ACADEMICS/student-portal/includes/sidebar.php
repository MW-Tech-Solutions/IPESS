<?php include __DIR__ . '/nav-data.php'; ?>

<nav class="sidebar nav-jostum" id="sidebar">
    <div class="logo">
        <img src="/JOSTUM/ADMIN/images/logo.jpeg" alt="School Logo" class="sidebar-logo-img">
        <span>JOSTUM PG</span>
    </div>

    <ul class="nav flex-column">
        <?php foreach ($nav_items as $index => $item): ?>
            <li class="nav-item">
                <a class="nav-link<?php echo $index === 0 ? ' active' : ''; ?>" href="#<?php echo htmlspecialchars($item['slug'], ENT_QUOTES, 'UTF-8'); ?>" data-page="<?php echo htmlspecialchars($item['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="bi <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                    <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
