<?php
session_start();
include("inc/main.config.php");
include("inc/selectorVendor.php");

/* =========================
   SESSION VALIDATION
========================= */
if (!isset($_SESSION['roleid'], $_SESSION['userid']) || $_SESSION['roleid'] == "" || $_SESSION['userid'] == "") {
    header("location:index.php");
    exit;
}

$rolesession = $_SESSION['roleid'];
$usersession = $_SESSION['userid'];

date_default_timezone_set("Africa/Lagos");
$datecreated = date("Y-m-d");
$today = date("Y-m-d H:i:s");
$msg = "";

/* =========================
   HANDLE PASSWORD RESET (user_access ONLY)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $staffIdToReset = (int)($_POST['target_staff_id'] ?? 0);
    if ($staffIdToReset > 0) {
        try {
            $stmtFind = $con->prepare("SELECT staffIDs, title, FirstName, LastName, userName, EmailAddress FROM user_access WHERE staffIDs = ? LIMIT 1");
            $stmtFind->execute([$staffIdToReset]);
            $staffRow = $stmtFind->fetch(PDO::FETCH_ASSOC);

            if ($staffRow) {
                $newPlain = '1234567';
                $newMd5 = md5($newPlain);

                // Update password in user_access
                $stmtUpdate = $con->prepare("UPDATE user_access SET passWord = ? WHERE staffIDs = ?");
                $stmtUpdate->execute([$newMd5, $staffIdToReset]);

                $staffFullName = trim("{$staffRow['title']} {$staffRow['FirstName']} {$staffRow['LastName']}");
                $staffUsername = $staffRow['userName'];

                $msg = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            Password for <strong>' . htmlspecialchars($staffFullName) . '</strong> (<code>' . htmlspecialchars($staffUsername) . '</code>) has been successfully reset to: <strong><code>1234567</code></strong> in <code>user_access</code>.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
            } else {
                $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Staff record not found in <code>user_access</code>.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
            }
        } catch (Throwable $e) {
            $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Error resetting password: ' . htmlspecialchars($e->getMessage()) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
        }
    }
}

/* =========================
   SEND MESSAGE
========================= */
if (isset($_POST['sendmsg'])) {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name && $email && $subject && $message) {
        $stmt = $con->prepare("
            INSERT INTO contact_message
            (ToUserID, emaildress, msgSubject, msgBody, datecreated, pendingStatus, dateTreated, treatedBy, fromuserID, createdtime)
            VALUES (?, ?, ?, ?, ?, 'pending', '', '', ?, ?)
        ");

        if ($stmt->execute([$name, $email, $subject, $message, $datecreated, $usersession, $today])) {
            $msg = '<div class="alert alert-success">Message sent successfully</div>';
        } else {
            $msg = '<div class="alert alert-danger">Failed to send message</div>';
        }
    } else {
        $msg = '<div class="alert alert-warning">All fields are required</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff & Admin Management - IPESS</title>
  <?php include("inc/htmlheaderotherfolders.php"); ?>
</head>

<body>

<?php include("inc/inerheader.php"); ?>
<?php include("inc/sidebar.php"); ?>

<main id="main" class="main">

<div class="pagetitle d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1>Staff & Admin Management</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
        <li class="breadcrumb-item active">Staff Users (<code>user_access</code>)</li>
      </ol>
    </nav>
  </div>
  <div>
    <a href="admin_createusers.php" class="btn btn-primary btn-sm">
      <i class="bi bi-person-plus-fill me-1"></i> Add New Staff
    </a>
  </div>
</div>

<section class="section">
<div class="row">
<div class="col-md-12">

<div class="card shadow-sm border-0">
<div class="card-body p-4">

<?= $msg ?>

<div class="table-responsive text-nowrap">
<table class="table datatable table-striped table-hover align-middle">
<thead>
<tr>
  <th>S/No</th>
  <th>Name</th>
  <th>Role / Position</th>
  <th>Phone</th>
  <th>Email</th>
  <th>Username</th>
  <th>Active Status</th>
  <th>Approve Status</th>
  <th class="text-center">Actions</th>
</tr>
</thead>
<tbody>

<?php
$sno = 0;
// Load all staff from user_access
$stmt = $con->query("SELECT * FROM user_access ORDER BY staffIDs DESC");

while ($readusers = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sno++;

    $fullusername = trim(
        $readusers['title']." ".
        $readusers['FirstName']." ".
        $readusers['MiddleName']." ".
        $readusers['LastName']
    );

    $roleName = 'Unassigned';
    try {
        $roleStmt = $con->prepare("SELECT role_name FROM roles WHERE role_id = ? LIMIT 1");
        $roleStmt->execute([$readusers['userRoleID']]);
        $rName = $roleStmt->fetchColumn();
        if ($rName) {
            $roleName = $rName;
        } else {
            $roleStmt2 = $con->prepare("SELECT access FROM acd_tbluser WHERE ID = ? LIMIT 1");
            $roleStmt2->execute([$readusers['userRoleID']]);
            $rName2 = $roleStmt2->fetchColumn();
            if ($rName2) $roleName = $rName2;
        }
    } catch (Throwable $e) {}
?>

<tr>
  <td><?= $sno ?></td>
  <td><strong><?= htmlspecialchars($fullusername) ?></strong></td>
  <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($roleName) ?></span></td>
  <td><?= htmlspecialchars($readusers['phoneno'] ?? '') ?></td>
  <td><?= htmlspecialchars($readusers['EmailAddress'] ?? '') ?></td>
  <td><code><?= htmlspecialchars($readusers['userName'] ?? '') ?></code></td>

  <td>
    <?php if ($readusers['activeStatus'] == "1") { ?>
        <span class="badge bg-success">Active</span>
    <?php } else { ?>
        <span class="badge bg-danger">Deactivated</span>
    <?php } ?>
  </td>

  <td>
    <?php if (strtolower($readusers['approveUser'] ?? '') === 'approved') { ?>
        <span class="badge bg-primary">Approved</span>
    <?php } else { ?>
        <span class="badge bg-warning text-dark"><?= htmlspecialchars($readusers['approveUser'] ?? 'Pending') ?></span>
    <?php } ?>
  </td>

  <td>
    <div class="d-flex align-items-center gap-1 justify-content-center">
      <a href="manage_user_status.php?id=<?= $readusers['staffIDs'] ?>" class="btn btn-sm btn-primary" title="Manage Status">
        <i class="bi bi-sliders me-1"></i>Status
      </a>
      <a href="change_user_role.php?id=<?= $readusers['staffIDs'] ?>" class="btn btn-sm btn-outline-warning" title="Change Role">
        <i class="bi bi-shield-lock me-1"></i>Role
      </a>
      
      <a href="reset_user_password.php?id=<?= $readusers['staffIDs'] ?>" class="btn btn-sm btn-outline-danger" title="Reset password to 1234567" onclick="return confirm('Are you sure you want to reset password for <?= htmlspecialchars(addslashes($fullusername)) ?> (<?= htmlspecialchars(addslashes($readusers['userName'])) ?>) to 1234567?');">
        <i class="bi bi-key-fill me-1"></i>Reset Pwd
      </a>
    </div>
  </td>
</tr>

<?php } ?>

</tbody>
</table>
</div>

</div>
</div>

</div>
</div>
</section>

</main>

<?php include("inc/footer.php"); ?>

<a href="#" class="back-to-top d-flex align-items-center justify-content-center">
  <i class="bi bi-arrow-up-short"></i>
</a>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/js/main.js"></script>

</body>
</html>
