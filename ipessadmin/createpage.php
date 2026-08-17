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
		
		$urlpages = $visiturl;
		
		//echo "SELECT * FROM pesonal_right_page_main_menus WHERE page_url = '$urlpages' AND userID = '$usersession' ";
		
		$checheacoage = $con->query("SELECT * FROM pesonal_right_page_main_menus WHERE page_url = '$urlpages' AND userID = '$usersession' ")->rowCount();
			/*	*/
		if(($_SESSION['role'] ?? '') !== 'DEVELOPER' && $checheacoage<1){
			header("location:logout.php"); exit;
			
		}
			/* */
//$process = new ProcessorVendor();
		$msg = "";

			
			if(isset($_POST['add'])){
		//$msg = $process->fileMenusAction();
		$tabID = (isset($_POST['tabcode'])&&!empty($_POST['tabcode']))?$_POST['tabcode']:"";
		$tabname = (isset($_POST['tabname'])&&!empty($_POST['tabname']))?$_POST['tabname']:"";
		$foldername = (isset($_POST['foldername'])&&!empty($_POST['foldername']))?$_POST['foldername']:"";
		$pageType = !empty($_POST['pageType'])?$_POST['pageType']:"";
		$url = !empty($_POST['url'])?trim($_POST['url']):"";
		// Prefix with the actual folder path from setup_folder if URL is a bare filename
		if ($url !== '' && strpos($url, '/') === false && !preg_match('#^https?://#i', $url) && !empty($foldername)) {
			$folderRow = $con->query("SELECT fname FROM setup_folder WHERE folderid = '" . intval($foldername) . "' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
			$folderPath = !empty($folderRow['fname']) ? trim($folderRow['fname'], '/') : 'ipessadmin';
			$url = $folderPath . '/' . $url;
		}
	   try{
		   $errors = "";
		
		$check = $con->query("SELECT * FROM page_main_menus WHERE page_url = '$url' ")->rowCount();
		if($check<1){
		$add = $con->query("INSERT INTO page_main_menus(menu_name,page_status,pageType,tabID,page_url,folder) VALUES( '$tabname', '1','$pageType','$tabID', '$url','$foldername' )");
		
		
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
              <h5 class="card-title">Create Pages</h5>
				<?php echo $msg; ?>
              <!-- Vertical Form -->
              <form class="row g-3" method = "POST">
                <div class="col-12">
                  <label for="inputNanme4" class="form-label">File Description</label>
                  <input type="text" class="form-control" id="tabname" name="tabname">
                </div>
                <div class="col-12">
                  <label for="inputEmail4" class="form-label">Select Menu Tab</label>
                <select name="tabcode" class="form-control" id="tabcode"  required> 
												
															<?php echo $myclass->selectAllTabsView() ?>
															</select>
                </div>
               <div class="col-12">
                  <label for="inputEmail4" class="form-label">Select Folder</label>
                <select name="foldername" class="form-control" id="foldername"  required> 
												
															<?php echo $myclass->selectFolderView() ?>
															</select>
                </div>
                <div class="col-12">
                  <label for="inputAddress" class="form-label">File Path(url)</label>
                  <input type="text" name = "url" class="form-control" id="inputAddress" placeholder="mypage.php">
                </div>
				
				 <div class="col-12">
                  <label for="inputAddress" class="form-label">Page Category</label>
                  <select name="pageType" class="form-control" id="pageType"  required> 
													 <option value="link"> Menu Page</option>
													 <option value="notlink">Not Menu Page</option>
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