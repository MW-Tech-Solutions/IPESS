<?php
session_start();
session_destroy();
require_once __DIR__ . '/../config/urls.php';
redirect_to('ADMIN/login.php');
?>
