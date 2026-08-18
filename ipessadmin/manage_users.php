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
  <title>Contact Admin</title>
  <?php include("inc/htmlheaderotherfolders.php"); ?>
</head>

<body>

<?php include("inc/inerheader.php"); ?>
<?php include("inc/sidebar.php"); ?>

<main id="main" class="main">

<div class="pagetitle">
  <h1>User Details</h1>
</div>

<section class="section">
<div class="row">
<div class="col-md-12">

<div class="card">
<div class="card-body">

<?= $msg ?>

<div class="table-responsive text-nowrap">
<table class="table datatable table-striped">
<thead>
<tr>
  <th>S/No</th>
  <th>Name</th>
  <th>Position</th>
  <th>Phone</th>
  <th>Email</th>
  <th>UserName</th>
  <th>Active Status</th>
  <th>Approve Status</th>
  <th>Manage</th>
</tr>
</thead>
<tbody>

<?php
$sno = 0;
$stmt = $con->prepare("SELECT * FROM user_access WHERE approveUser = 'approved'");
$stmt->execute();

while ($readusers = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sno++;

    $fullusername = trim(
        $readusers['title']." ".
        $readusers['FirstName']." ".
        $readusers['MiddleName']." ".
        $readusers['LastName']
    );

    $roleName = 'N/A';
    try {
        $roleStmt = $con->prepare("SELECT access FROM acd_tbluser WHERE ID = ?");
        $roleStmt->execute([$readusers['userRoleID']]);
        $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
        if ($role) {
            $roleName = $role['access'];
        }
    } catch (Throwable $e) {
        try {
            $roleStmt = $con->prepare("SELECT role_name FROM roles WHERE role_id = ? LIMIT 1");
            $roleStmt->execute([$readusers['userRoleID']]);
            $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
            if ($role) {
                $roleName = $role['role_name'];
            }
        } catch (Throwable $ex) {}
    }
?>

<tr>
  <td><?= $sno ?></td>
  <td><?= htmlspecialchars($fullusername) ?></td>
  <td><?= htmlspecialchars($roleName) ?></td>
  <td><?= htmlspecialchars($readusers['phoneno']) ?></td>
  <td><?= htmlspecialchars($readusers['EmailAddress']) ?></td>
  <td><?= htmlspecialchars($readusers['userName']) ?></td>

  <td>
    <?php if ($readusers['activeStatus'] == "1") { ?>
        <span class="badge bg-success">Active</span>
    <?php } else { ?>
        <span class="badge bg-danger">Deactivated</span>
    <?php } ?>
  </td>

  <td><?= htmlspecialchars($readusers['approveUser']) ?></td>

  <td>
    <a href="manage_user_status.php?id=<?= $readusers['staffIDs'] ?>" class="btn btn-sm btn-primary">
      Manage
    </a>
    <a href="change_user_role.php?id=<?= $readusers['staffIDs'] ?>" class="btn btn-sm btn-outline-warning ms-1">
      <i class="bi bi-shield-lock me-1"></i>Change Role
    </a>
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
