<?php
include("inc/main.config.php");

$userid = $_GET['id'];
//$checkactivity = $con->query("SELECT * FROM user_access WHERE staffIDs = '$userid' ")->fetch(PDO::FETCH_ASSOC);//
//$getusername = $checkactivity['userName'];


$delete = $con->query("DELETE FROM sch_departmental_officer WHERE ID = '$userid'  ");


if($delete){
	
	echo '<script>alert("Record has been deleted Successfully")</script>';
	echo '<script>window.location.href="manage_department_users.php"</script>';

}
?>