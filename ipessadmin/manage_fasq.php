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
      <h1>Manage FAQ</h1>
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
                    <th scope="col">Title</th>
                    <th scope="col">Description</th>
                    <th scope="col">Click Trash</th>
                  </tr>
                </thead>
                <tbody>
				 <?php
				  $sno = 0;
			$getfaq = $con->query("SELECT * FROM frq_asck_questions ");
				while($readfaq = $getfaq->fetch(PDO::FETCH_ASSOC)){
					$sno++;
					
					
					
				  ?>
                  <tr>
				 
                    <td scope="row"><?php echo $sno ?></td>
                    <td><?php echo $readfaq['quetionTitle']?></td>
					 <td><?php echo $readfaq['questionsBody']?></td>
					 <td> <a href="delete_faqs?id=<?php echo $readfaq['ID']?>"><i class="bi bi-trash"></li></a></td>
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