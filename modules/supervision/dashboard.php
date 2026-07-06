<?php

require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['SUPERVISOR', 'HOD', 'DEPARTMENT_ADMIN', 'SUPER_ADMIN'], 'ADMIN/login.php');

echo 'Supervision module dashboard is protected and ready for migration.';
