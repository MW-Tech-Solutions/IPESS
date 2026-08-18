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
$selectedUserId = $_GET['id'] ?? $_POST['user_id'] ?? '';
$targetUser = null;

// Handle Role Update Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $userIdToUpdate = trim($_POST['target_user_id'] ?? '');
    $newRoleId = (int)($_POST['new_role_id'] ?? 0);

    if (empty($userIdToUpdate) || $newRoleId <= 0) {
        $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Please select both a valid user and a target role.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    } else {
        try {
            // 1. Get role details from roles table
            $stmtRole = $con->prepare("SELECT role_id, role_key, role_name FROM roles WHERE role_id = ? LIMIT 1");
            $stmtRole->execute([$newRoleId]);
            $roleInfo = $stmtRole->fetch(PDO::FETCH_ASSOC);
            $roleName = $roleInfo['role_name'] ?? 'Role #' . $newRoleId;
            $roleKey = $roleInfo['role_key'] ?? 'GENERAL';

            // 2. Fetch target user to get user_id & email
            $stmtGet = $con->prepare("SELECT user_id, email, full_name FROM users WHERE user_id = ? OR email = ? LIMIT 1");
            $stmtGet->execute([$userIdToUpdate, $userIdToUpdate]);
            $userRow = $stmtGet->fetch(PDO::FETCH_ASSOC);

            $userEmail = $userRow['email'] ?? $userIdToUpdate;
            $uid = $userRow['user_id'] ?? $userIdToUpdate;

            // 3. Update modern users table
            $stmtUpUsers = $con->prepare("UPDATE users SET role_id = ? WHERE user_id = ? OR email = ?");
            $stmtUpUsers->execute([$newRoleId, $uid, $userEmail]);

            // 4. Update legacy user_access table
            try {
                $stmtUpUA = $con->prepare("UPDATE user_access SET userRoleID = ? WHERE EmailAddress = ? OR staffIDs = ? OR userName = ?");
                $stmtUpUA->execute([$newRoleId, $userEmail, $uid, $userEmail]);
            } catch (Throwable $e) {}

            // 5. Purge stale cached sidebar rows so the new role's menus take effect immediately
            try {
                if ($userEmail !== '') {
                    $con->prepare("DELETE FROM pesonal_right_page_main_menus WHERE userID = ?")->execute([$userEmail]);
                    $con->prepare("DELETE FROM personal_page_menu_tab WHERE userID = ?")->execute([$userEmail]);
                }
                if ($uid !== '') {
                    $con->prepare("DELETE FROM pesonal_right_page_main_menus WHERE userID = ?")->execute([$uid]);
                    $con->prepare("DELETE FROM personal_page_menu_tab WHERE userID = ?")->execute([$uid]);
                }
            } catch (Throwable $e) {}

            $msg = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i><strong>Success!</strong> User role has been changed to <strong>' . htmlspecialchars($roleName) . '</strong> (' . htmlspecialchars($roleKey) . '). Permissions and sidebar navigation have been refreshed.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';

            $selectedUserId = $uid;
        } catch (Throwable $e) {
            $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    }
}

// Fetch all staff users for the dropdown directly from users table joined with roles
$allStaff = [];
try {
    $stmtUsers = $con->query("
        SELECT u.user_id, u.email AS EmailAddress, u.full_name, u.role_id,
               COALESCE(r.role_name, 'Unassigned') AS current_role_name,
               u.user_id AS staff_id,
               u.email AS username
        FROM users u
        LEFT JOIN roles r ON r.role_id = u.role_id
        ORDER BY u.full_name ASC, u.email ASC
    ");
    if ($stmtUsers) {
        $allStaff = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    try {
        $stmtUA = $con->query("
            SELECT staffIDs AS staff_id, staffIDs AS user_id, EmailAddress,
                   TRIM(CONCAT(COALESCE(title, ''), ' ', COALESCE(FirstName, ''), ' ', COALESCE(LastName, ''))) AS full_name,
                   userName AS username, userRoleID AS role_id, 'Staff' AS current_role_name
            FROM user_access
            ORDER BY FirstName ASC
        ");
        if ($stmtUA) {
            $allStaff = $stmtUA->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $ex) {}
}

// If a user ID is specified, load their info
if (!empty($selectedUserId)) {
    try {
        $stmtUser = $con->prepare("
            SELECT u.user_id, u.email AS EmailAddress, u.full_name, u.role_id,
                   COALESCE(r.role_name, 'Unassigned') AS modern_role_name,
                   r.role_key,
                   u.user_id AS staff_id,
                   u.email AS userName
            FROM users u
            LEFT JOIN roles r ON r.role_id = u.role_id
            WHERE u.user_id = ? OR u.email = ?
            LIMIT 1
        ");
        $stmtUser->execute([$selectedUserId, $selectedUserId]);
        $targetUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        try {
            $stmtUser2 = $con->prepare("
                SELECT staffIDs AS user_id, staffIDs AS staff_id, EmailAddress,
                       TRIM(CONCAT(COALESCE(title, ''), ' ', COALESCE(FirstName, ''), ' ', COALESCE(LastName, ''))) AS full_name,
                       userName, userRoleID AS role_id, 'Staff' AS modern_role_name
                FROM user_access
                WHERE staffIDs = ? OR userName = ? OR EmailAddress = ?
                LIMIT 1
            ");
            $stmtUser2->execute([$selectedUserId, $selectedUserId, $selectedUserId]);
            $targetUser = $stmtUser2->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $ex) {}
    }
}

// Fetch all available roles
$allRoles = [];
try {
    $allRoles = $con->query("SELECT role_id, role_key, role_name FROM roles ORDER BY role_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$mypagename = "Change User Role";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Change User Role - IPESS Admin</title>
  <?php include("inc/htmlheaderotherfolders.php"); ?>
  <style>
    .user-card-highlight {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 20px;
    }
    .role-badge {
      font-size: 0.9rem;
      padding: 6px 14px;
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
        <h1>Change User Role</h1>
        <nav>
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item"><a href="manage_users.php">Staff Management</a></li>
            <li class="breadcrumb-item active">Change Role</li>
          </ol>
        </nav>
      </div>
      <div>
        <a href="manage_users.php" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-left me-1"></i> Back to User List
        </a>
      </div>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-8 mx-auto">

          <?= $msg ?>

          <!-- Card: Select and Change User Role -->
          <div class="card shadow-sm border-0">
            <div class="card-body p-4">
              <h5 class="card-title fw-bold text-primary mb-3">
                <i class="bi bi-shield-shaded me-2"></i>Modify User Role & Privileges
              </h5>
              <p class="text-muted small mb-4">
                Select a user and choose a new role. Changing the role will immediately update their dashboard permissions and sidebar modules.
              </p>

              <form method="GET" action="change_user_role.php" class="mb-4">
                <div class="row g-3 align-items-center">
                  <div class="col-md-9">
                    <label class="form-label fw-semibold">Select User to Manage:</label>
                    <select name="id" class="form-select form-select-lg" onchange="this.form.submit()">
                      <option value="">-- Choose a Staff User --</option>
                      <?php foreach ($allStaff as $st): 
                        $stName = !empty($st['full_name']) ? $st['full_name'] : $st['EmailAddress'];
                        $stId = !empty($st['user_id']) ? $st['user_id'] : $st['staff_id'];
                        $isSelected = ($targetUser && ($targetUser['user_id'] == $stId || $targetUser['EmailAddress'] == $st['EmailAddress'])) ? 'selected' : '';
                      ?>
                        <option value="<?= htmlspecialchars($stId) ?>" <?= $isSelected ?>>
                          <?= htmlspecialchars($stName) ?> (<?= htmlspecialchars($st['EmailAddress']) ?>) - Current: <?= htmlspecialchars($st['current_role_name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-secondary w-100 py-2">
                      <i class="bi bi-search me-1"></i> Load User
                    </button>
                  </div>
                </div>
              </form>

              <?php if ($targetUser): 
                $targetFull = !empty($targetUser['full_name']) ? $targetUser['full_name'] : $targetUser['EmailAddress'];
                $currentRoleDisplay = !empty($targetUser['modern_role_name']) ? $targetUser['modern_role_name'] : 'Unassigned / Custom';
              ?>
                <div class="user-card-highlight mb-4">
                  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                      <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($targetFull) ?></h5>
                      <div class="text-muted small">
                        <i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($targetUser['EmailAddress']) ?> &nbsp;|&nbsp;
                        <i class="bi bi-person me-1"></i> Username: <strong><?= htmlspecialchars($targetUser['userName'] ?? $targetUser['EmailAddress']) ?></strong>
                      </div>
                    </div>
                    <div>
                      <span class="badge bg-primary role-badge">
                        <i class="bi bi-shield-check me-1"></i> Current Role: <?= htmlspecialchars($currentRoleDisplay) ?>
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Update Role Form -->
                <form method="POST" action="change_user_role.php">
                  <input type="hidden" name="target_user_id" value="<?= htmlspecialchars($targetUser['user_id'] ?? $targetUser['staff_id']) ?>">

                  <div class="mb-4">
                    <label class="form-label fw-bold text-dark">Assign New Role <span class="text-danger">*</span></label>
                    <select name="new_role_id" class="form-select form-select-lg" required>
                      <option value="">-- Select New Role --</option>
                      <?php foreach ($allRoles as $role): 
                        $isCurrentRole = ($targetUser['role_id'] == $role['role_id']);
                      ?>
                        <option value="<?= htmlspecialchars($role['role_id']) ?>" <?= $isCurrentRole ? 'selected' : '' ?>>
                          <?= htmlspecialchars($role['role_name']) ?> (<?= htmlspecialchars($role['role_key']) ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <div class="form-text mt-2 text-muted">
                      Assigning a new role adjusts the user's dashboard view, approval gates, and menu permissions across the entire portal.
                    </div>
                  </div>

                  <div class="d-flex justify-content-end gap-2">
                    <a href="manage_users.php" class="btn btn-light px-4">Cancel</a>
                    <button type="submit" name="update_role" class="btn btn-primary px-4 fw-bold">
                      <i class="bi bi-check-lg me-1"></i> Save & Apply Role Change
                    </button>
                  </div>
                </form>
              <?php else: ?>
                <div class="text-center py-4 text-muted">
                  <i class="bi bi-person-badge" style="font-size: 3rem; color: #cbd5e1;"></i>
                  <p class="mt-2 mb-0">Please select a user above to view and modify their assigned role.</p>
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
