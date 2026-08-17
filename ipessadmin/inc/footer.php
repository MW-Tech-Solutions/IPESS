<?php
//include("inc/main.config.php");
$getfooter = $con->query("SELECT * FROM footer_tb WHERE footerStatus = '1' ")->fetch(PDO::FETCH_ASSOC);
?>
<footer id="footer" class="footer">
    <div class="copyright">
      &copy; Copyright <?php echo date("Y")?> - All Rights Reserved <strong><span><?php echo !empty($getfooter['footername'])?$getfooter['footername']:"No Footer Description" ?></span></strong>
    </div>
    <div class="credits">
      <!-- All the links in the footer should remain intact. -->
      <!-- You can delete the links only if you purchased the pro version. -->
      <!-- Licensing information: https://bootstrapmade.com/license/ -->
      <!-- Purchase the pro version with working PHP/AJAX contact form: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/ -->
     <!-- Designed by <a href="https://bootstrapmade.com/">BootstrapMade</a>-->
    </div>
  </footer>