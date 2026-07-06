<?php

require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['ADMISSIONS_OFFICER', 'PG_SCHOOL_OFFICER', 'PORTAL_ADMIN', 'SUPER_ADMIN'], 'ADMIN/login.php');

redirect_to('ADMIN/admin/dashboard.php');
