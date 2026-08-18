<?php
session_start();
require_once("inc/main.config.php");
require_once("inc/selectorVendor.php");

$myclass = new selectorVendor();

if (empty($_SESSION['roleid']) && empty($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}

$rolesession = $_SESSION['roleid'];
$usersession = $_SESSION['userid'];

$msg = "";
$selectedStaffId = $_GET['id'] ?? $_POST['staff_id'] ?? '';
$targetStaff = null;

// Handle Role Update Submission (STRICTLY user_access ONLY)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $staffIdToUpdate = (int)($_POST['staff_id'] ?? 0);
    $newRoleId = (int)($_POST['new_role_id'] ?? 0);

    if ($staffIdToUpdate <= 0 || $newRoleId <= 0) {
        $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Please select both a valid staff user and a role.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        try {
            // 1. Fetch staff member from user_access
            $stmtGet = $con->prepare("SELECT staffIDs, title, FirstName, MiddleName, LastName, EmailAddress, userName, userRoleID FROM user_access WHERE staffIDs = ? LIMIT 1");
            $stmtGet->execute([$staffIdToUpdate]);
            $staffRow = $stmtGet->fetch(PDO::FETCH_ASSOC);

            if (!$staffRow) {
                $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Staff record not found in user_access.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
            } else {
                $staffEmail = $staffRow['EmailAddress'];
                $staffUser = $staffRow['userName'];

                // 2. Update user_access table ONLY
                $stmtUp = $con->prepare("UPDATE user_access SET userRoleID = ? WHERE staffIDs = ?");
                $stmtUp->execute([$newRoleId, $staffIdToUpdate]);

                // 3. Resolve role name for display message
                $roleDisplayName = "Role #{$newRoleId}";
                try {
                    $stmtR = $con->prepare("SELECT role_name FROM roles WHERE role_id = ? LIMIT 1");
                    $stmtR->execute([$newRoleId]);
                    $rName = $stmtR->fetchColumn();
                    if ($rName) {
                        $roleDisplayName = $rName;
                    } else {
                        $stmtLeg = $con->prepare("SELECT access FROM acd_tbluser WHERE ID = ? LIMIT 1");
                        $stmtLeg->execute([$newRoleId]);
                        $legName = $stmtLeg->fetchColumn();
                        if ($legName) $roleDisplayName = $legName;
                    }
                } catch (Throwable $e) {}

                // 4. Purge cached sidebar menu entries so the staff's new role menus load fresh
                try {
                    if (!empty($staffUser)) {
                        $con->prepare("DELETE FROM pesonal_right_page_main_menus WHERE userID = ?")->execute([$staffUser]);
                        $con->prepare("DELETE FROM personal_page_menu_tab WHERE userID = ?")->execute([$staffUser]);
                    }
                    if (!empty($staffEmail)) {
                        $con->prepare("DELETE FROM pesonal_right_page_main_menus WHERE userID = ?")->execute([$staffEmail]);
                        $con->prepare("DELETE FROM personal_page_menu_tab WHERE userID = ?")->execute([$staffEmail]);
                    }
                    $con->prepare("DELETE FROM pesonal_right_page_main_menus WHERE userID = ?")->execute([$staffIdToUpdate]);
                    $con->prepare("DELETE FROM personal_page_menu_tab WHERE userID = ?")->execute([$staffIdToUpdate]);
                } catch (Throwable $e) {}

                $msg = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i><strong>Success!</strong> Staff role has been updated to <strong>' . htmlspecialchars($roleDisplayName) . '</strong> in <code>user_access</code>.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';

                $selectedStaffId = $staffIdToUpdate;
            }
        } catch (Throwable $e) {
            $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    }
}

// Fetch all staff users exclusively from user_access
$allStaff = [];
try {
    $stmtStaff = $con->query("
        SELECT staffIDs, title, FirstName, MiddleName, LastName, EmailAddress, userName, userRoleID
        FROM user_access
        ORDER BY FirstName ASC, LastName ASC
    ");
    if ($stmtStaff) {
        $allStaff = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {}

// Fetch roles list for dropdown
$rolesList = [];
try {
    $rStmt = $con->query("SELECT role_id AS id, role_name AS name, role_key AS code FROM roles ORDER BY role_name ASC");
    if ($rStmt) {
        $rolesList = $rStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {}

// If roles table is empty, fallback to acd_tbluser
if (empty($rolesList)) {
    try {
        $legStmt = $con->query("SELECT ID AS id, access AS name, access AS code FROM acd_tbluser ORDER BY access ASC");
        if ($legStmt) {
            $rolesList = $legStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {}
}

// Helper to get role name from ID
function getRoleNameById($roleId, $con) {
    if (empty($roleId)) return 'Unassigned';
    try {
        $stmt = $con->prepare("SELECT role_name FROM roles WHERE role_id = ? LIMIT 1");
        $stmt->execute([$roleId]);
        $name = $stmt->fetchColumn();
        if ($name) return $name;

        $stmt2 = $con->prepare("SELECT access FROM acd_tbluser WHERE ID = ? LIMIT 1");
        $stmt2->execute([$roleId]);
        $name2 = $stmt2->fetchColumn();
        if ($name2) return $name2;
    } catch (Throwable $e) {}
    return "Role #{$roleId}";
}

// If a staff ID is specified, load their info from user_access
if (!empty($selectedStaffId)) {
    try {
        $stmtSelected = $con->prepare("SELECT * FROM user_access WHERE staffIDs = ? OR userName = ? OR EmailAddress = ? LIMIT 1");
        $stmtSelected->execute([$selectedStaffId, $selectedStaffId, $selectedStaffId]);
        $targetStaff = $stmtSelected->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

$mypagename = "Change Staff Role";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Change Staff Role - IPESS Admin</title>
  <?php include("inc/htmlheaderotherfolders.php"); ?>
  <style>
    .staff-card-highlight {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 20px;
    }
    .role-badge {
      font-size: 0.95rem;
      padding: 8px 16px;
      border-radius: 20px;
    }
  </style>
</head>

<body>
  <!-- ======= Header ======= -->
  <?php include("inc/inerheader.php"); ?>
  <!-- End Header -->

  <!-- ======= Sidebar ======= -->
  <?php include("inc/sidebar.php"); ?>
  <!-- End Sidebar-->

  <main id="main" class="main">
    <div class="pagetitle d-flex justify-content-between align-items-center mb-3">
      <div>
        <h1>Change Staff Role</h1>
        <nav>
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item"><a href="manage_users.php">Staff Management</a></li>
            <li class="breadcrumb-item active">Change Staff Role</li>
          </ol>
        </nav>
      </div>
      <div>
        <a href="manage_users.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left me-1"></i> Back to Staff List
        </a>
      </div>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-8 mx-auto">

          <?= $msg ?>

          <!-- Card: Select and Change Staff Role -->
          <div class="card shadow-sm border-0">
            <div class="card-body p-4">
              <h5 class="card-title fw-bold text-primary mb-3">
                <i class="bi bi-person-badge-fill me-2"></i>Modify Staff Role (<code>user_access</code>)
              </h5>
              <p class="text-muted small mb-4">
                Select a staff or admin member from <code>user_access</code> and assign their role.
              </p>

              <!-- Staff Selector Dropdown -->
              <form method="GET" action="change_user_role.php" class="mb-4">
                <div class="row g-3 align-items-center">
                  <div class="col-md-9">
                    <label class="form-label fw-semibold">Select Staff Member:</label>
                    <select name="id" class="form-select form-select-lg" onchange="this.form.submit()">
                      <option value="">-- Choose Staff User --</option>
                      <?php foreach ($allStaff as $st): 
                        $stFullName = trim("{$st['title']} {$st['FirstName']} {$st['MiddleName']} {$st['LastName']}");
                        $stRoleName = getRoleNameById($st['userRoleID'], $con);
                        $isSelected = ($targetStaff && $targetStaff['staffIDs'] == $st['staffIDs']) ? 'selected' : '';
                      ?>
                        <option value="<?= htmlspecialchars($st['staffIDs']) ?>" <?= $isSelected ?>>
                          <?= htmlspecialchars($stFullName) ?> (<?= htmlspecialchars($st['userName']) ?> - <?= htmlspecialchars($st['EmailAddress']) ?>) [<?= htmlspecialchars($stRoleName) ?>]
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-secondary w-100 py-2">
                      <i class="bi bi-search me-1"></i> Load Staff
                    </button>
                  </div>
                </div>
              </form>

              <?php if ($targetStaff): 
                $targetFullName = trim("{$targetStaff['title']} {$targetStaff['FirstName']} {$targetStaff['MiddleName']} {$targetStaff['LastName']}");
                $currentRoleName = getRoleNameById($targetStaff['userRoleID'], $con);
              ?>
                <div class="staff-card-highlight mb-4">
                  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                      <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($targetFullName) ?></h5>
                      <div class="text-muted small">
                        <i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($targetStaff['EmailAddress']) ?> &nbsp;|&nbsp;
                        <i class="bi bi-person me-1"></i> Username: <strong><?= htmlspecialchars($targetStaff['userName']) ?></strong> &nbsp;|&nbsp;
                        <i class="bi bi-key me-1"></i> Staff ID: <strong><?= htmlspecialchars($targetStaff['staffIDs']) ?></strong>
                      </div>
                    </div>
                    <div>
                      <span class="badge bg-primary role-badge">
                        <i class="bi bi-shield-check me-1"></i> Current Role: <?= htmlspecialchars($currentRoleName) ?>
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Update Role Form -->
                <form method="POST" action="change_user_role.php">
                  <input type="hidden" name="staff_id" value="<?= htmlspecialchars($targetStaff['staffIDs']) ?>">

                  <div class="mb-4">
                    <label class="form-label fw-bold text-dark">Assign New Role <span class="text-danger">*</span></label>
                    <select name="new_role_id" class="form-select form-select-lg" required>
                      <option value="">-- Select New Role --</option>
                      <?php foreach ($rolesList as $role): 
                        $isCurrent = ($targetStaff['userRoleID'] == $role['id']);
                      ?>
                        <option value="<?= htmlspecialchars($role['id']) ?>" <?= $isCurrent ? 'selected' : '' ?>>
                          <?= htmlspecialchars($role['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <div class="form-text mt-2 text-muted">
                      This updates the staff's <code>userRoleID</code> in the <code>user_access</code> table.
                    </div>
                  </div>

                  <div class="d-flex justify-content-end gap-2">
                    <a href="manage_users.php" class="btn btn-light px-4">Cancel</a>
                    <button type="submit" name="update_role" class="btn btn-primary px-4 fw-bold">
                      <i class="bi bi-check-lg me-1"></i> Save Staff Role
                    </button>
                  </div>
                </form>
              <?php else: ?>
                <div class="text-center py-4 text-muted">
                  <i class="bi bi-people" style="font-size: 3rem; color: #cbd5e1;"></i>
                  <p class="mt-2 mb-0">Please select a staff member from <code>user_access</code> above to change their role.</p>
                </div>
              <?php endif; ?>

            </div>
          </div>

        </div>
      </div>
    </section>
  </main>

  <!-- ======= Footer ======= -->
  <?php include("inc/footer.php"); ?>
  <!-- End Footer -->

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>
</body>
</html>
