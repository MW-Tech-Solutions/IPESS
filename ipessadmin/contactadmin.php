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
			$name = !empty($_POST['name'])?$_POST['name']:"";
			$email = $_POST['email'];
			$subject = $_POST['subject'];
			$message = $_POST['message'];
			$support = $_POST['support'];
			//echo "INSERT INTO contact_message(userID,emaildress,msgSubject,msgBody,datecreated,pendingStatus,dateTreated,treatedBy)VALUES('$usersession','$email','$subject','$message','$datecreated','pending','','')";
		$sendme = $con->query("INSERT INTO contact_message(ToUserID,emaildress,msgSubject,msgBody,datecreated,pendingStatus,dateTreated,treatedBy,fromuserID,createdtime,SupportID,closeStatus)VALUES('','$email','$subject','$message','$datecreated','pending','','','$usersession','$today','$support','open')");	
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

    

    <section class="section contact">

      <div class="row gy-4">

        <div class="col-xl-6">

          <div class="row">
            <div class="col-lg-6">
              <div class="info-box card">
                <i class="bi bi-geo-alt"></i>
                <h3>Address</h3>
                <p>JOSTUM ICT,<br>North-Core Office</p>
              </div>
            </div>
            <div class="col-lg-6">
              <div class="info-box card">
                <i class="bi bi-telephone"></i>
                <h3>Call Us</h3>
                <p>(+234) 8162673786 <br>(+234) 7098162783</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="info-box card">
                <i class="bi bi-envelope"></i>
				 <h5> Messages</h5>
                <!--<h3>Email Us</h3>
                <p>info@example.com<br>contact@example.com</p>-->
              </div>
            </div>
            <div class="col-lg-6">
              <div class="info-box card">
                <i class="bi bi-clock"></i>
                <h3>Open Hours</h3>
                <p>Monday - Friday<br>8:00AM - 04:00PM</p>
              </div>
            </div>
          </div>

        </div>

        <div class="col-xl-6">
          <div class="card p-4">
		  <?php echo $msg ?>
            <form  method="post">
			<div class="row">Open new Ticket</div><br>
              <div class="row gy-4">

                <div class="col-md-6">
                 <select name="support" class="form-control" id="support"  required> 
					<option value="">Select Ticket</option>
				<?php 
				$support = !empty($_POST['support'])?$_POST['support']:"";
				$getsupport = $con->query("SELECT * FROM manage_support WHERE supportStatus = '1' ");
				while($readsupport = $getsupport->fetch(PDO::FETCH_ASSOC)){
					
				?>
				<option value="<?php echo $readsupport['ID']?>" ><?php echo $readsupport['supportDescription'] ?></option>
				
				<?php
				}
				?>
				</select>
                </div>

                <div class="col-md-6 ">
                  <input type="email" class="form-control" name="email" placeholder="Your Email"  value="<?php echo $emailddress ?>" readonly>
                </div>

                <div class="col-md-12">
                  <input type="text" class="form-control" name="subject" placeholder="Subject" required>
                </div>

                <div class="col-md-12">
                  <textarea class="form-control" name="message" rows="6" placeholder="Message" required></textarea>
                </div>

                <div class="col-md-12 text-center">
                  <!--<div class="loading">Loading</div>
                  <div class="error-message"></div>
                  <div class="sent-message">Your message has been sent. Thank you!</div>-->

                 <!-- <button type="submit" name="sendmsg" class="btn btn-primary">Send Message</button>-->
                </div>

              </div>
            </form>
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