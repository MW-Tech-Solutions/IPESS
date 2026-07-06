<?php
$pageTitle = 'Portal Admin Dashboard';
$pageSubtitle = 'Manage the public admissions content shown to applicants.';

require_once 'includes/db.php';

$stats = [
    'programmes' => 0,
    'requirements' => 0,
    'dates' => 0,
    'faqs' => 0,
    'notices' => 0,
];

if ($pdo) {
    try {
        if (admissions_content_table_exists($pdo, 'admissions_programmes')) {
            $stats['programmes'] = (int) $pdo->query("SELECT COUNT(*) FROM admissions_programmes WHERE is_active = 1")->fetchColumn();
        }
        if (admissions_content_table_exists($pdo, 'admissions_requirements')) {
            $stats['requirements'] = (int) $pdo->query("SELECT COUNT(*) FROM admissions_requirements WHERE is_active = 1")->fetchColumn();
        }
        if (admissions_content_table_exists($pdo, 'admissions_important_dates')) {
            $stats['dates'] = (int) $pdo->query("SELECT COUNT(*) FROM admissions_important_dates WHERE is_active = 1")->fetchColumn();
        }
        if (admissions_content_table_exists($pdo, 'admissions_faqs')) {
            $stats['faqs'] = (int) $pdo->query("SELECT COUNT(*) FROM admissions_faqs WHERE is_active = 1")->fetchColumn();
        }
        if (admissions_content_table_exists($pdo, 'admissions_notices')) {
            $stats['notices'] = (int) $pdo->query("SELECT COUNT(*) FROM admissions_notices WHERE is_active = 1")->fetchColumn();
        }
    } catch (Throwable $e) {
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Admissions Content Dashboard</h1>
        <p class="panel-muted">This role manages the public admissions page content, quick notices, dates, and FAQs.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-primary" href="main-page-content.php">Open Main Page Manager</a>
        <a class="btn btn-outline-primary" href="admission-landing-page.php">Open Admission Page Manager</a>
    </div>
</section>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
        <div><div class="stat-title">Active Programmes</div><div class="stat-value"><?php echo number_format($stats['programmes']); ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-list-check"></i></div>
        <div><div class="stat-title">Requirements</div><div class="stat-value"><?php echo number_format($stats['requirements']); ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-days"></i></div>
        <div><div class="stat-title">Important Dates</div><div class="stat-value"><?php echo number_format($stats['dates']); ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-circle-question"></i></div>
        <div><div class="stat-title">FAQs</div><div class="stat-value"><?php echo number_format($stats['faqs']); ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-bullhorn"></i></div>
        <div><div class="stat-title">Active Notices</div><div class="stat-value"><?php echo number_format($stats['notices']); ?></div></div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Managed Public Sections</h3>
            <div class="panel-muted">The admissions page now reads these blocks from the database instead of hard-coded HTML.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="row g-3">
            <div class="col-md-6 col-xl-4"><div class="card border-0 bg-light h-100"><div class="card-body"><h5>Programmes</h5><p class="text-muted mb-0">Examples: JUPEB, IPESS, PGD and any new academic offering.</p></div></div></div>
            <div class="col-md-6 col-xl-4"><div class="card border-0 bg-light h-100"><div class="card-body"><h5>Quick Notice Modal</h5><p class="text-muted mb-0">Breaking notices can be published to show automatically when applicants visit the page.</p></div></div></div>
            <div class="col-md-6 col-xl-4"><div class="card border-0 bg-light h-100"><div class="card-body"><h5>Admission Requirements</h5><p class="text-muted mb-0">The page label is now generic and the items are editable.</p></div></div></div>
            <div class="col-md-6 col-xl-4"><div class="card border-0 bg-light h-100"><div class="card-body"><h5>Important Dates</h5><p class="text-muted mb-0">Admission cycle dates are stored as proper dates and sorted accordingly.</p></div></div></div>
            <div class="col-md-6 col-xl-4"><div class="card border-0 bg-light h-100"><div class="card-body"><h5>FAQs</h5><p class="text-muted mb-0">Common applicant questions can be added, hidden, or removed without code edits.</p></div></div></div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
