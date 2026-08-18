<?php
session_start();
include("inc/main.config.php");
include("inc/selectorVendor.php");

$myclass = new selectorVendor();

if($_SESSION['roleid']!="" && $_SESSION['roleid']!=""){
		$rolesession = $_SESSION['roleid'];
		$usersession = $_SESSION['userid'];
		}
		if($usersession==""){
		header("location:index.php");
		}
		$visiturl = substr( $_SERVER['REQUEST_URI'], strrpos( $_SERVER['REQUEST_URI'],"/")+1);
		
	//	echo $visiturl;
		$urlpages = $visiturl;
		
		//echo "SELECT * FROM pesonal_right_page_main_menus WHERE page_url = '$urlpages' AND userID = '$usersession' ";
		$readpagename = $con->query("SELECT * FROM pesonal_right_page_main_menus WHERE page_url = '$urlpages' AND userID = '$usersession' ")->fetch(PDO::FETCH_ASSOC);
		$mypagename = !empty($readpagename['menu_name']) ? $readpagename['menu_name'] : '';
		$checheacoage = $con->query("SELECT * FROM pesonal_right_page_main_menus WHERE page_url = '$urlpages' AND userID = '$usersession' ")->rowCount();
			
		
		if(($_SESSION['role'] ?? '') !== 'DEVELOPER' && $checheacoage<1){
			header("location:logout.php"); exit;
			
		}
		
//$process = new ProcessorVendor();
		$msg = "";

			if(isset($_POST['add'])){
		//$msg = $process->fileMenusAction();
		$tabID = (isset($_POST['tabcode'])&&!empty($_POST['tabcode']))?$_POST['tabcode']:"";
		$pagescode = (isset($_POST['pagescode'])&&!empty($_POST['pagescode']))?$_POST['pagescode']:"";
		$rolecode = !empty($_POST['rolecode'])?$_POST['rolecode']:"";
		
		$getpages = $con->query("SELECT * FROM page_main_menus WHERE pageID ='$pagescode' ")->fetch(PDO::FETCH_ASSOC);
		$page_url = $getpages['page_url'];
		// Normalize URL: bare filenames (no slash) get prefixed with the actual folder name from setup_folder
		if ($page_url !== '' && strpos($page_url, '/') === false && !preg_match('#^https?://#i', $page_url)) {
			$folderID = intval($getpages['folder'] ?? 0);
			$folderRow = $folderID > 0 ? $con->query("SELECT fname FROM setup_folder WHERE folderid = '$folderID' LIMIT 1")->fetch(PDO::FETCH_ASSOC) : false;
			$folderPath = !empty($folderRow['fname']) ? trim($folderRow['fname'], '/') : 'ipessadmin';
			$page_url = $folderPath . '/' . $page_url;
			// Backfill source record so future reads are already correct
			$con->query("UPDATE page_main_menus SET page_url = '$page_url' WHERE pageID = '$pagescode'");
		}
		$menu_name = $getpages['menu_name'];
		$keep_active = $getpages['keep_active'];
		$page_status = $getpages['page_status'];
		$pageType = $getpages['pageType'];
		
		// Map numeric role ID from acd_tbluser to the standard string role key (e.g. 'DEVELOPER')
		$mappedRoleKey = $rolecode;
		$roleName = '';
		try {
			$stmtAcd = $con->prepare("SELECT access FROM acd_tbluser WHERE ID = ? LIMIT 1");
			$stmtAcd->execute([$rolecode]);
			$acdRow = $stmtAcd->fetch(PDO::FETCH_ASSOC);
			$roleName = $acdRow ? trim($acdRow['access']) : '';
		} catch (Throwable $e) {
			try {
				$stmtAcd = $con->prepare("SELECT role_name, role_key FROM roles WHERE role_id = ? OR role_key = ? LIMIT 1");
				$stmtAcd->execute([$rolecode, $rolecode]);
				$acdRow = $stmtAcd->fetch(PDO::FETCH_ASSOC);
				if ($acdRow) {
					$roleName = trim($acdRow['role_name']);
					$mappedRoleKey = $acdRow['role_key'];
				}
			} catch (Throwable $ex) {}
		}
		if (!empty($roleName)) {
			$stmtRoleMap = $con->prepare("
				SELECT role_key 
				FROM roles 
				WHERE LOWER(role_key) = LOWER(?) 
				   OR LOWER(role_name) = LOWER(?) 
				LIMIT 1
			");
			$stmtRoleMap->execute([$roleName, $roleName]);
			$roleMapRow = $stmtRoleMap->fetch(PDO::FETCH_ASSOC);
			if ($roleMapRow) {
				$mappedRoleKey = $roleMapRow['role_key'];
			} else {
				$mappedRoleKey = strtoupper(str_replace(' ', '_', $roleName));
			}
		}
		
	   try{
		   $errors = ""; //"INSERT INTO page_main_menus(menu_name,page_status,pageType,tabID,page_url) VALUES( '$tabname', '1','$pageType','$tabID', '$url' )" ;
		
		$check = $con->query("SELECT * FROM right_page_main_menus WHERE tabID = '$tabID' AND pageID ='$pagescode' AND roleID ='$mappedRoleKey' ")->rowCount();
		if($check<1){
		$add = $con->query("INSERT INTO right_page_main_menus(pageID,menu_name,roleID,page_status,pageType,tabID,page_url,keep_active) VALUES( '$pagescode','$menu_name','$mappedRoleKey', '$page_status','$pageType','$tabID', '$page_url','$keep_active' )");
		
		
		if($add){
		$msg = '<div class="alert alert-success alert-dismissible">
							<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
					  <strong>Success!</strong> Records created Successfully. '.$errors.'
							</div>';
		}else{
			
			$msg = '<div class="alert alert-danger alert-dismissible">
							<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
					  <strong>Error!</strong> Records creation fails.  '.$errors.'
							</div>';
		}
		}else{
			
			$msg = '<div class="alert alert-danger alert-dismissible">
							<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
					  <strong>Error!</strong> Records already exist.  '.$errors.'
							</div>';
		}
		
		}catch(PDOException $exp){
			
			echo $exp->getMessage();
		}
			}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title><?php echo $mypagename ?></title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <?php
  include("inc/htmlheaderotherfolders.php");
  ?>
</head>

<body>

  <!-- ======= Header ======= -->
  <?php
  include("inc/inerheader.php");
  ?>
  <!-- End Header -->

  <!-- ======= Sidebar ======= -->
  <?php
  include("inc/sidebar.php");
  ?>
  <!-- End Sidebar-->

  <main id="main" class="main">

    <!--<div class="pagetitle">
      <h1>Form Layouts</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="<?php echo app_url('ipessadmin'); ?>">Home</a></li>
          <li class="breadcrumb-item">Forms</li>
          <li class="breadcrumb-item active">Layouts</li>
        </ol>
      </nav>
    </div>-->
	
	<!-- End Page Title -->
    <section class="section">
      <div class="row">
        

        <div class="col-md-12">

          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Assign Page To role</h5>
				<?php echo $msg; ?>
              <!-- Vertical Form -->
              <form class="row g-3" method = "POST">
                <div class="col-12">
                  <label for="inputNanme4" class="form-label">Select Tab</label>
                 <select name="tabcode" class="form-control" id="tabcode" onchange = "this.form.submit();" required> 
																<option value="">Select Option</option>
															<?php 
															$tabls = !empty($_POST['tabcode'])?$_POST['tabcode']:"";
															$gettab = $con->query("SELECT * FROM page_menu_tab WHERE tab_status = '1' ");
															while($readtab = $gettab->fetch(PDO::FETCH_ASSOC)){
															?>
															<option value="<?php echo $readtab['ID']?>" <?php echo ($readtab['ID']==$tabls)?"selected":""?>><?php echo $readtab['tab_name']?></option>
															
															<?php
															}
															?>
															</select>
                </div>
                <div class="col-12">
                  <label for="inputEmail4" class="form-label">Select Page</label>
                 <select name="pagescode" class="form-control" id="pagescode"  required> 
												
															<?php
															$pages = "SELECT * from `page_main_menus`  WHERE tabID = '$tabls' " ;
										
															$querpages = $con->query($pages);
															
															//$querpages->execute($tabls);
															
															if($querpages->rowCount() > 0 ):
																	
																while( $pageresult = $querpages->fetchObject()):
															 
																	
															
															?>
															<option value="<?php echo $pageresult->pageID?>"><?php echo $pageresult->menu_name ?> </option>
															<?php
																endwhile;
															
																else:
																?>
																
																<option value="">No Record Found</option>
																<?php
																endif;	
																
																?>
															</select>
                </div>
               
                <div class="col-12">
                  <label for="inputAddress" class="form-label">Select Role</label>
                 <select name="rolecode" class="form-control" id="rolecode"  required> 
												
															<?php
															echo $myclass->selectDutTypeView();
																
																?>
															</select>
                </div>
                <div class="text-center">
                  <button type="submit" name="add" class="btn btn-primary">Submit</button>
                 
                </div>
              </form><!-- Vertical Form -->

            </div>
          </div>

         

        </div>
      </div>
    </section>

    </main><!-- End #main -->

  <!-- ======= Footer ======= -->
  <?php
  include("inc/footer.php");
  ?>
  <!-- End Footer -->

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/chart.js/chart.umd.js"></script>
  <script src="assets/vendor/echarts/echarts.min.js"></script>
  <script src="assets/vendor/quill/quill.min.js"></script>
  <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="assets/vendor/tinymce/tinymce.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>

  <!-- Template Main JS File -->
  <script src="assets/js/main.js"></script>

</body>

</html>