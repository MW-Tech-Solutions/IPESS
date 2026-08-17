<?php
session_start();
include("inc/main.config.php");
include("inc/selectorVendor.php");
//include("includes/ProcessorVendor.php");
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
			header("location:../signout.php");
			
		}
//$process = new ProcessorVendor();
		$msg = "";

					
$createdDate = date("Y-m-d");
$msg = "";
$target_dir = "../adminpictures/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0775, true);
}
		if(isset($_POST['sendinfor'])){
			
			$usertitle = $_POST['usertitle'];
			$fullname = $_POST['fullname'];
			$explode = explode(" ",$fullname);
			$firstname = $explode[0];
			$middlename = $explode[1];
			$lastname = !empty($explode[2])?$explode[2]:"";
			
			$UserName = $_POST['UserName'];
			$pwd = md5($_POST['pwd']);
			$emails = $_POST['emails'];
			$phoneno = $_POST['phoneno'];
			$deviceAddress = $_POST['deviceAddress'];
			$rolecode = $_POST['rolecode'];
			
			$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
			$photolink = $target_file;
$uploadOk = 1;
$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
  $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
  if($check !== false) {
   // echo "File is an image - ' . $check["mime"] . '.";
	
	$msg = '<div class="alert alert-warning" role="alert">
						File is an image - ' . $check["mime"] . '.
						</div>';
	
	
    $uploadOk = 1;
  } else {
    //echo "File is not an image.";
	
	$msg = '<div class="alert alert-warning" role="alert">
						File is not an image.
						</div>';
    $uploadOk = 0;
  }


// Check if file already exists
if (file_exists($target_file)) {
  //echo "Sorry, file already exists.";
  
  $msg = '<div class="alert alert-info" role="alert">
						Sorry, your file is too large.
						</div>';
  
  $uploadOk = 0;
}

// Check file size
if ($_FILES["fileToUpload"]["size"] > 500000) {
  //echo "Sorry, your file is too large.";
  
  $msg = '<div class="alert alert-warning" role="alert">
						Sorry, your file is too large.
						</div>';
  
  $uploadOk = 0;
}

// Allow certain file formats
if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
&& $imageFileType != "gif" ) {
  //echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
  $msg = '<div class="alert alert-warning" role="alert">
						Sorry, only JPG, JPEG, PNG & GIF files are allowed.
						</div>';
  $uploadOk = 0;
}

// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
  //echo "Sorry, your file was not uploaded.";
  $msg = '<div class="alert alert-danger" role="alert">
						Sorry, your file was not uploaded
						</div>';
// if everything is ok, try to upload file
} else {
	$checkdata = $con->query("SELECT * FROM user_access where EmailAddress = '$emails' AND userName = '$UserName' ")->rowCount();	
			if($checkdata<1){
  if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
    $imagesupload = "the file ". htmlspecialchars( basename( $_FILES["fileToUpload"]["name"])). " has been uploaded.";
	
	
	
				
		$con->query("INSERT INTO user_access
		(title,FirstName,MiddleName,LastName,userName,passWord,EmailAddress,
		mackAddress,pixUrl,createdDate,createdTime,activeStatus,phoneno,userRoleID,approveUser) 
		VALUES('$usertitle','$firstname','$middlename','$lastname','$UserName','$pwd','$emails','$deviceAddress',
		'$photolink','$createdDate','','1','$phoneno','$rolecode','pending')");	

        // Synchronize with the modern users table
        try {
            $rawPwd = $_POST['pwd'] ?? '';
            $newHash = password_hash($rawPwd, PASSWORD_DEFAULT);
            $roleMap = [
                1  => 12, // Developer -> DEVELOPER
                12 => 1,  // Supper User Support -> SUPER_ADMIN
                13 => 6,  // ICT Officer -> ICTO
                2  => 4,  // Course Lecturer -> SUPERVISOR
                4  => 24, // HOD -> HOD
                5  => 22, // Dean of Studies -> FACULTY_OFFICER
                7  => 14, // School Admin -> ICT_ADMIN
                8  => 27, // Registrar -> REGISTRY
                11 => 17  // Academic Officer -> ACADEMIC_MANAGER
            ];
            $newRoleId = $roleMap[(int)$rolecode] ?? null;

            $checkUsers = $con->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $checkUsers->execute([$emails]);
            $existsInUsers = $checkUsers->fetchColumn();

            if ($existsInUsers) {
                $stmtUp = $con->prepare("UPDATE users SET full_name = ?, password_hash = ?, role_id = ? WHERE email = ?");
                $stmtUp->execute([$fullname, $newHash, $newRoleId, $emails]);
            } else {
                $stmtIns = $con->prepare("INSERT INTO users (email, full_name, password_hash, role_id, account_status, created_at) VALUES (?, ?, ?, ?, 'Active', NOW())");
                $stmtIns->execute([$emails, $fullname, $newHash, $newRoleId]);
            }
        } catch (Throwable $syncError) {
            // Silently fail if table structure varies to prevent blocking user capture
        }

		$msg = '<div class="alert alert-success" role="alert">
						 User has been captured Successfully and '.$imagesupload.'
						</div>';
			
	
	
	
	
	
  } else {
    echo "Sorry, there was an error uploading your file.";
  }
  }else{
				
				$msg = '<div class="alert alert-danger" role="alert">
					 Sorry record already Exist
					</div>';
				
			}
  
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
              <h5 class="card-title">Admin Add New User</h5>
				<?php echo $msg; ?>
              <!-- Vertical Form -->
              
			  <form name="frm" method="POST" enctype="multipart/form-data">
					  <div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="basic-default-message">User Title</label>
                          <div class="col-sm-10">
						 <select name="usertitle" class="form-control" id="usertitle"  required> 
					
								<?php echo $myclass->selectTitleView() ?>
								</select>
						</div>
						</div>
                        <div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="basic-default-name">Full Name</label>
                          <div class="col-sm-10">
                            <input type="text" name = "fullname" class="form-control" id="basic-default-name" placeholder="Surname MiddleName LastName" required />
                          </div>
                        </div>
                        <div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="basic-default-company">UserName</label>
                          <div class="col-sm-10">
                            <input
                              type="text"
							  name = "UserName"
                              class="form-control"
                              id="basic-default-company"
                              placeholder="Username."
							  required
                            />
                          </div>
                        </div>
						 <div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="basic-default-company">Password</label>
                          <div class="col-sm-10">
                            <input
                              type="password"
							  name = "pwd"
                              class="form-control"
                              id="basic-default-company"
                              placeholder="Enter Password."
							  required
                            />
                          </div>
                        </div>
                        <div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="basic-default-email">Email</label>
                          <div class="col-sm-10">
                            <div class="input-group input-group-merge">
                              <input
                                type="email"
								name = "emails"
                                id="basic-default-email"
                                class="form-control"
                                placeholder="Provide Valide email"
                                aria-label="john.doe"
                                aria-describedby="basic-default-email2"
								required
								
                              />
                              <span class="input-group-text" id="basic-default-email2"></span>
                            </div>
                           
                          </div>
                        </div>
                        <div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="basic-default-phone">Phone No</label>
                          <div class="col-sm-10">
                            <input
                              type="text"
							  name = "phoneno"
                              id="basic-default-phone"
                              class="form-control phone-mask"
                              placeholder="08111658942"
                              aria-label="658 799 8941"
                              aria-describedby="basic-default-phone"
							  required
                            />
                          </div>
                        </div>
						
						
						<div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="basic-default-phone">Device Mac Address(Optional)</label>
                          <div class="col-sm-10">
                            <input
                              type="text"
							  name = "deviceAddress"
                              id="basic-default-MAC"
                              class="form-control mac-mask"
                              placeholder="00-1B-63-84-45-E6"
                              aria-label="00-1B-63-84-45-E6"
                              aria-describedby="basic-default-MAC"
                            />
                          </div>
                        </div>

						<div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="basic-default-message">Assign Role</label>
                          <div class="col-sm-10">
						 <select name="rolecode" class="form-control" id=""  required> 
								<option value="">-- Select Option -- </option>
					
								<?php 
						$getuser = $con->query("SELECT * FROM acd_tbluser WHERE `status` = '1' ");
								while($readuser = $getuser->fetch(PDO::FETCH_ASSOC)){
								?>
								<option value="<?php echo $readuser['ID'] ?>"><?php echo $readuser['access'] ?></option>
								<?php
								}
								?>
								</select>
						</div>
						</div>
						
						
                        <div class="row mb-3">
                          <label class="col-sm-2 col-form-label" for="basic-default-message">Upload Passport</label>
                          <div class="col-sm-10">
                            <input
                              type="file"
							  name = "fileToUpload"
                              id="basic-default-passport"
                              class="form-control phone-mask"
                              placeholder="08111658942"
                              aria-label="658 799 8941"
                              aria-describedby="basic-default-passport"
							  accept="image/*" onchange="loadFile(event)"
							  required
                            />
							<img id="output"/>
							<div class="form-text">Passport width 120px, height 150px</div>
                          </div>
						  
                        </div>
                        <div class="row justify-content-end">
                          <div class="col-sm-10">
                            <button type="submit" name = "sendinfor" class="btn btn-primary">Submit</button>
                          </div>
                        </div>
                      </form>
			  
			  <!-- Vertical Form -->

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