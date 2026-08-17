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
              <h5 class="card-title">Manage Department Users</h5><br>
				<?php echo $msg; ?>
              <!-- Vertical Form -->
              <form name="frm" method = "POST">
                
				<div class="row">
				
				
                <div class="col-6">
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
													<div class="col-6">
												<!-- 	<label for="inputEmail4" class="form-label">&nbs;</label>
                  <div class="text-center">
                  <button type="submit" name="add" class="btn btn-primary">Search</button>
                 
                </div>-->
															
															</div>
															<br><br><br><br>
															<div class="row">
															
		<table class="table table-bordered border-primary">
							  <tr>
																								
							  <td>
							  Full Name
							  </td>
							  <td>
							  Program
							  </td>
							  <td>
							  Start Date
							  </td>
							  <td>
							  End Date
							  </td>
							  <td>
							  Status
							  </td>
							  <td>
							  Action
							  </td>
							  </tr>
							  
							  <tr>
							  <?php
		$getusers = $con->query("SELECT * FROM sch_departmental_officer where departmentID = '$namedept' ");
					while($readusers = $getusers->fetch(PDO::FETCH_ASSOC)){
						
						$myuserid = $readusers['userID'];
						$myprogid = $readusers['programID'];
						
					$do = $con->query("SELECT * FROM sch_program_option WHERE programID= '$myprogid' ")->fetch(PDO::FETCH_ASSOC);
					$prgramname = $do['programName'];
					
					$dofor = $con->query("SELECT * FROM user_access where userName = '$myuserid' ")->fetch(PDO::FETCH_ASSOC);					
						$fullname = $dofor['title']." ".$dofor['FirstName']." ".$dofor['MiddleName']." ".$dofor['LastName'];
					?>	
							  <td>
							 <?php echo $fullname ?>
							  </td>
							  <td>
							  <?php echo $prgramname ?>
							  </td>
							  <td>
							 <?php echo $readusers['startDate']; ?>
							  </td>
							  <td>
							<?php echo $readusers['stopDate']; ?>
							  </td>
							  <td>
							   <?php echo $readusers['setupStatus']; ?>
							  </td>
							  <td>
							  <a href="delete_department_users?id=<?php echo $readusers['ID']; ?>">Remove</a>
							  </td>
							  </tr>
							  <?php
					}
							  ?>
							</table>
		
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