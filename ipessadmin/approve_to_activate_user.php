<?php
	session_start();
	include("inc/main.config.php");
	
	if($_SESSION['roleid']!="" && $_SESSION['roleid']!=""){
			$rolesession = $_SESSION['roleid'];
			$usersession = $_SESSION['userid'];
			}
			
	date_default_timezone_set("Africa/Lagos");
			$logindate = date("Y-m-d H:m:s");
			$urrentdate = date("Y-m-d");
			
	$userid = $_GET['id'];

	$getstatus = $con->query("SELECT * FROM user_access WHERE staffIDs = '$userid' ")->fetch(PDO::FETCH_ASSOC);

	$mystatus = $getstatus['activeStatus'];
	$username = $getstatus['userName'];
	if($username !=$usersession){
		echo $mystatus;
		//echo "UPDATE user_access SET `approveUser` = 'approved',approveBy = '$usersession', dateApproved = '$logindate'  WHERE staffIDs = '$userid' ";
	if($mystatus==1){
	
	$update = $con->query("UPDATE user_access SET `approveUser` = 'approved',approveBy = '$usersession', dateApproved = '$logindate' WHERE staffIDs = '$userid' ");
	
	if($update){
	
	echo '<script>alert("User has been approved Successfully")</script>';
	echo '<script>window.location.href="approve_users.php"</script>';
	}
	
	}



	}else{
		
		echo '<script>alert("Sorry, you cannot approve user yourself")</script>';
		echo '<script>window.location.href="approve_users"</script>';
		
	}

?>