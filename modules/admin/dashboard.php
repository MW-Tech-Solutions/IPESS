<?php

require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ipessadmin/login.php');
require_role(['SUPER_ADMIN', 'ICT_ADMIN', 'PORTAL_ADMIN', 'PG_SCHOOL_OFFICER'], 'ipessadmin/login.php');

redirect_to(dashboard_for_role());
