<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['DEVELOPER'], 'ADMIN/login.php');

$pageTitle = 'Download Project File';
$con = db();

$msg = '';

if (isset($_POST['search'])) {
    $file = $_POST['filename'] ?? '';
    // Redirect to stream downloader
    header("Location: downloadfiless.php?nam=" . urlencode($file));
    exit;
}

require_once __DIR__ . '/../super-admin/includes/header.php';
require_once __DIR__ . '/../super-admin/includes/topbar.php';
require_once __DIR__ . '/../super-admin/includes/sidebar.php';
?>

<main class="content-body p-4">
    <section class="page-hero mb-4">
        <div>
            <h1>Download Project File</h1>
            <p class="panel-muted">Select any file from the project directory to download it directly.</p>
        </div>
    </section>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4">Download File Form</h5>
                    
                    <?php echo $msg; ?>

                    <form method="POST" class="row g-3">
                        <div class="col-12 mb-4">
                            <label for="filename" class="form-label font-weight-bold">File Relative Path</label>
                            <input type="text" name="filename" class="form-control" id="filename" placeholder="e.g. ADMIN/usersetup/bind_roles_pages.php" required>
                        </div>

                        <div class="text-start">
                            <button type="submit" name="search" class="btn btn-primary px-4">Download File</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../super-admin/includes/footer.php';
?>
