<?php
include("inc/main.config.php");

$userid = $_GET['id'];
$checkactivity = $con->query("SELECT * FROM user_access WHERE staffIDs = '$userid' ")->fetch(PDO::FETCH_ASSOC);
$getusername = $checkactivity['userName'];

$checksession = $con->query("SELECT * FROM userloginsession WHERE username = '$getusername' ")->rowCount();
if($checksession>0){
	
	echo '<script>alert("This User you want to delete has performed many task and conly be deactivated")</script>';
	echo '<script>window.location.href="manage_users"</script>';
}else{

$delete = $con->query("DELETE FROM user_access WHERE staffIDs = '$userid' AND staffIDs = '$userid' ");


if($delete){
	
	echo '<script>alert("User has been deleted Successfully")</script>';
	echo '<script>window.location.href="manage_users.php"</script>';
}
}
?>