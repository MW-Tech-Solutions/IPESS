<?php

require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['PG_SCHOOL_OFFICER', 'FACULTY_OFFICER', 'DEPARTMENT_ADMIN', 'HOD', 'SUPER_ADMIN'], 'ADMIN/login.php');

echo 'Academics module dashboard is protected and ready for migration.';
