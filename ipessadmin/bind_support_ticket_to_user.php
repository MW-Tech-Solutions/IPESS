<?php
session_start();
include("inc/main.config.php");
include("inc/selectorVendor.php");

$myclass = new selectorVendor();
date_default_timezone_set("Africa/Lagos");
		$datecreated = date("Y-m-d");
		$today = date("Y-m-d H:i:s");
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
			header("location:../signout.php");
			
		}
		
//$process = new ProcessorVendor();
		$msg = "";

			if(isset($_POST['add'])){
		//$msg = $process->fileMenusAction();
		$supportticket = (isset($_POST['supportticket'])&&!empty($_POST['supportticket']))?$_POST['supportticket']:"";
		$username = (isset($_POST['username'])&&!empty($_POST['username']))?$_POST['username']:"";
		
		
		
	   try{
		   $errors = ""; //"INSERT INTO page_main_menus(menu_name,page_status,pageType,tabID,page_url) VALUES( '$tabname', '1','$pageType','$tabID', '$url' )" ;
		
		$check = $con->query("SELECT * FROM assign_user_support WHERE userName = '$username'  ")->rowCount();
		if($check<1){
		$add = $con->query("INSERT INTO assign_user_support(userName,supportID,manageStatus,dateCreated) VALUES( '$username','$supportticket','1','$today' )");
		
		
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
              <h5 class="card-title">Assign User to Ticket</h5>
				<?php echo $msg; ?>
              <!-- Vertical Form -->
              <form class="row g-3" method = "POST">
                <div class="col-12">
                  <label for="inputNanme4" class="form-label">Select UserName</label>
                 <select name="username" class="form-control" id="username"  required> 
																<option value="">Select Option</option>
															<?php 
															$myusername = !empty($_POST['username'])?$_POST['username']:"";
															$getuser = $con->query("SELECT * FROM user_access WHERE activeStatus = '1' ");
															while($readuser = $getuser->fetch(PDO::FETCH_ASSOC)){
																$fullusername = $readuser['title']." ".$readuser['FirstName']." ".$readuser['MiddleName']." ".$readuser['LastName'];
															?>
															<option value="<?php echo $readuser['userName']?>" ><?php echo $fullusername ?></option>
															
															<?php
															}
															?>
															</select>
                </div>
                <div class="col-12">
                  <label for="inputEmail4" class="form-label">Select Support</label>
                 <select name="supportticket" class="form-control" id="supportticket"  required> 
												
															<?php
															$pages = "SELECT * from `manage_support`  WHERE supportStatus = '1' " ;
										
															$quersupport = $con->query($pages);
															
															//$querpages->execute($tabls);
															
															if($quersupport->rowCount() > 0 ):
																	
																while( $supportresult = $quersupport->fetchObject()):
															 
																	
															
															?>
															<option value="<?php echo $supportresult->ID?>"><?php echo $supportresult->supportDescription ?> </option>
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