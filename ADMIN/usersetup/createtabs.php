<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['DEVELOPER'], 'ADMIN/login.php');

$pageTitle = 'Add New Tabs';
$con = db();

$msg = '';

if (isset($_POST['add'])) {
    $tabname = $_POST['tabname'] ?? '';

    try {
        $check = $con->prepare("SELECT COUNT(*) FROM page_menu_tab WHERE tab_name = ?");
        $check->execute([$tabname]);
        if ($check->fetchColumn() < 1) {
            $add = $con->prepare("INSERT INTO page_menu_tab (tab_name, open_active, tab_status) VALUES (?, 'notopen', 1)");
            $add->execute([$tabname]);

            $msg = '<div class="alert alert-success"><strong>Success!</strong> Menu Tab created successfully.</div>';
        } else {
            $msg = '<div class="alert alert-warning"><strong>Warning!</strong> A tab with this name already exists.</div>';
        }
    } catch (PDOException $exp) {
        $msg = '<div class="alert alert-danger"><strong>Error!</strong> ' . htmlspecialchars($exp->getMessage()) . '</div>';
    }
}

require_once __DIR__ . '/../super-admin/includes/header.php';
require_once __DIR__ . '/../super-admin/includes/topbar.php';
require_once __DIR__ . '/../super-admin/includes/sidebar.php';
?>

<main class="content-body p-4">
    <section class="page-hero mb-4">
        <div>
            <h1>Add New Sidebar Tab</h1>
            <p class="panel-muted">Create a new category/header for groupings in the sidebar.</p>
        </div>
    </section>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4">Create Tab Form</h5>
                    
                    <?php echo $msg; ?>

                    <form method="POST" class="row g-3">
                        <div class="col-12 mb-4">
                            <label for="tabname" class="form-label font-weight-bold">Tab Category Name</label>
                            <input type="text" name="tabname" class="form-control" id="tabname" placeholder="e.g. Finance Modules" required>
                        </div>

                        <div class="text-start">
                            <button type="submit" name="add" class="btn btn-primary px-4">Create Tab</button>
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
