<?php
session_start();
include("inc/main.config.php");
include("inc/selectorVendor.php");

if (empty($_SESSION['roleid']) || empty($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}

$msg = "";

// Action to reset/clear all permissions
if (isset($_POST['reset_all_permissions'])) {
    try {
        $con->exec("TRUNCATE TABLE right_page_main_menus");
        $con->exec("TRUNCATE TABLE pesonal_right_page_main_menus");
        $con->exec("TRUNCATE TABLE personal_page_menu_tab");

        $msg = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>All permissions successfully cleared!</strong> You can now assign fresh sidebar menus strictly for each role.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } catch (Throwable $e) {
        try {
            $con->exec("DELETE FROM right_page_main_menus");
            $con->exec("DELETE FROM pesonal_right_page_main_menus");
            $con->exec("DELETE FROM personal_page_menu_tab");
            $msg = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <strong>All permissions successfully cleared!</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        } catch (Throwable $ex) {
            $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Error: ' . htmlspecialchars($ex->getMessage()) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    }
}

// Action to reset permissions for a specific role
if (isset($_POST['reset_specific_role'])) {
    $targetRole = trim($_POST['target_role'] ?? '');
    if (!empty($targetRole)) {
        try {
            $stmt = $con->prepare("DELETE FROM right_page_main_menus WHERE roleID = ?");
            $stmt->execute([$targetRole]);

            $stmt2 = $con->prepare("DELETE FROM pesonal_right_page_main_menus WHERE roleID = ?");
            $stmt2->execute([$targetRole]);

            $msg = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <strong>Permissions for role "' . htmlspecialchars($targetRole) . '" successfully reset!</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        } catch (Throwable $e) {
            $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Error: ' . htmlspecialchars($e->getMessage()) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    }
}

// Count current permissions by role
$roleCounts = [];
try {
    $stmtCounts = $con->query("SELECT roleID, COUNT(*) AS total FROM right_page_main_menus GROUP BY roleID ORDER BY total DESC");
    if ($stmtCounts) {
        $roleCounts = $stmtCounts->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {}

$mypagename = "Reset Role Permissions";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Reset Role Permissions - IPESS</title>
  <?php include("inc/htmlheaderotherfolders.php"); ?>
</head>
<body>
  <?php include("inc/inerheader.php"); ?>
  <?php include("inc/sidebar.php"); ?>

  <main id="main" class="main">
    <div class="pagetitle d-flex justify-content-between align-items-center mb-3">
      <div>
        <h1>Reset Role Permissions</h1>
        <nav>
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item"><a href="bind_roles_pages.php">Permission Manager</a></li>
            <li class="breadcrumb-item active">Reset Permissions</li>
          </ol>
        </nav>
      </div>
      <div>
        <a href="bind_roles_pages.php" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-circle me-1"></i> Assign Permissions
        </a>
      </div>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-8 mx-auto">
          <?= $msg ?>

          <!-- Card: Current Assigned Counts -->
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
              <h5 class="card-title fw-bold text-dark mb-3">
                <i class="bi bi-shield-lock me-2 text-primary"></i>Current Role Permission Counts
              </h5>
              <?php if (empty($roleCounts)): ?>
                <div class="alert alert-info mb-0">
                  <i class="bi bi-info-circle me-2"></i>No permissions currently assigned. The permission table is clean.
                </div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-bordered table-striped align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>Role Identifier</th>
                        <th>Assigned Menu Pages</th>
                        <th class="text-center">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($roleCounts as $rc): ?>
                        <tr>
                          <td><strong><?= htmlspecialchars($rc['roleID']) ?></strong></td>
                          <td><span class="badge bg-secondary"><?= htmlspecialchars((string)$rc['total']) ?> pages</span></td>
                          <td class="text-center">
                            <form method="POST" action="reset_role_permissions.php" class="d-inline" onsubmit="return confirm('Clear all permissions for <?= htmlspecialchars(addslashes($rc['roleID'])) ?>?');">
                              <input type="hidden" name="target_role" value="<?= htmlspecialchars($rc['roleID']) ?>">
                              <button type="submit" name="reset_specific_role" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash me-1"></i>Reset Role
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Card: Reset All Permissions -->
          <div class="card shadow-sm border-danger border-1">
            <div class="card-body p-4">
              <h5 class="card-title fw-bold text-danger mb-2">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>Reset All Role Permissions
              </h5>
              <p class="text-muted small mb-4">
                This will clear all seeded and old permissions from <code>right_page_main_menus</code>, <code>pesonal_right_page_main_menus</code>, and <code>personal_page_menu_tab</code> so that each user role only sees the exact sidebar pages you dynamically assign in <a href="bind_roles_pages.php">Assign Page to Role</a>.
              </p>
              <form method="POST" action="reset_role_permissions.php" onsubmit="return confirm('Are you sure you want to reset ALL role permissions? This will clear all current sidebar menu assignments.');">
                <button type="submit" name="reset_all_permissions" class="btn btn-danger px-4 fw-bold">
                  <i class="bi bi-trash-fill me-1"></i> Clear All Permissions & Menus
                </button>
              </form>
            </div>
          </div>

        </div>
      </div>
    </section>
  </main>

  <?php include("inc/footer.php"); ?>
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>
</body>
</html>
