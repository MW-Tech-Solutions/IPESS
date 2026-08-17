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
		$datecreated = date("Y-m-d");
//$process = new ProcessorVendor();
		$msg = "";

			if(isset($_POST['add'])){
		//$msg = $process->fileMenusAction();
		$programename = (isset($_POST['programename'])&&!empty($_POST['programename']))?$_POST['programename']:"";
		$username = (isset($_POST['username'])&&!empty($_POST['username']))?$_POST['username']:"";
		$departments = (isset($_POST['departments'])&&!empty($_POST['departments']))?$_POST['departments']:"";
		$terminationdate = (isset($_POST['terminationdate'])&&!empty($_POST['terminationdate']))?$_POST['terminationdate']:"";
		$startdate = (isset($_POST['startdate'])&&!empty($_POST['startdate']))?$_POST['startdate']:"";
		
		$checkifexst = $con->query("SELECT * FROM sch_departmental_officer WHERE userID = '$username' AND departmentID = '$departments' AND programID = '$programename' ")->rowCount();
		if($checkifexst<1){
		 $inputs = $con->query("INSERT INTO sch_departmental_officer(userID,departmentID,programID,startDate,stopDate,dateCreated,setupStatus) 
		 VALUES('$username','$departments','$programename','$startdate','$terminationdate','$datecreated','Active') ");
		
		if($inputs=1){
			$msg = '<div class="alert alert-success alert-dismissible">
							<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
					  <strong>Success!</strong> Records created Successfully.
							</div>';
			
		}
		}else{
			
			$msg = '<div class="alert alert-danger alert-dismissible">
							<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
					  <strong>Error!</strong> Records already exist
							</div>';
			
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
              <h5 class="card-title">Assign Department To Users</h5><br>
				<?php echo $msg; ?>
              <!-- Vertical Form -->
              <form name="frm" method = "POST">
                
				<div class="row">
				<div class="col-4">
                  <label for="inputNanme4" class="form-label">Select UserName</label>
                 <select name="username" class="form-control" id="username"  required> 
																<option value="">Select Option</option>
															<?php 
															$myusername = !empty($_POST['username'])?$_POST['username']:"";
															$getuser = $con->query("SELECT * FROM user_access WHERE activeStatus = '1' ");
															while($readuser = $getuser->fetch(PDO::FETCH_ASSOC)){
																$fullusername = $readuser['title']." ".$readuser['FirstName']." ".$readuser['MiddleName']." ".$readuser['LastName'];
															?>
															<option value="<?php echo $readuser['userName']?>" <?php echo ($readuser['userName']==$myusername)?"selected":""?> ><?php echo $fullusername ?></option>
															
															<?php
															}
															?>
															</select>
                </div>
				
                <div class="col-4">
                  <label for="inputEmail4" class="form-label">Select Department</label>
                 <select name="departments" class="form-control" id="departments" onchange="this.form.submit();" required> 
												<option value="">Select Option</option>
															<?php
												$namedept = !empty($_POST['departments'])?$_POST['departments']:"";
												
															$dept = "SELECT * from `office_department`  WHERE departmentStatus = '1' " ;
										
															$querdept = $con->query($dept);
															
																																
																while( $deptresult = $querdept->fetch(PDO::FETCH_ASSOC)){
															 
															 $dptid = $deptresult['departmentID'];
																	
															
															?>
															<option value="<?php echo $dptid ?>" <?php echo ($dptid==$namedept)?"selected":"" ?>><?php echo $deptresult['departmentName'] ?> </option>
															<?php
																};
															
																
																?>
															</select>
															
															</div>
															<div class="col-4">
                  <label for="inputEmail4" class="form-label">Select Program</label>
                 <select name="programename" class="form-control" id="programename"  required> 
																<option value="">Select Option</option>
															<?php
															//$programid = "SELECT * from `sch_program_option`  WHERE programStatus = '1'  AND departmentID = '$namedept'" ;
															$programid = "SELECT * from `sch_program_option`  WHERE programStatus = '1'  AND departmentID = '$namedept'" ;
										
															$queryprogramid = $con->query($programid);
															
															
																	
																while( $programidresult = $queryprogramid->fetchObject()):
															 
																	
															
															?>
															<option value="<?php echo $programidresult->programID?>"><?php echo $programidresult->programName ?> </option>
															<?php
																endwhile;
															
																
																?>
															</select>
															
															</div>
															</div>
							<div class="row">
							
							<div class="col-6">
                  <label for="inputNanme4" class="form-label">Start Date</label>
                  <input type="date" class="form-control" id="startdate" name="startdate">
                </div>
                <div class="col-6">
                  <label for="inputNanme4" class="form-label">Termination Date</label>
                  <input type="date" class="form-control" id="terminationdate" name="terminationdate">
                </div>
							
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