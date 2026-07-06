<?php
require_once __DIR__ . '/app/bootstrap.php';

session_destroy();
redirect('index.php');
