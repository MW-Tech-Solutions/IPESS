<?php
declare(strict_types=1);

function render_header(string $title, string $section = ''): void
{
    $user = current_user();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#43B14B">
        <title><?= e($title) ?> | JOSTUM</title>
        <link rel="icon" type="image/png" href="<?= e(url('images/new_jostum_logo.png')) ?>">
        <link rel="apple-touch-icon" href="<?= e(url('images/new_jostum_logo.png')) ?>">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="<?= e(url('public/assets/styles.css')) ?>">
    </head>
    <body>
    <nav class="navbar navbar-expand-lg portal-nav sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(url()) ?>">
                <img src="<?= e(url('images/new_jostum_logo.png')) ?>" alt="JOSTUM logo" class="brand-logo">
                <span>
                    <strong>JOSTUM</strong>
                    <small>POST-UTME Portal</small>
                </span>
            </a>
            <?php if ($user): ?>
                <div class="header-session-actions">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url($user['role'] === 'applicant' ? 'applicant/dashboard.php' : 'admin/dashboard.php')) ?>">
                        <?= icon($user['role'] === 'applicant' ? 'layout-dashboard' : 'shield-check') ?><span><?= $user['role'] === 'applicant' ? 'Dashboard' : 'Admin' ?></span>
                    </a>
                    <a class="btn btn-sm btn-header-logout" href="<?= e(url('logout.php')) ?>">
                        <?= icon('log-out') ?><span>Logout</span>
                    </a>
                </div>
            <?php endif; ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <li class="nav-item"><a class="nav-link <?= $section === 'home' ? 'active' : '' ?>" href="<?= e(url()) ?>">Home</a></li>
                    <li class="nav-item"><a class="nav-link <?= $section === 'status' ? 'active' : '' ?>" href="<?= e(url('status.php')) ?>">Check Status</a></li>
                    <?php if ($user): ?>
                        <?php if ($user['role'] === 'applicant'): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= e(url('applicant/dashboard.php')) ?>">Dashboard</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="<?= e(url('admin/dashboard.php')) ?>">Admin</a></li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="btn btn-sm btn-header-logout" href="<?= e(url('logout.php')) ?>">
                                <?= icon('log-out') ?><span>Logout</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="btn btn-sm btn-portal-blue" href="<?= e(url('login.php')) ?>">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <main>
    <?php foreach (['success', 'error', 'info'] as $type): ?>
        <?php if ($message = flash($type)): ?>
            <div class="container mt-3">
                <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?>"><?= e($message) ?></div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php
}

function render_footer(): void
{
    ?>
    </main>
    <footer class="portal-footer">
        <div class="container d-flex flex-column flex-md-row justify-content-between gap-2">
            <span>&copy; <?= date('Y') ?> Joseph Sarwuan Tarka University, Makurdi.</span>
            <span>Secure screening workflow for Nigerian applicants.</span>
        </div>
    </footer>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="<?= e(url('public/assets/app.js')) ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}

function render_steps(array $steps): void
{
    echo '<div class="stepper">';
    foreach ($steps as $label => $done) {
        echo '<div class="step ' . ($done ? 'done' : '') . '"><span>' . ($done ? '&#10003;' : '') . '</span><small>' . e($label) . '</small></div>';
    }
    echo '</div>';
}

function icon(string $name): string
{
    return '<i data-lucide="' . e($name) . '" aria-hidden="true"></i>';
}

function workspace_links(string $type): array
{
    if ($type === 'admin') {
        return [
            ['Dashboard', 'admin/dashboard.php', 'layout-dashboard'],
            ['Candidates', 'admin/candidates.php', 'users-round'],
            ['Applications', 'admin/applications.php', 'clipboard-check'],
            ['Payments', 'admin/payments.php', 'credit-card'],
            ['JAMB Import', 'admin/import-jamb.php', 'file-spreadsheet'],
            ['Settings', 'admin/settings.php', 'settings'],
            ['Staff Users', 'admin/users.php', 'user-cog'],
        ];
    }
    return [
        ['Dashboard', 'applicant/dashboard.php', 'layout-dashboard'],
        ['Payment', 'applicant/payment.php', 'credit-card'],
        ['Screening Form', 'applicant/form.php', 'file-pen-line'],
        ['Review', 'applicant/review.php', 'clipboard-list'],
        ['Status Tracking', 'applicant/status.php', 'radar'],
        ['Acknowledgement Slip', 'applicant/slip.php', 'receipt-text'],
    ];
}

function render_workspace_start(string $type, array $profile, string $active = 'Dashboard'): void
{
    $isAdmin = $type === 'admin';
    $title = $isAdmin ? 'Staff Console' : trim(($profile['surname'] ?? '') . ' ' . ($profile['first_name'] ?? ''));
    $subtitle = $isAdmin ? ucwords(str_replace('_', ' ', $profile['role'] ?? '')) : ($profile['jamb_reg_no'] ?? '');
    ?>
    <section class="app-workspace">
        <div class="container">
            <div class="workspace-layout">
                <aside class="workspace-sidebar" id="workspaceSidebar">
                    <div class="sidebar-profile">
                        <div class="sidebar-brand-row">
                            <img src="<?= e(url('images/new_jostum_logo.png')) ?>" alt="JOSTUM logo">
                            <span></span>
                            <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="workspaceSidebar" aria-expanded="true" aria-label="Toggle sidebar">
                                <?= icon('panel-left-close') ?>
                            </button>
                        </div>
                        <strong><?= e($title ?: 'JOSTUM Portal') ?></strong>
                        <span><?= e($subtitle) ?></span>
                    </div>
                    <nav class="sidebar-menu" aria-label="<?= $isAdmin ? 'Admin' : 'Applicant' ?> menu">
                        <?php foreach (workspace_links($type) as [$label, $href, $icon]): ?>
                            <?php
                            $role = $profile['role'] ?? '';
                            if ($isAdmin && $label === 'Applications' && !in_array($role, ['admissions_officer', 'super_admin'], true)) {
                                continue;
                            }
                            if ($isAdmin && $label === 'Payments' && !in_array($role, ['finance_officer', 'super_admin'], true)) {
                                continue;
                            }
                            if ($isAdmin && in_array($label, ['JAMB Import', 'Settings'], true) && !in_array($role, ['ict_admin', 'super_admin'], true)) {
                                continue;
                            }
                            if ($isAdmin && $label === 'Staff Users' && $role !== 'super_admin') {
                                continue;
                            }
                            ?>
                            <a class="<?= strtolower($active) === strtolower($label) ? 'active' : '' ?>" href="<?= e(url($href)) ?>">
                                <?= icon($icon) ?><span><?= e($label) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </aside>
                <div class="workspace-content">
    <?php
}

function render_workspace_end(): void
{
    ?>
                </div>
            </div>
        </div>
    </section>
    <?php
}
