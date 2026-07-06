<?php

require_once __DIR__ . '/app/bootstrap.php';
require_login('APPLICANT/ADMISSIONS/login.php');
redirect_to(dashboard_for_role());
