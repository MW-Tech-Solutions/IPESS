<?php
session_start();
error_reporting(0);
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
		
	//	echo $visiturl;
		$urlpages = $visiturl;
		
		//echo "SELECT * FROM pesonal_right_page_main_menus WHERE page_url = '$urlpages' AND userID = '$usersession' ";
		$readpagename = $con->query("SELECT * FROM pesonal_right_page_main_menus WHERE page_url = '$urlpages' AND userID = '$usersession' ")->fetch(PDO::FETCH_ASSOC);
		$mypagename = !empty($readpagename['menu_name']) ? $readpagename['menu_name'] : '';
		$checheacoage = $con->query("SELECT * FROM pesonal_right_page_main_menus WHERE page_url = '$urlpages' AND userID = '$usersession' ")->rowCount();
			
		
		if(($_SESSION['role'] ?? '') !== 'DEVELOPER' && $checheacoage<1){
			header("location:logout.php"); exit;
			
		}
		
//$process = new ProcessorVendor();
		$msg = "";

			if(isset($_POST['add'])){
		//$msg = $process->fileMenusAction();
        $tabid=$_POST["tabcode"];

        foreach($_POST["page"] as $val =>$k){
		$pageID =$_POST["page"][$val];
		
		$deletepage=$con->query("DELETE FROM `page_main_menus` WHERE pageID='$pageID'");

		$deleteinrole=$con->query("DELETE FROM `right_page_main_menus` WHERE pageID='$pageID' AND tabID='$tabid'");
   $deeelet= $con->query("DELETE FROM pesonal_right_page_main_menus WHERE pageID = '$pageID' AND tabID = '$tabid'");
        }

$checheacoage11 = $con->query("SELECT * FROM pesonal_right_page_main_menus WHERE tabID = '$tabid' AND userID = '$usersession' ")->rowCount();
			
if($checheacoage11<1){
  
  $deltab=$con->query("DELETE FROM `personal_page_menu_tab` WHERE `tabID`='$tabid' AND `userID`='$usersession';");
}

        $msg = '<div class="alert alert-success alert-dismissible">
							<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
					  <strong>Success!</strong> Records Remove Successfully. '.$errors.'
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
              <h5 class="card-title">MANAGE PAGES</h5>
				<?php echo $msg; ?>
              <!-- Vertical Form -->
              <form class="row g-3" method = "POST">
                <div class="col-12">
                  <label for="inputNanme4" class="form-label">Select Tab</label>
                 <select name="tabcode" class="form-control" id="tabcode" onchange = "this.form.submit();" required> 
																<option value="">Select Option</option>
															<?php 
															$tabls = !empty($_POST['tabcode'])?$_POST['tabcode']:"";
															$gettab = $con->query("SELECT * FROM page_menu_tab WHERE tab_status = '1' ");
															while($readtab = $gettab->fetch(PDO::FETCH_ASSOC)){
															?>
															<option value="<?php echo $readtab['ID']?>" <?php echo ($readtab['ID']==$tabls)?"selected":""?>><?php echo $readtab['tab_name']?></option>
															
															<?php
															}
															?>
															</select>
                </div>
               
												
															<?php
                                                            if(isset($_POST["tabcode"])){
															$pages = "SELECT * from `page_main_menus`  WHERE tabID = '{$_POST["tabcode"]}' " ;
										
															$querpages = $con->query($pages);
															
															?>

                <table class="table table-hover">
							<thead>
								<tr>
								  <th scope="col">S/N</th>
								  <th scope="col">Page Name</th>
								  <th scope="col">URL</th>
								  <th scope="col">Action</th>
								  
									  <!-- <th scope="col">Status</th>-->
								</tr>
							  </thead>
							  <body>
							  <tr>
							  <?php
                              $sno=0;
							  while($page=$querpages->fetchObject()){
                                $sno++;
							  ?>
							  <td>
							  <?php echo $sno ?>
							  </td>
							  <td>
							  <?php echo $page->menu_name ?>
							  </td>
							  <td>
							   <?php echo $page->page_url ?>
							  </td>
							  <td>
							   <input type="checkbox"  id="page" name="page[]" value="<?php echo $page->pageID?>">
							  </td>
							 
							  </tr>
							  <?php
							  }
							  ?>
							  </body>
						</table>
               


               
                <div class="text-center">
                  <button type="submit" name="add" class="btn btn-primary">Remove</button>
                 
                </div>
               <?php }?>
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