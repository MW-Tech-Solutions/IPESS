<?php 
		//session_start();
		include("main.config.php");
		
		if((!isset($_SESSION['roleid'])) && (!isset($_SESSION['roleid']))){
				
				header("Location:authlogin");
				
			}else{
		$rolesession = $_SESSION['roleid'];
		$usersession = $_SESSION['userid'];
			}
			
			
			
			
			
		$usertype = $con->query("SELECT * FROM dash_borad WHERE userType = '$rolesession' ")->fetch(PDO::FETCH_ASSOC);
			
			//$dashboard = explode(".",$usertype['pageName']);
			$dashboardname = !empty($usertype['pageName']) ? $usertype['pageName'] : ''; //$dashboard[0];
		
		// Determine exact role keys/IDs strictly for this staff user
		$possibleRoles = array_unique(array_filter([
			$rolesession,
			$_SESSION['role'] ?? '',
			$_SESSION['role_id'] ?? ''
		]));

		if ($usersession) {
			try {
				$stmtStaffRole = $con->prepare("SELECT userRoleID FROM user_access WHERE userName = ? OR staffIDs = ? OR EmailAddress = ? LIMIT 1");
				$stmtStaffRole->execute([$usersession, $usersession, $usersession]);
				$uRoleId = (int)$stmtStaffRole->fetchColumn();
				if ($uRoleId > 0) {
					$possibleRoles[] = (string)$uRoleId;
					
					// Resolve exact modern role_key & role_name
					$stmtModern = $con->prepare("SELECT role_key, role_name FROM roles WHERE role_id = ? OR role_key = ? LIMIT 1");
					$stmtModern->execute([$uRoleId, $rolesession]);
					$modRow = $stmtModern->fetch(PDO::FETCH_ASSOC);
					if ($modRow) {
						if (!empty($modRow['role_key'])) $possibleRoles[] = $modRow['role_key'];
						if (!empty($modRow['role_name'])) $possibleRoles[] = $modRow['role_name'];
					}

					// Resolve legacy acd_tbluser name
					$stmtAcd = $con->prepare("SELECT access FROM acd_tbluser WHERE ID = ? LIMIT 1");
					$stmtAcd->execute([$uRoleId]);
					$acdName = $stmtAcd->fetchColumn();
					if ($acdName) {
						$possibleRoles[] = $acdName;
					}
				}
			} catch (Throwable $e) {}
		}

		$possibleRoles = array_values(array_unique(array_filter($possibleRoles)));

		// Refresh personal menu cache for this user strictly based on right_page_main_menus
		try {
			$con->prepare("DELETE FROM pesonal_right_page_main_menus WHERE userID = ?")->execute([$usersession]);
			$con->prepare("DELETE FROM personal_page_menu_tab WHERE userID = ?")->execute([$usersession]);
		} catch (Throwable $e) {}

		if (!empty($possibleRoles)) {
			$rolePlaceholders = implode(',', array_fill(0, count($possibleRoles), '?'));
			$personalrole = $con->prepare("SELECT * FROM right_page_main_menus WHERE roleID IN ($rolePlaceholders) AND page_status = '1'");
			$personalrole->execute($possibleRoles);

			while ($readpages = $personalrole->fetch(PDO::FETCH_ASSOC)) {
				$pageID = $readpages['pageID'];
				$page_url = $readpages['page_url'];
				$tabID = $readpages['tabID'];
				$menu_name = $readpages['menu_name'];
				$roleID = $readpages['roleID'];
				$keep_active = $readpages['keep_active'] ?? 'inactive';
				$page_status = $readpages['page_status'] ?? '1';
				$pageType = $readpages['pageType'] ?? 'link';
				$getmain = $con->query("SELECT * FROM page_main_menus WHERE pageID = '$pageID' ")->fetch(PDO::FETCH_ASSOC);
				$folderids = !empty($getmain['folder']) ? $getmain['folder'] : "";

				$checktab = $con->query("SELECT * FROM personal_page_menu_tab WHERE tabID = '$tabID' AND userID = '$usersession' ")->rowCount();
				if ($checktab < 1) {
					$gettabls = $con->query("SELECT * FROM page_menu_tab WHERE ID = '$tabID' AND tab_status = '1' ")->fetch(PDO::FETCH_ASSOC);
					if ($gettabls) {
						$tab_name = $gettabls['tab_name'];
						$open_active = $gettabls['open_active'];
						$tab_status = $gettabls['tab_status'];
						$con->query("INSERT INTO personal_page_menu_tab(tabID,tab_name,open_active,userID,tab_status) VALUES('$tabID','$tab_name','$open_active','$usersession','$tab_status')");
					}
				}

				$checkperson = $con->query("SELECT * FROM pesonal_right_page_main_menus WHERE pageID = '$pageID' AND userID = '$usersession' ")->rowCount();
				if ($checkperson < 1) {
					$con->query("INSERT INTO pesonal_right_page_main_menus(pageID,menu_name,roleID,page_status,pageType,tabID,page_url,keep_active,userID,folderID) VALUES('$pageID','$menu_name','$roleID', '$page_status','$pageType','$tabID', '$page_url','$keep_active', '$usersession','$folderids')");
				}
			}
		}
		//endwhile;
		
		// Clean up orphaned menu tabs that have no active links inside them for this user
		$con->query("DELETE FROM personal_page_menu_tab 
		             WHERE userID = '$usersession' 
		               AND tabID NOT IN (
		                   SELECT DISTINCT tabID 
		                   FROM pesonal_right_page_main_menus 
		                   WHERE userID = '$usersession' 
		                     AND page_status = '1' 
		                     AND pageType = 'link'
		               )");
		
		$visiturl = substr( $_SERVER['REQUEST_URI'], strrpos( $_SERVER['REQUEST_URI'],"/")+1);
		
		$urlpages = $visiturl;
		//$urlpages = $visiturl;
		
		//check for each role pages
		
		
		$checheacoage = $con->query("SELECT * FROM pesonal_right_page_main_menus WHERE page_url = '$urlpages' AND userID = '$usersession' ")->rowCount();
		
		
		//if($checheacoage>0){
			
			
		
		//echo $urlpages;
	
		//echo "SELECT * FROM pesonal_right_page_main_menus WHERE page_url = '$urlpages' AND userID = '$usersession' ";
		
		$getkeepactive = $con->query("SELECT * FROM pesonal_right_page_main_menus WHERE page_url = '$urlpages' AND userID = '$usersession' ")->fetch(PDO::FETCH_ASSOC);
		
		$mytabid = !empty($getkeepactive['tabID'])?$getkeepactive['tabID']:"";
		
		
		$pageid = !empty($getkeepactive['pageID'])?$getkeepactive['pageID']:"";
		
		// deactivate only pages are not in use and activate only the page in use
		if (!empty($pageid)) {
			$activebyid = $con->query(" UPDATE pesonal_right_page_main_menus SET keep_active = 'active' WHERE pageID = '$pageid' AND page_status = '1'  AND userID = '$usersession' ");
			$con->query(" UPDATE pesonal_right_page_main_menus SET keep_active = 'inactive' WHERE pageID != '$pageid' AND page_status = '1'  AND userID = '$usersession' ");
		} else {
			$con->query(" UPDATE pesonal_right_page_main_menus SET keep_active = 'inactive' WHERE userID = '$usersession' ");
		}
		
		// deactivate only tabs are not in use and active current tab only
		if (!empty($mytabid)) {
			$con->query("UPDATE personal_page_menu_tab SET open_active = 'show', collapslink='' WHERE tabID = '$mytabid' AND userID = '$usersession' ");
			$con->query("UPDATE personal_page_menu_tab SET open_active = 'notopen',collapslink='collapsed' WHERE tabID != '$mytabid' AND userID = '$usersession' ");
		} else {
			$con->query("UPDATE personal_page_menu_tab SET open_active = 'notopen',collapslink='collapsed' WHERE userID = '$usersession' ");
		}
		
		/*
		if($mypage==$urlpages){
		$myactivepage = $activebyid['keep_active'];
		}else{
			$myactivepage = "";
		}
		
		
		$myactivepage = ($mypage=$urlpages)?$activebyid['keep_active']:"";
		*/
		try {
			require_once __DIR__ . '/../../app/helpers/auth.php';
		} catch (Throwable $e) {}

		if (function_exists('dashboard_for_role')) {
			$mydashboard = dashboard_for_role($rolesession);
		} else {
			$getdashboard = $con->query("SELECT * FROM dash_borad WHERE userType = '$rolesession' ")->fetch(PDO::FETCH_ASSOC);
			$mydashboard = !empty($getdashboard['pageName']) ? $getdashboard['pageName'] : 'ipessadmin/general-dashboard.php';
		}
		$firstdash = explode(".", $mydashboard);
		$folders = 'ipessadmin';
		?>
<aside id="sidebar" class="sidebar">

  <div class="sidebar-brand-wrapper d-flex align-items-center mb-3 pb-2 border-bottom">
      <img src="assets/img/1.png" alt="IPESS Logo" style="max-height: 32px; width: auto; margin-right: 10px;">
      <span class="sidebar-brand-text" style="color: #012970; font-size: 20px; font-weight: 700; font-family: 'Nunito', sans-serif;">IPESS</span>
  </div>

    <ul class="sidebar-nav" id="sidebar-nav">

      <li class="nav-item">
        <a class="nav-link collapsed" href="<?php echo app_url($mydashboard); ?>">
          <i class="bi bi-grid"></i>
          <span>Dashboard</span>
        </a>
      </li><!-- End Dashboard Nav -->

      <?php if (in_array(strtoupper(trim($rolesession ?? '')), ['SUPER_ADMIN', 'DEVELOPER', 'ICT_ADMIN'], true)): ?>
      <li class="nav-item">
        <a class="nav-link collapsed" href="<?php echo app_url('ipessadmin/user-logs.php'); ?>">
          <i class="bi bi-file-earmark-text"></i>
          <span>User Logs</span>
        </a>
      </li>
      <?php endif; ?>



			<?php
			$tab = $con->query("SELECT * FROM personal_page_menu_tab WHERE tab_status = '1' AND userID = '$usersession' ");	
			while($gettab = $tab->fetch(PDO::FETCH_ASSOC)){
				$gettabname = !empty($gettab['tab_name'])?$gettab['tab_name']:"";
				$tabID = !empty($gettab['tabID'])?$gettab['tabID']:"";
				$openstatus = !empty($gettab['open_active'])?$gettab['open_active']:"";
				
				$collapslinks = !empty($gettab['collapslink'])?$gettab['collapslink']:"";
				
				$getpagetarget = $con->query("SELECT * FROM page_menu_tab WHERE ID = '$tabID'")->fetch(PDO::FETCH_ASSOC);
				$targetname = !empty($getpagetarget['taget'])?$getpagetarget['taget']:"";
				
				$tabIcon = 'bi-menu-button-wide';
				$lowerTabName = strtolower($gettabname);
				if (strpos($lowerTabName, 'developer') !== false || strpos($lowerTabName, 'security') !== false) {
					$tabIcon = 'bi-shield-lock';
				} else if (strpos($lowerTabName, 'admin') !== false) {
					$tabIcon = 'bi-person-badge';
				} else if (strpos($lowerTabName, 'admission') !== false || strpos($lowerTabName, 'workflow') !== false) {
					$tabIcon = 'bi-journal-check';
				} else if (strpos($lowerTabName, 'student') !== false) {
					$tabIcon = 'bi-people';
				} else if (strpos($lowerTabName, 'payment') !== false || strpos($lowerTabName, 'finance') !== false) {
					$tabIcon = 'bi-credit-card';
				} else if (strpos($lowerTabName, 'report') !== false || strpos($lowerTabName, 'audit') !== false) {
					$tabIcon = 'bi-bar-chart-line';
				} else if (strpos($lowerTabName, 'setting') !== false || strpos($lowerTabName, 'config') !== false) {
					$tabIcon = 'bi-gear';
				}
				?>
      <li class="nav-item">
	  
        <a class="nav-link <?php echo $collapslinks ?>" data-bs-target="#<?php echo $tabID ?>-nav" data-bs-toggle="collapse" href="#">
          <i class="bi <?php echo $tabIcon; ?>"></i><span><?php echo $gettabname ?></span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
		
        <ul id="<?php echo $tabID ?>-nav" class="nav-content collapse <?php /*Show*/ echo $openstatus ?>" data-bs-parent="#sidebar-nav">
		<?php
		$menus = $con->query("SELECT * FROM pesonal_right_page_main_menus WHERE tabID = '$tabID' AND page_status = '1' AND userID = '$usersession' GROUP BY pageID ORDER BY pageID ASC");
		while ($getmenu = $menus->fetch(PDO::FETCH_ASSOC)) {
			$mactivepage = !empty($getmenu['keep_active']) ? $getmenu['keep_active'] : "";
			$readpage = !empty($getmenu['page_url']) ? $getmenu['page_url'] : "";
			$menu_name = !empty($getmenu['menu_name']) ? $getmenu['menu_name'] : "";
			$pageID = $getmenu['pageID'];
			$getpgid = $con->query("SELECT * FROM page_main_menus where pageID = '$pageID' ")->fetch(PDO::FETCH_ASSOC);
			$folderID = !empty($getpgid['folder']) ? intval($getpgid['folder']) : 0;
			
			if (strpos($readpage, '/') === false && !preg_match('#^https?://#', $readpage) && $readpage !== '') {
				$folderRow = $folderID > 0 ? $con->query("SELECT fname FROM setup_folder WHERE folderid = '$folderID' LIMIT 1")->fetch(PDO::FETCH_ASSOC) : false;
				$folderPath = !empty($folderRow['fname']) ? trim($folderRow['fname'], '/') : 'ipessadmin';
				$fullPageUrl = $folderPath . '/' . $readpage;
			} else {
				$fullPageUrl = $readpage;
			}
		?>
          <li>
            <a href="<?php echo app_url($fullPageUrl); ?>" class="<?php echo $mactivepage ?>">
              <i class="bi bi-circle"></i><span><?php echo htmlspecialchars($menu_name); ?></span>
            </a>
          </li>
        <?php } ?>
          
          
          
        </ul>
		<?php }?>
      </li>
	  
	  <!-- End Components Nav -->
	  
	  <!--
	   <li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#components-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-menu-button-wide"></i><span>Components</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
		
        <ul id="components-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
          <li>
            <a href="components-alerts.html">
              <i class="bi bi-circle"></i><span>Alerts</span>
            </a>
          </li>
          <li>
            <a href="components-accordion.html">
              <i class="bi bi-circle"></i><span>Accordion</span>
            </a>
          </li>
          
          
          
        </ul>
      </li>
	  
	  
	  -->

     <!-- <li class="nav-item">
        <a class="nav-link " data-bs-target="#forms-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-journal-text"></i><span>Forms</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="forms-nav" class="nav-content collapse show" data-bs-parent="#sidebar-nav">
          <li>
            <a href="forms-elements.html">
              <i class="bi bi-circle"></i><span>Form Elements</span>
            </a>
          </li>
          <li>
            <a href="forms-layouts.html" class="active">
              <i class="bi bi-circle"></i><span>Form Layouts</span>
            </a>
          </li>
          <li>
            <a href="forms-editors.html">
              <i class="bi bi-circle"></i><span>Form Editors</span>
            </a>
          </li>
          <li>
            <a href="forms-validation.html">
              <i class="bi bi-circle"></i><span>Form Validation</span>
            </a>
          </li>
        </ul>
      </li>--><!-- End Forms Nav -->

      <!--<li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#tables-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-layout-text-window-reverse"></i><span>Tables</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="tables-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
          <li>
            <a href="tables-general.html">
              <i class="bi bi-circle"></i><span>General Tables</span>
            </a>
          </li>
          <li>
            <a href="tables-data.html">
              <i class="bi bi-circle"></i><span>Data Tables</span>
            </a>
          </li>
        </ul>
      </li>-->
	  
	  
	  <!-- End Tables Nav -->

      <!--<li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#charts-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-bar-chart"></i><span>Charts</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="charts-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
          <li>
            <a href="charts-chartjs.html">
              <i class="bi bi-circle"></i><span>Chart.js</span>
            </a>
          </li>
          <li>
            <a href="charts-apexcharts.html">
              <i class="bi bi-circle"></i><span>ApexCharts</span>
            </a>
          </li>
          <li>
            <a href="charts-echarts.html">
              <i class="bi bi-circle"></i><span>ECharts</span>
            </a>
          </li>
        </ul>
      </li>-->
	  
	  <!-- End Charts Nav -->

     <!-- <li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#icons-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-gem"></i><span>Icons</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="icons-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
          <li>
            <a href="icons-bootstrap.html">
              <i class="bi bi-circle"></i><span>Bootstrap Icons</span>
            </a>
          </li>
          <li>
            <a href="icons-remix.html">
              <i class="bi bi-circle"></i><span>Remix Icons</span>
            </a>
          </li>
          <li>
            <a href="icons-boxicons.html">
              <i class="bi bi-circle"></i><span>Boxicons</span>
            </a>
          </li>
        </ul>
      </li>-->
	  
	  <!-- End Icons Nav -->

      <li class="nav-heading">Other Menus</li>

      <li class="nav-item">
        <a class="nav-link collapsed" href="user_profile.php">
          <i class="bi bi-person"></i>
          <span>Profile</span>
        </a>
      </li><!-- End Profile Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="frequestquestions.php">
          <i class="bi bi-question-circle"></i>
          <span>F.A.Q</span>
        </a>
      </li><!-- End F.A.Q Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="contactadmin.php">
          <i class="bi bi-envelope"></i>
          <span>Contact</span>
        </a>
      </li><!-- End Contact Page Nav -->

      <!--<li class="nav-item">
        <a class="nav-link collapsed" href="pages-register.html">
          <i class="bi bi-card-list"></i>
          <span>Register</span>
        </a>
      </li>-->
	  
	  <!-- End Register Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="logout.php">
          <i class="bi bi-box-arrow-in-right"></i>
          <span>Logout</span>
        </a>
      </li><!-- End Login Page Nav -->

      <!--<li class="nav-item">
        <a class="nav-link collapsed" href="pages-error-404.html">
          <i class="bi bi-dash-circle"></i>
          <span>Error 404</span>
        </a>
      </li>-->
	  
	  <!-- End Error 404 Page Nav -->

      <!--<li class="nav-item">
        <a class="nav-link collapsed" href="pages-blank.html">
          <i class="bi bi-file-earmark"></i>
          <span>Blank</span>
        </a>
      </li>-->
	  
	  <!-- End Blank Page Nav -->

    </ul>

  </aside>