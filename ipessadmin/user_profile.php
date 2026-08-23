<?php
session_start();
include("inc/main.config.php");
include("inc/selectorVendor.php");

if (!function_exists('table_exists')) {
    function table_exists(PDO $pdo, string $table): bool {
        try {
            $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $pdo->query("SELECT 1 FROM `{$sanitized}` LIMIT 0");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!isset($_SESSION['roleid'], $_SESSION['userid']) || $_SESSION['roleid'] == "" || $_SESSION['userid'] == "") {
    header("location:index.php");
    exit;
}

$rolesession = $_SESSION['roleid'];
$usersession = $_SESSION['userid'];

// Fetch legacy profile
$getstatus = $con->prepare("SELECT * FROM user_access WHERE userName = ? LIMIT 1");
$getstatus->execute([$usersession]);
$getprofile = $getstatus->fetch(PDO::FETCH_ASSOC);

if (!$getprofile) {
    header("location:index.php");
    exit;
}

$currentpwd_md5 = $getprofile['passWord'];
$current_email = $getprofile['EmailAddress'];

// Fetch modern profile
$stmtModern = $con->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmtModern->execute([$current_email]);
$modernUser = $stmtModern->fetch(PDO::FETCH_ASSOC);
$currentpwd_bcrypt = $modernUser ? $modernUser['password_hash'] : '';

$msg = "";
$target_dir = "../adminpictures/";
if (!is_dir($target_dir)) {
    @mkdir($target_dir, 0775, true);
}

// 1. SAVE CHANGES
if (isset($_POST['savechanges'])) {
    $fullname_input = trim($_POST['fullname'] ?? '');
    $about = $_POST['about'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $twitter = $_POST['twitter'] ?? '';
    $facebook = $_POST['facebook'] ?? '';
    $instagram = $_POST['instagram'] ?? '';
    $linkedin = $_POST['linkedin'] ?? '';

    $uploadOk = 1;
    $photolink = $getprofile['pixUrl']; // Default to current

    // Parse full name
    $explode = explode(" ", $fullname_input);
    $firstname = !empty($explode[0]) ? $explode[0] : '';
    $middlename = !empty($explode[1]) ? $explode[1] : '';
    $lastname = !empty($explode[2]) ? $explode[2] : '';

    // Uniqueness checks for email
    if ($email !== $current_email) {
        $checkEmailU = $con->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $checkEmailU->execute([$email]);
        $existsU = (int)$checkEmailU->fetchColumn();

        $checkEmailUA = $con->prepare("SELECT COUNT(*) FROM user_access WHERE EmailAddress = ?");
        $checkEmailUA->execute([$email]);
        $existsUA = (int)$checkEmailUA->fetchColumn();

        if ($existsU > 0 || $existsUA > 0) {
            $msg = '<div class="alert alert-danger" role="alert">Error: The email address is already in use by another account.</div>';
            $uploadOk = 0;
        }
    }

    // Process profile image upload if provided
    if ($uploadOk && !empty($_FILES["fileToUpload"]["name"])) {
        $imageFileType = strtolower(pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_EXTENSION));
        $target_file = $target_dir . $usersession . '.' . $imageFileType;
        $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
        
        if ($check !== false) {
            // Check file size (5MB limit)
            if ($_FILES["fileToUpload"]["size"] > 5000000) {
                $msg = '<div class="alert alert-warning" role="alert">Sorry, your file is too large (max 5MB).</div>';
                $uploadOk = 0;
            }
            // Allow certain file formats
            if ($uploadOk && $imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                $msg = '<div class="alert alert-warning" role="alert">Sorry, only JPG, JPEG, PNG & GIF files are allowed.</div>';
                $uploadOk = 0;
            }
            
            if ($uploadOk) {
                if (file_exists($target_file)) {
                    @unlink($target_file);
                }
                if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
                    $photolink = $target_file;
                } else {
                    $msg = '<div class="alert alert-danger" role="alert">Sorry, there was an error uploading your file.</div>';
                    $uploadOk = 0;
                }
            }
        } else {
            $msg = '<div class="alert alert-warning" role="alert">File is not an image.</div>';
            $uploadOk = 0;
        }
    }

    if ($uploadOk) {
        try {
            $con->beginTransaction();

            // Update user_access table
            $stmtUpUA = $con->prepare("
                UPDATE user_access 
                SET FirstName = ?, MiddleName = ?, LastName = ?, phoneno = ?, EmailAddress = ?, 
                    contactaddress = ?, aboutmyself = ?, twitter = ?, facebook = ?, 
                    myinstatgram = ?, myLink = ?, pixUrl = ?
                WHERE userName = ?
            ");
            $stmtUpUA->execute([
                $firstname, $middlename, $lastname, $phone, $email,
                $address, $about, $twitter, $facebook,
                $instagram, $linkedin, $photolink, $usersession
            ]);

            // Update users table
            $stmtUpU = $con->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, avatar_url = ? 
                WHERE email = ?
            ");
            $stmtUpU->execute([$fullname_input, $email, $photolink, $current_email]);

            $con->commit();

            // Refresh loaded data
            $getstatus->execute([$usersession]);
            $getprofile = $getstatus->fetch(PDO::FETCH_ASSOC);
            $current_email = $getprofile['EmailAddress'];

            $stmtModern->execute([$current_email]);
            $modernUser = $stmtModern->fetch(PDO::FETCH_ASSOC);

            $msg = '<div class="alert alert-success" role="alert">Profile updated successfully!</div>';
        } catch (Throwable $e) {
            if ($con->inTransaction()) {
                $con->rollBack();
            }
            $msg = '<div class="alert alert-danger" role="alert">Database update error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// 2. CHANGE PASSWORD
if (isset($_POST['changepaasword'])) {
    $password = $_POST['password'] ?? '';
    $newpassword = $_POST['newpassword'] ?? '';
    $renewpassword = $_POST['renewpassword'] ?? '';

    // Verify current password: it could be MD5 or BCRYPT
    $pwdMatch = (md5($password) === $currentpwd_md5);
    if (!$pwdMatch && $currentpwd_bcrypt) {
        $pwdMatch = password_verify($password, $currentpwd_bcrypt);
    }

    if (!$pwdMatch) {
        $msg = '<div class="alert alert-danger" role="alert">Current password does not match.</div>';
    } else {
        if ($newpassword !== $renewpassword) {
            $msg = '<div class="alert alert-danger" role="alert">Repeated password does not match, please try again.</div>';
        } else {
            $newpwd_md5 = md5($newpassword);
            $newpwd_bcrypt = password_hash($newpassword, PASSWORD_BCRYPT);

            if ($newpwd_md5 !== $currentpwd_md5) {
                try {
                    $con->beginTransaction();

                    // Update user_access table
                    $stmtPassUA = $con->prepare("UPDATE user_access SET passWord = ? WHERE userName = ?");
                    $stmtPassUA->execute([$newpwd_md5, $usersession]);

                    // Update users table
                    $stmtPassU = $con->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                    $stmtPassU->execute([$newpwd_bcrypt, $current_email]);

                    $con->commit();

                    // Refresh
                    $getstatus->execute([$usersession]);
                    $getprofile = $getstatus->fetch(PDO::FETCH_ASSOC);
                    $currentpwd_md5 = $getprofile['passWord'];

                    $stmtModern->execute([$current_email]);
                    $modernUser = $stmtModern->fetch(PDO::FETCH_ASSOC);
                    $currentpwd_bcrypt = $modernUser ? $modernUser['password_hash'] : '';

                    $msg = '<div class="alert alert-success" role="alert">Password Changed Successfully.</div>';
                } catch (Throwable $e) {
                    if ($con->inTransaction()) {
                        $con->rollBack();
                    }
                    $msg = '<div class="alert alert-danger" role="alert">Password update failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            } else {
                $msg = '<div class="alert alert-danger" role="alert">New Password must not match the previous password, please try again.</div>';
            }
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

    <div class="pagetitle">
      <h1>Profile</h1>
      <!--<nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="<?php echo app_url('ipessadmin'); ?>">Home</a></li>
          <li class="breadcrumb-item">Users</li>
          <li class="breadcrumb-item active">Profile</li>
        </ol>
      </nav>-->
    </div><!-- End Page Title -->

    <section class="section profile">
      <div class="row">
        <div class="col-xl-4">

          <div class="card">
            <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">

              <img src="<?php echo $images ?>" alt="Profile" class="rounded-circle" >
              <h6><?php echo $fullname ?></h6>
              <h3><?php echo $rolename ?></h3>
              <div class="social-links mt-2">
					<?php
					if($getprofile['twitter'] !=""){
					?>
                <a href="<?php echo ($getprofile['twitter'] !="")?$getprofile['twitter']:"#" ?>" class="twitter" target="_blank"><i class="bi bi-twitter"></i></a>
                <?php
					}
					if($getprofile['facebook'] !=""){
				?>
				<a href="<?php echo ($getprofile['facebook'] !="")?$getprofile['facebook']:"#" ?>" class="facebook" target="_blank"><i class="bi bi-facebook"></i></a>
                <?php
					}
					if($getprofile['myinstatgram'] !=""){
				?>
				<a href="<?php echo ($getprofile['myinstatgram'] !="")?$getprofile['myinstatgram']:"#" ?>" class="instagram" target="_blank"><i class="bi bi-instagram"></i></a>
                <?php
					}
				if($getprofile['myLink'] !=""){
				?>
				<a href="<?php echo ($getprofile['myLink'] !="")?$getprofile['myLink']:"#" ?>" class="linkedin" target="_blank"><i class="bi bi-linkedin"></i></a>
              
			  <?php
				}
			  ?>
			  
			  </div>
            </div>
          </div>

        </div>

        <div class="col-xl-8">

          <div class="card">
            <div class="card-body pt-3">
              <!-- Bordered Tabs -->
              <ul class="nav nav-tabs nav-tabs-bordered">

                <li class="nav-item">
                  <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile-overview">Overview</button>
                </li>

                <li class="nav-item">
                  <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-edit">Edit Profile</button>
                </li>

                <!--<li class="nav-item">
                  <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-settings">Settings</button>
                </li>-->

                <li class="nav-item">
                  <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-change-password">Change Password</button>
                </li>

              </ul>
			  <?php echo $msg ?>
              <div class="tab-content pt-2">

                <div class="tab-pane fade show active profile-overview" id="profile-overview">
                  <h5 class="card-title">About myself</h5>
                  <p class="small fst-italic"><?php echo $getprofile['aboutmyself']; ?></p>

                  <h5 class="card-title">Profile Details</h5>

                  <div class="row">
                    <div class="col-lg-3 col-md-4 label ">Full Name</div>
                    <div class="col-lg-9 col-md-8"><?php echo $fullname ?></div>
                  </div>

                  <div class="row">
                    <div class="col-lg-3 col-md-4 label">Organization</div>
                    <div class="col-lg-9 col-md-8"><?php echo $oragname ?></div>
                  </div>

                  <div class="row">
                    <div class="col-lg-3 col-md-4 label">Job</div>
                    <div class="col-lg-9 col-md-8"><?php echo $rolename ?></div>
                  </div>

                  <!--<div class="row">
                    <div class="col-lg-3 col-md-4 label">Country</div>
                    <div class="col-lg-9 col-md-8">USA</div>
                  </div>-->

                  <div class="row">
                    <div class="col-lg-3 col-md-4 label">Address</div>
                    <div class="col-lg-9 col-md-8"><?php echo $getprofile['contactaddress']; ?></div>
                  </div>

                  <div class="row">
                    <div class="col-lg-3 col-md-4 label">Phone</div>
                    <div class="col-lg-9 col-md-8"><?php echo $phoneno ?></div>
                  </div>

                  <div class="row">
                    <div class="col-lg-3 col-md-4 label">Email</div>
                    <div class="col-lg-9 col-md-8"><?php echo $emailddress ?></div>
                  </div>

                </div>

                <div class="tab-pane fade profile-edit pt-3" id="profile-edit">

                  <!-- Profile Edit Form -->
                  <form method = "POST" enctype="multipart/form-data">
                    <div class="row mb-3">
                      <label for="profileImage" class="col-md-4 col-lg-3 col-form-label">Profile Image</label>
                      <div class="col-md-8 col-lg-9">
                        <input type="file" class="form-control" name="fileToUpload" accept="image/*">
                        <div class="form-text text-muted mt-1 small">Saves as username-based passport photo. Leave empty to keep current.</div>
                      </div>
                    </div>
                    <div class="row mb-3">
                      <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Full Name</label>
                      <div class="col-md-8 col-lg-9">
                        <input name="fullname" type="text" class="form-control" id="fullName" value="<?php echo $fullname ?>" required>
                      </div>
                    </div>

                    <div class="row mb-3">
                      <label for="about" class="col-md-4 col-lg-3 col-form-label">About myself</label>
                      <div class="col-md-8 col-lg-9">
                        <textarea name="about" class="form-control" id="about" style="height: 100px"><?php echo $getprofile['aboutmyself']; ?></textarea>
                      </div>
                    </div>

                   <!-- <div class="row mb-3">
                      <label for="company" class="col-md-4 col-lg-3 col-form-label">Company</label>
                      <div class="col-md-8 col-lg-9">
                        <input name="company" type="text" class="form-control" id="company" value="Lueilwitz, Wisoky and Leuschke">
                      </div>
                    </div>

                    <div class="row mb-3">
                      <label for="Job" class="col-md-4 col-lg-3 col-form-label">Job</label>
                      <div class="col-md-8 col-lg-9">
                        <input name="job" type="text" class="form-control" id="Job" value="Web Designer">
                      </div>
                    </div>

                    <div class="row mb-3">
                      <label for="Country" class="col-md-4 col-lg-3 col-form-label">Country</label>
                      <div class="col-md-8 col-lg-9">
                        <input name="country" type="text" class="form-control" id="Country" value="USA">
                      </div>
                    </div>-->

                    <div class="row mb-3">
                      <label for="Address" class="col-md-4 col-lg-3 col-form-label">Address</label>
                      <div class="col-md-8 col-lg-9">
                        <input name="address" type="text" class="form-control" id="Address" value="<?php echo $getprofile['contactaddress']; ?>">
                      </div>
                    </div>

                    <div class="row mb-3">
                      <label for="Phone" class="col-md-4 col-lg-3 col-form-label">Phone</label>
                      <div class="col-md-8 col-lg-9">
                        <input name="phone" type="text" class="form-control" id="Phone" value="<?php echo $getprofile['phoneno']; ?>">
                      </div>
                    </div>

                    <div class="row mb-3">
                      <label for="Email" class="col-md-4 col-lg-3 col-form-label">Email</label>
                      <div class="col-md-8 col-lg-9">
                        <input name="email" type="email" class="form-control" id="Email" value="<?php echo $getprofile['EmailAddress']; ?>">
                      </div>
                    </div>

                    <div class="row mb-3">
                      <label for="Twitter" class="col-md-4 col-lg-3 col-form-label">Twitter Profile</label>
                      <div class="col-md-8 col-lg-9">
                        <input name="twitter" type="text" class="form-control" id="Twitter" value="<?php echo $getprofile['twitter']; ?>">
                      </div>
                    </div>

                    <div class="row mb-3">
                      <label for="Facebook" class="col-md-4 col-lg-3 col-form-label">Facebook Profile</label>
                      <div class="col-md-8 col-lg-9">
                        <input name="facebook" type="text" class="form-control" id="Facebook" value="<?php echo $getprofile['facebook']; ?>">
                      </div>
                    </div>

                    <div class="row mb-3">
                      <label for="Instagram" class="col-md-4 col-lg-3 col-form-label">Instagram Profile</label>
                      <div class="col-md-8 col-lg-9">
                        <input name="instagram" type="text" class="form-control" id="Instagram" value="<?php echo $getprofile['myinstatgram']; ?>">
                      </div>
                    </div>

                    <div class="row mb-3">
                      <label for="Linkedin" class="col-md-4 col-lg-3 col-form-label">Linkedin Profile</label>
                      <div class="col-md-8 col-lg-9">
                        <input name="linkedin" type="text" class="form-control" id="Linkedin" value="<?php echo $getprofile['myLink']; ?>">
                      </div>
                    </div>

                    <div class="text-center">
                      <button type="submit" name = "savechanges" class="btn btn-primary">Save Changes</button>
                    </div>
                  </form><!-- End Profile Edit Form -->

                </div>
 <!-- Settings Form -->
                <!--<div class="tab-pane fade pt-3" id="profile-settings">

                 
                  <form>

                    <div class="row mb-3">
                      <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Email Notifications</label>
                      <div class="col-md-8 col-lg-9">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="changesMade" checked>
                          <label class="form-check-label" for="changesMade">
                            Changes made to your account
                          </label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="newProducts" checked>
                          <label class="form-check-label" for="newProducts">
                            Information on new products and services
                          </label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="proOffers">
                          <label class="form-check-label" for="proOffers">
                            Marketing and promo offers
                          </label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="securityNotify" checked disabled>
                          <label class="form-check-label" for="securityNotify">
                            Security alerts
                          </label>
                        </div>
                      </div>
                    </div>

                    <div class="text-center">
                      <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                  </form>
				  
				  

                </div>-->
<!-- End settings Form -->
                <div class="tab-pane fade pt-3" id="profile-change-password">
                  <!-- Change Password Form -->
                  <form method="POST" name = "changefrm">

                    <div class="row mb-3">
                      <label for="currentPassword" class="col-md-4 col-lg-3 col-form-label">Current Password</label>
                      <div class="col-md-8 col-lg-9">
                        <input name="password" type="password" class="form-control" id="currentPassword">
                      </div>
                    </div>

                    <div class="row mb-3">
                      <label for="newPassword" class="col-md-4 col-lg-3 col-form-label">New Password</label>
                      <div class="col-md-8 col-lg-9">
                        <input name="newpassword" type="password" class="form-control" id="newPassword">
                      </div>
                    </div>

                    <div class="row mb-3">
                      <label for="renewPassword" class="col-md-4 col-lg-3 col-form-label">Re-enter New Password</label>
                      <div class="col-md-8 col-lg-9">
                        <input name="renewpassword" type="password" class="form-control" id="renewPassword">
                      </div>
                    </div>

                    <div class="text-center">
                      <button type="submit" name = "changepaasword" class="btn btn-primary">Change Password</button>
                    </div>
                  </form><!-- End Change Password Form -->

                </div>

              </div><!-- End Bordered Tabs -->

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