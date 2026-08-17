<?php
session_start();
include("inc/main.config.php");
include("inc/selectorVendor.php");
	if($_SESSION['roleid']!="" && $_SESSION['roleid']!=""){
		$rolesession = $_SESSION['roleid'];
		$usersession = $_SESSION['userid'];
		}
		if($_SESSION['roleid']=="" && $_SESSION['roleid']==""){
		header("location:index.php");
		}
		
		
		$visiturl = substr( $_SERVER['REQUEST_URI'], strrpos( $_SERVER['REQUEST_URI'],"/")+1);
		
		$urlpages = $visiturl;
		
		$readpagename = $con->query("SELECT * FROM pesonal_right_page_main_menus WHERE page_url = '$urlpages' AND userID = '$usersession' ")->fetch(PDO::FETCH_ASSOC);
		$mypagename = !empty($readpagename['menu_name']) ? $readpagename['menu_name'] : '';
		
		date_default_timezone_set("Africa/Lagos");
		$datecreated = date("Y-m-d");
		$today = date("Y-m-d H:i:s");
		$msg = "";
		if(isset($_POST['sendmsg'])){
			$name = $_POST['name'];
			$email = $_POST['email'];
			$subject = $_POST['subject'];
			$message = $_POST['message'];
			//echo "INSERT INTO contact_message(userID,emaildress,msgSubject,msgBody,datecreated,pendingStatus,dateTreated,treatedBy)VALUES('$usersession','$email','$subject','$message','$datecreated','pending','','')";
		$sendme = $con->query("INSERT INTO contact_message(ToUserID,emaildress,msgSubject,msgBody,datecreated,pendingStatus,dateTreated,treatedBy,fromuserID,createdtime)VALUES('$name','$email','$subject','$message','$datecreated','pending','','','$usersession','$today')");	
			$msg = '<div class="alert alert-success" role="alert">
						 Message sent Successfully
						</div>';
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

    <div class="pagetitle">
      <h1>Approve User </h1>
      <!--<nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="<?php echo app_url('ipessadmin'); ?>">Home</a></li>
          <li class="breadcrumb-item">Tables</li>
          <li class="breadcrumb-item active">Data</li>
        </ol>
      </nav>-->
    </div><!-- End Page Title -->

    <section class="section">
      <div class="row">
        <div class="col-md-12">

          <div class="card">
            <div class="card-body">
              <!-- <h5 class="card-title">Datatables</h5>
             <p>Add lightweight datatables to your project with using the <a href="https://github.com/fiduswriter/Simple-DataTables" target="_blank">Simple DataTables</a> library. Just add <code>.datatable</code> class name to any table you wish to conver to a datatable</p>-->

              <!-- Table with stripped rows -->
			  <div class="table-responsive text-nowrap">
              <table class="table datatable table-stripeds">
                <thead>
                  <tr style="font-size:2%">
                    <th scope="col">S/No</th>
                    <th scope="col">Name</th>
                    <th scope="col">Position</th>
                    <th scope="col">Phone</th>
					 <th scope="col">Email</th>
					  <th scope="col">UserName</th>
                    <th scope="col">Active Status</th>
					<th scope="col">Approve Status</th>
					<th scope="col">Click Approve</th>
					
                  </tr>
                </thead>
                <tbody>
				 <?php
				  $sno = 0;
			$getusers = $con->query("SELECT * FROM user_access WHERE approveUser = 'pending' AND activeStatus = '1' ");
				while($readusers = $getusers->fetch(PDO::FETCH_ASSOC)){
					$sno++;
					
					$fullusername = $readusers['title']." ".$readusers['FirstName']." ".$readusers['MiddleName']." ".$readusers['LastName'];
					$phoneno  = $readusers['phoneno'];
					$myrole  = $readusers['userRoleID'];
					$getuserrole = $con->query("SELECT * FROM acd_tbluser WHERE ID = '$myrole' ")->fetch(PDO::FETCH_ASSOC);
					$userrolename = $getuserrole['access'];
					
				  ?>
                  <tr>
				 
                    <td scope="row"><?php echo $sno ?></td>
                    <td><?php echo $fullusername?></td>
                    <td><?php echo $userrolename?></td>
                    <td><?php echo $phoneno?></td>
                    <td><?php echo $readusers['EmailAddress'];?></td>
					<td><?php echo $readusers['userName'];?></td>
					<?php
					if($readusers['activeStatus']=="1"){
					?>
					<td><span class="badge bg-success"><?php echo "Active";?></span></td>
					<?php
					}else{
					?>
					<td><span class="badge bg-danger"><?php echo "Deactived";?></span></td>
					<?php
					}
					?>
					<td><?php echo $readusers['approveUser'];?></td>
					<td><a href="approve_to_activate_user.php?id=<?php echo $readusers['staffIDs']?>">Approve</a></td>
					
                  </tr>
				  <?php
				}
				  ?>
                  
                </tbody>
              </table>
			  </div>
              <!-- End Table with stripped rows -->

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