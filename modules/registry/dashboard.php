<?php

require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ipessadmin/login.php');
require_role(['REGISTRY', 'SUPER_ADMIN'], 'ipessadmin/login.php');

echo 'Registry module dashboard is protected and ready for migration.';
