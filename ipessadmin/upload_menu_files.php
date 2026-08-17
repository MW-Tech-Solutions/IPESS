<?php
session_start();
include("inc/main.config.php");
include("inc/selectorVendor.php");
error_reporting(0);
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
		


		if(($_SESSION['role'] ?? '') !== 'DEVELOPER' && $checheacoage<1){
			header("location:logout.php"); exit;
			
		}
					
//$process = new ProcessorVendor();
		$msg = "";

			if(isset($_POST["add"])){
	

   $folder=$_POST["foldername"];

      $file_name = $_FILES['fileToUpload']['name'];
      $file_size =$_FILES['fileToUpload']['size'];
      $file_tmp =$_FILES['fileToUpload']['tmp_name'];
      $file_type=$_FILES['fileToUpload']['type'];
      $file_ext=strtolower(end(explode('.',$_FILES['fileToUpload']['name'])));
     
      $extensions= array("php");
     
      if(in_array($file_ext,$extensions)=== true){
     
      
      if($file_size < 2097152){
     
	$path=trim($folder.$file_name);

	if(move_uploaded_file($file_tmp,$path)){
		
	echo '<script>alert("FILE UPLOADED SUCCESSFULLY")</script>';	
		
	}else{
	  echo '<script>alert("ERROR: TRY AGAIN")</script>';	 	
		
	}
 
    }else{
	  
		  echo '<script>alert("ERROR: file too large")</script>';	  
		  
	  } 
		  
	
      }else{
		echo '<script>alert("ERROR: file format not supported")</script>';  
		  
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

   <!-- <div class="pagetitle">
      <h1>Form Layouts</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="<?php echo app_url('ipessadmin'); ?>">Home</a></li>
          <li class="breadcrumb-item">Forms</li>
          <li class="breadcrumb-item active">Layouts</li>
        </ol>
      </nav>
    </div>--><!-- End Page Title -->
    <section class="section">
      <div class="row">
        

        <div class="col-md-12">

          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Upload Files</h5>
				<?php echo $msg; ?>
              <!-- Vertical Form -->
			  <hr>
              <form class="row g-3" method = "POST" enctype="multipart/form-data">
                <div class="col-12">
                  <label for="inputNanme4" class="form-label">Folder</label>
                  <input type="text" class="form-control" id="folder" name="foldername" placeholder="folder/">
                </div>
                	<div class="form-control">
					<label>Select php  File:</label>
					<input type="file" name="fileToUpload" required="required"/>
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