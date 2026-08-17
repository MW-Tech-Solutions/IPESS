<?php
session_start();
include("inc/main.config.php");
if($_SESSION['roleid']!="" && $_SESSION['roleid']!=""){
		$rolesession = $_SESSION['roleid'];
		$usersession = $_SESSION['userid'];
		}
		if($usersession==""){
		header("location:index.php");
		}
date_default_timezone_set("Africa/Lagos");
		$logindate = date("Y-m-d H:m:s");
		$urrentdate = date("Y-m-d");
$userid = $_GET['id'];

$getstatus = $con->query("SELECT * FROM user_access WHERE staffIDs = '$userid' ")->fetch(PDO::FETCH_ASSOC);

$mystatus = $getstatus['activeStatus'];
$username = $getstatus['userName'];
if($username !=$usersession){
if($mystatus==1){
	
	$update = $con->query("UPDATE user_access SET `activeStatus` = '0',deactivatedBy = '$usersession', dateDeactivated = '$logindate' WHERE staffIDs = '$userid' ");
}else{
	
	$update = $con->query("UPDATE user_access SET `activeStatus` = '1' WHERE staffIDs = '$userid' ");
	
}

if($update){
	
	echo '<script>alert("Operation has been performed Successfully")</script>';
	echo '<script>window.location.href="manage_users.php"</script>';
}
}else{
	
	echo '<script>alert("Sorry, you cannot manage login status yourself")</script>';
	echo '<script>window.location.href="manage_users.php"</script>';
	
}

?>