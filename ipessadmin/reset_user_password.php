<?php
session_start();
include("inc/main.config.php");

if (empty($_SESSION['roleid']) || empty($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}

$staffId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($staffId <= 0) {
    echo '<script>alert("Invalid staff ID specified."); window.location.href="manage_users.php";</script>';
    exit;
}

try {
    $stmtFind = $con->prepare("SELECT staffIDs, title, FirstName, LastName, userName, EmailAddress FROM user_access WHERE staffIDs = ? LIMIT 1");
    $stmtFind->execute([$staffId]);
    $staff = $stmtFind->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        echo '<script>alert("Staff record not found in user_access."); window.location.href="manage_users.php";</script>';
        exit;
    }

    $newPasswordPlain = '1234567';
    $newMd5 = md5($newPasswordPlain);

    $stmtUp = $con->prepare("UPDATE user_access SET passWord = ? WHERE staffIDs = ?");
    $stmtUp->execute([$newMd5, $staffId]);

    $fullName = trim("{$staff['title']} {$staff['FirstName']} {$staff['LastName']}");
    $user = $staff['userName'];

    echo '<script>alert("Password for ' . addslashes($fullName) . ' (' . addslashes($user) . ') has been successfully reset to: 1234567 in user_access."); window.location.href="manage_users.php";</script>';
    exit;
} catch (Throwable $e) {
    echo '<script>alert("Error resetting password: ' . addslashes($e->getMessage()) . '"); window.location.href="manage_users.php";</script>';
    exit;
}
