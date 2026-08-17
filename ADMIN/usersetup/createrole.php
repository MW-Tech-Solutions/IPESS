<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['DEVELOPER'], 'ADMIN/login.php');

$pageTitle = 'Add Role';
$con = db();

$msg = '';

if (isset($_POST['add'])) {
    $rolename = $_POST['rolename'] ?? '';
    $rolekey = strtoupper(str_replace([' ', '-'], '_', trim($rolename)));

    try {
        $check = $con->prepare("SELECT COUNT(*) FROM roles WHERE role_key = ?");
        $check->execute([$rolekey]);
        if ($check->fetchColumn() < 1) {
            $add = $con->prepare("INSERT INTO roles (role_key, role_name) VALUES (?, ?)");
            $add->execute([$rolekey, $rolename]);

            $msg = '<div class="alert alert-success"><strong>Success!</strong> Role created successfully.</div>';
        } else {
            $msg = '<div class="alert alert-warning"><strong>Warning!</strong> A role with this key/name already exists.</div>';
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
            <h1>Add Role</h1>
            <p class="panel-muted">Define a new user role/duty type in the system.</p>
        </div>
    </section>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4">Create Role Form</h5>
                    
                    <?php echo $msg; ?>

                    <form method="POST" class="row g-3">
                        <div class="col-12 mb-4">
                            <label for="rolename" class="form-label font-weight-bold">Role Display Name</label>
                            <input type="text" name="rolename" class="form-control" id="rolename" placeholder="e.g. registry assistant" required>
                        </div>

                        <div class="text-start">
                            <button type="submit" name="add" class="btn btn-primary px-4">Create Role</button>
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
