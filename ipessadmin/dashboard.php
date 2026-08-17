<?php

require_once __DIR__ . '/../app/bootstrap.php';
require_login('ipessadmin/login.php');
redirect_to(dashboard_for_role());
