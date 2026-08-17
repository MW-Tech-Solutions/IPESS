<?php
include("inc/main.config.php");

$userid = $_GET['id'];
//$checkactivity = $con->query("SELECT * FROM user_access WHERE staffIDs = '$userid' ")->fetch(PDO::FETCH_ASSOC);//
//$getusername = $checkactivity['userName'];


$delete = $con->query("DELETE FROM frq_asck_questions WHERE ID = '$userid'  ");


if($delete){
	
	echo '<script>alert("Record has been deleted Successfully")</script>';
	echo '<script>window.location.href="manage_fasq.php"</script>';

}
?>