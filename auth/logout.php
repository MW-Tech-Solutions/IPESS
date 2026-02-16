<?php
session_start();
require_once __DIR__ . '/../config/urls.php';
session_destroy();
redirect_to('APPLICANT/ADMISSIONS/login.php');
?>
