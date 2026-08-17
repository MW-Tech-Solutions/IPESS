<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['DEVELOPER'], 'ADMIN/login.php');

$pageTitle = 'Add Dashboard';
$con = db();

$msg = '';

if (isset($_POST['add'])) {
    $userRole = $_POST['tabcode'] ?? '';
    $description = $_POST['tabname'] ?? '';
    $url = $_POST['url'] ?? '';
    $folder = $_POST['foldername'] ?? '';

    try {
        $check = $con->prepare("SELECT COUNT(*) FROM dash_borad WHERE userType = ? OR pageName = ?");
        $check->execute([$userRole, $url]);
        if ($check->fetchColumn() < 1) {
            $add = $con->prepare("
                INSERT INTO dash_borad (pageName, PageDescription, userType, PageStatus, folder) 
                VALUES (?, ?, ?, 1, ?)
            ");
            $add->execute([$url, $description, $userRole, $folder]);

            $msg = '<div class="alert alert-success"><strong>Success!</strong> Dashboard configuration mapped successfully.</div>';
        } else {
            $msg = '<div class="alert alert-warning"><strong>Warning!</strong> A dashboard map for this role or URL already exists.</div>';
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
            <h1>Add Dashboard Configuration</h1>
            <p class="panel-muted">Assign a custom dashboard landing page for individual user roles.</p>
        </div>
    </section>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4">Create Dashboard Form</h5>
                    
                    <?php echo $msg; ?>

                    <form method="POST" class="row g-3">
                        <div class="col-12 mb-3">
                            <label for="tabcode" class="form-label font-weight-bold">Select Role</label>
                            <select name="tabcode" class="form-select form-control" id="tabcode" required>
                                <option value="">Select Option</option>
                                <?php
                                $roles = $con->query("SELECT role_key, role_name FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($roles as $role) {
                                    echo '<option value="' . htmlspecialchars($role['role_key']) . '">' . htmlspecialchars($role['role_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="tabname" class="form-label font-weight-bold">Dashboard Description</label>
                            <input type="text" name="tabname" class="form-control" id="tabname" placeholder="e.g. Developer landing page" required>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="url" class="form-label font-weight-bold">Dashboard File Relative URL</label>
                            <input type="text" name="url" class="form-control" id="url" placeholder="e.g. ADMIN/usersetup/bind_roles_pages.php" required>
                        </div>

                        <div class="col-12 mb-4">
                            <label for="foldername" class="form-label font-weight-bold">Base Folder</label>
                            <input type="text" name="foldername" class="form-control" id="foldername" placeholder="e.g. ADMIN/usersetup">
                        </div>

                        <div class="text-start">
                            <button type="submit" name="add" class="btn btn-primary px-4">Save Dashboard</button>
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
