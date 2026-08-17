<?php
//session_start();
	if(!empty($_SESSION['roleid'])){
		$rolesession = $_SESSION['roleid'];
		$usersession = $_SESSION['userid'];
	}
		
	$checklogin = 0;
	try {
		$stmtCheck = $con->prepare("SELECT COUNT(*) FROM users WHERE (user_id = ? OR email = ?) AND account_status != 'Active'");
		$stmtCheck->execute([$usersession, $usersession]);
		$checklogin = (int)$stmtCheck->fetchColumn();
	} catch (Throwable $e) {}
	
	if ($checklogin === 0) {
		try {
			$stmtCheckLegacy = $con->prepare("SELECT COUNT(*) FROM user_access WHERE (userName = ? OR staffIDs = ?) AND activeStatus = '0'");
			$stmtCheckLegacy->execute([$usersession, $usersession]);
			$checklogin = (int)$stmtCheckLegacy->fetchColumn();
		} catch (Throwable $e) {}
	}

	if($checklogin > 0){
		header("location:logout.php");
		exit;
	}
		
		date_default_timezone_set("Africa/Lagos");
		//Creating Function
function TimeAgo ($oldTime, $newTime) {
$timeCalc = strtotime($newTime) - strtotime($oldTime);
if ($timeCalc >= (60*60*24*30*12*2)){
	$timeCalc = intval($timeCalc/60/60/24/30/12) . " years ago";
	}else if ($timeCalc >= (60*60*24*30*12)){
		$timeCalc = intval($timeCalc/60/60/24/30/12) . " year ago";
	}else if ($timeCalc >= (60*60*24*30*2)){
		$timeCalc = intval($timeCalc/60/60/24/30) . " months ago";
	}else if ($timeCalc >= (60*60*24*30)){
		$timeCalc = intval($timeCalc/60/60/24/30) . " month ago";
	}else if ($timeCalc >= (60*60*24*2)){
		$timeCalc = intval($timeCalc/60/60/24) . " days ago";
	}else if ($timeCalc >= (60*60*24)){
		$timeCalc = " Yesterday";
	}else if ($timeCalc >= (60*60*2)){
		$timeCalc = intval($timeCalc/60/60) . " hours ago";
	}else if ($timeCalc >= (60*60)){
		$timeCalc = intval($timeCalc/60/60) . " hour ago";
	}else if ($timeCalc >= 60*2){
		$timeCalc = intval($timeCalc/60) . " minutes ago";
	}else if ($timeCalc >= 60){
		$timeCalc = intval($timeCalc/60) . " minute ago";
	}else if ($timeCalc > 0){
		$timeCalc .= " seconds ago";
	}
return $timeCalc;
}

		
		
		
		
		
		
	
	
$images = '';
$fullname = 'System User';
$emailddress = '';
$phoneno = '';

// 1. Try fetching from users table
$userFound = false;
try {
    $stmtUser = $con->prepare("SELECT * FROM users WHERE user_id = ? OR email = ? LIMIT 1");
    $stmtUser->execute([$usersession, $usersession]);
    $uData = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($uData) {
        $fullname = $uData['full_name'] ?? 'System User';
        $emailddress = $uData['email'] ?? '';
        $images = $uData['avatar_url'] ?? '';
        $userFound = true;
    }
} catch (Throwable $e) {}

// 2. Try fetching from user_access table if not found in users
if (!$userFound) {
    try {
        $stmtAccess = $con->prepare("SELECT * FROM user_access WHERE userName = ? OR staffIDs = ? LIMIT 1");
        $stmtAccess->execute([$usersession, $usersession]);
        $getprofile = $stmtAccess->fetch(PDO::FETCH_ASSOC);
        if ($getprofile) {
            $images = $getprofile['pixUrl'] ?? '';
            $fullname = trim(($getprofile['title'] ?? '') . " " . ($getprofile['FirstName'] ?? '') . " " . ($getprofile['MiddleName'] ?? '') . " " . ($getprofile['LastName'] ?? ''));
            $emailddress = $getprofile['EmailAddress'] ?? '';
            $phoneno = $getprofile['phoneno'] ?? '';
        }
    } catch (Throwable $e) {}
}

$getrole = $con->query("SELECT * FROM acd_tbluser WHERE ID = '$rolesession' OR LOWER(access) = LOWER('$rolesession') LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$rolename = $getrole ? $getrole['access'] : $rolesession;

$getorgan= $con->query("SELECT * FROM organization_information  ")->fetch(PDO::FETCH_ASSOC);
$oragname = $getorgan['organName'];
$organCode = $getorgan['organCode'];

$getsuport = $con->query("SELECT * FROM assign_user_support WHERE userName = '$usersession' ")->fetch(PDO::FETCH_ASSOC);
	$supportid = $getsuport['supportID'] ?? "";

$countmsge = $con->query("SELECT * FROM contact_message WHERE SupportID = '$supportid' AND pendingStatus = 'pending' ")->rowCount();
$mymsge = $con->query("SELECT * FROM contact_message WHERE SupportID = '$supportid' AND pendingStatus = 'pending' ");

?>
<header id="header" class="header fixed-top d-flex align-items-center">

    <div class="d-flex align-items-center justify-content-between">
      <a href="<?php echo app_url('ipessadmin'); ?>" class="logo d-flex align-items-center" style="text-decoration: none;">
        <span class="d-none d-lg-block" style="color: #012970; font-size: 22px; font-weight: 700; font-family: 'Nunito', sans-serif;">IPESS</span>
      </a>
      <i class="bi bi-list toggle-sidebar-btn"></i>
    </div><!-- End Logo -->

    <div class="search-bar">
      <!--<form class="search-form d-flex align-items-center" method="POST" action="#">
        <input type="text" name="query" placeholder="Search" title="Enter search keyword">
        <button type="submit" title="Search"><i class="bi bi-search"></i></button>
      </form>-->
    </div><!-- End Search Bar -->

    <nav class="header-nav ms-auto">
      <ul class="d-flex align-items-center">

        <li class="nav-item d-block d-lg-none">
          <a class="nav-link nav-icon search-bar-toggle " href="#">
            <i class="bi bi-search"></i>
          </a>
        </li>
		<!-- End Search Icon-->

		<!-- End Notification Nav -->

        <li class="nav-item dropdown">

          <a class="nav-link nav-icon" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-chat-left-text"></i>
            <span class="badge bg-success badge-number"><?php echo $countmsge ?></span>
          </a><!-- End Messages Icon -->

          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow messages">
            <li class="dropdown-header">
              You have <?php echo $countmsge ?> new messages
              <a href="#"><span class="badge rounded-pill bg-primary p-2 ms-2">View all</span></a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>
<?php
			while($getmsg = $mymsge->fetch(PDO::FETCH_ASSOC)){
				$userids = $getmsg['fromuserID'];
			$getmsgprofile = $con->query("SELECT * FROM user_access WHERE userName = '$userids' ")->fetch(PDO::FETCH_ASSOC);
/*
              $msgimages = $getmsgprofile['pixUrl'];
$msgfullname = $getmsgprofile['title']." ".$getmsgprofile['FirstName']." ".$getmsgprofile['MiddleName']." ".$getmsgprofile['LastName'];	
			*/
              $msgimages = $getmsgprofile['pixUrl'] ?? "";
              $titles = $getmsgprofile['title'] ?? "";
              $surname = $getmsgprofile['FirstName'] ?? "";
              $middname = $getmsgprofile['MiddleName'] ?? "";
              $lastnames = $getmsgprofile['LastName'] ?? "";
              $msgfullname = $titles.' '.$surname.' '.$middname.' '.$lastnames;
              $messagetilte = $getmsg['msgSubject'];
			$createdtime = $getmsg['createdtime'];
			
			?>
            <li class="message-item">
			
              <a href="#">
                <img src="<?php echo $msgimages ?>" alt="" class="rounded-circle">
                <div>
                  <h6><?php echo $msgfullname ?></h6>
                  <p><?php echo $messagetilte ?></p>
                  <p><?php echo TimeAgo($createdtime, date("Y-m-d H:i:s"));?></p>
                </div>
              </a>
			 
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>
				<?php
			}
			  ?>
            <!--<li class="message-item">
              <a href="#">
                <img src="assets/img/messages-2.jpg" alt="" class="rounded-circle">
                <div>
                  <h4>Anna Nelson</h4>
                  <p>Velit asperiores et ducimus soluta repudiandae labore officia est ut...</p>
                  <p>6 hrs. ago</p>
                </div>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li class="message-item">
              <a href="#">
                <img src="assets/img/messages-3.jpg" alt="" class="rounded-circle">
                <div>
                  <h4>David Muldon</h4>
                  <p>Velit asperiores et ducimus soluta repudiandae labore officia est ut...</p>
                  <p>8 hrs. ago</p>
                </div>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>-->

            <li class="dropdown-footer">
              <a href="#">Show all messages</a>
            </li>

          </ul><!-- End Messages Dropdown Items -->

        </li><!-- End Messages Nav -->

        <li class="nav-item dropdown pe-3">

          <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
            <img src="<?php echo $images ?>" alt="Profile" class="rounded-circle">
            <span class="d-none d-md-block dropdown-toggle ps-2"><?php echo $fullname ?></span>
          <!-- End Profile Iamge Icon -->
            </a>

          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
            <li class="dropdown-header">
              <h6><?php echo $fullname ?></h6>
              <span><?php echo $rolename ?></span>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li>
              <a class="dropdown-item d-flex align-items-center" href="user_profile.php">
                <i class="bi bi-person"></i>
                <span>My Profile</span>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li>
              <a class="dropdown-item d-flex align-items-center" href="user_profile.php">
                <i class="bi bi-gear"></i>
                <span>Account Settings</span>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li>
              <a class="dropdown-item d-flex align-items-center" href="frequestquestions.php">
                <i class="bi bi-question-circle"></i>
                <span>Need Help?</span>
              </a>
            </li>
            <li>
              <hr class="dropdown-divider">
            </li>

            <li>
              <a class="dropdown-item d-flex align-items-center" href="logout.php">
                <i class="bi bi-box-arrow-right"></i>
                <span>Sign Out</span>
              </a>
            </li>

          </ul><!-- End Profile Dropdown Items -->
        </li><!-- End Profile Nav -->

      </ul>
    </nav><!-- End Icons Navigation -->

  </header>